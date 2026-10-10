# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- M0 skeleton: composer manifest, SharedKernel value objects, domain stubs per Technical-PRD.md §8, Pest 3 + ArchTest, CI matrix, examples, tools placeholders.
- M1 prayer parity: presets frozen (18 methods, dual-entered from pin
  `a7baa8b202790d3bc84c47a610ac763be6c0b7e7`, freeze `c151828`
  checksummed `sha256 3bb6df93…9356`); Meeus Ch.15/25 engine
  (`MeanSolarCalculator`: transit/hour-angle interpolation, solar coordinates,
  EoT); full pipeline (`PrayerTimesCalculator` + polar/high-latitude/Ramadan
  branches) + `TimetableService` + `NextPrayerResolver`; goldens 100%
  minute-equality (15 files: 11 pin + turkey/kemenag/muis/brunei official-table
  vectors; tehran/morocco pending, no obtainable tables). Known gaps: UOIF is
  pin-only with no preset; provisional residual cells emptied per file with
  reasons (`adee976`: turkey Dec maghrib +1 seasonal wobble; kemenag 13 cells
  ±1 ephemeris gap vs Kemenag tables; muis June fajr single-cell rounding
  noise; brunei dhuha — no published KHEU Doha definition — + Dec ±1 noise);
  abs-vs-signed seventh-gate resolved to signed `latitude >= 55` (`47facea`).
  `docs/prayer.md` algorithm appendix + PRD §§5.2/18/Appendix A synced to code.

## [1.0.0] - TBD

- M5 hardening gate.
