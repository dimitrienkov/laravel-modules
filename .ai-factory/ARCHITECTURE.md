# Архитектура `dimitrienkov0/laravel-modules`

> Текущая архитектура v2.0 core: manifest-driven registry, typed manifest layer, loader-pipeline, scoped feature runtime, отдельное state-хранилище и optional integration bridges. Документ описывает фактический runtime, а roadmap-функции помечает отдельно.

## Архитектурный паттерн

**Modular Laravel Runtime с Manifest Layer и Loader-Pipeline.**

- Хост-приложение остаётся обычным Laravel-монолитом.
- Модуль — директория внутри `app_path()` хост-приложения. Namespace резолвится через `Application::getNamespace()` и `Application::path()`.
- `module.json` — immutable manifest, содержит только `meta` и `settings.schema`. В нём НЕТ `state` и `settings.values`.
- Mutable state (`enabled`, `installed_at`, `updated_at`) и explicit feature values хранятся в `state.json` в приватном хранилище хоста (`storage/app/private/modules/{module-name}/state.json`).
- Registry строится из manifest'ов, сортируется по dependencies и может быть прогрет в `bootstrap/cache/modules.php`. Cache (v3) кеширует только manifest descriptors, state НЕ кешируется.
- Loader-pipeline применяет независимые loaders к enabled-модулям в dependency order.
- Runtime feature values читаются из `state.json` через `ModuleStateRepositoryInterface`, а не из production registry cache.

Выбранный паттерн подходит пакету, потому что задача — дать host-приложению модульный runtime без собственного composer autoload на каждый модуль, без БД для состояния и без жёсткой привязки к MoonShine/Inertia.

## Текущая структура пакета

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
│           ├── ModulesOptimizeCommand.php
│           ├── ModulesOptimizeClearCommand.php
│           ├── ModulesRemoveCommand.php
│           └── ModulesUpdateCommand.php
├── Contracts/
│   ├── FeatureRepositoryInterface.php
│   ├── LoaderInterface.php
│   ├── ManifestValidatorInterface.php
│   ├── ModuleManifestRepositoryInterface.php
│   ├── ModuleRegistryInterface.php
│   ├── ModuleStateRepositoryInterface.php
│   └── NamespaceResolverInterface.php
├── Exceptions/
│   └── *Exception.php
├── Loaders/
│   ├── BladeComponentLoader.php
│   ├── BroadcastLoader.php
│   ├── CommandLoader.php
│   ├── ConfigLoader.php
│   ├── ConsoleRouteLoader.php
│   ├── EventLoader.php
│   ├── FactoryLoader.php
│   ├── LangLoader.php
│   ├── MiddlewareLoader.php
│   ├── MigrationLoader.php
│   ├── ObserverLoader.php
│   ├── PolicyLoader.php
│   ├── ServiceProviderLoader.php
│   ├── RouteLoader.php
│   ├── ViewLoader.php
│   └── Pipeline/
│       └── ModuleLoaderPipeline.php
├── Manifest/
│   ├── Enums/
│   │   └── FeatureType.php
│   ├── Parsing/
│   │   ├── FeatureDefinitionFactory.php
│   │   ├── FeatureValueNormalizer.php
│   │   └── ManifestFieldReader.php
│   ├── VO/
│   │   ├── FeatureDefinition.php
│   │   ├── FeatureSchema.php
│   │   ├── FeatureValues.php
│   │   ├── ManifestMeta.php
│   │   ├── Module.php
│   │   ├── ModuleDependencies.php
│   │   ├── ModuleDependency.php
│   │   ├── ModuleState.php
│   │   └── ModuleStateDocument.php
│   ├── FeatureRepository.php
│   ├── ManifestDocumentReader.php
│   ├── ManifestSettingsValidator.php
│   ├── ManifestValidator.php
│   ├── ModuleManifestRepository.php
│   ├── ModuleRegistry.php
│   └── ModuleStateRepository.php
├── MoonShine/
│   └── MoonShineModuleAutoloader.php
├── Providers/
│   └── ModuleLoaderServiceProvider.php
├── Registry/
│   ├── ModuleDirectoryScanner.php
│   ├── ModuleRegistryCache.php
│   └── VO/
│       ├── CachedModuleDescriptor.php
│       └── ModuleRegistryCachePayload.php
└── Support/
    ├── ApplicationNamespaceResolver.php
    ├── AtomicFileWriter.php
    ├── AtomicJsonWriter.php
    ├── ContainerLifecycleHooks.php
    ├── ModuleLayout.php
    ├── ModuleStatePaths.php
    ├── TopologicalSorter.php
    └── ZipExtractor.php
```

## Dependency rules

- `Providers` могут собирать зависимости и регистрировать bindings, но бизнес-правила manifest/registry остаются в соответствующих сервисах.
- `Loaders` зависят от `Contracts`, `Manifest\VO\Module`, `Support\ModuleLayout` и Laravel services.
- `Manifest` отвечает за parsing, validation, VO hydration, manifest repository, state repository, registry orchestration и runtime feature API.
- `Registry` отвечает только за directory scan и production cache format.
- `Support` содержит инфраструктурные утилиты: path layout, state path resolution, atomic writes, namespace resolution, topological sort, zip extraction и container lifecycle hooks.
- `Application` содержит lifecycle UseCase-классы, support-сервисы и DTOs. `Application/UseCases` → `Contracts + Manifest\VO + Application/Support + Application/DTOs + Support`. `Application/Support` → `Contracts + Manifest\VO + Support + Registry`. `Application/DTOs` → `∅`. `Application` не зависит от `Loaders`, `Providers`, `MoonShine`.
- `Console/Commands` → `Application/UseCases + Contracts`.
- `MoonShine` — optional bridge; core runtime не должен требовать MoonShine классы без guard.
- `Contracts` не зависят от реализаций.
- В `src/` запрещены Laravel facades, debug/termination calls и mutable static properties.

Разрешённые направления:

- `Provider -> Contracts/Manifest/Registry/Support/Loaders/MoonShine/Application`
- `Application -> Contracts + Manifest\VO + Application\Support + Application\DTOs + Support`
- `Console\Commands -> Application\UseCases + Contracts`
- `Loaders -> Contracts + Manifest\VO + Support + Laravel abstractions`
- `Manifest -> Contracts + Registry + Manifest\VO/Parsing/Enums + Support`
- `Registry -> Manifest\VO + Contracts + Support`
- `Support -> Manifest\VO` только там, где утилита работает с typed module path объектом; `Support -> Laravel abstractions` допустим для namespace resolution, container lifecycle hooks и config resolution (ModuleStatePaths).

Запрещённые направления:

- `Manifest` не должен зависеть от конкретных loaders.
- `Support` не должен вызывать service provider или artisan commands. Единственное исключение для container lifecycle — `ContainerLifecycleHooks`, повторяющий package-style `ServiceProvider::callAfterResolving()` без привязки к конкретному provider.
- `Contracts` не должны импортировать реализации.
- Optional integrations не должны становиться обязательными runtime dependencies.

## Bootstrap flow

1. `ModuleLoaderServiceProvider::register()` делает `mergeConfigFrom(config/modules.php, 'modules')`.
2. Provider регистрирует core bindings:
   - singleton: `ModuleLayout`, `ModuleStatePaths`, `AtomicJsonWriter`, `ContainerLifecycleHooks`, `TopologicalSorter`, validator, manifest repository, state repository, scanner, cache, registry.
   - scoped: `FeatureRepositoryInterface`.
3. Provider регистрирует default loaders и тегирует их `ModuleLoaderServiceProvider::LOADER_TAG`.
4. Provider регистрирует `MoonShineModuleAutoloader` binding только если существует `MoonShine\Contracts\Core\DependencyInjection\CoreContract`.
5. `boot()` публикует config, регистрирует console commands и Laravel optimizer hooks.
6. `boot()` ставит MoonShine `callAfterResolving()` hook, если MoonShine доступен.
7. `boot()` создаёт `ModuleLoaderPipeline` из tagged loaders и вызывает `boot()`.
8. Pipeline сортирует loaders по `priority()` и применяет их к enabled-модулям в `ModuleRegistryInterface::loadOrder()`.

```php
<?php

declare(strict_types=1);

foreach ($this->sortedLoaders() as $loader) {
    foreach ($this->registry->loadOrder() as $module) {
        if (! $module->isEnabled()) {
            continue;
        }

        $loader->load($module);
    }
}
```

## Core contracts

### LoaderInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface LoaderInterface
{
    public function load(Module $module): void;

    public function priority(): int;
}
```

Loader сам решает, есть ли работа для модуля, через ранний return при отсутствии нужной директории или файла. В manifest нет whitelist-флагов загрузки.

### ModuleRegistryInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface ModuleRegistryInterface
{
    /** @return array<int, Module> */
    public function all(): array;

    /** @return array<int, Module> */
    public function loadOrder(): array;

    public function find(string $name): Module;
}
```

`find()` возвращает `Module` или бросает `ModuleNotFoundException`; nullable lookup не является текущим контрактом.

### ModuleManifestRepositoryInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface ModuleManifestRepositoryInterface
{
    public function load(string $modulePath): Module;

    public function writeManifest(Module $module): void;
}
```

`load()` читает и валидирует immutable `module.json`, затем дочитывает актуальный state из `state.json` через `ModuleStateRepositoryInterface`. `writeManifest()` записывает только immutable часть (`meta` + `settings.schema`).

### ModuleStateRepositoryInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;

interface ModuleStateRepositoryInterface
{
    public function read(string $moduleName, Module $module): ModuleStateDocument;

    public function readState(string $moduleName, Module $module): ModuleState;

    public function readValues(Module $module): FeatureValues;

    public function write(string $moduleName, ModuleStateDocument $document): void;

    public function updateState(Module $module, ModuleState $state): Module;

    public function saveValues(Module $module, FeatureValues $values): void;

    public function delete(string $moduleName): void;

    public function moveToBackup(string $moduleName, string $backupPath): ?string;

    public function exists(string $moduleName): bool;
}
```

`ModuleStateRepository` управляет `state.json` в приватном хранилище хоста. Путь резолвится через `ModuleStatePaths`. Запись атомарна через `AtomicJsonWriter`. При отсутствии `state.json` возвращается `ModuleState::disabledDefault()` с пустыми values.

### FeatureRepositoryInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface FeatureRepositoryInterface
{
    public function get(string $moduleName, string $key): bool|int|string;

    public function getBool(string $moduleName, string $key): bool;

    public function getInt(string $moduleName, string $key): int;

    public function getString(string $moduleName, string $key): string;
}
```

Typed getters бросают `FeatureTypeMismatchException`, если фактический тип значения не совпадает с ожидаемым.

## Manifest layer

`ManifestValidator` разрешает только:

- top-level: `meta`, `settings`
- `settings`: `schema`
- `FeatureType`: `bool`, `int`, `string`, `enum`

`state`, `settings.values` и `autoload` прямо запрещены в `module.json`. Loader applicability определяется только convention-based путями через `ModuleLayout`.

`FeatureSchema` хранит definitions и defaults (в immutable `module.json`). `FeatureValues` хранит только explicit values (в mutable `state.json`) и возвращает default из schema, если explicit override отсутствует.

```json
{
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "version": "1.0.0",
    "dependencies": {
      "users": "^1.5"
    }
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true
      }
    }
  }
}
```

State и values (`state.json`):

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00",
  "settings": {
    "values": {
      "enable_comments": false
    }
  }
}
```

## State хранилище

Mutable state отделён от immutable manifest:

- **`module.json`** (в директории модуля) — immutable; содержит `meta` и `settings.schema`. Source-модули поставляются с этим файлом.
- **`state.json`** (в `storage/app/private/modules/{module-name}/`) — mutable; содержит `enabled`, `installed_at`, `updated_at` и `settings.values`. Создаётся хост-приложением при install, НЕ поставляется с модулем.

`ModuleStatePaths` резолвит пути state-файлов:
- `stateRoot()` — корневая директория (из конфига `modules.paths.state` или default `storage/app/private/modules`)
- `stateDirectory(string $moduleName)` — директория state модуля
- `stateFile(string $moduleName)` — путь к `state.json`
- `validate()` — проверяет, что state root не находится внутри директории модулей

`ModuleStateDocument` — VO, оборачивающий `ModuleState` + `FeatureValues` для атомарного чтения/записи полного state-документа.

Lifecycle-команды (`install`, `update`, `remove`, `enable`, `disable`) модифицируют только `state.json` через `ModuleStateRepositoryInterface`, никогда `module.json`.

## Registry и cache

`ModuleRegistry` хранит два lazy поля:

- `?array $modules`
- `?array $orderedModules`

При первом обращении он выбирает источник:

- cache exists -> `ModuleRegistryCache::load()`
- cache missing -> scan через `ModuleDirectoryScanner`

Cache payload (v3):

- `version` (= 3)
- `modules[name] = path + namespace + manifest descriptor` (только `meta` + `settings.schema`, без state и values)
- `load_order = list<moduleName>`

`ModuleRegistryCache` валидирует версию и структуру payload перед hydration. Это защищает runtime от устаревшего или повреждённого cache file.

State и feature values НЕ кешируются в registry cache. При загрузке из cache `ModuleManifestRepository::load()` читает актуальный state из `state.json` через `ModuleStateRepository`. Это гарантирует, что изменения state и settings применяются немедленно без необходимости `modules:optimize-clear`.

## Dependency sorting

`TopologicalSorter`:

- сортирует modules по имени перед обходом для детерминированности;
- проверяет дубли `meta.name`;
- обнаруживает циклы и бросает `CyclicDependencyException`;
- для enabled-модулей требует наличие enabled dependency;
- проверяет dependency version через `Composer\Semver\Semver::satisfies()`;
- отключённый модуль не заставляет валидировать отсутствующие dependencies, но dependency graph всё равно обходит существующие связи.

## Loader pipeline

Текущие loaders:

| Loader | Priority | Правило |
|--------|----------|---------|
| `ConfigLoader` | 10 | Загружает `Config/*.php` в config key `<module>.<file>` |
| `ServiceProviderLoader` | 20 | Регистрирует классы `Providers/*ServiceProvider.php`, пропуская abstract |
| `MigrationLoader` | 30 | Добавляет `Database/Migrations/` в Laravel migrator paths |
| `FactoryLoader` | 31 | Настраивает factory name guessing для `Domain\Models` -> `Database\Factories` |
| `LangLoader` | 32 | Регистрирует translation namespace `<module_name>` |
| `ViewLoader` | 33 | Регистрирует view namespace `<module_name>` |
| `BladeComponentLoader` | 34 | Регистрирует Blade component namespace |
| `EventLoader` | 35 | Добавляет event discovery paths для `Domain/Listeners` |
| `ObserverLoader` | 36 | Регистрирует observers для matching models, пропуская abstract |
| `PolicyLoader` | 37 | Регистрирует policies для matching models, пропуская abstract |
| `CommandLoader` | 40 | Регистрирует command paths через `addCommandPaths()` |
| `MiddlewareLoader` | 45 | Регистрирует middleware aliases `<module>.<snake_name>` |
| `RouteLoader` | 50 | Загружает flat и versioned route files по `modules.routing.types` |
| `ConsoleRouteLoader` | 51 | Регистрирует console routes через `addCommandRoutePaths()` (deferred) |
| `BroadcastLoader` | 52 | Загружает broadcast channels (deferred до boot) |

Добавление кастомного loader:

```php
<?php

declare(strict_types=1);

use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use App\Modules\Support\MyCustomLoader;

$this->app->singleton(MyCustomLoader::class);
$this->app->tag([MyCustomLoader::class], ModuleLoaderServiceProvider::LOADER_TAG);
```

Кастомный loader должен быть idempotent и делать ранний return при отсутствии своего файла/директории.

## Routing architecture

`RouteLoader` читает route types из `modules.routing.types`.

Поддерживаются:

- flat file: `Routes/api.php`, `Routes/web.php`, `Routes/inertia.php`
- versioned API files: `Routes/api/v1.php`, `Routes/api/v2.php`

Для versioned API prefix строится как `<api-prefix>/<filename>`, например `api/v1`. `Routes/inertia.php` пропускается, если Inertia package отсутствует. При cached routes loader ничего не регистрирует.

## MoonShine integration

MoonShine integration реализована как optional bridge, а не как обязательный loader.

`ModuleLoaderServiceProvider` проверяет наличие `MoonShine\Contracts\Core\DependencyInjection\CoreContract`. Если контракт доступен, provider регистрирует `MoonShineModuleAutoloader`, а в `boot()` ставит package-style `callAfterResolving()` hook. Hook срабатывает и для уже resolved core и вызывает:

```php
$core->autoload($module->namespace);
```

для каждого enabled-модуля из `ModuleRegistry::loadOrder()`.

В текущем core нет `ModulesResource`, `ModuleSettingsPage` или reflection/classmap discovery для MoonShine artifacts. Это roadmap.

## Runtime state и Octane safety

- `ModuleRegistry` — singleton с lazy immutable-ish cache после первого scan/cache load.
- `FeatureRepositoryInterface` — scoped binding; его per-request array cache сбрасывается Laravel container'ом между requests под Octane.
- `FeatureRepository` читает values через `ModuleStateRepositoryInterface::readValues()`, чтобы видеть изменения `state.json` на следующем request.
- `ModuleStateRepository` — readonly singleton, без internal mutable state; каждый вызов читает `state.json` с диска.
- В `src/` запрещены mutable static properties.
- Optional integrations должны быть guarded через `class_exists`, `interface_exists` или container lifecycle checks.

## ModuleLayout

`ModuleLayout` — единая точка формирования путей внутри модуля:

- `manifestFile()` / `manifestFilePath()`
- `configDir()`
- `providersDir()`
- `migrationsDir()`
- `factoriesDir()`
- `routesDir()` / `routeFile()`
- `langDir()`, `viewsDir()`, `bladeComponentsDir()`
- `commandsDir()`, `consoleRoutesFile()`, `channelsFile()`
- `observersDir()`, `policiesDir()`, `middlewareDir()`

Не все пути имеют реализованный loader в текущем core. Методы под будущие loaders нужны, чтобы расширения использовали единый path contract.

## ModuleStatePaths

`ModuleStatePaths` — единая точка формирования путей state-хранилища:

- `stateRoot()` — корневая директория из `modules.paths.state` или default `storage/app/private/modules`
- `stateDirectory(string $moduleName)` — директория state конкретного модуля
- `stateFile(string $moduleName)` — путь к `state.json`
- `validate()` — проверяет, что state root не пересекается с директориями модулей

## Quality architecture

Архитектурные тесты находятся в `tests/Architecture/ArchitectureTest.php` и запускаются Pest:

- классы пакета используют strict types;
- `.php` файлы в `src`, `tests`, `stubs` содержат `declare(strict_types=1)`;
- concrete classes в `src` final;
- VO в `Manifest\VO` final readonly;
- loaders final и реализуют `LoaderInterface`, кроме `ModuleLoaderPipeline`;
- `src` не содержит debug/termination calls;
- `src` не использует Laravel facades;
- `src` не содержит mutable static properties;
- exceptions final и наследуют `RuntimeException`;
- contracts являются interfaces;
- providers наследуют Laravel `ServiceProvider`;
- artisan commands наследуют Laravel `Command`;
- manifest enums являются string-backed enums.

Кодовый стиль проекта ориентирован на PER Coding Style 3.0, а конкретный formatter profile хранится в `.php-cs-fixer.dist.php`. PR-шаблон требует прогон `composer test`, `composer phpstan`, `composer format:dry`, `composer rector:dry`.

## Roadmap boundaries

Следующие элементы не являются текущим runtime и должны описываться как roadmap, пока соответствующие классы не появятся в `src/`:

- module-aware generators (`make:* --module`);
- MoonShine admin resources/pages;
- package signing, marketplace, remote delivery;
- lifecycle events (`ModuleInstalled`, `ModuleEnabled`, etc.).

Документация должна оставаться синхронизированной с фактической структурой `src/`, `tests/`, `composer.json` и `.github/PULL_REQUEST_TEMPLATE.md`.
