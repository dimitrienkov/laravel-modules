# Правила манифеста

> Правила для работы с `module.json`. Загружаются после `rules/base.md`.

## Правила

- `module.json` остаётся единственным источником правды для метаданных и `settings.schema`; mutable state и explicit values хранятся только в `state.json`.
- `meta` и `settings.schema` неизменяемы после установки; меняй их только через `modules:update` и `ModuleManifestRepositoryInterface::writeManifest()`.
- В обычных runtime-операциях изменяй только `state` и `settings.values` в `state.json`, всегда через `ModuleStateRepositoryInterface`.
- Валидируй `settings.values` по `settings.schema` при каждом чтении и записи state.
- Не добавляй секцию `autoload` в `module.json`; лоадеры находят работу по файлам и директориям модуля через `ModuleLayout`.
- Не поддерживай list-form dependencies; `meta.dependencies` принимает только object-form `moduleName => Composer SemVer constraint`, включая явный `"*"` для wildcard.
