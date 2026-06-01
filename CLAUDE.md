# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`dimitrienkov0/laravel-modules` — a manifest-driven module loader and lifecycle toolkit for Laravel 12/13 (PHP 8.3+).

## Commands

```bash
composer test          # arch + unit + feature (full suite)
composer test:unit     # Unit suite only (PHPUnit)
composer test:feature  # Feature suite only (Testbench)
composer test:arch     # Architecture invariants (Pest)
composer phpstan       # static analysis — src/ only (level 8 + Larastan)
composer format        # PHP-CS-Fixer (PER 3.0 + project rules)
composer format:dry    # check formatting without writing
composer rector:dry    # preview Rector changes
```

Run a single test: `vendor/bin/phpunit --filter <TestName>` (Unit/Feature) or `vendor/bin/pest --filter <name>` (Architecture).
Run `composer format`, `composer phpstan`, `composer test` **separately** before any commit/PR.

## Critical rules

These are enforced by architecture tests or are non-obvious invariants — violating them breaks the build:

- **No Laravel facades or global helpers in `src/`.** Constructor DI only. Arch test forbids `Illuminate\Support\Facades\*`.
- **No `dd`/`dump`/`var_dump`/`print_r`/`exit`/`die` in `src/`.** Arch test fails.
- **`declare(strict_types=1);`** in every `.php` file under `src/`, `tests/`, `stubs/`.
- **Services/UseCases are `final readonly` with constructor DI.** No helpers, no facades.
- **`module.json` is immutable at runtime** — write it only via `ModuleManifestRepository::writeManifest()`. Mutable state (`enabled`, timestamps, `settings.values`, source provenance) lives in `state.json` and is written only via `ModuleStateRepository`. Never use raw `file_put_contents` for either.
- **Document actual runtime separately from roadmap.** Unimplemented commands/generators/admin pages are roadmap, not current functionality.
- **Do not edit `.ai-factory/rules/*`** without an explicit request (separate `$aif-rules` workflow).
- PR title in English, Conventional Commits format; other PR template sections in Russian.

## Key architectural concept

Two-file model per module: `module.json` (immutable `meta` + `settings.schema`) vs `state.json` in `storage/app/private/modules/{name}/` (mutable `enabled`, timestamps, `settings.values`, `source` provenance). `modules:optimize` caches discovery to `bootstrap/cache/modules.php` but **never** caches state or feature values — so changing `settings.values` needs no cache clear.

## Map & docs

- **Project map** (structure, entry points, runtime, stack): `AGENTS.md`
- **Architecture** (registry, cache, loader pipeline, lifecycle): `docs/architecture.md`
- **Manifest contract**: `docs/manifest.md` · **Configuration**: `docs/configuration.md`
- **CLI commands**: `docs/cli.md` · **Feature toggles**: `docs/feature-toggles.md`
- **Module structure**: `docs/module-structure.md` · **Getting started**: `docs/getting-started.md`
- **Quality gates & PR rules**: `docs/contributing.md`
