# Documentation map

This repository has two documentation audiences. Keep each fact in the tree
that owns it, and link to the other tree instead of repeating it.

## Audiences

| Tree | Audience | Canonical for | Published? |
|---|---|---|---|
| `docs/` | contributors and maintainers | algorithm appendices, provenance, internal invariants, error-message targets | no |
| `docs/user/` | library users | installation, usage, API reference, migration, changelog, citations | yes (Astro Starlight) |
| `docs/plans/` | maintainers | historical implementation plans | no |
| `docs/artifacts/` | maintainers and reviewers | dated point-in-time audits and strategic reports | no |
| `ARCHITECTURE.md`, `UQ_PROVENANCE.md` | contributors | architecture and table provenance | repo only |

## Page map

| Contributor page | Canonical for | User-facing counterpart |
|---|---|---|
| [prayer.md](prayer.md) | prayer algorithm appendix and method freeze | [user/prayer-times.md](user/prayer-times.md), [user/calculation-methods.md](user/calculation-methods.md), [user/high-latitude.md](user/high-latitude.md) |
| [qibla.md](qibla.md) | spherical and WGS84 geodesy internals | [user/qibla.md](user/qibla.md) |
| [moon.md](moon.md) | crescent geometry and criteria internals | [user/moon-sighting.md](user/moon-sighting.md) |
| [hijri.md](hijri.md) | calendar algorithms and table provenance | [user/hijri-converter.md](user/hijri-converter.md) |
| [sunnah.md](sunnah.md) | night-fraction internals | [user/sunnah-times.md](user/sunnah-times.md) |
| [polar.md](polar.md) | polar fallback internals | [user/polar-regions.md](user/polar-regions.md) |
| [migration.md](migration.md) | M1-M5 architecture migration | [user/migration.md](user/migration.md) |
| [integrations.md](integrations.md) | executable plain-`new` wiring | [user/integration.md](user/integration.md) |

## Artifacts

Dated point-in-time audits and strategy reports. They record what was true at
their own commit/date; do not rewrite their findings in place. Later
verification belongs in a new artifact or the CHANGELOG. Historical plans may
still cite the original root filenames; the links above are canonical.

| Artifact | Scope |
|---|---|
| [2026-10-22-repo-hygiene-report.md](artifacts/2026-10-22-repo-hygiene-report.md) | Repository hygiene snapshot at `ebcc3aa`, including the 2026-11-02 tier-delivery verification |
| [2026-11-11-project-improvement-report.md](artifacts/2026-11-11-project-improvement-report.md) | Strategic improvement plan, package boundaries, and release roadmap |

## Editing rules

- New user-facing page: add it under `docs/user/`, wire it into
  `docs_site/astro.config.mjs`, and follow the release snapshot process.
- New contributor note: add it under `docs/` and add a row to this map.
- Never state the same fact in both trees. `docs/` links to `docs/user/` for
  usage; `docs/user/` does not link to internals.
- Error messages cite `docs/*.md`; `tests/ArchDocsTest` fails if a cited page
  disappears.
- `docs/plans/` is historical: plans record decisions at a point in time and
  are not updated after execution.
