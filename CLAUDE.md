# Alcor OS — Coding Test

Take-home coding test for a job application at Alcor OS (an all-in-one platform for
managing contractors for product companies). Assignment source: `doc/Alcor code
assignment.docx.pdf`.

**Implementation is complete.** The domain model, application layer, CLI demo, full
PHPUnit suite, and PHPStan/Deptrac/PHP-CS-Fixer tooling are all in place and green —
see the README for how to run them and the ADRs below for the design.

Architectural/design decisions are recorded as ADRs in **`doc/adr/`**:

- **[0001-earning-domain-model.md](doc/adr/0001-earning-domain-model.md)** — the
  `Earning`/`Correction`/`Money` domain model: naming, the core `currentValue()`
  formula, invariants, and alternatives considered and rejected.
- **[0002-project-structure.md](doc/adr/0002-project-structure.md)** — the modular
  monolith / DDD layered folder structure (`src/Payroll/Domain|Application|
  Infrastructure|Ui`), Command+Handler application layer, domain events placement,
  Symfony naming conventions (`Interface`/`Trait`/`Exception` suffixes, acronym
  casing), and the `tests/Unit|Integration|Fixtures` split.

Read both before writing any code so naming, layering, and invariants stay consistent
with what was agreed. Future non-trivial design decisions should get their own
numbered ADR in that folder.

## Task

Design and implement a domain model titled **"History of Manual Adjustments to an
Earning Line"**.

### Business case

- A payroll specialist reviews a line with an employee's base salary that the system
  calculates automatically.
- Sometimes the specialist needs to manually correct it (e.g. a declined benefit or a
  late correction).
- The correction is entered as a positive or negative amount with a **mandatory
  comment** explaining why.

### Hard rules

1. A single line can receive multiple corrections over time.
   - Each correction must stay visible and traceable — **never edited or silently
     deleted** once saved.
   - If the specialist made a mistake, they add a **new** correction that offsets the
     previous one (append-only).
2. Once a line has received **at least one** manual correction, automatic system
   recalculation must **no longer affect it** — the specialist's corrections take
   permanent precedence, even if the underlying source data used to calculate the line
   later changes (i.e. the system value gets frozen at the point of the first
   correction).
3. At any point it must be possible to see the line's **current value** and the **full
   audit history** of corrections that produced it.

### Worked example (must match exactly)

| Step | Event | Amount | Comment | Value after step |
|---|---|---|---|---|
| 1 | System calculates the line | — | — | $1,000.00 |
| 2 | Source data changes, system recalculates (no correction yet → allowed) | — | — | $1,050.00 |
| 3 | Specialist adds a manual correction | −$45.55 | "Employee declined dental benefit; reversing deduction" | $1,004.45 |
| 4 | Source data changes again, system attempts to recalculate | — | (ignored — line already has a manual correction) | $1,004.45 |
| 5 | Specialist adds a second correction | +$100.10 | "Late correction: missed approved overtime bonus" | $1,104.55 |
| 6 | Specialist adds a third correction | −$0.10 | "Minor rounding adjustment" | $1,104.45 |
| 7 | Specialist adds a fourth correction | −$0.20 | "Second minor rounding adjustment" | $1,104.25 |
| 8 | Specialist adds a compensating correction (fixing step 7) | +$0.20 | "Correcting mistake in adjustment #4" | $1,104.45 |

Expected final audit history:

| Entry | Value |
|---|---|
| System value (frozen at step 3) | $1,050.00 |
| Adjustment 1 | −$45.55 |
| Adjustment 2 | +$100.10 |
| Adjustment 3 | −$0.10 |
| Adjustment 4 | −$0.20 |
| Adjustment 5 | +$0.20 |
| Current (new) value | $1,104.45 |

Any implementation must reproduce these exact numbers when run through this scenario.

## Deliverables expected by Alcor

- Solution written in easy to understand, modern PHP.
- Test coverage with **PHPUnit**.
- A **README** explaining how it works and any assumptions made.
- Pushed to a **public** GitHub repo.
- Using AI tools while working on this is explicitly welcomed/encouraged.

## Grading emphasis (from the recruiter email template)

Not everything needs to be demonstrated — treat this as a menu, not a checklist:

- Modern PHP
- Good separation / encapsulation of concerns
- Small, accurate interfaces / classes / commands / events / aggregate boundaries
- Dependency injection
- Source control: conventional, meaningful commits and essential (non-redundant)
  comments

Their real codebase uses **DDD + CQRS + Event Sourcing**, but a simpler
object-oriented design (no Event Sourcing) is explicitly acceptable — what matters is
that the business rules are implemented correctly and reliably, and the design choice
can be justified.

**This is a data modeling exercise, not a UI/framework exercise.** No web UI or
framework is needed — a CLI entry point plus unit tests is sufficient and preferred,
to leave more time for the domain design itself.

Recommended time budget: 2–6 hours.

## Current project state / environment

- `composer.json`: PHP 8.4, `symfony/uid` and `moneyphp/money` as runtime deps;
  `phpunit/phpunit` and `bamarni/composer-bin-plugin` as dev deps — the latter isolates
  `phpstan`, `deptrac`, and `php-cs-fixer` under their own `tools/*/composer.json`
  namespaces so their dependencies can't version-conflict with the main project.
- `src/Payroll/` and `src/Shared/` hold the full implementation (domain model,
  application layer, in-memory infrastructure, CLI) described in the ADRs below.
- `tests/Unit/`, `tests/Integration/`, `tests/Fixtures/` hold the PHPUnit suite.
- `bin/demo` runs the assignment's full 8-step worked example end to end.
- `Dockerfile`: `php:8.4-cli` base image with `xdebug`, `ext-intl` (for
  locale-aware money formatting), and `composer` (v2, copied from the official
  composer image) installed.
- `docker-compose.yml`: single `php` service, bind-mounts the repo to `/app`, runs
  `tail -f /dev/null` (exec into it to run commands), Xdebug configured to connect back
  to the host IDE (`host.docker.internal:9003`, idekey `PHPSTORM`).
- `.gitignore` excludes `.idea`, `vendor/`, `tools/*/vendor/`, and the
  phpunit/php-cs-fixer/deptrac cache files.
- `doc/` holds the original assignment PDF (not meant to be treated as source code)
  and `doc/adr/` for the ADRs.

## Working agreement

- **Never create files or run commands (composer, docker, git, package installs,
  etc.) without an explicit, unambiguous instruction to do so — this includes
  scaffolding, not just domain implementation code.** A question about how to do
  something ("як це зробити?") is a request for an explanation, not permission to do
  it. Default to explaining the steps in chat and wait for an explicit go-ahead
  before executing anything. (Writing/updating markdown docs the user directly asked
  for, like this file or an ADR, is the one thing that doesn't need re-confirming
  each time.)
- **Never run `git commit` (or `git push`) in this repo.** The user handles all
  commits themselves. Editing/creating files is fine when explicitly asked; leave
  staging and committing to the user.
