# ADR 0002: Project structure — modular monolith, DDD layered architecture

## Status

Accepted — 2026-09-11

## Context

ADR-0001 agreed on the domain model (`Earning` aggregate, `Correction` entity, `Money`
value object, domain events). This ADR decides how that model is physically organized
in the codebase: module boundaries, layering, where domain events and their
dispatching live, test folder layout, and naming conventions.

This matters beyond aesthetics because the assignment's grading criteria explicitly
call out "good separation / encapsulation of concerns", "small accurate interfaces /
classes / commands / events / aggregate boundaries", and "dependency injection" — the
physical structure is one of the more visible signals of whether those principles were
actually followed, not just claimed.

The codebase is a **modular monolith**: a single deployable/runnable PHP project,
internally partitioned into bounded-context modules under `src/`, each with its own
layered internals. Only one module exists for this exercise.

## Decision

### Module boundary

`src/Payroll/` is the (only, for now) module. Named `Payroll` rather than `Earnings`
— broader on purpose, to leave room for the module to grow beyond just the `Earning`
aggregate if the proof of concept were ever extended (e.g. pay runs, deductions),
even though today it implements only what ADR-0001 describes. A future, genuinely
separate bounded context (if one ever appeared) would be a sibling folder next to
`Payroll/`, not a restructuring of it.

### Layers, and the dependency rule

Each module has four layers:

```
Payroll/
├── Domain/          # entities, value objects, aggregate, domain events, ports (interfaces)
├── Application/      # use cases: commands/queries + their handlers
├── Infrastructure/   # concrete adapters implementing Domain's ports
└── Ui/                # translates external input/output (CLI) to/from Application
```

Dependency direction is one-way, inward, toward `Domain/`:

- `Domain/` depends on nothing else in the project and on no framework — it defines
  ports (`EarningRepositoryInterface`, `ClockInterface`, `EventDispatcherInterface`) as
  interfaces, never concrete implementations.
- `Application/` depends only on `Domain/`.
- `Infrastructure/` implements `Domain/`'s ports; it may depend on `Domain/` and
  external libraries, never the reverse.
- `Ui/` depends on `Application/` (dispatches commands/queries) and is the thinnest
  layer — parses input, calls a handler, formats output. No business logic here.

Concrete implementations are wired to their ports only at the composition root
(`bin/console`), which is the only place allowed to know about both a port and its
concrete implementation at once.

### Application layer: Command + Handler

Each use case is a pair: an immutable command/query DTO (`AddCorrection`,
`RecalculateEarning`, `GetAuditHistory`) and a handler that depends on `Domain/` ports
to carry it out (`AddCorrectionHandler`, `RecalculateEarningHandler`,
`GetAuditHistoryHandler`). Chosen over a single generic "Application Service" class
per aggregate specifically to demonstrate the small, single-purpose
command/handler pattern the assignment's grading list calls out — may be
reconsidered if it proves like overkill once implementation starts.

### Domain events

`Domain/Event/` holds `EarningCalculated` and `CorrectionAdded` (raised by `Earning`
when `recalculate()` actually applies and when `addCorrection()` succeeds,
respectively), a `RecordsDomainEventsTrait` giving the aggregate a shared
mechanism to accumulate them, and an `EventDispatcherInterface` port. Only the
interface is decided now — no implementation or consumer exists yet; what (if
anything) needs to react to these events is deferred to implementation time.

### Naming conventions

Adopted the Symfony coding standard (symfony.com/doc/current/contributing/code/standards.html)
project-wide:

- Suffix interfaces with `Interface` (`EarningRepositoryInterface`, `ClockInterface`,
  `EventDispatcherInterface`).
- Suffix traits with `Trait` (`RecordsDomainEventsTrait`).
- Suffix exceptions with `Exception` (`CorrectionCommentCannotBeEmptyException`).
- Treat acronyms as ordinary words in PascalCase — first letter capitalized, rest
  lowercase (`Ui`, not `UI`; `Cli`, not `CLI`; `Id`, not `ID` — already used in
  `EarningId`, `CorrectionId`, etc.).

### Tests

`tests/Unit/` and `tests/Integration/` (PascalCase, matching PSR-4 namespace
segments — not `tests/unit`/`tests/integration`), each mirroring `src/Payroll/`'s
internal layer structure. Test doubles/dummy classes that aren't themselves tests
(e.g. `FixedClock`, a deterministic `ClockInterface` implementation used by both
suites) live in `tests/Fixtures/` — matching Symfony's own convention for exactly
this purpose (its components use `Tests/Fixtures/` for dummy/stub classes, not only
static data).

### Full tree

```
alcor-test/
├── composer.json
├── composer.lock
├── Dockerfile
├── docker-compose.yml
├── xdebug.ini
├── .gitignore
├── README.md
├── CLAUDE.md
├── doc/
│   ├── Alcor code assignment.docx.pdf
│   └── adr/
│       ├── 0001-earning-domain-model.md
│       └── 0002-project-structure.md
├── bin/
│   └── console                            # CLI entry point / composition root
├── src/
│   └── Payroll/
│       ├── Domain/
│       │   ├── Earning.php                 # aggregate root
│       │   ├── Correction.php              # entity
│       │   ├── EarningRepositoryInterface.php
│       │   ├── Event/
│       │   │   ├── RecordsDomainEventsTrait.php
│       │   │   ├── EarningCalculated.php
│       │   │   ├── CorrectionAdded.php
│       │   │   └── EventDispatcherInterface.php
│       │   ├── Exception/
│       │   │   └── CorrectionCommentCannotBeEmptyException.php
│       │   ├── ValueObject/
│       │   │   ├── EarningId.php
│       │   │   ├── EmployeeId.php
│       │   │   ├── CorrectionId.php
│       │   │   ├── PayrollSpecialistId.php
│       │   │   └── Money.php
│       │   ├── Audit/
│       │   │   ├── AuditHistory.php
│       │   │   └── AuditEntry.php
│       │   └── Clock/
│       │       └── ClockInterface.php
│       │
│       ├── Application/
│       │   ├── Command/
│       │   │   ├── AddCorrection.php
│       │   │   ├── AddCorrectionHandler.php
│       │   │   ├── RecalculateEarning.php
│       │   │   └── RecalculateEarningHandler.php
│       │   └── Query/
│       │       ├── GetAuditHistory.php
│       │       └── GetAuditHistoryHandler.php
│       │
│       ├── Infrastructure/
│       │   ├── Persistence/
│       │   │   └── InMemoryEarningRepository.php
│       │   └── Clock/
│       │       └── SystemClock.php          # implements ClockInterface
│       │
│       └── Ui/
│           └── Cli/
│               ├── AddCorrectionCommand.php
│               ├── RecalculateCommand.php
│               └── ShowAuditHistoryCommand.php
│
└── tests/
    ├── Unit/
    │   └── Payroll/
    │       ├── Domain/
    │       │   ├── EarningTest.php
    │       │   ├── CorrectionTest.php
    │       │   └── MoneyTest.php
    │       └── Application/
    │           ├── AddCorrectionHandlerTest.php
    │           └── RecalculateEarningHandlerTest.php
    ├── Integration/
    │   └── Payroll/
    │       ├── Infrastructure/
    │       │   └── InMemoryEarningRepositoryTest.php
    │       └── Ui/
    │           └── ConsoleApplicationTest.php   # end-to-end through bin/console
    └── Fixtures/
        └── FixedClock.php                        # implements ClockInterface
```

Indicative `composer.json` autoload mapping:

```json
{
    "autoload": {
        "psr-4": { "Alcor\\Payroll\\": "src/Payroll/" }
    },
    "autoload-dev": {
        "psr-4": {
            "Alcor\\Payroll\\Tests\\Unit\\": "tests/Unit/Payroll/",
            "Alcor\\Payroll\\Tests\\Integration\\": "tests/Integration/Payroll/",
            "Alcor\\Payroll\\Tests\\Fixtures\\": "tests/Fixtures/"
        }
    }
}
```

## Consequences

**Positive:**

- The layer split and port/adapter placement make the grading criteria's "separation
  of concerns", "dependency injection", and "small accurate interfaces" visible
  directly in the file layout, not just arguable from reading class bodies.
- Adding a second bounded context later is additive (a sibling module folder), not a
  refactor of `Payroll/`.
- Consistent Symfony-style naming (suffixes + acronym casing) makes it possible to
  tell whether something is an interface, trait, exception, or concrete class from
  its name alone, without opening the file.
- Splitting `tests/Unit/` from `tests/Integration/` lets a CI pipeline (or a local
  `composer test:unit`) run the fast, dependency-free suite separately from the
  slower, wiring-heavy one.

**Negative / trade-offs accepted:**

- More files and folders than a flat, single-namespace layout would need for an
  exercise of this size. Accepted because the structure itself is part of what's
  being evaluated per the assignment's grading list, not pure overhead.
- Command+Handler pairs double the file count compared to one Application Service
  class per aggregate. Accepted to explicitly demonstrate the command/handler
  pattern; may be simplified later if it proves like ceremony without benefit once
  real use cases are implemented.
- `EventDispatcherInterface` currently has no implementation or consumer — a
  deliberate placeholder. If nothing ever ends up needing to react to
  `EarningCalculated`/`CorrectionAdded`, the interface (and possibly the events
  themselves) should be removed rather than left unused.

### Alternatives considered and rejected

- **Module named `Earnings`** — rejected in favor of `Payroll`, to leave room for the
  module to grow beyond just this one aggregate.
- **Flat `src/` with no layer folders** — rejected; wouldn't visibly demonstrate the
  layered/DDD separation the assignment's grading criteria call out.
- **No `Interface`/`Trait`/`Exception` suffixes** (e.g. bare `EarningRepository`,
  `Clock`) — initially adopted, then reverted in favor of the Symfony suffix
  convention, both because the `Ui` layer may end up depending on `symfony/console`
  and for consistency with that ecosystem's well-known style.
- **`tests/Support/`, `tests/Shared/`, or `tests/Tools/`** for test doubles —
  rejected in favor of `tests/Fixtures/`, matching Symfony's own established
  convention for dummy/stub test classes, keeping the whole project under one naming
  philosophy.
- **Lowercase `tests/unit/`, `tests/integration/`** — rejected in favor of PascalCase
  (`Unit/`, `Integration/`) to mirror PSR-4 namespace segments, conventionally
  StudlyCaps in PHP.
