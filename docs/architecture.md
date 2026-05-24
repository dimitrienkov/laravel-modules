[← Configuration](configuration.md) · [Back to README](../README.MD) · [Feature Toggles →](feature-toggles.md)

# Architecture

Текущий v2.0 core - это Modular Laravel Runtime с typed manifest layer, dependency-aware registry, loader pipeline, scoped feature runtime и optional integration bridges.

## Runtime flow

1. `ModuleLoaderServiceProvider::register()` merge'ит package config.
2. Provider регистрирует core bindings, default loaders и optional MoonShine integration.
3. `boot()` публикует config, регистрирует console commands и подключает Laravel optimizer hooks.
4. `boot()` создаёт `ModuleLoaderPipeline` из tagged loaders.
5. Pipeline применяет отсортированные loaders к enabled-модулям из `ModuleRegistryInterface::loadOrder()`.

## Core services

| Service | Responsibility |
|---------|----------------|
| `ModuleManifestRepository` | Читает, валидирует, гидратирует и пишет `module.json` |
| `ModuleDirectoryScanner` | Находит module directories в configured roots |
| `ModuleRegistry` | Загружает modules из cache или filesystem и даёт lookup/load order |
| `ModuleRegistryCache` | Читает и пишет `bootstrap/cache/modules.php` payload format |
| `TopologicalSorter` | Сортирует modules по dependencies и проверяет SemVer constraints |
| `ModuleLoaderPipeline` | Сортирует loaders по priority и применяет их к enabled modules |
| `FeatureRepository` | Читает текущие feature values из `module.json` per request |

## Loader pipeline

Default loaders tagged через `ModuleLoaderServiceProvider::LOADER_TAG`.

| Loader | Priority | Loads |
|--------|----------|-------|
| `ConfigLoader` | 10 | `Config/*.php` |
| `ServiceProviderLoader` | 20 | `Providers/*ServiceProvider.php` |
| `MigrationLoader` | 30 | `Database/Migrations/` |
| `FactoryLoader` | 31 | `Database/Factories/` |
| `RouteLoader` | 50 | `Routes/api.php`, `Routes/api/*.php`, `Routes/web.php`, `Routes/inertia.php` |

Меньшее значение priority выполняется раньше.

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

Если cache существует, registry загружает его через `require`. Если cache нет, registry сканирует configured directories, читает manifests, резолвит namespaces и сортирует modules.

`modules:optimize` пишет cache payload. `modules:optimize-clear` удаляет его и сбрасывает in-memory registry.

## Feature runtime

`FeatureRepositoryInterface` биндится как scoped. Это сохраняет request-level cache совместимым с Octane-style runtimes и предотвращает stale feature values между requests.

Feature values читаются из `module.json`, а не из production registry cache. Изменение `settings.values` не требует `modules:optimize-clear`.

## Optional MoonShine bridge

MoonShine autoload не является pipeline loader. `ModuleLoaderServiceProvider` регистрирует `MoonShineModuleAutoloader` только когда существует `MoonShine\Contracts\Core\DependencyInjection\CoreContract`.

После resolve MoonShine core bridge вызывает `$core->autoload($module->namespace)` для каждого enabled-модуля.

## Dependency rules

- `Contracts` задают public boundaries и не должны зависеть от implementations.
- `Manifest` владеет parsing, validation, value objects, repository, registry orchestration и feature API.
- `Registry` владеет directory scan и production cache format.
- `Loaders` зависят от contracts, `Manifest\VO\Module`, `ModuleLayout` и Laravel services.
- Optional integrations должны оставаться guarded и optional.
- Runtime code в `src/` должен оставаться DI-first, без Laravel facades.

## See Also

- [Manifest](manifest.md) - manifest validation и write boundary.
- [Feature Toggles](feature-toggles.md) - scoped runtime values.
- [CLI](cli.md) - cache commands и optimizer hooks.
