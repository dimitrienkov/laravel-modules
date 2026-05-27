# Research

Updated: 2026-05-27 22:30
Status: active

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
Topic: Добавление `schema_version` (top-level) и `meta.kind` (enum) в `module.json`
Goal: Версионировать формат manifest и классифицировать модули по типу для UI/CLI навигации без runtime-ограничений
Constraints:
- `schema_version` — top-level integer, required, strict-fail (нет fallback на default), начинаем с 1
- `meta.kind` — required backed string enum `ModuleKind` (`module` | `subsystem` | `integration`), чисто презентационное — loader pipeline и dependency resolution не зависят от kind
- Kind immutable (живёт в `module.json`, не в `state.json`)
- Cache payload version bump 3 → 4 (новые поля в descriptor)
- Добавление опционального поля в `meta` (как будущий `group`) — НЕ breaking change, schema_version остаётся 1
- Без `$schema` URI в v1 (можно позже для IDE tooling, runtime игнорирует)
Decisions:
- `schema_version` — top-level ключ (атрибут формата, не модуля), integer, не semver/URI
- `meta.kind` — внутри meta (метаданные модуля), backed enum `ModuleKind` в `src/Manifest/Enums/`
- Kind обязательный — scaffold подставляет из целевой директории (`app/Modules` → `module`, `app/Integrations` → `integration`, `app/Subsystems` → `subsystem`)
- Kind НЕ расширяемый пользователем — конечный enum, новый case через minor release пакета
- Kind НЕ влияет на runtime: не ограничивает зависимости, не определяет набор лоадеров, не влияет на enable/disable
- `meta.group` для модулей — будущая задача (сейчас group есть только в `settings.schema.*.group` для feature definitions)
- Все существующие тесты потребуют обновления формата manifest fixtures
Open questions:
- Нужен ли `--kind=` фильтр в `modules:list` сразу или отложить до MoonShine UI?
- Нужен ли `meta.group` (опциональный) одновременно с `meta.kind` или отдельная задача?
Success signals:
- `module.json` с `schema_version: 1` и `meta.kind` проходит валидацию
- Manifest без `schema_version` или с неизвестной версией — reject с `InvalidManifestException`
- `make:module` и scaffold автоматически проставляют kind из целевой директории
- `modules:list` показывает столбец Kind
- Cache payload v4 сериализует/десериализует schema_version и kind
- Все существующие тесты проходят с обновлённым форматом
Next step: `/aif-plan` для создания плана реализации
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->
### 2026-05-27 22:30 — schema_version + meta.kind исследование

What changed:
- Изучена текущая manifest-цепочка: `ManifestDocumentReader` → `ManifestValidator` → `Module::fromManifest()` → `ManifestMeta::fromArray()` → VO
- Изучен cache payload v3 (`ModuleRegistryCachePayload`, `CachedModuleDescriptor`)
- Изучены scaffold/install use cases — как пишется `module.json`
- Проведено сравнение с другими системами: Drupal (type: required enum, strict-fail), Magento (dual-layer type), WordPress (по директории), nWidart (нет kind/schema version), Kubernetes (apiVersion + kind, strict-fail), Docker Compose (version obsolete — анти-паттерн), npm (type = module system, не taxonomy)
- Strict-fail — доминирующий паттерн для production-систем
- Integer schema version — совпадает с паттерном cache version (уже int в payload)

Key notes:
- `ManifestValidator::ALLOWED_TOP_LEVEL_KEYS` — точка добавления `schema_version`
- `ManifestMeta::ALLOWED_KEYS` — точка добавления `kind`
- `ModuleRegistryCachePayload::SUPPORTED_VERSION = 3` → bump до 4
- `ModuleSourceKind` (Directory/Zip) уже существует в `Application/Enums/` — `ModuleKind` будет в `Manifest/Enums/` (отличный namespace)
- `config/modules.php` `paths.directories` уже содержит три пути: `app/Modules`, `app/Integrations`, `app/Subsystems` — natural mapping для kind inference
- `Module::toDescriptorArray()` — точка сериализации, должен включить `schema_version`
- `ScaffoldModuleUseCase` создаёт `ManifestMeta` напрямую через конструктор — нужен новый параметр `kind`

Links (paths):
- `src/Manifest/ManifestValidator.php` — top-level key validation
- `src/Manifest/VO/ManifestMeta.php` — meta parsing и allowed keys
- `src/Manifest/VO/Module.php` — fromManifest/toDescriptorArray
- `src/Manifest/Enums/FeatureType.php` — пример существующего backed enum
- `src/Application/Enums/ModuleSourceKind.php` — пример enum в Application слое
- `src/Registry/VO/ModuleRegistryCachePayload.php` — cache version и payload format
- `src/Registry/ModuleRegistryCache.php` — cache read/write
- `src/Application/UseCases/ScaffoldModuleUseCase.php` — scaffold flow
- `src/Console/Commands/Modules/ModulesListCommand.php` — list display
- `config/modules.php` — paths.directories → kind inference

Точки изменения (17 файлов/тестов):
1. `ModuleKind` enum (NEW: `src/Manifest/Enums/ModuleKind.php`)
2. `ManifestValidator` — ALLOWED_TOP_LEVEL_KEYS += schema_version, validate schema_version
3. `ManifestMeta` — ALLOWED_KEYS += kind, + public ModuleKind $kind
4. `Module` — toDescriptorArray() += schema_version, fromManifest() пробрасывает
5. `ModuleRegistryCachePayload` — SUPPORTED_VERSION 3 → 4
6. `ScaffoldModuleUseCase` — ManifestMeta с kind, module.json с schema_version
7. `InstallModuleUseCase` — валидация schema_version при чтении source
8. `ScaffoldModuleConfig` DTO — + ?ModuleKind $kind
9. `ModulesListCommand` — столбец Kind, опционально --kind= фильтр
10. `MakeModuleCommand` — --kind= опция
11. `stubs/module.json.stub` — schema_version + kind
12-17. Тесты: unit + feature + arch + regression fixtures
<!-- aif:sessions:end -->
