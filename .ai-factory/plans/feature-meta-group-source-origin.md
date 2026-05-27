# Implementation Plan: meta.group + source descriptor в state.json

Branch: feature/meta-group-source-origin
Created: 2026-05-27
Updated: 2026-05-27 (v3 — acceptance criteria, rule-compliance fixes, detail gaps)

## Settings
- Testing: yes
- Logging: нет (пользователь отказался от логирования)
- Docs: yes (mandatory docs checkpoint)

## Roadmap Linkage
Milestone: "MoonShine admin-UI" (Фаза 2)
Rationale: `meta.group` и `source` — prerequisites для MoonShine UI, где модули группируются по `meta.group` и показывается provenance

## Research Context
Source: .ai-factory/RESEARCH.md (Active Summary)

Goal: Группировка модулей по бизнес-области в UI/CLI + хранение provenance для будущих обновлений
Constraints:
- `meta.group` — optional string в module.json, kebab-case (`/^[a-z][a-z0-9-]*$/`), чисто презентационное
- `schema_version` остаётся 1 — optional field не требует bump
- Display labels для групп — в хост-конфиге `config/modules.php`, не в manifest
- `source` — новая секция в state.json (host-owned, mutable)
- **Два отдельных enum:** `ModuleSourceKind` (staging, только `Zip`) и `ModuleOriginKind` (provenance: `Local`, `Zip`)
- Registry cache не затрагивается — source живёт в state.json; meta.group попадёт в cache автоматически через descriptor
- **Directory install удалён** — модуль либо создают scaffold'ом, либо ставят из zip
- **locator удалён** — provenance хранит только kind + installed_version + ?checksum, без мёртвых ссылок на fs
Decisions:
- **Group**: строка-code в `meta.group` + хост-конфиг `modules.groups` для display labels
- **Provenance в state.json**: секция `source` внутри state.json
- **Два enum**: `ModuleSourceKind` (Application/Enums, staging: `Zip`) и `ModuleOriginKind` (Manifest/Enums, provenance: `Local` | `Zip`) — разные концепции, разные слои
- **Checksum**: sha256 zip-файла, считается в `ModuleSourcePreparer` до распаковки
- **ModuleOrigin VO**: kind, installedVersion, ?checksum + immutable setter `withInstalledVersion()`
Open questions: нет

## Ключевые архитектурные решения

### Почему два enum, а не один
Staging (`ModuleSourceKind`) отвечает на вопрос «что мне дали на вход?» — это процесс установки. Provenance (`ModuleOriginKind`) отвечает на вопрос «откуда модуль взялся?» — это запись в state.json. Staging не сериализуется в JSON. Provenance не участвует в staging pipeline. Разные концепции — разные типы.

### Почему удалён directory install
Модуль либо создают scaffold'ом (`modules:make`), либо ставят из zip. Установка из директории — лишний сценарий без практического применения. Staging pipeline (`ModuleSourcePreparer`) теперь работает только с zip.

### Почему убран locator
`locator` хранил путь к zip-файлу, но после установки zip обычно удалён (temp download, скачанный архив). Мёртвая ссылка в state.json — мусор. Provenance обходится тремя полями: `kind`, `installed_version`, `?checksum`.

### Модель данных после реализации
```
module.json (immutable)          state.json (mutable, host-owned)
├── schema_version: 1            ├── enabled: bool
├── meta:                        ├── installed_at, updated_at
│   ├── name, display_name       ├── source:              ◄ NEW
│   ├── kind: ModuleKind         │   ├── kind: ModuleOriginKind
│   ├── group: ?string   ◄ NEW  │   ├── installed_version: string
│   ├── version                  │   └── ?checksum: string
│   └── dependencies, etc.       └── settings:
└── settings:                        └── values: {}
    └── schema: {}
```

### Nullable origin — не backward compatibility
`?ModuleOrigin $origin = null` в `ModuleStateDocument` — штатный кейс, не BC-fallback. Модули без state.json (первая загрузка registry до scaffold/install, или ручное создание директории) получают `origin = null`. Это часть design, не legacy-поддержка.

## Commit Plan
- **Commit 1** (task 1): `refactor(staging): remove directory install, zip-only pipeline`
- **Commit 2** (tasks 2-3): `feat(manifest): add meta.group to manifest`
- **Commit 3** (tasks 4-6): `feat(state): add ModuleOriginKind, ModuleOrigin VO and source section to state.json`
- **Commit 4** (tasks 7-8): `feat(lifecycle): write source provenance in scaffold/install/update`
- **Commit 5** (task 9): `feat(cli): add Group column to modules:list and groups config`

## Критерии приёмки

### ✅ Критерии успеха (все должны быть выполнены)

| # | Критерий | Проверка |
|---|----------|----------|
| 1 | `ModuleSourceKind::Directory` удалён | Grep `Directory` в `ModuleSourceKind.php` → 0 |
| 2 | `prepareFromDirectory()` удалён | Grep в `ModuleSourcePreparer.php` → 0 |
| 3 | `modules:install` signature = «Path to .zip archive» | `modules:install --help` |
| 4 | `ManifestMeta` принимает `meta.group` (optional, `/^[a-z][a-z0-9-]*$/`) | Unit-тест: valid/invalid/null |
| 5 | `ManifestMeta::toArray()` включает group при non-null, omit при null | Unit-тест: round-trip |
| 6 | `ScaffoldModuleConfig` имеет `?string $group`, `MakeModuleCommand` — `--group=` | Inspection |
| 7 | Scaffold передаёт `group` из config в `ManifestMeta` и записывает в module.json | Feature-тест |
| 8 | `ModuleOriginKind`: string-backed enum, `Local` + `Zip`, в `Manifest/Enums/` | Inspection + arch-тест |
| 9 | `ModuleOrigin`: final readonly, `forLocal()`, `forZip()`, `fromArray()`, `toArray()`, `withInstalledVersion()` | Unit-тесты |
| 10 | `ModuleStateDocument` имеет `?ModuleOrigin $origin` | Inspection |
| 11 | `state.json` с `source` → origin гидратируется; без `source` → `origin = null` | Unit-тест repository |
| 12 | `writeState()` и `writeValues()` сохраняют существующий origin | Unit-тест: write → read → origin на месте |
| 13 | `PreparedSource` имеет `?string $checksum` | Inspection |
| 14 | `prepareFromZip()` считает sha256 до распаковки | Unit-тест |
| 15 | Scaffold → `source: {kind: "local", installed_version: "1.0.0"}` | Feature-тест |
| 16 | Install → `source: {kind: "zip", installed_version, checksum}` | Feature-тест |
| 17 | Update → `source.installed_version` обновлён, остальные поля сохранены | Feature-тест |
| 18 | Update без source → origin остаётся null | Feature-тест |
| 19 | `modules:list` показывает колонку Group | Feature-тест |
| 20 | `config/modules.php` содержит ключ `groups` | Inspection |
| 21 | `composer phpstan` — 0 ошибок | CI gate |
| 22 | `composer test` — все suite зелёные | CI gate |

### ❌ Критерии отказа (любой = не принято)

| # | Критерий |
|---|----------|
| 1 | `ModuleSourceKind::Directory` всё ещё существует |
| 2 | Осталось упоминание «directory» в signature/description `modules:install` |
| 3 | `ModuleOrigin` содержит `locator`, `registry_url`, `channel`, `checked_at` |
| 4 | `source` данные попадают в registry cache |
| 5 | Фасады или глобальные helpers в новом коде |
| 6 | Отсутствует `declare(strict_types=1)` в новом файле |
| 7 | Новый VO не `final readonly` или enum не string-backed |
| 8 | PHPStan ошибки в `src/` |
| 9 | Падающие тесты |
| 10 | `writeState()`/`writeValues()` молча теряют origin |
| 11 | Любой fallback/shim для backward compatibility |

## Tasks

### Phase 0: Staging Cleanup

- [x] Task 1: Удалить directory install из staging pipeline
  - Удалить `ModuleSourceKind::Directory` кейс из enum (остаётся только `Zip`)
  - Удалить `ModuleSourcePreparer::prepareFromDirectory()` метод
  - Упростить `ModuleSourcePreparer::prepare()`: убрать `isDirectory()` check, оставить только zip-ветку и `unsupportedType` fallback
  - Обновить `ModulesInstallCommand::$signature`: аргумент `source` → `Path to .zip archive`
  - Обновить `ModulesInstallCommand::$description`: `Install a module from an archive`
  - Обновить тесты: удалить directory-кейсы в `ModuleSourcePreparerTest`, `InstallModuleUseCaseTest`, `ModulesInstallCommandTest`
  - Файлы: `src/Application/Enums/ModuleSourceKind.php`, `src/Application/Support/ModuleSourcePreparer.php`, `src/Console/Commands/Modules/ModulesInstallCommand.php`, `tests/Unit/Application/Support/ModuleSourcePreparerTest.php`, `tests/Unit/Application/UseCases/InstallModuleUseCaseTest.php`, `tests/Feature/Commands/ModulesInstallCommandTest.php`
<!-- Commit checkpoint: task 1 -->

### Phase 1: Manifest Foundation

- [x] Task 2: Добавить meta.group в ManifestMeta и scaffold pipeline
  - Добавить `'group'` в `ManifestMeta::ALLOWED_KEYS`
  - Добавить `public ?string $group = null` в конструктор `ManifestMeta`
  - Парсить через `ManifestFieldReader::optionalString()` в `fromArray()`
  - Валидация формата: `/^[a-z][a-z0-9-]*$/` (если задан) — в `fromArray()` после парсинга
  - Включить в `toArray()` при non-null
  - Добавить `?string $group = null` в `ScaffoldModuleConfig` DTO
  - Добавить `--group=` опцию в `MakeModuleCommand::$signature`
  - В `MakeModuleCommand::handle()`: прочитать `$this->option('group')` и передать как `group:` в `ScaffoldModuleConfig`
  - В `ScaffoldModuleUseCase::execute()`: передать `$config->group` в конструктор `ManifestMeta` (строка 85-94, inline construction)
  - Файлы: `src/Manifest/VO/ManifestMeta.php`, `src/Application/DTOs/ScaffoldModuleConfig.php`, `src/Console/Commands/Modules/MakeModuleCommand.php`, `src/Application/UseCases/ScaffoldModuleUseCase.php`

- [x] Task 3: Unit-тесты для meta.group (depends on 2)
  - ManifestMeta: парсинг с `group: "content"` → group = "content"
  - ManifestMeta: парсинг без group → group = null
  - ManifestMeta: невалидный group формат ("Content", "my group", "") → ошибка
  - ManifestMeta: `toArray()` включает/исключает group
  - ManifestMeta: round-trip fromArray → toArray → fromArray
  - Файлы: `tests/Unit/Manifest/VO/ManifestMetaTest.php` (расширить)
<!-- Commit checkpoint: tasks 2-3 -->

### Phase 2: Origin VO и State Layer

- [x] Task 4: Создать ModuleOriginKind enum и ModuleOrigin VO
  - Новый `enum ModuleOriginKind: string` в `src/Manifest/Enums/` с кейсами `Local = 'local'`, `Zip = 'zip'`
  - Новый `final readonly class ModuleOrigin` в `src/Manifest/VO/`
  - Свойства: `ModuleOriginKind $kind`, `string $installedVersion`, `?string $checksum`
  - Static factories: `forLocal(version)`, `forZip(version, checksum)`
  - Методы: `fromArray(array, contextPath)`, `toArray()` (ksort, omit null), `withInstalledVersion(version)`
  - Валидация в fromArray: kind обязателен (валидный ModuleOriginKind), installed_version обязательна
  - Файлы: `src/Manifest/Enums/ModuleOriginKind.php` (новый), `src/Manifest/VO/ModuleOrigin.php` (новый)
  - Существующие arch-тесты автоматически покроют: «Manifest enums are string-backed» и «Value objects must be final readonly»

- [x] Task 5: Расширить ModuleStateDocument и ModuleStateRepository (depends on 4)
  - `ModuleStateDocument`: добавить `public ?ModuleOrigin $origin = null` в конструктор
  - `ModuleStateDocument::toArray()`: добавить `'source' => origin->toArray()` при origin !== null
  - `ModuleStateRepository`: добавить `'source'` в `ALLOWED_TOP_LEVEL_KEYS`
  - `ModuleStateRepository::read()`: парсить source через `ModuleOrigin::fromArray()` если присутствует, передать как `origin:` в `ModuleStateDocument`
  - **`ModuleStateRepository::writeState()`**: переписать — вместо отдельного `readValues()` делать `$current = $this->read(...)`, чтобы получить и values, и origin; передать `$current->origin` в новый `ModuleStateDocument`
  - **`ModuleStateRepository::writeValues()`**: аналогично — при чтении state также получать origin из текущего документа и передавать в новый `ModuleStateDocument`
  - Файлы: `src/Manifest/VO/ModuleStateDocument.php`, `src/Manifest/ModuleStateRepository.php`

- [x] Task 6: Unit-тесты для ModuleOrigin и ModuleStateDocument/Repository (depends on 4, 5)
  - ModuleOrigin: construction (forLocal/forZip), round-trip fromArray/toArray, withInstalledVersion, error paths
  - ModuleStateDocument: toArray с/без origin
  - ModuleStateRepository: read с source → ModuleOrigin; read без source → null; write/read round-trip
  - ModuleStateRepository: **writeState() сохраняет origin**, **writeValues() сохраняет origin**
  - Файлы: `tests/Unit/Manifest/VO/ModuleOriginTest.php` (новый), расширить существующие тесты
<!-- Commit checkpoint: tasks 4-6 -->

### Phase 3: Lifecycle Integration

- [x] Task 7: Lifecycle integration: PreparedSource + все UseCases (depends on 4, 5)
  - `PreparedSource`: добавить `public ?string $checksum = null`
  - `ModuleSourcePreparer::prepareFromZip()`: `hash_file('sha256', $zipPath)` до распаковки → checksum в PreparedSource
  - `ScaffoldModuleUseCase`: origin = `ModuleOrigin::forLocal($module->meta->version)`, передать в `ModuleStateDocument` (строка 102)
  - `InstallModuleUseCase`: origin = `ModuleOrigin::forZip($version, $prepared->checksum)`, передать в `ModuleStateDocument` (строка 88-91)
  - `UpdateModuleUseCase`: прочитать origin из `$existingStateDocument->origin` (строка 67), если есть → `withInstalledVersion($candidate->meta->version)`, если нет → null; передать в новый `ModuleStateDocument` (строка 89-91)
  - Rollback: `$existingStateDocument` уже содержит origin, восстановление через `writeDocument()` (строка 96) корректно — дополнительных изменений не нужно
  - Файлы: `src/Application/Support/PreparedSource.php`, `src/Application/Support/ModuleSourcePreparer.php`, `src/Application/UseCases/ScaffoldModuleUseCase.php`, `src/Application/UseCases/InstallModuleUseCase.php`, `src/Application/UseCases/UpdateModuleUseCase.php`

- [x] Task 8: Feature-тесты lifecycle (depends on 7)
  - Scaffold: state.json содержит `source.kind: "local"`, `source.installed_version`, без checksum
  - Install из zip: `source.kind: "zip"`, `source.installed_version`, `source.checksum` = sha256
  - Update: `source.installed_version` обновлён, остальные поля сохранены
  - Update без source: origin остаётся null
  - Файлы: расширить существующие feature-тесты в `tests/Feature/Application/UseCases/`
<!-- Commit checkpoint: tasks 7-8 -->

### Phase 4: Config и CLI

- [x] Task 9: Config groups + ModulesListCommand Group колонка + тесты (depends on 2)
  - `config/modules.php`: новая секция `'groups' => []`
  - `ModulesListCommand`: добавить колонку Group (после Kind), значение `$module->meta->group ?? ''`
  - `ModulesListCommand`: добавить `--group=` опцию для фильтрации
  - Feature-тесты: Group колонка, --group фильтр, модуль без group → пустая ячейка
  - Файлы: `config/modules.php`, `src/Console/Commands/Modules/ModulesListCommand.php`, `tests/Feature/Console/Commands/Modules/ModulesListCommandTest.php`
<!-- Commit checkpoint: task 9 -->
