# al-falaq/al-falaq (PHP)

Offline, zero-dependency PHP 8.2+ library for Islamic astronomy: prayer times, Qibla, Sunnah markers, crescent visibility, Hijri conversion.

Status: **M0 skeleton**. See `Technical-PRD.md` (build contract, Appendices A–C normative).

## Install

```bash
composer require al-falaq/al-falaq
```

Requires PHP `>=8.2` (64-bit), no runtime dependencies.

## Quickstart (M0)

```php
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

$coords = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('UTC')));
```

Full prayer pipeline lands in M1 after Appendix A freeze (`PYTHON_PIN` + `tools/export_goldens.py`).

## Scripts

```bash
composer lint && composer cs && composer stan && composer test
composer ci   # all of the above
composer bench
```

## Docs

- `Technical-PRD.md` — build contract
- `ARCHITECTURE.md` — layering summary
- `docs/` — per-context guides (M1–M5)
- `UQ_PROVENANCE.md` — Umm al-Qura source + checksum (M4)
