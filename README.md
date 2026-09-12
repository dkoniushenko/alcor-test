# Alcor OS — History of Manual Adjustments to an Earning Line

A proof-of-concept domain model for Alcor OS's payroll platform, built as a take-home
coding exercise. The assignment brief is in
[`doc/Alcor code assignment.docx.pdf`](doc/Alcor%20code%20assignment.docx.pdf).

## The problem

A payroll specialist reviews an earning line whose value the system calculates
automatically. Sometimes the specialist needs to correct it manually (e.g. a declined
benefit, a late correction). The model has to guarantee:

- every manual correction stays visible and traceable — none may ever be edited or
  silently deleted once saved; a mistake is fixed by adding a new, offsetting
  correction, not by changing the old one;
- once a line has received at least one manual correction, automatic recalculation
  must never affect it again, even if the underlying source data later changes;
- at any point, both the line's current value and the full audit trail of corrections
  that produced it must be inspectable.

## How it works

Every rule reduces to one formula, once the right invariant is chosen:

```
currentValue() = calculatedValue + Σ(corrections[].amount)
```

`calculatedValue` is the last value the (external, out-of-scope) automatic system
calculation produced. Freezing isn't a separate flag — it's derived:
`isFrozen() = !empty(corrections)`. Since `corrections` is append-only, `isFrozen()`
can only ever go from `false` to `true`, never back. Once frozen, `recalculate()`
becomes a silent no-op — not an error, since a source-data change after freezing is
normal, expected system behaviour, not a bug.

Walking the assignment's own worked example through this formula reproduces every
intermediate and final value exactly, including the case where a recalculation
attempt is ignored (step 4) and the case where a specialist corrects their own
earlier correction (steps 7–8):

| Step | Event | Amount | Value after |
|---|---|---|---|
| 1 | System calculates the line | — | $1,000.00 |
| 2 | Source data changes, recalculates (unfrozen — allowed) | — | $1,050.00 |
| 3 | Manual correction | −$45.55 | $1,004.45 |
| 4 | Recalculation attempted — ignored (frozen) | — | $1,004.45 |
| 5 | Second correction | +$100.10 | $1,104.55 |
| 6 | Third correction | −$0.10 | $1,104.45 |
| 7 | Fourth correction | −$0.20 | $1,104.25 |
| 8 | Compensating correction, fixing step 7 | +$0.20 | $1,104.45 |

This exact scenario is encoded as `testTheFullWorkedExampleFromTheAssignment()` in
[`tests/Unit/Payroll/Domain/EarningTest.php`](tests/Unit/Payroll/Domain/EarningTest.php),
including the final audit history matching the assignment's own expected table.

`Earning::auditHistory()` doesn't store anything separately — it's a read-only
projection computed on demand from `calculatedValue` and `corrections`, so it can
never drift out of sync with the aggregate's real state.

## Assumptions made

- **No `User`/`Employee` aggregate.** `EmployeeId` and `PayrollSpecialistId` are
  opaque UUID references only — none of the assignment's rules depend on
  authentication, roles, or permissions, so modeling a full identity bounded context
  would be scope creep.
- **No Event Sourcing.** State is plain fields on the aggregate, not rebuilt by
  replaying events, even though the real Alcor codebase uses DDD+CQRS+ES — the
  assignment explicitly allows a simpler design, and the business rules don't need it.
- **`Correction` is an entity, not a value object.** The assignment's own example
  references a specific prior correction by identity ("Correcting mistake in
  adjustment #4"), and each correction carries who made it and when — that makes it a
  specific historical fact (like a ledger entry), not an interchangeable value.
- **`Money` comes from the `moneyphp/money` library**, not a hand-rolled value
  object — correct rounding and arithmetic for currency is a well-solved problem, and
  the assignment's own example ($0.10 / $0.20 rounding corrections) is specifically
  designed to catch float-precision mistakes.
- **Computing `calculatedValue` from raw source data is out of scope.** The
  assignment states "the system calculates automatically" — that computation happens
  upstream, outside this domain model. `Earning` only ever receives an
  already-computed candidate value via `recalculate()`.
- **A correction's amount can never be zero.** The assignment describes corrections
  as a "positive or negative" amount — zero is neither, and a zero-amount correction
  wouldn't correct anything.
- **A correction's comment can never be empty or whitespace-only.** The assignment
  calls the comment mandatory; a comment of only spaces/tabs doesn't explain anything.
- **Currency is fixed to USD.** The assignment has no multi-currency requirement.

Each of these — along with the alternatives that were considered and rejected, and
why — is written up in full in the ADRs below.

## Architecture

A modular monolith with DDD-style layering per module
(`Domain` / `Application` / `Infrastructure` / `Ui`), plus a small `Shared` kernel for
code with no module-specific meaning (UUID-based IDs, the domain exception base, the
clock port, domain-event plumbing).

```
src/
├── Shared/
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── Payroll/
    ├── Domain/
    ├── Application/
    │   ├── Command/
    │   ├── CommandHandler/
    │   ├── Query/
    │   ├── QueryHandler/
    │   └── Exception/
    ├── Infrastructure/
    │   └── Persistence/
    └── Ui/
        └── Cli/
```

Full reasoning, alternatives considered, and the folder/namespace skeleton are in:

- [`doc/adr/0001-earning-domain-model.md`](doc/adr/0001-earning-domain-model.md) —
  the domain model itself: naming, the core formula, invariants.
- [`doc/adr/0002-project-structure.md`](doc/adr/0002-project-structure.md) — the
  modular monolith structure, layering, naming conventions, test layout.

## Current status

All four layers are implemented and tested, end to end: the domain model (`Earning`,
`Correction`, value objects, domain events, audit history), the Application layer
(`CalculateEarning`, `RecalculateEarning`, `AddCorrection` commands +
`GetAuditHistory` query, each with a handler), the in-memory
`EarningRepositoryInterface`/`ClockInterface` implementations, and a CLI entry point
(`bin/demo`) that wires all of it together and runs the assignment's full 8-step
scenario in one process — see "Running it" below.

Domain events are recorded on the aggregate and dispatched by each command handler
after `save()`, via a minimal `ConsoleEventDispatcher` that prints one line per
dispatched event.

## Development tooling

Static analysis, code style, and architecture-boundary checks live in their own
isolated Composer namespaces under `tools/` (via
[`bamarni/composer-bin-plugin`](https://github.com/bamarni/composer-bin-plugin)), so
their dependencies (e.g. `friendsofphp/php-cs-fixer`'s `symfony/*` packages) can never
version-conflict with the main project's own dependencies. `composer install` at the
root installs everything, tools included (`forward-command` is enabled), and each
tool's binary is symlinked into the usual `vendor/bin/`.

| Command | Tool | Checks |
|---|---|---|
| `composer cs-check` | [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) | Code style, dry-run |
| `composer cs-fix` | PHP-CS-Fixer | Code style, auto-fix |
| `composer phpstan` | [PHPStan](https://phpstan.org/) (level 8) | Static types |
| `composer deptrac` | [Deptrac](https://github.com/deptrac/deptrac) | Layer boundaries from [ADR-0002](doc/adr/0002-project-structure.md) |
| `composer check` | all three | cs-check + phpstan + deptrac |

## Running it

Everything runs inside Docker — the host doesn't need PHP or Composer installed.

```bash
docker compose up -d
docker compose exec php composer install
docker compose exec php vendor/bin/phpunit
```

### Running the demo

```bash
docker compose exec php php bin/demo
```

`bin/demo` runs `RunDemoCommand`, which drives the assignment's full 8-step
scenario through those handlers exactly as a real caller would — no test doubles
anywhere in this path.

It's a single self-contained run rather than separate `bin/demo <sub-command>`
invocations, because storage is in-memory: each `php bin/demo ...` call starts a
fresh process with an empty repository, so a later invocation could never find an
`Earning` a previous invocation created. Running the whole scenario in one process is
what makes the freeze behaviour (step 4) and the corrected mistake (steps 7–8)
observable at all.

Expected output:

```
1. System calculates the line.
[event] EarningCalculated occurred at <timestamp>
2. Source data changes, system recalculates (no correction yet, allowed).
[event] EarningCalculated occurred at <timestamp>
3. Specialist adds a manual correction.
[event] CorrectionAdded occurred at <timestamp>
4. Source data changes again, system attempts to recalculate — ignored (already frozen).
5. Specialist adds a second correction.
[event] CorrectionAdded occurred at <timestamp>
6. Specialist adds a third correction.
[event] CorrectionAdded occurred at <timestamp>
7. Specialist adds a fourth correction.
[event] CorrectionAdded occurred at <timestamp>
8. Specialist adds a compensating correction, realizing step 7 was a mistake.
[event] CorrectionAdded occurred at <timestamp>

Final audit history:
  Calculated value (frozen)                                               $1,050.00
  Correction 1 — Employee declined dental benefit; reversing deduction      -$45.55
  Correction 2 — Late correction: missed approved overtime bonus          $100.10
  Correction 3 — Minor rounding adjustment                                 -$0.10
  Correction 4 — Second minor rounding adjustment                          -$0.20
  Correction 5 — Correcting mistake in adjustment #4                        $0.20
  Current value                                                           $1,104.45
```

(Each run generates fresh `EmployeeId`/`PayrollSpecialistId` UUIDs and real
timestamps, so only the labels and dollar amounts above are stable between runs —
matching the assignment's own expected numbers exactly. Step 4 has no `[event]` line:
the recalculation is ignored because the line is already frozen, so `Earning` never
records an event for it — the absence of that line is itself observable proof the
freeze rule held.)
