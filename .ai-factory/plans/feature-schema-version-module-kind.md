# Implementation Plan: schema_version + ModuleKind

Branch: feature/schema-version-module-kind
Created: 2026-05-27
Refined: 2026-05-27

## Settings
- Testing: yes
- Logging: no (диагностическое логирование — отдельный этап в ROADMAP)
- Docs: yes (обязательный checkpoint)

## Roadmap Linkage
Milestone: "Фаза 2 — Классификация модулей для UI и версия manifest-схемы"
Rationale: Точное совпадение — задача реализует `schema_version` и `meta.kind` из roadmap item Фазы 2.

## Research Context
Source: .ai-factory/RESEARCH.md (Active Summary)

Goal: Версионировать формат manifest и классифицировать модули по типу для UI/CLI навигации без runtime-ограничений

Constraints:
- `schema_version` — top-level integer, required, strict-fail (нет fallback на default), начинаем с 1
- `meta.kind` — required backed string enum `ModuleKind` (`module` | `subsystem` | `integration`), чисто презентационное — loader pipeline и dependency resolution не зависят от kind
- Kind immutable (живёт в `module.json`, не в `state.json`)
- Cache payload version bump 3 → 4 (новые поля в descriptor)
- Без `$schema` URI в v1 (можно позже для IDE tooling, runtime игнорирует)

Decisions:
- `schema_version` — top-level ключ (атрибут формата, не модуля), integer, не semver/URI
- `meta.kind` — внутри meta (метаданные модуля), backed enum `ModuleKind` в `src/Manifest/Enums/`
- Kind обязательный — scaffold подставляет из целевой директории
- Kind НЕ расширяемый пользователем — конечный enum, новый case через minor release пакета
- Kind НЕ влияет на runtime: не ограничивает зависимости, не определяет набор лоадеров, не влияет на enable/disable
- `meta.group` — отдельная задача (не входит в этот план)
- `--kind=` фильтр в `modules:list` — включён в этот план

Open questions: resolved — все вопросы закрыты в этом плане.

## Анализ необходимости

### Зачем schema_version
- Manifest format будет эволюционировать (kind — первое расширение, дальше — `$schema`, `group`, etc.)
- Без версионирования невозможно корректно reject'ить несовместимые manifest при install/update
- Паттерн совпадает с существующим cache version (integer) — единообразие
- Drupal, Kubernetes, все production-системы используют strict-fail schema versioning
- nwidart/laravel-modules не имеет schema version — мы закрываем пробел в Laravel-экосистеме

### Зачем meta.kind
- Конфиг уже определяет 3 директории: `app/Modules`, `app/Integrations`, `app/Subsystems`
- Без kind единственный сигнал классификации — путь к директории (fragile, не portable)
- MoonShine admin-UI (следующий roadmap) требует первый уровень навигации по типу модуля
- `settings.schema.*.group` группирует настройки ВНУТРИ модуля — не подходит как классификатор между модулями
- Drupal использует required type enum — ближайший прецедент для нашего подхода
- npm type тоже required enum, но влияет на runtime — наш kind чисто презентационный

### Что НЕ меняется
- Loader pipeline: kind не влияет на загрузку — все модули проходят один pipeline
- Dependency resolution: kind не ограничивает зависимости между модулями
- Enable/disable: kind не влияет на state
- Feature toggles: kind не влияет на feature values
- `state.json`: kind не добавляется в state — это immutable атрибут manifest

## Верификация цепочки валидации

`ManifestValidator::validate()` — единственная точка валидации manifest, вызывается:
1. `ModuleManifestRepository::load()` — при загрузке модуля
2. `ModuleManifestRepository::writeManifest()` — при записи (re-validate before write)
3. `ModuleSourcePreparer::prepare()` — при install из directory/zip
4. `ModuleRegistryCache::load()` — при загрузке из cache (re-validate each descriptor)

Добавление `schema_version` в валидатор автоматически покрывает ВСЕ entry points.
Cache version bump 3→4 гарантирует reject старого cache на уровне payload.

## Commit Plan

- **Commit 1** (после tasks 1-6): `feat(manifest): add schema_version and ModuleKind to manifest contract`
- **Commit 2** (после tasks 7-11): `feat(lifecycle): wire schema_version and kind into scaffold, install, and CLI`
- **Commit 3** (после task 12): `test: migrate all test fixtures to schema_version 1 and meta.kind`
- **Commit 4** (после tasks 13-14): `test: add comprehensive coverage for schema_version and ModuleKind`
- **Commit 5** (после task 15): `docs: update manifest contract documentation for schema_version and kind`

## Tasks

### Phase 1: Инфраструктура парсинга и Enum (фундамент)

- [x] Task 1: Добавить `requiredInt` в `ManifestFieldReader`
  - Файл: `src/Manifest/Parsing/ManifestFieldReader.php`
  - Реализация: метод `requiredInt(array $data, string $key, string $context, string $manifestPath): int`
  - Паттерн: аналогично `requiredString` — проверяет наличие ключа, тип `is_int`, бросает `InvalidManifestException` при отсутствии или неверном типе
  - **Приёмка ✅:** метод существует, возвращает `int`, корректно бросает `InvalidManifestException` для отсутствующего ключа и не-integer значений (string `"1"`, float `1.0`, null)
  - **Приёмка ❌:** метод отсутствует или валидация `schema_version` реализована inline в `ManifestValidator` (нарушение DRY и паттерна `ManifestFieldReader`)

- [x] Task 2: Создать enum `ModuleKind` в `src/Manifest/Enums/ModuleKind.php`
  - Cases: `Module = 'module'`, `Subsystem = 'subsystem'`, `Integration = 'integration'`
  - Паттерн: аналогично `FeatureType` (backed string enum)
  - Namespace: `Manifest\Enums` (НЕ `Application\Enums` — kind описывает манифест, не операцию)
  - **Приёмка ✅:** enum файл существует, 3 cases, backed string, проходит существующий arch-тест `'manifest enums are string backed enums'`
  - **Приёмка ❌:** enum в неверном namespace (`Application\Enums`), не backed string, или отсутствует case

### Phase 2: Manifest Layer (валидация и VO)

- [x] Task 3: Расширить `ManifestValidator` — валидация `schema_version` (depends on 1)
  - `ALLOWED_TOP_LEVEL_KEYS += 'schema_version'`
  - Константа `public const int CURRENT_SCHEMA_VERSION = 1` (public — для переиспользования в тестах и use cases)
  - Validate: в `validate()` после `assertTopLevelKeys()`, но ДО парсинга `meta` — strict-fail до обработки контента
  - Использовать `ManifestFieldReader::requiredInt()` для чтения
  - Проверка: значение === `CURRENT_SCHEMA_VERSION`, иначе throw `InvalidManifestException`
  - Файл: `src/Manifest/ManifestValidator.php`
  - **Приёмка ✅:** manifest без `schema_version` → `InvalidManifestException`; `schema_version: 2` → `InvalidManifestException`; `schema_version: "1"` (string) → `InvalidManifestException`; `schema_version: 1` → проходит
  - **Приёмка ❌:** `schema_version` отсутствует в `ALLOWED_TOP_LEVEL_KEYS`; константа private; fallback на default вместо strict-fail

- [x] Task 4: Расширить `ManifestMeta` — свойство `kind` (depends on 2)
  - `ALLOWED_KEYS += 'kind'`
  - Property: `public ModuleKind $kind` (required) — добавить в конструктор
  - `fromArray()`: `ManifestFieldReader::requiredString()` для `kind`, затем `ModuleKind::tryFrom()`, throw `InvalidManifestException` при неизвестном значении
  - `toArray()`: `'kind' => $this->kind->value` — в выходной массив
  - Roundtrip: `ManifestMeta::fromArray($meta->toArray(), path)` воспроизводит идентичный объект
  - Файл: `src/Manifest/VO/ManifestMeta.php`
  - **Приёмка ✅:** конструктор принимает `ModuleKind $kind`; `fromArray()` парсит `kind` → enum; `toArray()` сериализует `kind`; неизвестный kind → `InvalidManifestException`
  - **Приёмка ❌:** kind опционален или имеет fallback; kind не попадает в `toArray()`; camelCase ключ `moduleKind` вместо `kind`

<!-- Commit checkpoint: tasks 1-4 + tasks 5-6 → Commit 1 -->

### Phase 3: Module VO и Cache

- [x] Task 5: Расширить `Module` VO — `schemaVersion` property (depends on 3, 4)
  - Property: `public int $schemaVersion` — добавить в конструктор
  - `fromManifest()` → читает `$manifest['schema_version']` (top-level, не из meta)
  - `toDescriptorArray()` → включает `'schema_version' => $this->schemaVersion` на верхнем уровне (рядом с `meta` и `settings`, НЕ внутри них)
  - `withState()` → сохраняет `$this->schemaVersion` в новом экземпляре
  - Файл: `src/Manifest/VO/Module.php`
  - **Приёмка ✅:** конструктор имеет `int $schemaVersion`; `fromManifest()` читает `schema_version` из top-level; `toDescriptorArray()` содержит `schema_version` на верхнем уровне; `withState()` переносит `schemaVersion`
  - **Приёмка ❌:** `schema_version` в `toDescriptorArray()` попадает внутрь `meta` или `settings`; `withState()` теряет `schemaVersion`

- [x] Task 6: Bump `ModuleRegistryCachePayload` `SUPPORTED_VERSION` 3→4 (depends on 5)
  - Файл: `src/Registry/VO/ModuleRegistryCachePayload.php`
  - Изменение: `private const int SUPPORTED_VERSION = 4;`
  - Следствие: существующий cache v3 будет автоматически reject (strict-fail в `fromCachedArray`)
  - **Приёмка ✅:** `SUPPORTED_VERSION === 4`; `fromModules()` генерирует payload с version 4; `fromCachedArray()` с version 3 → `InvalidModuleCacheException`
  - **Приёмка ❌:** version остался 3; payload не содержит `schema_version` в descriptor manifest

<!-- Commit checkpoint: tasks 1-6 → Commit 1 -->

### Phase 4: Application Layer и CLI

- [x] Task 7: Расширить `ScaffoldModuleConfig` + `ScaffoldModuleUseCase` (depends on 2-5)
  - DTO: `public ?ModuleKind $kind = null` в `ScaffoldModuleConfig`
  - UseCase: kind inference из target directory — сопоставить `targetRoot` с configured roots:
    - Последний сегмент `Modules` → `ModuleKind::Module`
    - Последний сегмент `Integrations` → `ModuleKind::Integration`
    - Последний сегмент `Subsystems` → `ModuleKind::Subsystem`
    - Default → `ModuleKind::Module`
  - `$config->kind` если не null — override inference
  - `new Module()` вызов: добавить `schemaVersion: ManifestValidator::CURRENT_SCHEMA_VERSION`
  - `new ManifestMeta()` вызов: добавить `kind: $resolvedKind`
  - Файлы: `src/Application/DTOs/ScaffoldModuleConfig.php`, `src/Application/UseCases/ScaffoldModuleUseCase.php`
  - **Приёмка ✅:** scaffold пишет `schema_version: 1` и `kind` в manifest; kind определяется из целевой директории; explicit `--kind=` override работает; `null` kind → inference
  - **Приёмка ❌:** kind не попадает в manifest при scaffold; `schema_version` отсутствует; inference не работает

- [x] Task 8: Обновить `MakeModuleCommand` — `--kind` опция (depends on 7)
  - Опция: `--kind=module|subsystem|integration` (optional, inference если не указана)
  - Пробросить в `ScaffoldModuleConfig(kind: ...)` — парсинг `ModuleKind::tryFrom()`, error на невалидное значение
  - Команда остаётся тонким адаптером — никакой бизнес-логики
  - Файл: `src/Console/Commands/Modules/MakeModuleCommand.php`
  - **Приёмка ✅:** `--kind=integration` → scaffold с `kind: integration`; без `--kind` → inference из директории; `--kind=invalid` → error с перечислением допустимых значений
  - **Приёмка ❌:** команда содержит inference-логику; невалидный kind не показывает допустимые значения

- [x] Task 9: Обновить `ListModulesUseCase` — параметр `kindFilter` (depends on 2, 5)
  - `execute(?bool $enabledFilter = null, ?ModuleKind $kindFilter = null): ListModulesResult`
  - Фильтрация: `$m->meta->kind === $kindFilter` (AND с `$enabledFilter`)
  - Файл: `src/Application/UseCases/ListModulesUseCase.php`
  - **Приёмка ✅:** `execute(kindFilter: ModuleKind::Integration)` возвращает только integration-модули; AND-фильтр с `enabledFilter` работает; `null` → без фильтрации
  - **Приёмка ❌:** фильтрация по kind реализована в команде, а не в use case (нарушение base.md: тонкие команды)

- [x] Task 10: Обновить `ModulesListCommand` — столбец Kind + `--kind=` фильтр (depends on 5, 9)
  - Новый столбец: Kind (после Name) — значение `$m->meta->kind->value`
  - Фильтр: `--kind=module|subsystem|integration` с валидацией через `ModuleKind::tryFrom()`
  - Проброс в `ListModulesUseCase::execute(kindFilter: $kind)`
  - `--kind=` + `--enabled` работают вместе (AND-фильтр)
  - `--kind=invalid` → ошибка с перечислением допустимых значений
  - Файл: `src/Console/Commands/Modules/ModulesListCommand.php`
  - **Приёмка ✅:** таблица содержит столбец Kind; `--kind=module` фильтрует; `--kind=invalid` → ошибка; `--kind=module --enabled` — AND-фильтр
  - **Приёмка ❌:** столбец Kind отсутствует; фильтрация по kind в команде вместо use case

- [x] Task 11: Обновить `module.json.stub` (depends on 7)
  - Добавить `"schema_version": 1` на верхнем уровне и `"kind": "{{ kind }}"` в meta
  - **Важно:** этот stub НЕ используется в scaffold flow (`module.json` генерируется программно через `Module::toDescriptorArray()`). Stub — reference-шаблон для документации и IDE.
  - Файл: `stubs/module.json.stub`
  - **Приёмка ✅:** stub содержит `schema_version: 1` и `meta.kind`; формат соответствует секции «Обновлённый manifest contract»
  - **Приёмка ❌:** stub не обновлён или расходится с фактическим форматом `toDescriptorArray()`

<!-- Commit checkpoint: tasks 7-11 → Commit 2 -->

### Phase 5: Миграция тестовых фикстур (критический путь)

- [x] Task 12: Обновить ВСЕ существующие test fixtures (depends on 1-6)
  - **Критические точки изменения:**
    1. `tests/Support/CreatesModuleFiles.php` — `writeModuleManifest()`: добавить `"schema_version": 1` в manifest, добавить `"kind"` параметр (default `"module"`) в meta секцию
    2. `tests/Support/ModuleFactory.php` — `make()`: добавить параметры `?ModuleKind $kind = ModuleKind::Module` и `int $schemaVersion = 1`, пробросить в конструкторы `Module` и `ManifestMeta`
    3. `tests/Unit/Manifest/VO/ModuleTest.php` — `validManifest()`: добавить `schema_version` и `kind`
    4. `tests/Unit/Manifest/ManifestValidatorTest.php` — все inline manifest arrays: добавить `schema_version: 1` и `kind`
    5. `tests/Unit/Manifest/VO/ManifestMetaTest.php` — все inline meta arrays: добавить `kind`
    6. `tests/Unit/Registry/ModuleRegistryCacheTest.php` — version 3 → 4 в фикстурах, manifest arrays в cache entries: добавить `schema_version` и `kind`
    7. Все остальные тесты, использующие `CreatesModuleFiles` или `ModuleFactory` — автоматически подтянут через обновлённые helpers
  - **Переиспользование трейтов:** новые тесты в Tasks 13-14 ДОЛЖНЫ использовать `CreatesModuleFiles`, `ModuleFactory`, `CreatesLifecycleEnvironment`, `RegistersLifecycleCommands` — не дублировать setup
  - Без этого ВСЕ существующие тесты упадут
  - **Приёмка ✅:** `composer test` проходит полностью; `CreatesModuleFiles::writeModuleManifest()` генерирует `schema_version: 1` и `kind`; `ModuleFactory::make()` принимает `kind` и `schemaVersion`
  - **Приёмка ❌:** хотя бы один тест падает с `unknown top-level key [schema_version]`, `meta.kind must be...`, или `cache version is not supported`

<!-- Commit checkpoint: task 12 → Commit 3 -->

### Phase 6: Новые тесты

- [x] Task 13: Unit-тесты (depends on 12)
  - `ManifestFieldReaderTest` — `requiredInt`: valid int, missing key, string value, float value, null
  - `ModuleKindTest` — cases, `from()`/`tryFrom()` valid/invalid string, value property
  - `ManifestMetaTest` (расширение) — kind parsing, round-trip, unknown kind, missing kind
  - `ManifestValidatorTest` (расширение) — schema_version: missing, invalid type, unknown version, valid
  - `ModuleTest` (расширение) — schemaVersion round-trip через fromManifest/toDescriptorArray, withState сохраняет schemaVersion, toDescriptorArray содержит schema_version на top-level
  - `CachePayloadTest` (расширение) — version 4, reject version 3, descriptor содержит schema_version
  - **Переиспользование:** использовать `ModuleFactory::make()` для создания Module в тестах; не дублировать конструкторы вручную
  - **Приёмка ✅:** все перечисленные сценарии покрыты; тесты используют `ModuleFactory` где применимо; `composer test:unit` проходит
  - **Приёмка ❌:** тесты дублируют конструкторы Module/ManifestMeta вместо использования factory; отсутствуют error paths

- [x] Task 14: Feature-тесты команд и arch-тесты (depends on 8, 10, 12)
  - `MakeModuleCommandTest` (расширение) — default kind inference, explicit `--kind=integration`, invalid `--kind` → error
  - `ModulesListCommandTest` (расширение) — Kind column в таблице, `--kind=module` filter, invalid `--kind` → error, `--kind=` + `--enabled` (AND-фильтр)
  - **Переиспользование:** тесты используют `CreatesLifecycleEnvironment`, `RegistersLifecycleCommands`, `CreatesModuleFiles` — как существующие тесты в этих файлах
  - Arch-тесты: новый тест — Loaders НЕ зависят от `ModuleKind` (подтверждение arch boundary: kind не влияет на runtime). Существующие arch-тесты `'manifest enums are string backed enums'` и `'value objects are final readonly'` уже покрывают `ModuleKind` и `ManifestMeta` — дублировать НЕ нужно
  - **Приёмка ✅:** feature-тесты покрывают happy path и error paths; arch-тест Loaders↛ModuleKind проходит; существующие arch-тесты не сломаны
  - **Приёмка ❌:** тесты не переиспользуют существующие трейты; дублирующие arch-тесты для уже покрытых инвариантов; Kind column не проверяется в list output

<!-- Commit checkpoint: tasks 13-14 → Commit 4 -->

### Phase 7: Документация

- [x] Task 15: Обновить DESCRIPTION.md, ARCHITECTURE.md, ROADMAP.md (depends on 12-14)
  - DESCRIPTION.md: manifest contract с `schema_version` и `kind`, JSON-примеры, текущий статус, cache v4
  - ARCHITECTURE.md: инварианты (kind не влияет на runtime, schema_version strict-fail), JSON-примеры, структура src/ (новый enum), cache v4
  - ROADMAP.md: отметить milestone как выполненный
  - **Приёмка ✅:** JSON-примеры в DESCRIPTION.md содержат `schema_version` и `kind`; ARCHITECTURE.md описывает инвариант «kind не влияет на loader pipeline»; ROADMAP.md отмечает Фазу 2
  - **Приёмка ❌:** документация не отражает cache v4; JSON-примеры без `schema_version`

<!-- Commit checkpoint: task 15 → Commit 5 -->

## Обновлённый manifest contract (целевой формат)

```json
{
    "schema_version": 1,
    "meta": {
        "name": "blog",
        "display_name": "Blog",
        "kind": "module",
        "description": "Corporate blog with comments",
        "version": "1.0.0",
        "author": "Acme Studio",
        "license": "proprietary",
        "dependencies": {
            "users": "^1.5",
            "media": ">=1.4 <3.0"
        }
    },
    "settings": {
        "schema": {
            "enable_comments": {
                "type": "bool",
                "default": true,
                "label": "Enable comments"
            }
        }
    }
}
```

## Kind inference mapping

| Target directory | Inferred kind |
|-----------------|---------------|
| `app/Modules/*` | `module` |
| `app/Integrations/*` | `integration` |
| `app/Subsystems/*` | `subsystem` |
| Other / unresolved | `module` (default) |

## Точки изменения (summary)

| # | Файл | Тип изменения |
|---|------|---------------|
| 1 | `src/Manifest/Parsing/ManifestFieldReader.php` | MODIFY (+ requiredInt) |
| 2 | `src/Manifest/Enums/ModuleKind.php` | NEW |
| 3 | `src/Manifest/ManifestValidator.php` | MODIFY |
| 4 | `src/Manifest/VO/ManifestMeta.php` | MODIFY |
| 5 | `src/Manifest/VO/Module.php` | MODIFY |
| 6 | `src/Registry/VO/ModuleRegistryCachePayload.php` | MODIFY |
| 7 | `src/Application/DTOs/ScaffoldModuleConfig.php` | MODIFY |
| 8 | `src/Application/UseCases/ScaffoldModuleUseCase.php` | MODIFY |
| 9 | `src/Application/UseCases/ListModulesUseCase.php` | MODIFY (+ kindFilter) |
| 10 | `src/Console/Commands/Modules/MakeModuleCommand.php` | MODIFY |
| 11 | `src/Console/Commands/Modules/ModulesListCommand.php` | MODIFY |
| 12 | `stubs/module.json.stub` | MODIFY (reference only) |
| 13 | `tests/Support/CreatesModuleFiles.php` | MODIFY |
| 14 | `tests/Support/ModuleFactory.php` | MODIFY |
| 15+ | `tests/**` | MODIFY (fixtures + new tests) |
| 16 | `.ai-factory/DESCRIPTION.md` | MODIFY |
| 17 | `.ai-factory/ARCHITECTURE.md` | MODIFY |
| 18 | `.ai-factory/ROADMAP.md` | MODIFY |
