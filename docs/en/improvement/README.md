# Improvement Notes

This folder collects design-level proposals and technical debt notes that are
larger than a single bug fix but smaller than a roadmap item. Nothing here has
been applied to the codebase — each document is a punch list of ideas to be
reviewed, prioritised and scheduled.

## Scope

Improvement notes focus on:

- **Schema changes** — indexes, foreign keys, column type tightening,
  partitioning, retention.
- **Cross-cutting concerns** — naming conventions, enums, soft deletes,
  locale handling.
- **Observability** — additional columns or tables that help diagnose
  failures (e.g. per-phase sync progress).

They do *not* cover application-level refactors, API changes, or UX work —
those belong in `architecture/` or `features/`.

## Index

| Document | Topic |
|----------|-------|
| [database-tables.md](./database-tables.md) | Per-table schema review, critical DB-related bugs, priority matrix |

## Relationship to other docs

- **`architecture/data-model.md`** — describes the schema as it is today.
- **`bugs/report_20apr.md`** — captures the live bugs surfaced during the
  multi-country sync rewrite; the DB-relevant subset is re-framed here as
  schema proposals.
- **`improvement/`** (this folder) — describes the schema as we would like
  it to be, with migration snippets and impact notes.

When an improvement note is implemented, the corresponding section should be
moved into `architecture/data-model.md` and removed from here, keeping this
folder focused on open proposals only.
