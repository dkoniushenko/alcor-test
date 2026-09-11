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

`src/Payroll/` is the (only, for now) domain module. Named `Payroll` rather than
`Earnings` — broader on purpose, to leave room for the module to grow beyond just the
`Earning` aggregate if the proof of concept were ever extended (e.g. pay runs,
deductions), even though today it implements only what ADR-0001 describes. A future,
genuinely separate bounded context (if one ever appeared) would be a sibling folder
next to `Payroll/`, not a restructuring of it.

### `src/Shared/`: a deliberately small shared kernel

`src/Shared/` holds code with no allegiance to a single module — the DDD "shared
kernel" pattern: a small, explicitly shared subset of the domain model that more than
one bounded context may depend on. Currently just `AbstractUuidId`
(`Shared/Domain/ValueObject/AbstractUuidId.php`, moved here from `Payroll/` once it
became clear it wasn't a `Payroll` concept at all — a UUID-based identifier base
class is generic value-object machinery any future module could equally need).

Rules for what goes here, and the dependency direction:

- `Shared/` follows the same internal layering as any other module — `Domain/`, plus
  `Application/` now that `EventDispatcherInterface` needs it (see "Domain events"
  below); `Infrastructure/`/`Ui/` only if it ever genuinely needs them.
- Other modules may depend on `Shared/` (e.g. `Payroll\Domain\ValueObject\EarningId
  extends Shared\Domain\ValueObject\AbstractUuidId`), but `Shared/` must never depend
  back on `Payroll/` or any other module — that direction would quietly recreate a
  circular dependency between modules and defeat the point of having a shared kernel.
- It should stay small and change rarely. Something earns its way into `Shared/` by
  being genuinely, identically useful to more than one module — not merely similar-
  looking. Anything with module-specific business meaning belongs in that module, not
  here.

### PSR-4 autoload strategy

One root mapping per prefix, not one per module: `Alcor\` → `src/` for production
code, `Alcor\Tests\` → `tests/` for tests (see `composer.json` for the actual
entries — package name and the exact mapping live there, not duplicated here). PSR-4
resolves everything after the mapped prefix straight onto the filesystem, so adding a
new module needs zero `composer.json` changes — creating `src/Identity/...` under
the existing `Alcor\` mapping would be enough.

Test namespaces are ordered `Alcor\Tests\<Unit|Integration>\<Module>\...` — `Tests`
right after `Alcor\`, not `Alcor\<Module>\Tests\...` — since that's what makes the
single `autoload-dev` mapping valid.

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
  ports (`EarningRepositoryInterface`, `ClockInterface`) as interfaces, never
  concrete implementations. The one accepted exception is pure value-type libraries
  with no infrastructure coupling of their own — `symfony/uid` and `moneyphp/money`
  are used directly in `Domain/` (in `EarningId`/`CorrectionId`/etc. and in
  `Money`-typed fields), not hidden behind a port. This is acceptable, often
  unavoidable, dependency leakage: unlike a database client, HTTP client, or ORM
  (infrastructure concerns, which *do* require a port), a UUID or money-arithmetic
  library is itself just a value type with no side effects or environment coupling —
  functionally no different from depending on PHP's own `DateTimeImmutable`.
- `Application/` depends only on `Domain/`, and may define its own ports too, not
  just consume `Domain/`'s — `EventDispatcherInterface` is one (see "Domain events"
  below): a capability only Application-layer orchestration needs, not the
  aggregate itself.
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

Split the same way as the other shared-vs-module concerns above, but across two
layers, not just two modules: `Shared/Domain/Event/` holds `AbstractDomainEvent`
(just an `occurredAt` timestamp, common to every event) and
`RecordsDomainEventsTrait` (lets an aggregate accumulate events, then
`pullDomainEvents()` them) — both genuinely used by Domain code (the aggregate
itself). `EventDispatcherInterface` (the dispatch port) lives in
`Shared/Application/Event/` instead: the aggregate never calls `dispatch()`, only
`record()` — dispatching the pulled events is an Application-layer concern (a
command handler's job after persisting), unlike `EarningRepositoryInterface`, which
stays a Domain port because a repository is conventionally part of the aggregate's
own persistence lifecycle (the canonical DDD placement), not just "something only
Application happens to call". `Payroll/Domain/Event/` holds the actual business
facts —
`EarningCalculated` and `CorrectionAdded` (raised by `Earning` when `recalculate()`
actually applies and when `addCorrection()` succeeds, respectively) — each a flat,
self-contained set of scalar/value-object fields rather than embedding the
`Earning`/`Correction` entities themselves, so an event stays a stable, independent
fact even if the entity's own shape changes later. Only `EventDispatcherInterface`'s
signature is decided so far — no implementation or consumer exists yet; what (if
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
- Domain exceptions extend the shared `AbstractDomainException`
  (`Shared/Domain/Exception/`, itself extending SPL's `\DomainException`), not an
  SPL exception directly — lets calling code catch any business-rule violation
  uniformly regardless of which module raised it.

### Tests

`tests/Unit/` and `tests/Integration/` (PascalCase, matching PSR-4 namespace
segments — not `tests/unit`/`tests/integration`), each mirroring `src/Payroll/`'s
internal layer structure. Test doubles/dummy classes that aren't themselves tests
(e.g. `FixedClock`, a deterministic `ClockInterface` implementation used by both
suites) live in `tests/Fixtures/` — matching Symfony's own convention for exactly
this purpose (its components use `Tests/Fixtures/` for dummy/stub classes, not only
static data).

### Namespace / folder skeleton

This is the part meant to stay stable and be remembered — individual files aren't
tracked here (check the actual `src/`/`tests/` trees for what currently exists in
each folder); only add a new line below when a genuinely new folder/layer appears.

```
src/
├── Shared/
│   ├── Domain/
│   │   ├── ValueObject/          # shared kernel — see "src/Shared/" above
│   │   ├── Clock/                 # ditto — no Payroll-specific meaning
│   │   ├── Exception/             # AbstractDomainException — base for all domain exceptions
│   │   └── Event/                 # AbstractDomainEvent, RecordsDomainEventsTrait
│   └── Application/
│       └── Event/                 # EventDispatcherInterface — an Application, not Domain, port
└── Payroll/
    ├── Domain/
    │   ├── Event/
    │   ├── Exception/
    │   ├── ValueObject/
    │   └── Audit/
    ├── Application/
    │   ├── Command/
    │   └── Query/
    ├── Infrastructure/
    │   └── Persistence/
    └── Ui/
        └── Cli/

tests/
├── Unit/
│   ├── Shared/
│   │   └── Domain/
│   │       ├── ValueObject/
│   │       └── Event/
│   └── Payroll/
│       ├── Domain/
│       └── Application/
├── Integration/
│   └── Payroll/
│       ├── Infrastructure/
│       └── Ui/
└── Fixtures/
```

## Consequences

**Positive:**

- The layer split and port/adapter placement make the grading criteria's "separation
  of concerns", "dependency injection", and "small accurate interfaces" visible
  directly in the file layout, not just arguable from reading class bodies.
- Adding a second bounded context later is additive (a sibling module folder), not a
  refactor of `Payroll/` — and thanks to the single root PSR-4 mapping, it needs zero
  `composer.json` changes too.
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
  philosophy. (Unrelated to the later `Shared/` module below — that one names a
  cross-module shared kernel, not a folder for test doubles; this rejected option
  would have meant something different if picked.)
- **Lowercase `tests/unit/`, `tests/integration/`** — rejected in favor of PascalCase
  (`Unit/`, `Integration/`) to mirror PSR-4 namespace segments, conventionally
  StudlyCaps in PHP.
- **One PSR-4 entry per module** (`"Alcor\\Payroll\\": "src/Payroll/"`, and a new
  entry for every future module) — rejected in favor of a single root mapping
  (`"Alcor\\": "src/"`), which needs no `composer.json` change when a module is
  added and is the more common convention for this project shape. Same reasoning
  applied to `autoload-dev` (`"Alcor\\Tests\\": "tests/"` instead of one entry per
  module per suite).
- **Keeping `AbstractUuidId` inside `Payroll/`** — the original placement, then moved
  to `Shared/` once it became clear the class has no `Payroll`-specific meaning at
  all (it's generic UUID-identifier machinery, not a payroll concept); leaving it in
  `Payroll/` would have meant a future module either duplicating it or reaching into
  `Payroll/`'s internals, both worse than a small, explicit shared kernel.
