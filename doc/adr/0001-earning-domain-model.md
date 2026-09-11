# ADR 0001: Domain model for the `Earning` aggregate

## Status

Accepted — 2026-09-11

## Context

The assignment ("History of Manual Adjustments to an Earning Line",
`doc/Alcor code assignment.docx.pdf`) asks for a domain model satisfying this business
case:

- A payroll specialist reviews a line with an employee's base salary that the system
  calculates automatically.
- Sometimes the specialist needs to manually correct it (e.g. a declined benefit or a
  late correction).
- The correction is entered as a positive or negative amount with a mandatory comment
  explaining why.

And these hard rules:

1. A single line can receive multiple corrections over time. Each one must stay
   visible and traceable — no correction may ever be edited or silently deleted once
   saved. If the specialist made a mistake, they add a new correction that fixes the
   previous one.
2. Once a line has received at least one manual correction, automatic system
   recalculation must no longer affect it — the specialist's corrections take
   permanent precedence, even if the underlying source data used to calculate the line
   later changes.
3. At any point, it must be possible to see the line's current value and the full
   audit history of corrections that produced it.

The assignment supplies a worked example (8 steps, from an initial $1,000.00 system
value to a final $1,104.45 current value after five corrections, including one
ignored recalculation attempt and one compensating correction) that any implementation
must reproduce exactly. Full step-by-step numbers are in
`Alcor code assignment.docx.pdf` and in `CLAUDE.md`.

This ADR was worked out collaboratively, iterating on naming and modeling choices
before writing any code.

## Decision

### Ubiquitous language

| Term | Meaning |
|---|---|
| `Earning` | The aggregate root — one earning line for one employee (renamed from the assignment's "Earning Line"; "Line" was dropped because it's a spreadsheet/UI artifact, not a domain concept). |
| `Correction` | A single manual correction applied to an `Earning`. The assignment also calls these "Adjustment" in its audit table — same concept, one name used in code. |
| `calculatedValue` | The latest value the (external, out-of-scope) automatic system calculation produced. Named after the assignment's own verb: "the system **calculates** automatically". |
| `Employee` | The person the earning belongs to. Referenced only by `EmployeeId` — no `Employee` aggregate is modeled here. |
| `Payroll specialist` | The person who enters a correction. Referenced only by `PayrollSpecialistId` — no `User`/specialist aggregate is modeled here. |

### Core insight

All the business rules reduce to one formula, once the right invariant is chosen:

```
currentValue() = calculatedValue + Σ(corrections[].amount)
```

Freezing is not a separate stored flag — it's derived:

```
isFrozen() = !empty(corrections)
```

`corrections` is append-only (can only grow, never shrink back to empty), so once
`isFrozen()` becomes `true` it stays `true` forever. `recalculate()` is a no-op
whenever `isFrozen()` is `true`, so `calculatedValue` becomes permanently frozen the
moment the first correction is added — no separate `frozenValue` field is needed.

This formula was checked against all 8 steps of the assignment's worked example and
reproduces every intermediate and final value exactly, including the
compensating-correction case (steps 7–8) and the ignored-recalculation case (step 4).

### Aggregate: `Earning`

```
Earning (aggregate root)
  id: EarningId                       (UUID v7)
  employeeId: EmployeeId               (UUID v7)
  calculatedValue: Money
  corrections: Correction[]            (ordered, append-only)
```

**Behavior:**

- `recalculate(Money candidate): void`
  No-op ("no operation" — the call is accepted but changes nothing, no exception) if
  `isFrozen()`. Otherwise sets `calculatedValue = candidate`. This maps directly to
  the assignment's step 4: a legitimate, non-erroneous recalculation attempt that must
  be silently ignored once a correction exists.

- `addCorrection(Money amount, string comment, PayrollSpecialistId correctedBy): void`
  Validates `comment` is non-empty (throws a domain exception otherwise), creates a new
  `Correction` with a fresh `CorrectionId` and the current time (via an injected clock,
  not `new DateTimeImmutable()` directly, for testability), appends it to
  `corrections`. Never edits or removes existing entries.

- `isFrozen(): bool` → `!empty(corrections)`.

- `currentValue(): Money` → the core formula above.

- `auditHistory(): AuditHistory` → see below.

**Invariants the aggregate protects:**

1. `corrections` only grows — no method exists to edit or remove an entry.
2. A correction's `comment` is always non-empty.
3. Freezing is monotonic and permanent — once true, `isFrozen()` can never become
   false again.
4. `recalculate()` after freezing never throws and never changes state — it's a no-op,
   not an error, because a source-data change after freezing is normal system
   behavior, not a bug.

**Explicitly out of scope for this aggregate:** how `calculatedValue` is computed from
raw source data (hours worked, rates, benefit elections, etc.). The assignment states
"the system calculates automatically" — that computation happens upstream, outside
this domain model. `Earning` only ever receives an already-computed candidate value
through `recalculate()`. The assignment's phrase "even if the underlying source data
... later changes" and "even if `calculatedValue` would change" describe the same
event from this aggregate's point of view: a `recalculate()` call carrying a new
candidate, made after freezing, which is ignored regardless of why it happened or what
the candidate value is.

### Entity: `Correction`

```
Correction (entity — immutable, has its own identity)
  id: CorrectionId                     (UUID v7)
  amount: Money
  comment: string                       (mandatory, non-empty)
  correctedBy: PayrollSpecialistId      (UUID v7)
  recordedAt: DateTimeImmutable
```

No `sequence` field is stored — the display position ("Correction 1", "Correction 2",
...) is derived from the item's index in `corrections[]` at render time, since the
array's order already encodes it. Storing it separately would just duplicate
information already implicit in list order.

### Value objects: ID types — shared `AbstractUuidId` base

`EarningId`, `EmployeeId`, `CorrectionId`, and `PayrollSpecialistId` all extend a
shared `AbstractUuidId` base class (wrapping a `symfony/uid` `UuidV7`), rather than
being four independent classes or sharing behavior via a trait.

Deciding heuristic: is-a vs. has-a-capability. These four types genuinely *are* a
kind of the same thing — a UUID-based domain identifier — not just unrelated
classes that happen to share some code; that's a real "is-a" relationship, which is
what inheritance is for. A trait would share the implementation but wouldn't
establish a common type, and the duplication avoided here (constructor, `equals()`,
`__toString()`, a `generate()` named constructor) is genuinely identical across all
four, not superficially similar.

- `AbstractUuidId` is `abstract` and holds the shared `equals()` / `__toString()` /
  `generate()` (via late static binding, so `EarningId::generate()` returns an
  `EarningId`, not the abstract base).
- Each concrete class (`EarningId`, `EmployeeId`, `CorrectionId`,
  `PayrollSpecialistId`) is `final`, extending only the abstract base — standard
  practice for value objects, so no further subclassing can alter equality
  semantics.

Contrast with `RecordsDomainEventsTrait` (ADR-0002): that's a has-a-capability case
(an aggregate root gaining the orthogonal ability to accumulate domain events), not
an is-a relationship — which is why that one is a trait and this one is an
abstract class.

### Value object: `Money` — via `moneyphp/money`

Uses the `moneyphp/money` library (`Money\Money`, `Money\Currency`) rather than a
hand-rolled value object. Same reasoning as choosing `symfony/uid` for IDs instead of
hand-rolling UUID v7: correct monetary rounding, arithmetic, and comparison are a
well-solved problem, and a mature, widely-used library gets the edge cases right in
ways that are easy to get subtly wrong by hand — exactly what the assignment's
worked example ($0.10/$0.20 rounding corrections) is designed to catch.

- Amounts are integer minor units under the hood (never `float`) — the library
  enforces this, it isn't something this project has to get right on its own.
- Currency is always constructed as `Money\Currency('USD')` — the assignment has no
  multi-currency requirement, so the library's multi-currency support exists but is
  never exercised.
- `Money\Money` is immutable, with `add`/`subtract`/`equals`/`compare` already
  provided — no custom arithmetic to write or test.

### `auditHistory()`

Not stored state — a read-only projection computed on demand from `calculatedValue`
and `corrections`, shaped to match the assignment's "Expected final audit history"
table exactly:

```
1. Calculated-value entry   — Earning.calculatedValue, flagged frozen/live via isFrozen()
2. One entry per Correction — in corrections[] order, numbered by position (not a stored field)
3. Current-value entry      — Earning.currentValue()
```

Example, using the assignment's worked scenario after step 8:

| Entry | Value |
|---|---|
| Calculated value (frozen) | $1,050.00 |
| Correction 1 — "Employee declined dental benefit; reversing deduction" | −$45.55 |
| Correction 2 — "Late correction: missed approved overtime bonus" | +$100.10 |
| Correction 3 — "Minor rounding adjustment" | −$0.10 |
| Correction 4 — "Second minor rounding adjustment" | −$0.20 |
| Correction 5 — "Correcting mistake in adjustment #4" | +$0.20 |
| Current value | $1,104.45 |

Because it's recomputed from the same two fields every time, it can never drift out of
sync with the aggregate's actual state.

## Consequences

**Positive:**

- The freeze rule is enforced structurally (an invariant of `corrections` being
  append-only), not by a flag that code could forget to check — there is no code path
  that can un-freeze an `Earning` or mutate `calculatedValue` after freezing.
- `currentValue()` and `auditHistory()` can never drift from the aggregate's real
  state, since both are pure computations over `calculatedValue` + `corrections`
  rather than separately maintained state.
- Using `moneyphp/money` (integer minor units under the hood, never `float`) removes
  an entire class of rounding bugs that the assignment's example is specifically
  designed to catch (steps 6–8), without this project having to implement or test
  that arithmetic itself.
- The aggregate stays small and self-contained; no dependency on a `User`/`Employee`
  aggregate or an event store keeps the design easy to unit test in isolation.

**Negative / trade-offs accepted:**

- `EmployeeId` and `PayrollSpecialistId` are unvalidated opaque references — nothing
  in this model guarantees they refer to a real employee/specialist. Acceptable
  because identity/user management is explicitly out of scope for this exercise.
- No history of recalculation *attempts* is kept (only the final frozen
  `calculatedValue` before the first correction) — the assignment's audit
  requirement only covers corrections, not recalculation attempts, so this was not
  modeled.
- If a future requirement needed to know *why* a recalculation was ignored (e.g. for
  operational logging), `recalculate()` would need to start returning a
  result/status instead of `void`. Not needed for the current rules, so deferred.

### Alternatives considered and rejected

- **Separate `frozen: bool` / `frozenValue: Money` fields** — rejected in favor of
  deriving `isFrozen()` from `!empty(corrections)` and letting `recalculate()` no-op
  once frozen. Fewer fields, and it's structurally impossible for `frozen` and
  `corrections` to disagree.
- **`recalculate()` throws when frozen** — considered, but rejected: an ignored
  recalculation is normal, expected system behavior (source data changes constantly
  after freezing), not an error condition, so a silent no-op fits better than an
  exception.
- **`Correction` as a value object** — considered, but rejected in favor of an entity.
  The assignment's own example references a specific prior correction by identity
  ("Correcting mistake in adjustment #4"), and each correction carries `correctedBy` +
  `recordedAt`, making it a specific historical fact rather than an interchangeable
  value — the standard DDD pattern here is an *immutable entity* (akin to a ledger
  entry or domain event), not a value object.
- **Storing a `sequence` field on `Correction`** — rejected as redundant; position in
  `corrections[]` already encodes order.
- **A full `User`/`Employee` aggregate** — rejected as scope creep; none of the
  assignment's rules depend on authentication, roles, or permissions.
- **Event Sourcing** — rejected in favor of plain state fields; the assignment
  explicitly allows a simpler design, and the real codebase's DDD+CQRS+ES style is
  stated as optional context, not a requirement.
- **Hand-rolled `Money` value object** — the original plan, then rejected in favor of
  `moneyphp/money`. Correct monetary arithmetic/rounding is a well-solved problem
  outside this domain's core concern; reimplementing it risks subtly getting wrong
  exactly the kind of cent-precision cases the assignment's own example is built to
  test, for no benefit over a mature library.
- **Four independent ID classes with no shared code** — rejected; the duplicated
  constructor/`equals()`/`__toString()` would be genuinely identical across all
  four, not superficially similar, which is exactly the case where sharing an
  implementation is justified over keeping them fully independent.
- **A shared trait instead of an abstract base class for the ID types** — rejected;
  the four ID types have a genuine is-a relationship (all are UUID-based domain
  identifiers), which inheritance expresses correctly, and a trait would share the
  implementation without giving them a common type.
