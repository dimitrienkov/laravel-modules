# Lifecycle UseCase-классы и Artisan-команды жизненного цикла модулей

**Ветка:** `feature/lifecycle-usecases-commands`
**Создан:** 2026-05-25
**Тип:** feature

## Настройки

- **Тестирование:** да — unit-тесты на каждый UseCase, feature-тесты на каждую команду
- **Логирование:** нет — без логирования по выбору пользователя
- **Документация:** да — обязательный docs-чекпоинт по завершении

## Связь с дорожной картой

- **Этап:** "Фаза 2 — v2.0 расширение (новые функции)"
- **Обоснование:** Lifecycle UseCase-классы и Artisan-команды — ключевые пункты Фазы 2, разблокирующие MoonShine admin-UI и генераторы `make:* --module`

## Описание

Реализация полного цикла управления модулями: включение/отключение, установка/обновление/удаление, scaffolding (`make:module`), список модулей. Архитектура: `final readonly` UseCase-классы с DI в `src/Application/UseCases/`, тонкие Artisan-команды как обёртки в `src/Console/Commands/Modules/`.

### Ключевые решения

- **UseCase-классы** резолвятся из контейнера автоматически — явная привязка не нужна (concrete classes с interface DI)
- **Запись `module.json`** идёт только через `ModuleManifestRepository::updateState()` и `saveValues()`; для initial writes UseCase строит `Module` из валидированного manifest и пишет canonical manifest через repository; прямой `file_put_contents` допустим только в тестовых фикстурах
- **После успешной мутации** lifecycle UseCase-классы сбрасывают production cache (`bootstrap/cache/modules.php`) и in-memory `ModuleRegistry::reset()`
- **Внешние источники install/update** сначала stage-ятся и валидируются через `ManifestDocumentReader` + `ManifestValidatorInterface`; `ModuleManifestRepository::load()` для временных путей и source paths не используется, потому что namespace resolver принимает только пути внутри `app_path()`
- **Миграции** не запускаются автоматически: install добавляет файлы, `MigrationLoader` подхватывает их на следующем boot, пользователь запускает `php artisan migrate` вручную
- **Remove** не откатывает миграции автоматически — команда напоминает о необходимости `migrate:rollback` перед удалением
- **Install из zip** использует `ZipExtractor` с защитой от zip-slip и выхода за пределы целевой директории; manifest валидируется ДО копирования файлов
- **Update** бэкапит explicit `settings.values`, заменяет файлы, затем возвращает только те сохранённые values, которые есть в новой `settings.schema` и проходят нормализацию; defaults остаются в schema и не записываются в values
- **DTOs** — `ScaffoldModuleConfig`, result DTOs для команд и `PreparedSource` являются `final readonly`; команды получают structured result, а не парсят side effects UseCase-классов
- **ext-zip** подключается как `require` в `composer.json`, потому что zip-install является first-class lifecycle source; `composer.lock` обновляется только по content-hash/platform requirements, без package churn; `ZipExtractor` всё равно содержит defensive runtime guard на случай игнорирования platform requirements
- **Source strategy:** текущий план реализует local directory и zip sources. Git/remote sources не входят в текущий scope, но `ModuleSourcePreparer` проектируется как staging boundary, чтобы будущий `git` source мог подготовить временную директорию и дальше пройти тот же manifest/dependency/replace pipeline; не завязываться на предположение, что git всегда поставляет zip.
- **InstallHook** (упомянут в ROADMAP) — осознанно отложен до Фазы 3 (lifecycle events); в текущем плане хуки не реализуются
- **Concurrency:** `AtomicJsonWriter` гарантирует атомарность одиночной записи `module.json`, но не транзакционность check-then-write в UseCases; для CLI-команд это приемлемо, для MoonShine admin UI в Фазе 2 рассмотреть file-based advisory lock
- **Backup directory** для remove/update конфигурируется в `config/modules.php` (ключ `paths.backup`), по умолчанию `storage_path('app/module-backups')`, формат `<name>-<Ymd-His>`; авто-очистка не предусмотрена (ответственность host-приложения)

## Задачи

### Фаза A — Exceptions и базовая lifecycle-инфраструктура (Задачи 1–7)

**Задача 1: Lifecycle-исключения** ✅
- Новые exceptions в `src/Exceptions/`: `ModuleAlreadyEnabledException`, `ModuleAlreadyDisabledException`, `DependentModulesExistException`, `ModuleAlreadyExistsException`, `ModuleScaffoldException`, `ModuleInstallException`, `ModuleUpdateException`, `ModuleRemoveException`, `ModuleSourceException`, `ModuleArchiveException`
- Все `final class extends RuntimeException` со static named constructors
- Сообщения должны содержать имя модуля/путь источника и причину, чтобы команды могли выводить их без дополнительного маппинга
- Unit-тесты: `tests/Unit/Exceptions/LifecycleExceptionsTest.php`
- **Блокируется:** —

**Задача 2: LifecycleRegistryInvalidator** ✅
- `src/Application/Support/LifecycleRegistryInvalidator.php` — общий сервис для lifecycle-мутаций
- Делает `ModuleRegistryCache::forget()` и `ModuleRegistry::reset()` после успешной записи или файловой операции
- Не падает, если cache отсутствует; пробрасывает typed cache exception, если cache нельзя удалить
- Unit-тесты: cache существует, cache отсутствует, ошибка удаления cache, `registry reset` вызван
- **Блокируется:** —

**Задача 3: ModuleLifecyclePaths** ✅
- `src/Application/Support/ModuleLifecyclePaths.php` — единая точка resolution путей lifecycle-операций
- Читает `modules.paths.directories` из config и валидирует так же строго, как `ModuleDirectoryScanner`: список non-empty strings, configured roots должны резолвиться внутри `app_path()`
- `--directory` должен указывать на один из configured module roots после normalization, а не на произвольную вложенную директорию; иначе созданный/установленный модуль не будет найден scanner'ом
- Default target root — первый configured root
- Target module path строится детерминированно из module name, например `<root>/<StudlyName>`; collision проверяется и по registry name, и по target path
- Backup root берётся из `modules.paths.backup`; добавить default `storage_path('app/module-backups')` в `config/modules.php`
- Unit-тесты: default root, explicit configured root, unknown root rejected, root outside `app_path()` rejected, backup root default/custom, deterministic target path
- **Блокируется:** Задача 1

**Задача 4: ModuleDependencyGuard** ✅
- `src/Application/Support/ModuleDependencyGuard.php` — shared guard для lifecycle dependency checks
- Enable/install/update: строит candidate graph и прогоняет `TopologicalSorter::sort()`; typed dependency exceptions из sorter не маппятся в generic exceptions
- Disable: запрещает операцию только при enabled dependents; disabled dependents не блокируют disable
- Remove: запрещает операцию при любых installed dependents, включая disabled, согласно `.ai-factory/rules/lifecycle.md`
- Update: проверяет graph с новым manifest descriptor, но с сохранённым target state (`enabled`, `installed_at`) до замены файлов
- Unit-тесты: enabled-only disable guard, all-dependents remove guard, replacement graph для update, missing/disabled/incompatible dependency propagation, duplicate name propagation
- **Блокируется:** Задача 1

**Задача 5: ModuleDirectoryOperations** ✅
- `src/Application/Support/ModuleDirectoryOperations.php` — filesystem-операции lifecycle без бизнес-правил
- Операции: staged copy directory, replace target directory with backup, restore backup on failure, move target to backup with collision-safe suffix, delete directory, cleanup temporary directories
- Использует `Illuminate\Filesystem\Filesystem` через DI; без facades и global helpers
- Backup directory format `<name>-<Ymd-His>`; при коллизии добавляется numeric suffix
- Не пишет `module.json` напрямую; только переносит файлы как часть directory operation, canonical manifest write остаётся в UseCase через repository
- Unit-тесты: copy success, copy failure cleanup, replace rollback, move-to-backup suffix, delete no-backup, cleanup idempotency, refusal to operate outside resolved lifecycle paths
- **Блокируется:** Задачи 1, 3

**Задача 6: ZipExtractor** ✅
- `src/Support/ZipExtractor.php` — извлечение zip через `ZipArchive`
- Методы: `extract(zipPath, targetDir)`, `extractToTemp(zipPath)`
- `ext-zip` добавляется в `require` секцию `composer.json`; `composer.lock` обновляется только для lock content-hash/platform metadata, без обновления package versions
- Runtime guard: `class_exists(ZipArchive::class)` в начале каждого публичного метода; при отсутствии бросает `ModuleArchiveException` с сообщением "ext-zip is required for archive operations"
- Защита от zip-slip и выхода за пределы целевой директории: entries с `../`, абсолютные пути и Windows drive paths запрещены до записи
- `extractToTemp()` возвращает путь временной директории и не удаляет её на success; caller владеет cleanup через `PreparedSource`
- Unit-тесты: успешное извлечение, битый zip, отсутствующий файл, попытка выхода за пределы директории, cleanup временных файлов на ошибке
- **Блокируется:** Задача 1

**Задача 7: ModuleSourcePreparer** ✅
- `src/Application/Support/ModuleSourcePreparer.php` — общий staging boundary для install/update источников
- Поддерживает текущие типы источников: local directory и zip
- Для directory-source проверяет наличие `module.json`, читает JSON через `ManifestDocumentReader`, валидирует через `ManifestValidatorInterface`
- Для zip-source использует `ZipExtractor::extractToTemp()`, затем валидирует manifest в staged directory
- Не использует `ModuleManifestRepository::load()` на staged/source paths, чтобы не запускать namespace resolution вне `app_path()`
- Возвращает `PreparedSource` (`final readonly class` в `Application/Support/`) с полями `string $path`, `string $manifestPath`, `array $manifest`, `?string $temporaryRoot`; UseCase обязан вызвать cleanup для `temporaryRoot` в `finally`
- Future-proofing: detection/dispatch source type держать внутри preparer, чтобы позже добавить `git` source без переписывания install/update UseCase
- Unit-тесты: directory-source, zip-source, отсутствующий manifest, невалидный manifest, unsupported source, cleanup temporary directory through `PreparedSource`
- **Блокируется:** Задачи 1, 6

### Фаза B — Enable/Disable/List (Задачи 8–9)

**Задача 8: EnableModuleUseCase + DisableModuleUseCase** ✅
- `src/Application/UseCases/EnableModuleUseCase.php` — enable с проверкой dependencies
- `src/Application/UseCases/DisableModuleUseCase.php` — disable с проверкой reverse dependencies
- Enable:
  - берёт модуль из `ModuleRegistryInterface::find()`
  - бросает `ModuleAlreadyEnabledException`, если модуль уже enabled
  - проверяет dependencies через `ModuleDependencyGuard`: берёт `registry->all()`, заменяет целевой модуль на `module->withState(new ManifestState(enabled: true, ...))`, прогоняет `TopologicalSorter`
  - пишет новое состояние через `ModuleManifestRepositoryInterface::updateState()`, сохраняя `installed_at` и обновляя/заполняя `updated_at`
- Disable:
  - бросает `ModuleAlreadyDisabledException`, если модуль уже disabled
  - запрещает disable, если есть enabled dependents; disabled dependents не блокируют disable
  - пишет новое состояние через `updateState()`, сохраняя `installed_at` и обновляя `updated_at`
- После успешного изменения вызывает `LifecycleRegistryInvalidator`
- Unit-тесты: успешный сценарий, уже enabled/disabled, отсутствующая/disabled/несовместимая dependency, нарушение reverse dependency, disabled dependent не блокирует, timestamps состояния, сброс cache
- **Блокируется:** Задачи 1, 2, 4

**Задача 9: Команды modules:enable, modules:disable, modules:list** ✅
- Три Artisan-команды: `modules:enable {name}`, `modules:disable {name}`, `modules:list`
- `modules:list` — табличный вывод только для чтения с фильтрами `--enabled`/`--disabled`
- Команды остаются тонкими: вызывают UseCase или registry, ловят lifecycle exceptions, возвращают `Command::SUCCESS`/`FAILURE`
- `modules:list` показывает минимум: name, display name, version, enabled, path
- Feature-тесты: успешные enable/disable, код завершения при ошибке, сообщение исключения, табличный вывод, фильтры `--enabled`/`--disabled`
- **Блокируется:** Задача 8

### Фаза C — Scaffold (Задачи 10–12)

**Задача 10: Scaffold DTO и stubs** ✅
- `src/Application/DTOs/ScaffoldModuleConfig.php` — входной DTO
- `src/Application/DTOs/ScaffoldModuleResult.php` — результат для команды (`name`, `path`, `enabled`, `providerClass`)
- Stubs в корневой директории `stubs/`: `module-service-provider.stub`, `module.json.stub`
- PHP stub содержит `declare(strict_types=1);`
- `module.json.stub` используется только как template source; финальный manifest валидируется и пишется canonical path через `ModuleManifestRepositoryInterface::saveValues()`
- Unit-тесты: DTO readonly, stub placeholders, PHP stub strict types
- **Блокируется:** —

**Задача 11: ScaffoldModuleUseCase** ✅
- `src/Application/UseCases/ScaffoldModuleUseCase.php` — создание структуры модуля
- UseCase:
  - валидирует имя через тот же lowercase snake_case contract, что и manifest layer
  - резолвит target root через `ModuleLifecyclePaths`; `--directory` должен быть configured root
  - не перезаписывает существующий модуль без `--force`
  - создаёт минимальную структуру и provider stub
  - строит raw manifest, валидирует через `ManifestValidatorInterface`, создаёт `Module` descriptor с namespace target path и пишет canonical manifest через `saveValues()`
  - вызывает `LifecycleRegistryInvalidator`
- Unit-тесты: successful scaffold, invalid name, unknown target root, existing module without force, force overwrite, disabled scaffold, manifest validation, registry invalidation
- **Блокируется:** Задачи 1, 2, 3, 5, 10

**Задача 12: MakeModuleCommand** ✅
- `src/Console/Commands/Modules/MakeModuleCommand.php` — Artisan-обёртка
- Команда: `make:module {name} {--directory=app/Modules} {--disabled} {--force}`
- Тонкая команда: собирает `ScaffoldModuleConfig`, вызывает UseCase, выводит path/provider, возвращает `Command::SUCCESS`/`FAILURE`
- Feature-тесты: успешный scaffold, disabled option, explicit directory, existing module failure, force overwrite, invalid name message
- **Блокируется:** Задача 11

### Фаза D — Install (Задачи 13–14)

**Задача 13: InstallModuleUseCase** ✅
- `src/Application/DTOs/InstallModuleResult.php` — результат для команды (`name`, `path`, `enabled`, `sourceType`)
- `src/Application/UseCases/InstallModuleUseCase.php` — install из prepared source
- Flow:
  - `ModuleSourcePreparer` валидирует source ДО копирования
  - target root резолвится через `ModuleLifecyclePaths`; default — первый configured root, option `--directory=...` — только configured root
  - module name берётся из `source.meta.name`; проверяется отсутствие installed module с тем же name и отсутствие target path
  - candidate `Module` создаётся с target path, resolved namespace и state `enabled=!$disabled`, `installed_at=now`, `updated_at=now`
  - dependencies проверяются до копирования; disabled install не требует missing/disabled dependencies, но cycle/duplicate checks остаются
  - files копируются через `ModuleDirectoryOperations`; после копирования canonical manifest пишется через `saveValues()`/`updateState()`
  - `PreparedSource` cleanup вызывается в `finally`
  - после успешной операции вызывается `LifecycleRegistryInvalidator`
- Unit-тесты: install из directory, install из zip, duplicate registry name, duplicate target path, invalid manifest, dependency failure before copy, disabled install, canonical manifest state/values, prepared source cleanup, сброс cache
- **Блокируется:** Задачи 1, 2, 3, 4, 5, 7

**Задача 14: ModulesInstallCommand** ✅
- `src/Console/Commands/Modules/ModulesInstallCommand.php`
- Команда: `modules:install {source} {--directory=app/Modules} {--disabled}`
- Выводит installed module name/path/enabled state и reminder: `php artisan migrate`
- Ловит lifecycle/source/archive/manifest exceptions, выводит message без дополнительного маппинга
- Feature-тесты: directory source, zip source, duplicate module failure, invalid manifest failure, disabled option, migration reminder, exit codes
- **Блокируется:** Задача 13

### Фаза E — Update (Задачи 15–16)

**Задача 15: UpdateModuleUseCase** ✅
- `src/Application/DTOs/UpdateModuleResult.php` — результат (`name`, `oldVersion`, `newVersion`, `skippedValues`, `path`)
- `src/Application/UseCases/UpdateModuleUseCase.php`
- Flow:
  - existing module берётся через `ModuleRegistryInterface::find()`
  - source валидируется через `ModuleSourcePreparer`; `{name}` должен совпадать с `source.meta.name`
  - state сохраняется из target module: `enabled` и `installed_at` не берутся из source; `updated_at` обновляется
  - explicit `settings.values` читаются из target через `ModuleManifestRepositoryInterface::readValues()`
  - dependency graph с новым manifest descriptor проверяется ДО replace, чтобы update не сломал enabled graph
  - target directory заменяется через `ModuleDirectoryOperations` с backup и rollback
  - после replace canonical manifest пишется через repository с preserved state и merged values
  - `PreparedSource` cleanup вызывается в `finally`
- Merge values:
  - сохраняет explicit values только для ключей, которые существуют в новой schema
  - повторно нормализует каждое сохранённое value через новую schema
  - значения, которые больше невалидны для новой schema, не пишутся обратно и выводятся командой как skipped
  - defaults из новой schema не добавляются в `settings.values`
- После успешной операции вызывает `LifecycleRegistryInvalidator`
- Unit-тесты: успешный update, несовпадение имени source, сохранение валидных explicit values, удаление исчезнувших ключей, пропуск невалидных values, state preservation, dependency graph failure before replace, rollback при ошибке после replace, prepared source cleanup, сброс cache
- **Блокируется:** Задачи 1, 2, 3, 4, 5, 7

**Задача 16: ModulesUpdateCommand** ✅
- `src/Console/Commands/Modules/ModulesUpdateCommand.php`
- Команда: `modules:update {name} {source}` с выводом old -> new version
- Выводит skipped values отдельным блоком, если merge отбросил невалидные/исчезнувшие keys
- Feature-тесты: successful update, source name mismatch, skipped values output, rollback failure output, exit codes
- **Блокируется:** Задача 15

### Фаза F — Remove (Задачи 17–18)

**Задача 17: RemoveModuleUseCase** ✅
- `src/Application/DTOs/RemoveModuleResult.php` — результат (`name`, `removedPath`, `backupPath`)
- `src/Application/UseCases/RemoveModuleUseCase.php`
- UseCase: проверка reverse deps через `ModuleDependencyGuard`, затем перемещение в backup или удаление
- Remove запрещён, если любой installed module зависит от удаляемого, включая disabled dependents
- При `noBackup=true` UseCase удаляет директорию; без `noBackup` модуль перемещается в backup directory из `config('modules.paths.backup')` (по умолчанию `storage_path('app/module-backups')`), формат `<name>-<Ymd-His>`; при коллизии добавляется числовой suffix
- Remove не откатывает миграции; команда явно напоминает выполнить `php artisan migrate:rollback` до удаления, если это нужно host-приложению
- После успешной операции вызывает `LifecycleRegistryInvalidator`
- Unit-тесты: блокировка enabled dependent, блокировка disabled dependent, перенос в backup, удаление с no-backup, collision-safe suffix, ошибка filesystem не сбрасывает cache, сброс cache после success
- **Блокируется:** Задачи 1, 2, 3, 4, 5

**Задача 18: ModulesRemoveCommand** ✅
- `src/Console/Commands/Modules/ModulesRemoveCommand.php`
- Команда: `modules:remove {name} {--force} {--no-backup}` с confirmation prompt
- Confirmation живёт только в command; UseCase не спрашивает пользователя
- `--force` пропускает prompt, но не отключает dependency safety
- Перед удалением выводит reminder про ручной `php artisan migrate:rollback`, если host-приложению нужен rollback
- Feature-тесты: confirmation cancel, `--force` skips prompt, backup output, no-backup output, dependent failure, migration rollback reminder, exit codes
- **Блокируется:** Задача 17

### Фаза G — Интеграция, архитектура и документация (Задачи 19–21)

**Задача 19: Интеграция в ServiceProvider и package metadata** ✅
- Регистрация всех новых команд в `ModuleLoaderServiceProvider::boot()`
- Регистрация `LifecycleRegistryInvalidator`, `ModuleLifecyclePaths`, `ModuleDependencyGuard`, `ModuleDirectoryOperations`, `ZipExtractor`, `ModuleSourcePreparer` как singleton
- UseCase-классы остаются concrete auto-resolvable; явные bindings не нужны
- Добавить `paths.backup` в `config/modules.php` со значением по умолчанию `storage_path('app/module-backups')`
- Publish root `stubs/` под отдельным tag `modules-stubs`
- Добавить `ext-zip` в `require` секцию `composer.json`; обновить `composer.lock` без package version churn
- Feature-тесты provider: все команды зарегистрированы, support services singleton, stubs publish tag, backup config default
- Прогон `composer test`
- **Блокируется:** Задачи 9, 12, 14, 16, 18

**Задача 20: Архитектурные тесты** ✅
- Расширение `tests/Architecture/ArchitectureTest.php`:
  - `Application\UseCases\*` → `final readonly`, без facades, без global helpers
  - `Application\Support\*` → `final readonly`, без facades, без global helpers
  - `Application\DTOs\*` → `final readonly`
  - `Application` не зависит от `Loaders`, `Providers`, `MoonShine`
  - Новые commands → `final`, extends `Command`
  - Новые exceptions → `final`, extends `RuntimeException`
  - `ZipExtractor` → `final readonly`
  - root `stubs/*.php` при наличии содержат `declare(strict_types=1);`
- Прогон `composer test` + `composer phpstan`
- **Блокируется:** Задача 19

**Задача 21: Документация и AI-контекст** ✅
- Обновить `README.MD`: lifecycle-команды больше не roadmap; добавить короткий блок использования
- Обновить `docs/getting-started.md`: заменить ручной scaffold на `make:module`
- Обновить `docs/cli.md`: перенести `make:module`, `modules:list`, `modules:enable`, `modules:disable`, `modules:install`, `modules:update`, `modules:remove` в реализованные команды, описать опции и заметки по безопасности
- Обновить `docs/configuration.md`: описать `paths.backup`, configured roots для `--directory`, `modules-stubs` publish tag
- Обновить `docs/architecture.md`: описать UseCase-классы, сброс cache, source staging и отсутствие auto-migrate/auto-rollback
- Обновить `.ai-factory/ARCHITECTURE.md`: добавить dependency rules для слоя `Application` (`Application/UseCases → Contracts + Manifest\VO + Application/Support + Application/DTOs + Support`; `Application/Support → Contracts + Manifest\VO + Support + Registry`; `Application/DTOs → ∅`; `Application` не зависит от `Loaders`, `Providers`, `MoonShine`; `Console/Commands → Application/UseCases + Contracts`; `Provider → Application`)
- Обновить `.ai-factory/ROADMAP.md`: синхронизировать с фактическими решениями плана — (1) `InstallHook` явно отложен до Фазы 3 (lifecycle events), (2) `modules:remove` без авто-отката миграций (ручной `migrate:rollback`), (3) future `git`/remote sources остаются roadmap и должны входить через staging boundary, а не менять install/update flow
- Обновить `.ai-factory/DESCRIPTION.md`, `AGENTS.md`: разделить фактический runtime и оставшийся roadmap без drift
- Прогон `composer format:dry`, `composer phpstan`, `composer test`
- **Блокируется:** Задачи 19, 20

## Новые файлы

```
src/
├── Application/
│   ├── DTOs/
│   │   ├── InstallModuleResult.php
│   │   ├── RemoveModuleResult.php
│   │   ├── ScaffoldModuleConfig.php
│   │   ├── ScaffoldModuleResult.php
│   │   └── UpdateModuleResult.php
│   ├── Support/
│   │   ├── LifecycleRegistryInvalidator.php
│   │   ├── ModuleDependencyGuard.php
│   │   ├── ModuleDirectoryOperations.php
│   │   ├── ModuleLifecyclePaths.php
│   │   ├── ModuleSourcePreparer.php
│   │   └── PreparedSource.php
│   └── UseCases/
│       ├── DisableModuleUseCase.php
│       ├── EnableModuleUseCase.php
│       ├── InstallModuleUseCase.php
│       ├── RemoveModuleUseCase.php
│       ├── ScaffoldModuleUseCase.php
│       └── UpdateModuleUseCase.php
├── Console/
│   └── Commands/
│       └── Modules/
│           ├── MakeModuleCommand.php
│           ├── ModulesDisableCommand.php
│           ├── ModulesEnableCommand.php
│           ├── ModulesInstallCommand.php
│           ├── ModulesListCommand.php
│           ├── ModulesRemoveCommand.php
│           └── ModulesUpdateCommand.php
├── Exceptions/
│   ├── DependentModulesExistException.php
│   ├── ModuleArchiveException.php
│   ├── ModuleAlreadyDisabledException.php
│   ├── ModuleAlreadyEnabledException.php
│   ├── ModuleAlreadyExistsException.php
│   ├── ModuleInstallException.php
│   ├── ModuleScaffoldException.php
│   ├── ModuleSourceException.php
│   ├── ModuleUpdateException.php
│   └── ModuleRemoveException.php
├── Support/
│   └── ZipExtractor.php

stubs/
├── module-service-provider.stub
└── module.json.stub

tests/
├── Unit/
│   ├── Application/
│   │   ├── Support/
│   │   │   ├── LifecycleRegistryInvalidatorTest.php
│   │   │   ├── ModuleDependencyGuardTest.php
│   │   │   ├── ModuleDirectoryOperationsTest.php
│   │   │   ├── ModuleLifecyclePathsTest.php
│   │   │   └── ModuleSourcePreparerTest.php
│   │   └── UseCases/
│   │       ├── DisableModuleUseCaseTest.php
│   │       ├── EnableModuleUseCaseTest.php
│   │       ├── InstallModuleUseCaseTest.php
│   │       ├── RemoveModuleUseCaseTest.php
│   │       ├── ScaffoldModuleUseCaseTest.php
│   │       └── UpdateModuleUseCaseTest.php
│   ├── Exceptions/
│   │   └── LifecycleExceptionsTest.php
│   └── Support/
│       └── ZipExtractorTest.php
└── Feature/
    └── Commands/
        ├── MakeModuleCommandTest.php
        ├── ModulesDisableCommandTest.php
        ├── ModulesEnableCommandTest.php
        ├── ModulesInstallCommandTest.php
        ├── ModulesListCommandTest.php
        ├── ModulesRemoveCommandTest.php
        └── ModulesUpdateCommandTest.php
```

## Модифицируемые файлы

- `src/Providers/ModuleLoaderServiceProvider.php` — регистрация команд и bindings
- `config/modules.php` — новый ключ `paths.backup`
- `tests/Architecture/ArchitectureTest.php` — новые arch-правила
- `composer.json`, `composer.lock` — `ext-zip` в `require` без package version churn
- `README.MD`, `docs/getting-started.md`, `docs/cli.md`, `docs/configuration.md`, `docs/architecture.md` — документация фактического runtime жизненного цикла
- `AGENTS.md`, `.ai-factory/DESCRIPTION.md`, `.ai-factory/ARCHITECTURE.md`, `.ai-factory/ROADMAP.md` — AI-контекст без drift между roadmap и runtime

## План коммитов

| Чекпоинт | Задачи | Сообщение коммита |
|----------|--------|-------------------|
| 1 | 1–7 | `feat(lifecycle): add lifecycle foundation services` |
| 2 | 8–9 | `feat(lifecycle): add enable, disable, and list module commands` |
| 3 | 10–12 | `feat(scaffold): add module scaffolding command` |
| 4 | 13–14 | `feat(lifecycle): add module install command` |
| 5 | 15–16 | `feat(lifecycle): add module update command` |
| 6 | 17–18 | `feat(lifecycle): add module remove command` |
| 7 | 19–21 | `docs(lifecycle): register and document lifecycle commands` |
