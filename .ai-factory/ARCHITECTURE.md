# Архитектура `dimitrienkov0/laravel-modules`

> Фактическая архитектура v2.0 core. Этот документ описывает runtime-границы, dependency flow и ключевые инварианты. Короткие рабочие правила для агентов держатся отдельно в `.ai-factory/rules/base.md`.

## Архитектурный паттерн

**Modular Laravel Runtime с Manifest Layer, Registry Snapshot и Loader Pipeline.**

Пакет добавляет host-приложению Laravel модульный runtime без отдельного Composer autoload на каждый модуль, без БД для состояния модулей и без обязательной зависимости от MoonShine или Inertia.

Основные решения:

- Модуль - директория внутри настроенного `app_path()`-root с обязательным `module.json`.
- `module.json` иммутабелен в runtime и содержит только `meta` и `settings.schema`.
- Mutable state (`enabled`, timestamps, explicit feature values) хранится отдельно в `state.json` под `storage/app/private/modules/{module-name}/`.
- Registry строится как `ModuleRegistrySnapshot`: ordered load list плюс lookup-map по имени модуля.
- Production cache `bootstrap/cache/modules.php` хранит только manifest descriptors и `load_order`, без state и `settings.values`.
- Loader pipeline применяет convention-based loaders к enabled-модулям в dependency order.
- Runtime feature values читаются из `state.json` через `ModuleStateRepositoryInterface`, а не из registry cache.

## Runtime Слои

| Слой | Ответственность |
|------|-----------------|
| `Contracts` | Публичные интерфейсы пакета. Не импортируют реализации. |
| `Manifest` | Валидация manifest, parser layer, VO hydration, manifest/state repositories, registry facade, feature runtime API. |
| `Registry` | Filesystem discovery, snapshot builder, production cache format, registry/cache VO. |
| `Application` | UseCase orchestration для lifecycle и optimize flows, DTO, support-сервисы операций над модулями. |
| `Console/Commands` | Тонкие Artisan adapters над `Application/UseCases` и `Contracts`. |
| `Loaders` | Convention-based runtime loaders. Не читают manifest-флаги загрузки. |
| `Support` | Общие инфраструктурные утилиты: atomic write, paths, namespace resolution, filesystem wrapper, topological sort. |
| `Providers` | Container wiring, default loader registration, optimizer hooks, optional bridge wiring. |
| `MoonShine` | Optional bridge, активируется только при наличии MoonShine contracts. |

## Текущая Структура `src/`

```
src/
├── Application/
│   ├── DTOs/
│   │   ├── ClearModulesOptimizeCacheResult.php
│   │   ├── InstallModuleResult.php
│   │   ├── ListModulesResult.php
│   │   ├── OptimizeModulesResult.php
│   │   ├── RemoveModuleResult.php
│   │   ├── ScaffoldModuleConfig.php
│   │   ├── ScaffoldModuleResult.php
│   │   ├── SkippedFeatureValue.php
│   │   └── UpdateModuleResult.php
│   ├── Enums/
│   │   ├── ModuleSourceKind.php
│   │   └── RemoveStrategy.php
│   ├── Support/
│   │   ├── LifecycleRegistryInvalidator.php
│   │   ├── ModuleDependencyGuard.php
│   │   ├── ModuleDirectoryOperations.php
│   │   ├── ModuleDirectoryPaths.php
│   │   ├── ModuleSkeletonBuilder.php
│   │   ├── ModuleSourcePreparer.php
│   │   ├── PartialModuleRollback.php
│   │   └── PreparedSource.php
│   └── UseCases/
│       ├── ClearModulesOptimizeCacheUseCase.php
│       ├── DisableModuleUseCase.php
│       ├── EnableModuleUseCase.php
│       ├── InstallModuleUseCase.php
│       ├── ListModulesUseCase.php
│       ├── OptimizeModulesUseCase.php
│       ├── RemoveModuleUseCase.php
│       ├── ScaffoldModuleUseCase.php
│       └── UpdateModuleUseCase.php
├── Console/Commands/Modules/
├── Contracts/
├── Exceptions/
├── Loaders/
│   └── Pipeline/ModuleLoaderPipeline.php
├── Manifest/
│   ├── Enums/
│   │   ├── FeatureType.php
│   │   ├── ModuleKind.php
│   │   └── ModuleOriginKind.php
│   ├── Parsing/
│   └── VO/
├── MoonShine/
├── Providers/
├── Registry/
│   └── VO/
└── Support/
```

Детальная карта проекта и docs-index находятся в `AGENTS.md`. Этот документ фиксирует только архитектурные границы и runtime flow.

## Dependency Rules

Разрешённые направления:

- `Providers -> Contracts + Manifest + Registry + Support + Loaders + MoonShine + Application`
- `Console/Commands -> Application/UseCases + Contracts`
- `Application/UseCases -> Contracts + Manifest/VO + Application/Support + Application/DTOs + Support + Registry`
- `Application/Support -> Contracts + Manifest/VO + Support + Registry`
- `Application/DTOs ->` без зависимостей на runtime-сервисы
- `Manifest -> Contracts + Registry + Manifest/VO + Manifest/Parsing + Manifest/Enums + Support`
- `Registry -> Contracts + Manifest/VO + Registry/VO + Support`
- `Loaders -> Contracts + Manifest/VO/Module + Support + Laravel abstractions`
- `Support -> Laravel abstractions` только для инфраструктурных адаптеров; `Support -> Manifest/VO` допустим, когда утилита работает с typed module object.

Запрещённые направления:

- `Contracts` не зависят от реализаций.
- `Application` не зависит от `Loaders`, `Providers`, `MoonShine`.
- `Manifest` не зависит от конкретных loaders.
- `Support` не вызывает service providers или Artisan commands.
- Optional integrations не становятся обязательными runtime dependencies.
- В `src/` не используются Laravel facades, mutable static properties, debug/termination calls и runtime logging через `Log`.

## Bootstrap Flow

1. `ModuleLoaderServiceProvider::register()` делает `mergeConfigFrom(config/modules.php, 'modules')`.
2. Provider регистрирует singleton support services, manifest/state repositories, scanner, cache, snapshot builder и registry.
3. `FeatureRepositoryInterface` регистрируется как scoped binding.
4. Default loaders регистрируются как singleton services и тегируются `ModuleLoaderServiceProvider::LOADER_TAG`.
5. MoonShine binding регистрируется только если существует `MoonShine\Contracts\Core\DependencyInjection\CoreContract`.
6. `boot()` публикует config/stubs, регистрирует Artisan commands и Laravel optimizer hooks.
7. `boot()` ставит MoonShine `callAfterResolving()` hook при доступном MoonShine.
8. Provider резолвит tagged loaders. Любой tagged service не реализующий `LoaderInterface` падает fail-loud через `InvalidConfigurationException`.
9. `ModuleLoaderPipeline` сортирует loaders по `priority()` и применяет их к enabled-модулям из `ModuleRegistryInterface::all()`.

## Manifest И State

`module.json` разрешает только top-level ключи `schema_version`, `meta` и `settings`.

```json
{
  "schema_version": 1,
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "kind": "module",
    "group": "content",
    "version": "1.0.0",
    "dependencies": {
      "users": "^1.5"
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

State и explicit values находятся в отдельном `state.json`:

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00",
  "updated_at": "2026-05-23T14:12:00+00:00",
  "source": {
    "installed_version": "1.0.0",
    "kind": "zip",
    "checksum": "sha256:abc123..."
  },
  "settings": {
    "values": {
      "enable_comments": false
    }
  }
}
```

Инварианты:

- `schema_version` — обязательный top-level integer. Текущая версия: `1`. Неизвестная или отсутствующая версия → strict-fail (`InvalidManifestException`), без fallback на default.
- `meta.kind` — обязательный backed string enum `ModuleKind` (`module`, `subsystem`, `integration`). Чисто презентационный: не влияет на loader pipeline, dependency resolution или enable/disable. Arch-тест `loaders do not depend on ModuleKind` закрепляет эту границу.
- `meta.group` — опциональный string, kebab-case (`/^[a-z][a-z0-9-]*$/`). Используется для логической группировки модулей (отображение в `modules:list`, конфигурация `modules.paths.groups`). Не влияет на loader pipeline и dependency resolution.
- `state`, `settings.values`, `autoload` и неизвестные top-level ключи запрещены в `module.json`.
- `meta.dependencies` поддерживает только object-form `moduleName => Composer constraint`; list-form dependencies не являются текущим контрактом.
- `settings.schema` поддерживает `bool`, `int`, `string`, `enum`; metadata `label`, `description`, `group` допустима.
- `source` — опциональная секция в `state.json`, записывается через `ModuleOrigin` VO. Содержит `kind` (`local` | `zip`), `installed_version` и опциональный `checksum`. Записывается lifecycle UseCase-классами (scaffold, install, update) для фиксации provenance установленного модуля. Не читается registry cache и не влияет на loader pipeline.
- `ModuleManifestRepository::load()` валидирует `module.json`, гидратирует `Module` и дочитывает актуальный state через `ModuleStateRepositoryInterface`.
- `ModuleManifestRepository::writeManifest()` записывает только immutable descriptor (`schema_version` + `meta` + `settings.schema`).
- `ModuleStateRepositoryInterface` управляет `state.json`: state, values, source origin, delete, backup, existence checks.
- `FeatureValues` валидирует explicit values против `FeatureSchema` при чтении и записи.

## Registry И Cache

Registry flow:

1. `ModuleRegistry::ensureLoaded()` возвращает in-memory `ModuleRegistrySnapshot`, если он уже загружен.
2. Если cache существует, `ModuleRegistryCacheInterface::load()` читает `bootstrap/cache/modules.php`.
3. Если cache отсутствует, `ModuleRegistrySnapshotBuilder::build()` делает fresh filesystem scan.
4. `ModuleRegistry::reset()` сбрасывает только in-memory snapshot.

Fresh scan flow:

1. `ModuleDirectoryScanner` читает `modules.paths.directories`, проверяет roots внутри `app_path()` и возвращает подпапки с `module.json`.
2. `ModuleManifestRepositoryInterface::load()` загружает каждый manifest и state.
3. `TopologicalSorter` сортирует modules по `meta.dependencies`, проверяет duplicate names, missing/disabled/incompatible dependencies и cycles.
4. `ModuleRegistrySnapshot` строит ordered list и lookup-map; `all()` возвращает deterministic load order.

Cache payload v4:

- `version = 4`
- `modules[name] = path + namespace + manifest descriptor` (descriptor включает `schema_version`, `meta` с `kind`, `settings`)
- `load_order = list<moduleName>`

Cache не содержит state или `settings.values`. При чтении cache `ModuleRegistryCache` валидирует payload, пересобирает `Module` из manifest descriptor и дочитывает fresh state через `ModuleStateRepositoryInterface::readState()`. Cache v3 автоматически reject'ится при чтении.

Optimize flow:

- `OptimizeModulesUseCase` всегда строит cache из fresh `ModuleRegistrySnapshotBuilder`, а не из уже загруженного `ModuleRegistry`.
- `ClearModulesOptimizeCacheUseCase` проверяет `ModuleRegistryCacheInterface::exists()` для CLI-result и очищает cache через `LifecycleRegistryInvalidator`.
- `LifecycleRegistryInvalidator::flushAndReset()` вызывает `cache->forget()` и в `finally` делает `registry->reset()`.

## Loader Pipeline

`LoaderInterface` остаётся минимальным:

```php
interface LoaderInterface
{
    public function load(Module $module): void;

    public function priority(): int;
}
```

Default loaders:

| Loader | Priority | Convention |
|--------|----------|------------|
| `ConfigLoader` | 10 | `Config/*.php` |
| `ServiceProviderLoader` | 20 | `Providers/*ServiceProvider.php` |
| `MigrationLoader` | 30 | `Database/Migrations/` |
| `FactoryLoader` | 31 | `Database/Factories/` |
| `LangLoader` | 32 | `Lang/` |
| `ViewLoader` | 33 | `Resources/views/` |
| `BladeComponentLoader` | 34 | `View/Components/` |
| `EventLoader` | 35 | `Domain/Listeners/` |
| `ObserverLoader` | 36 | `Domain/Observers/` |
| `PolicyLoader` | 37 | `Domain/Policies/` |
| `CommandLoader` | 40 | `Console/Commands/` |
| `MiddlewareLoader` | 45 | `Http/Middleware/` |
| `RouteLoader` | 50 | `Routes/api.php`, `Routes/api/*.php`, `Routes/web.php`, `Routes/inertia.php` |
| `ConsoleRouteLoader` | 51 | `Routes/console.php` |
| `BroadcastLoader` | 52 | `Routes/channels.php` |

Pipeline behavior:

- Lower priority runs earlier.
- Equal priorities keep registration order.
- Disabled modules are skipped.
- Exceptions from one loader are reported as `ModuleLoaderException` and do not stop remaining loaders/modules.
- Loader applicability is convention-based: loader returns early when expected files/directories are absent.

## Lifecycle Commands

Implemented Artisan surface:

- `make:module`
- `modules:list`
- `modules:install`
- `modules:update`
- `modules:remove`
- `modules:enable`
- `modules:disable`
- `modules:optimize`
- `modules:optimize-clear`

Lifecycle-команды остаются тонкими adapters над use cases и support-сервисами. Они меняют `state.json` для enable/disable и пишут `module.json` только через `ModuleManifestRepositoryInterface::writeManifest()` в scaffold/install/update flows. Успешные lifecycle-мутации инвалидируют optimized registry cache.

`modules:install` и `modules:update` валидируют source structure, manifest и dependencies до замены target files. `modules:update` сохраняет совместимые customer `settings.values`; удалённые или invalid values попадают в skipped result.

## Optional Integrations

- MoonShine optional. `MoonShineModuleAutoloader` подключается только при наличии MoonShine `CoreContract`, затем вызывает `$core->autoload($module->namespace)` для enabled-модулей.
- Inertia optional. `RouteLoader` загружает `Routes/inertia.php` только при наличии Inertia.
- Optional integrations должны оставаться в `suggest` / `require-dev`, а не в обязательном runtime `require`.

## Runtime Safety

- Per-request mutable feature value cache живёт только в scoped `FeatureRepository`.
- Singleton runtime-сервисы stateless или держат immutable-ish lazy snapshots.
- Direct filesystem operations изолированы в инфраструктурных классах: `LocalFilesystem`, `AtomicFileWriter`, `ManifestDocumentReader`, `ModuleRegistryCache`.
- Atomic writes используют temp file, lock и rename через shared writers.
- Package core не пишет runtime logs; failures видны через typed exceptions, command output и tests.
