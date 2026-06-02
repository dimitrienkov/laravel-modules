# Базовые правила проекта

> Архитектурные границы, manifest-контракты, документация и quality gates. Правила качества кода — в `code-quality.md`, именования — в `naming.md`. Подробную архитектуру, runtime flow и таблицы loader priority смотри в `.ai-factory/ARCHITECTURE.md`.

## Границы И DI

- `Console/Commands` остаются тонкими adapters: вызывают `Application/UseCases` или публичные `Contracts`, не оркестрируют runtime напрямую.
- `Application/UseCases` оркестрируют операции и не зависят от `Loaders`, `Providers`, `MoonShine`.
- `Contracts` не импортируют реализации.
- `Loaders` реализуют только convention-based loading через `LoaderInterface`; не добавляй manifest-флаги или whitelist-контракты загрузки.
- Optional integrations защищай проверками классов/контейнера, чтобы пакет запускался без MoonShine или Inertia.
- Конструкторы сервисов не должны делать side effects.

## Manifest И State

- `module.json` в runtime иммутабелен: только `meta` и `settings.schema`; `state`, `settings.values` и `autoload` в manifest запрещены.
- Mutable state и explicit feature values хранятся в `state.json` и пишутся через `ModuleStateRepositoryInterface`.
- Immutable manifest descriptor записывается только через `ModuleManifestRepositoryInterface::writeManifest()`.
- `meta.dependencies` принимает только object-form `moduleName => Composer SemVer constraint`; list-form dependencies не поддерживаются.
- `settings.schema` может содержать metadata `label`, `description`, `group`; это текущий допустимый контракт.
- Source-модуль не должен поставлять `state.json`; state принадлежит host-приложению.

## Документация И Roadmap

- Документируй только фактический runtime как реализованный.
- Module-aware генераторы `make:* --module` и архитектурные генераторы (`make:use-case` / `make:action` / `make:query` / `make:dto` / `make:vo`) реализованы — документируй их как текущий runtime, не как roadmap.
- Нереализованные full MoonShine admin UI, lifecycle events, marketplace/signature/fetch flows помечай как roadmap.
- Не дублируй большие описания между AI-context файлами: `ARCHITECTURE.md` хранит дизайн и runtime flow, `base.md` - короткие правила, `AGENTS.md` - карту проекта, `DESCRIPTION.md` - product/runtime summary.

## Тесты И Quality Gates

- Unit-тесты должны покрывать новые VO/services/use cases как минимум valid path и один error path.
- Для новых structural rules добавляй architecture tests.
- Перед PR/коммитом запускай отдельно:

```bash
composer format
composer phpstan
composer test
```

- Для review полезны read-only проверки:

```bash
composer format:dry
composer rector:dry
```
