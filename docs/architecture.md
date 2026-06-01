[← Configuration](configuration.md) · [Back to README](../README.MD) · [Feature Toggles →](feature-toggles.md)

# Architecture

Текущий v2.0 core — Modular Laravel Runtime с typed manifest layer, dependency-aware registry, loader pipeline, lifecycle UseCase-классами, scoped feature runtime и optional integration bridges.

## Runtime flow

1. `ModuleLoaderServiceProvider::register()` merge'ит package config.
2. Provider регистрирует core bindings, default loaders и optional MoonShine integration.
3. `boot()` публикует config, регистрирует console commands и подключает Laravel optimizer hooks.
4. `boot()` создаёт `ModuleLoaderPipeline` из tagged loaders.
5. Pipeline применяет отсортированные loaders к enabled-модулям из `ModuleRegistryInterface::all()`.

## Core services

| Service | Responsibility |
|---------|----------------|
| `ModuleManifestRepository` | Читает и валидирует иммутабельный `module.json`; `load()` и `writeManifest()` |
| `ModuleStateRepository` | Читает/пишет mutable `state.json` (`enabled`, timestamps, `source` origin, `settings.values`) |
| `ModuleStatePaths` | Resolution путей к `state.json`: state root, state directory, state file |
| `ModuleDirectoryScanner` | Находит module directories в configured roots |
| `ModuleRegistry` | Загружает modules из cache или filesystem и даёт lookup/load order |
| `ModuleRegistryCache` | Читает и пишет `bootstrap/cache/modules.php` payload format |
| `TopologicalSorter` | Сортирует modules по dependencies и проверяет SemVer constraints |
| `ModuleLoaderPipeline` | Сортирует loaders по priority и применяет их к enabled modules |
| `FeatureRepository` | Читает текущие feature values из `state.json` через `ModuleStateRepository` per request |

## Loader pipeline

Default loaders tagged через `ModuleLoaderServiceProvider::LOADER_TAG`.

| Loader | Priority | Loads |
|--------|----------|-------|
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

Меньшее значение priority выполняется раньше. Pipeline изолирует ошибки: исключение в одном loader не останавливает загрузку остальных.

## Custom loader example

```php
<?php

declare(strict_types=1);

namespace App\Modules\Support;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class TranslationLoader implements LoaderInterface
{
    public function load(Module $module): void
    {
        // Register module translations here.
    }

    public function priority(): int
    {
        return 40;
    }
}
```

Зарегистрируйте и tagged loader в service provider host-приложения:

```php
<?php

declare(strict_types=1);

use App\Modules\Support\TranslationLoader;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;

$this->app->singleton(TranslationLoader::class);
$this->app->tag([TranslationLoader::class], ModuleLoaderServiceProvider::LOADER_TAG);
```

## Production cache

`ModuleRegistry` сначала проверяет `bootstrap/cache/modules.php`.

Если cache существует, registry загружает его через `require` и заворачивает повреждённый PHP cache в `InvalidModuleCacheException`. Если cache нет, registry сканирует configured directories, читает manifests, резолвит namespaces и сортирует modules.

`modules:optimize` пишет cache payload атомарно через temp file, flush и rename. `modules:optimize-clear` удаляет его и сбрасывает in-memory registry.

## Feature runtime

`FeatureRepositoryInterface` биндится как scoped. Это сохраняет request-level cache совместимым с Octane-style runtimes и предотвращает stale feature values между requests.

Feature values читаются из `state.json`, а не из production registry cache. Изменение `settings.values` не требует `modules:optimize-clear`.

## Optional MoonShine bridge

MoonShine autoload не является pipeline loader. `ModuleLoaderServiceProvider` регистрирует `MoonShineModuleAutoloader` только когда существует `MoonShine\Contracts\Core\DependencyInjection\CoreContract`.

Bridge ставит package-style `callAfterResolving()` hook, поэтому работает и когда MoonShine core уже resolved. После resolve MoonShine core bridge вызывает `$core->autoload($module->namespace)` для каждого enabled-модуля.

## Lifecycle layer

UseCase-классы в `Application/UseCases/` реализуют бизнес-операции жизненного цикла модулей: enable, disable, scaffold, install, update, remove. Все — `final readonly` с constructor DI.

### Core services

| Service | Responsibility |
|---------|----------------|
| `EnableModuleUseCase` | Включает модуль с проверкой dependency graph |
| `DisableModuleUseCase` | Отключает модуль с проверкой reverse dependencies |
| `ScaffoldModuleUseCase` | Создаёт структуру нового модуля из stubs |
| `InstallModuleUseCase` | Устанавливает модуль из `.zip` source |
| `UpdateModuleUseCase` | Обновляет модуль с backup и merge settings values |
| `RemoveModuleUseCase` | Удаляет модуль с backup или без |

### Support services

| Service | Responsibility |
|---------|----------------|
| `ModuleSourcePreparer` | Staging boundary: валидирует `.zip` source до копирования |
| `ModuleDependencyGuard` | Проверяет dependency graph перед мутациями |
| `ModuleDirectoryOperations` | Filesystem-операции: copy, replace with backup, restore, delete |
| `ModuleDirectoryPaths` | Resolution путей: target root, configured roots, backup directory |
| `ModuleSkeletonBuilder` | Создаёт scaffold-директории и ServiceProvider stub |
| `PartialModuleRollback` | Удаляет частично созданный target после failed persistence |
| `LifecycleRegistryInvalidator` | Сбрасывает production cache и in-memory registry после мутаций |
| `ZipExtractor` | Извлечение zip с защитой от zip-slip |

### Cache invalidation

После каждой успешной мутации (enable, disable, install, update, remove, scaffold) `LifecycleRegistryInvalidator` сбрасывает `bootstrap/cache/modules.php` через `ModuleRegistryCache::forget()` и in-memory state через `ModuleRegistry::reset()`. Это гарантирует, что следующий `ModuleRegistry::all()` вернёт актуальное состояние.

### Source staging

Install и update валидируют source ДО копирования файлов. `ModuleSourcePreparer` читает `module.json` из source через `ManifestDocumentReader`, прогоняет через `ManifestValidatorInterface` и возвращает `PreparedSource`. `ModuleManifestRepository::load()` не используется для source paths вне `app_path()`.

`.zip` source не должен содержать `state.json`. Такой source отклоняется, потому что state принадлежит приватному storage host-приложения и переносится отдельными lifecycle boundaries.

### Provenance

Scaffold, install и update фиксируют происхождение модуля в `state.json` через `ModuleOrigin` VO (секция `source`): scaffold пишет `kind = local` без checksum, install/update — `kind = zip` с `installed_version` и sha256 `checksum` архива. Инвариант `kind ↔ checksum` выражен kind-driven через `ModuleOriginKind::requiresChecksum()` и проверяется при чтении state. Provenance не влияет на loader pipeline или registry cache.

### Миграции и rollback

Lifecycle-команды не запускают и не откатывают миграции автоматически. `modules:install` напоминает про `php artisan migrate` после установки. `modules:remove` напоминает про `php artisan migrate:rollback` до удаления. `MigrationLoader` подхватывает миграции модуля на следующем boot.

## Dependency rules

- `Contracts` задают public boundaries и не должны зависеть от implementations.
- `Manifest` владеет parsing, validation, value objects, repository, registry orchestration и feature API.
- `Registry` владеет directory scan и production cache format.
- `Loaders` зависят от contracts, `Manifest\VO\Module`, `ModuleLayout` и Laravel services.
- `Application/UseCases` → `Contracts + Manifest\VO + Application/Support + Application/DTOs + Support`.
- `Application/Support` → `Contracts + Manifest\VO + Support + Registry`.
- `Application/DTOs` → `∅` (value objects без зависимостей).
- `Application` не зависит от `Loaders`, `Providers`, `MoonShine`.
- `Console/Commands` → `Application/UseCases + Contracts`.
- Optional integrations должны оставаться guarded и optional.
- Runtime code в `src/` должен оставаться DI-first, без Laravel facades.

## See Also

- [Manifest](manifest.md) - manifest validation и write boundary.
- [Feature Toggles](feature-toggles.md) - scoped runtime values.
- [CLI](cli.md) - cache commands и optimizer hooks.
