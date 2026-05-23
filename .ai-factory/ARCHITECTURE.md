# Архитектура `dimitrienkov0/laravel-modules` v2.0

> Идеальная архитектура с нуля, без обратной совместимости с 1.x. Дорожная карта — в `.ai-factory/ROADMAP.md`.

## 1. Архитектурный паттерн

**Modular Monolith с Loader-Pipeline.**

- Хост-приложение — Laravel-монолит.
- Модули — независимые папки с собственным манифестом, кодом, миграциями, ресурсами.
- Загрузка модулей — единый pipeline лоадеров, каждый из которых работает с готовым `Module`-объектом и сам решает, есть ли у него работа (через `ModuleLayout`).
- Внутри модуля — слоистая архитектура (Application/Domain/Infrastructure/Presentation) с UseCase + Action + Query как операционными единицами.

Принципы:
- **Один сканнер, много лоадеров** — `ModuleRegistry` сканирует FS один раз, передаёт `Module[]` каждому лоадеру.
- **Pure convention over configuration** — соглашение о папках задаёт поведение; манифест нужен только для метаданных и фичетоглов. Никакого `autoload`-whitelist'а.
- **JSON как источник правды** — состояние и значения фичетоглов хранятся в `module.json` модуля, без БД.
- **Идемпотентность** — повторный вызов `load()` ничего не ломает; все Loader'ы детектируют свой «уже загружено» через состояние Laravel или ранний return при отсутствии файлов.
- **Опциональная MoonShine** — пакет работает без неё; интеграция включается на `afterResolving(CoreContract::class)`.
- **Octane-safe by default** — никаких статических полей, никакого in-memory mutable-state на singleton; runtime-сервисы — scoped.
- **DTO/VO на границах** — публичные API оперируют типизированными объектами, не `array`.
- **`declare(strict_types=1);`** в каждом `.php`-файле `src/`, `tests/`, `stubs/`.

## 2. Структура пакета

```
src/
├── Console/
│   └── Commands/
│       ├── Modules/                # modules:* команды жизненного цикла
│       │   ├── ModulesMakeCommand.php
│       │   ├── ModulesListCommand.php
│       │   ├── ModulesEnableCommand.php
│       │   ├── ModulesDisableCommand.php
│       │   ├── ModulesInstallCommand.php
│       │   ├── ModulesUpdateCommand.php
│       │   ├── ModulesRemoveCommand.php
│       │   ├── ModulesOptimizeCommand.php
│       │   └── ModulesOptimizeClearCommand.php
│       └── Make/                   # make:module-* генераторы
│           ├── MakeModuleControllerCommand.php
│           ├── MakeModuleModelCommand.php
│           ├── MakeModuleUseCaseCommand.php
│           ├── MakeModuleActionCommand.php
│           ├── MakeModuleQueryCommand.php
│           ├── MakeModuleDtoCommand.php
│           ├── MakeModuleEnumCommand.php
│           ├── MakeModuleEventCommand.php
│           ├── MakeModuleListenerCommand.php
│           ├── MakeModuleObserverCommand.php
│           ├── MakeModulePolicyCommand.php
│           ├── MakeModuleMiddlewareCommand.php
│           ├── MakeModuleRequestCommand.php
│           ├── MakeModuleResourceCommand.php
│           ├── MakeModuleMigrationCommand.php
│           ├── MakeModuleFactoryCommand.php
│           ├── MakeModuleSeederCommand.php
│           ├── MakeModuleCommandCommand.php
│           ├── MakeModuleMoonShineResourceCommand.php
│           └── MakeModuleMoonShinePageCommand.php
├── Contracts/
│   ├── LoaderInterface.php
│   ├── ModuleRegistryInterface.php
│   ├── ModuleManifestRepositoryInterface.php
│   ├── FeatureRepositoryInterface.php
│   ├── NamespaceResolverInterface.php
│   └── ManifestValidatorInterface.php
├── Loaders/
│   ├── RouteLoader.php
│   ├── MigrationLoader.php
│   ├── FactoryLoader.php
│   ├── ConfigLoader.php
│   ├── LangLoader.php
│   ├── ViewLoader.php
│   ├── BladeComponentLoader.php
│   ├── CommandLoader.php
│   ├── ConsoleRouteLoader.php      # Routes/console.php (closures + Schedule)
│   ├── EventLoader.php
│   ├── ObserverLoader.php
│   ├── PolicyLoader.php
│   ├── MiddlewareLoader.php
│   ├── BroadcastLoader.php
│   ├── ServiceProviderLoader.php
│   └── MoonShineLoader.php
├── Manifest/
│   ├── Module.php                  # value object
│   ├── ManifestMeta.php            # readonly DTO
│   ├── ManifestState.php           # readonly DTO
│   ├── ModuleDependencies.php      # readonly VO (list<string>)
│   ├── FeatureSchema.php           # readonly DTO + FeatureDefinition[]
│   ├── FeatureDefinition.php       # readonly DTO
│   ├── FeatureValues.php           # readonly VO
│   ├── ModuleManifestRepository.php
│   ├── ModuleRegistry.php
│   ├── FeatureRepository.php       # Octane-safe runtime reader
│   └── ManifestValidator.php
├── Application/
│   └── UseCases/
│       ├── InstallModuleUseCase.php
│       ├── UpdateModuleUseCase.php
│       ├── RemoveModuleUseCase.php
│       ├── EnableModuleUseCase.php
│       ├── DisableModuleUseCase.php
│       └── ScaffoldModuleUseCase.php
├── MoonShine/
│   ├── Resources/
│   │   └── ModulesResource.php     # CRUD по модулям
│   └── Pages/
│       └── ModuleSettingsPage.php  # форма по settings.schema
├── Providers/
│   └── ModuleLoaderServiceProvider.php
├── Support/
│   ├── ModuleLayout.php            # пути модуля (routesDir, migrationsDir, …)
│   ├── AtomicJsonWriter.php        # tmp + rename + lock
│   ├── ComposerNamespaceResolver.php
│   ├── TopologicalSorter.php       # сортировка по dependencies
│   └── ZipExtractor.php            # для install/update
├── Exceptions/
│   ├── InvalidManifestException.php
│   ├── ModuleNotFoundException.php
│   ├── ModuleDependencyMissingException.php
│   ├── CyclicDependencyException.php
│   └── ManifestWriteException.php
└── stubs/                          # шаблоны для make:module-*
    ├── module.json.stub
    ├── ServiceProvider.stub
    ├── usecase.stub
    ├── action.stub
    ├── query.stub
    └── ...
```

## 3. Ключевые контракты

### LoaderInterface

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\Module;

interface LoaderInterface
{
    public function load(Module $module): void;

    /** Lower value loads earlier. */
    public function priority(): int;
}
```

Загрузка фильтруется ранним return'ом внутри самого лоадера: если соответствующей папки/файла в модуле нет — лоадер ничего не делает. Никакого `key()`-метода, никаких манифестных whitelist'ов.

### ModuleLayout

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Manifest\Module;

final readonly class ModuleLayout
{
    public function routesDir(Module $module): string
    {
        return $module->path . '/Routes';
    }

    public function migrationsDir(Module $module): string
    {
        return $module->path . '/Database/Migrations';
    }

    public function factoriesDir(Module $module): string
    {
        return $module->path . '/Database/Factories';
    }

    public function configDir(Module $module): string
    {
        return $module->path . '/Config';
    }

    public function langDir(Module $module): string
    {
        return $module->path . '/Lang';
    }

    public function viewsDir(Module $module): string
    {
        return $module->path . '/Resources/views';
    }

    public function bladeComponentsDir(Module $module): string
    {
        return $module->path . '/View/Components';
    }

    public function commandsDir(Module $module): string
    {
        return $module->path . '/Console/Commands';
    }

    public function consoleRoutesFile(Module $module): string
    {
        return $module->path . '/Routes/console.php';
    }

    public function channelsFile(Module $module): string
    {
        return $module->path . '/Routes/channels.php';
    }

    public function providersDir(Module $module): string
    {
        return $module->path . '/Providers';
    }

    public function observersDir(Module $module): string
    {
        return $module->path . '/Domain/Observers';
    }

    public function policiesDir(Module $module): string
    {
        return $module->path . '/Domain/Policies';
    }

    public function middlewareDir(Module $module): string
    {
        return $module->path . '/Http/Middleware';
    }
}
```

Перебиндить через `$this->app->bind(ModuleLayout::class, CustomLayout::class)` в сервис-провайдере хост-приложения — единственный override-механизм.

### Module value object

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

final readonly class Module
{
    public function __construct(
        public string             $name,           // 'blog'
        public string             $displayName,    // 'Blog'
        public string             $namespace,      // 'App\\Modules\\Blog'
        public string             $path,           // absolute /var/www/.../app/Modules/Blog
        public ManifestMeta       $meta,
        public ManifestState      $state,
        public FeatureSchema      $features,
        public FeatureValues      $values,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->state->enabled;
    }
}
```

### ModuleManifestRepository

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\Module;

interface ModuleManifestRepositoryInterface
{
    public function load(string $modulePath): Module;

    public function save(Module $module): void;

    public function exists(string $modulePath): bool;

    public function updateState(Module $module, ManifestState $state): Module;

    public function updateFeatureValues(Module $module, FeatureValues $values): Module;
}
```

Реализация:
- Read: `file_get_contents` + `json_decode` + `ManifestValidator::validate()` (JSON Schema).
- Write: через `AtomicJsonWriter`:
  1. `flock` на файл-маркер.
  2. Запись в `module.json.tmp`.
  3. `rename` (атомарно на POSIX).
  4. Снятие лока.

### ModuleRegistry

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\Module;

interface ModuleRegistryInterface
{
    /** @return list<Module> */
    public function all(): array;

    /** @return list<Module> */
    public function enabled(): array;

    public function find(string $name): ?Module;

    public function has(string $name): bool;

    /** @return list<Module> sorted by dependency topology */
    public function loadOrder(): array;
}
```

Реализация `ModuleRegistry`:
- Singleton в контейнере, прогревается на boot worker'а.
- Если есть кеш `bootstrap/cache/modules.php` — читает его (один `require`).
- Иначе: проходит `paths.directories`, в каждой ищет подпапки с `module.json`, читает манифесты, резолвит namespaces через `NamespaceResolverInterface`, строит `Module[]`, применяет `TopologicalSorter`.
- Никакого mutable-state кроме private `?array $cache = null` — заполняется один раз при первом обращении.

### FeatureRepository — runtime-чтение фичетоглов

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface FeatureRepositoryInterface
{
    public function get(string $module, string $key): mixed;

    public function bool(string $module, string $key): bool;

    public function int(string $module, string $key): int;

    public function string(string $module, string $key): string;
}
```

`FeatureRepository` биндится как **scoped** (`$this->app->scoped()`):
- Под Octane Laravel сбрасывает scoped-биндинги между запросами — гарантия отсутствия leak.
- Под `php-fpm` каждый запрос = новый процесс, scoped = singleton, поведение идентично.
- Внутри request читает `module.json` максимум один раз; если в течение request обращение повторное — отдаёт из in-memory кеша request'а.
- Атомарная запись через `ModuleManifestRepository` гарантирует, что `FeatureRepository` следующего request'а увидит новое значение.

## 4. Манифест `module.json`

```json
{
  "$schema": "https://schemas.dimitrienkov0.dev/laravel-modules/manifest-v2.json",
  "meta": {
    "name":         "blog",
    "display_name": "Blog",
    "description":  "Корпоративный блог с комментариями и модерацией",
    "version":      "1.0.0",
    "author":       "Acme Studio",
    "license":      "proprietary",
    "dependencies": ["users", "media"]
  },
  "state": {
    "enabled":      true,
    "installed_at": "2026-05-23T14:12:00+00:00",
    "updated_at":   "2026-05-23T14:12:00+00:00"
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type":        "bool",
        "default":     true,
        "label":       "Включить комментарии",
        "description": "Когда включено, пользователи могут оставлять комментарии",
        "group":       "Фичи"
      },
      "max_posts_per_page": {
        "type":    "int",
        "default": 20,
        "min":     1,
        "max":     100,
        "label":   "Постов на странице",
        "group":   "Отображение"
      },
      "moderation_mode": {
        "type":    "enum",
        "default": "auto",
        "options": ["auto", "manual", "off"],
        "label":   "Модерация",
        "group":   "Модерация"
      }
    },
    "values": {
      "enable_comments":    false,
      "max_posts_per_page": 50
    }
  }
}
```

**Контракт записи:**
- `meta`, `settings.schema` — read-only после установки модуля; меняются только через `modules:update`.
- `state`, `settings.values` — mutable через `ModuleManifestRepository`.
- `ManifestValidator` валидирует тип/min/max/options для `values` против `schema` при каждой записи.
- Никакой секции `autoload` — наличие папок/файлов в модуле и есть конфигурация.

## 5. Bootstrap pipeline

`ModuleLoaderServiceProvider::boot()` делает один проход:

```php
<?php

declare(strict_types=1);

public function boot(ModuleRegistryInterface $registry): void
{
    $this->publishes([
        __DIR__ . '/../../config/modules.php' => config_path('modules.php'),
    ], 'modules-config');

    if ($this->app->runningInConsole()) {
        $this->commands($this->moduleCommands());
        $this->optimizes('modules:optimize', 'modules:optimize-clear');
    }

    $modules = $registry->loadOrder();
    $loaders = $this->resolveLoaders();         // отсортированы по priority()

    foreach ($loaders as $loader) {
        foreach ($modules as $module) {
            if (! $module->isEnabled()) {
                continue;
            }
            $loader->load($module);
        }
    }
}

public function register(): void
{
    $this->mergeConfigFrom(__DIR__ . '/../../config/modules.php', 'modules');

    $this->app->singleton(ModuleLayout::class);
    $this->app->singleton(ModuleRegistryInterface::class, ModuleRegistry::class);
    $this->app->singleton(ModuleManifestRepositoryInterface::class, ModuleManifestRepository::class);
    $this->app->scoped(FeatureRepositoryInterface::class, FeatureRepository::class);

    $this->app->afterResolving(CoreContract::class, function (): void {
        $this->app->make(MoonShineLoader::class)->register();
    });
}
```

Релевантность лоадера модулю определяется внутри `load()` ранним return'ом при отсутствии файлов — никаких проверок по манифесту.

**Порядок приоритетов лоадеров (`priority()`):**

| Loader | priority |
|--------|----------|
| ConfigLoader | 10 |
| ServiceProviderLoader | 20 |
| MigrationLoader | 30 |
| FactoryLoader | 31 |
| LangLoader | 40 |
| ViewLoader | 41 |
| BladeComponentLoader | 42 |
| RouteLoader | 50 |
| BroadcastLoader | 51 |
| MiddlewareLoader | 52 |
| CommandLoader | 60 |
| ConsoleRouteLoader | 61 |
| EventLoader | 70 |
| ObserverLoader | 71 |
| PolicyLoader | 72 |
| MoonShineLoader | 90 |

## 6. Примеры реализации ключевых лоадеров

### RouteLoader

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;

final readonly class RouteLoader implements LoaderInterface
{
    public function __construct(
        private Application  $app,
        private Router       $router,
        private Repository   $config,
        private Filesystem   $files,
        private ModuleLayout $layout,
    ) {
    }

    public function priority(): int
    {
        return 50;
    }

    public function load(Module $module): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routesDir = $this->layout->routesDir($module);
        if (! $this->files->isDirectory($routesDir)) {
            return;
        }

        /** @var array<string, array{prefix?: string, middleware?: list<string>}> $routeTypes */
        $routeTypes = $this->config->get('modules.routing.types', []);

        foreach ($routeTypes as $type => $cfg) {
            // Routes/api/v1.php, Routes/api/v2.php → prefixed api/v1, api/v2
            foreach ($this->files->glob("{$routesDir}/{$type}/*.php") ?: [] as $file) {
                $version = pathinfo($file, PATHINFO_FILENAME);
                $this->registerGroup($cfg, $version, $file);
            }

            // Routes/api.php — flat
            $flat = "{$routesDir}/{$type}.php";
            if ($this->files->exists($flat)) {
                $this->registerGroup($cfg, null, $flat);
            }
        }
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>} $cfg
     */
    private function registerGroup(array $cfg, ?string $version, string $file): void
    {
        $prefix = trim(($cfg['prefix'] ?? '') . '/' . ($version ?? ''), '/');

        $this->router
            ->prefix($prefix)
            ->middleware($cfg['middleware'] ?? [])
            ->group($file);
    }
}
```

### ConsoleRouteLoader

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Foundation\Application;

final readonly class ConsoleRouteLoader implements LoaderInterface
{
    public function __construct(
        private Application  $app,
        private ModuleLayout $layout,
    ) {
    }

    public function priority(): int
    {
        return 61;
    }

    public function load(Module $module): void
    {
        $file = $this->layout->consoleRoutesFile($module);
        if (! is_file($file)) {
            return;
        }

        $this->app->booted(static function () use ($file): void {
            require $file;
        });
    }
}
```

Файл модуля `Routes/console.php` (Laravel 11+ стиль):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('blog:greet', function (): void {
    $this->info('hi from blog');
})->purpose('Greet from blog module');

Schedule::command('blog:cleanup-drafts')->daily();
```

### MoonShineLoader (родной механизм)

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use MoonShine\Contracts\Core\CoreContract;

final readonly class MoonShineLoader
{
    public function __construct(
        private CoreContract            $core,
        private ModuleRegistryInterface $registry,
    ) {
    }

    public function register(): void
    {
        foreach ($this->registry->enabled() as $module) {
            $this->core->autoload($module->namespace);
        }
    }
}
```

### FeatureRepository

```php
<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;

final class FeatureRepository implements FeatureRepositoryInterface
{
    /** @var array<string, FeatureValues> */
    private array $cache = [];

    public function __construct(
        private readonly ModuleRegistryInterface           $registry,
        private readonly ModuleManifestRepositoryInterface $manifests,
    ) {
    }

    public function get(string $module, string $key): mixed
    {
        return $this->values($module)->get($key);
    }

    public function bool(string $module, string $key): bool
    {
        return (bool) $this->get($module, $key);
    }

    public function int(string $module, string $key): int
    {
        return (int) $this->get($module, $key);
    }

    public function string(string $module, string $key): string
    {
        return (string) $this->get($module, $key);
    }

    private function values(string $module): FeatureValues
    {
        if (isset($this->cache[$module])) {
            return $this->cache[$module];
        }

        $instance = $this->registry->find($module)
            ?? throw new ModuleNotFoundException($module);

        // Re-read manifest from disk to pick up admin-UI changes.
        $fresh = $this->manifests->load($instance->path);

        return $this->cache[$module] = $fresh->values;
    }
}
```

`scoped`-биндинг гарантирует, что инстанс `$cache` живёт ровно один request — никаких stale-данных между запросами под Octane.

## 7. Структура модуля (скелет `modules:make`)

```
Modules/Blog/
├── Application/
│   ├── UseCases/PublishPostUseCase.php
│   ├── Actions/CreatePostAction.php
│   └── Queries/ListPostsQuery.php
├── Domain/
│   ├── Models/Post.php
│   ├── DTOs/PostData.php
│   ├── Enums/PostStatus.php
│   ├── Events/PostPublished.php
│   ├── Observers/PostObserver.php
│   └── Policies/PostPolicy.php
├── Infrastructure/
│   └── Repositories/EloquentPostRepository.php
├── Http/
│   ├── Controllers/PostController.php
│   ├── Requests/StorePostRequest.php
│   ├── Resources/PostResource.php
│   └── Middleware/
├── Console/
│   └── Commands/CleanupDraftsCommand.php
├── Database/
│   ├── Migrations/2026_05_23_120000_create_posts_table.php
│   ├── Factories/PostFactory.php
│   └── Seeders/PostSeeder.php
├── Routes/
│   ├── api/v1.php
│   ├── web.php
│   ├── inertia.php
│   ├── console.php
│   └── channels.php
├── Config/blog.php
├── Lang/{en,ru}/blog.php
├── Resources/views/posts/index.blade.php
├── View/Components/PostCard.php
├── MoonShine/
│   ├── Resources/PostResource.php
│   └── Pages/BlogDashboard.php
├── Providers/BlogServiceProvider.php
└── module.json
```

## 8. Namespace-резолюция

`ComposerNamespaceResolver` читает `composer.json` хост-приложения, строит таблицу `path-prefix → namespace`:

```php
// composer.json хоста:
{
  "autoload": {
    "psr-4": {
      "App\\":     "app/",
      "Modules\\": "modules/"
    }
  }
}

// Резолвер:
$resolver->resolve('app/Modules/Blog')   // → 'App\\Modules\\Blog'
$resolver->resolve('modules/Shop')       // → 'Modules\\Shop'
```

Если PSR-4 не покрывает путь — исключение `InvalidManifestException`. Никакого хардкода `App\\`.

## 9. MoonShine UI

Когда MoonShine разрешён в контейнере:

**`ModulesResource`** (CRUD по списку модулей):
- Список: имя, версия, описание, статус (toggle), действия (`Установить`, `Обновить`, `Удалить`).
- Источник данных — `ModuleRegistry::all()` (in-memory list, не Eloquent).
- Custom field-resolver — кастомная коллекция в `IndexPage` без модели Eloquent.

**`ModuleSettingsPage`** (детальная форма):
- Принимает `$moduleName` из роута.
- Динамически строит форму по `Module::$features->schema`:
  - `bool` → `Switcher`.
  - `int` → `Number` с `min`/`max`.
  - `enum` → `Select` с `options`.
  - `string` → `Text`.
- При сохранении: собирает `FeatureValues` VO и вызывает `ManifestRepository::updateFeatureValues($module, $values)`.

В коде приложения чтение фичетогла — через `FeatureRepositoryInterface`:

```php
public function __construct(
    private FeatureRepositoryInterface $features,
) {}

public function index(): View
{
    $perPage = $this->features->int('blog', 'max_posts_per_page');
    // ...
}
```

## 10. Жизненный цикл модуля

### `modules:install <source>`

```
1. ZipExtractor распаковывает в tmp.
2. ManifestValidator проверяет module.json (наличие, JSON Schema, type/min/max).
3. ModuleRegistry проверяет dependencies — все ли установлены и enabled.
4. Файлы переносятся в target dir (modules/<Name>).
5. composer dump-autoload (если есть свой psr-4, иначе пропускаем — namespace уже в корневом composer.json).
6. InstallHook::handle($module) — опциональный класс модуля Application\InstallHook (php artisan migrate выполняется внутри).
7. ManifestRepository::updateState(state: enabled=true, installed_at=now()).
8. Cache::clear() для modules:optimize.
```

### `modules:update <name> <source>`

```
1. ManifestRepository::load(current) — снять backup settings.values.
2. ZipExtractor распаковывает новый source.
3. ManifestValidator проверяет новый module.json.
4. Diff dependencies — если требования усилились и условие не выполнено → ошибка с откатом.
5. Файлы переносятся (старая папка → backup/, новая → target/).
6. ManifestRepository::save с merged settings.values (старые значения, кроме исчезнувших из схемы).
7. UpdateHook::handle($oldModule, $newModule) — миграции, очистка.
8. Если ошибка — rollback из backup/.
```

### `modules:remove <name>`

```
1. ModuleRegistry проверяет — нет ли других модулей с этим в dependencies.
2. RemoveHook::handle($module) — опционально rollback миграций.
3. Папка перемещается в backup/.
4. Cache::clear().
```

## 11. Кеширование

`modules:optimize` создаёт `bootstrap/cache/modules.php`:

```php
<?php

declare(strict_types=1);

return [
    'generated_at' => 1700000000,
    'modules' => [
        [
            'name'        => 'blog',
            'namespace'   => 'App\\Modules\\Blog',
            'path'        => '/var/www/app/Modules/Blog',
            'meta'        => [...],
            'state'       => ['enabled' => true, ...],
            'features'    => [...],
            'values'      => [...],
        ],
        ...
    ],
    'load_order' => ['users', 'media', 'blog'],
];
```

`ModuleRegistry::all()` при наличии кеша делает один `require` и десериализует в `Module[]`. Никаких FS-операций на каждый запрос.

`FeatureRepository` **намеренно не использует** этот кеш: он читает `module.json` напрямую, чтобы MoonShine-изменения подхватывались следующим request'ом без `modules:optimize-clear`.

MoonShine'овский `OptimizerCollection` имеет собственный кеш — он не дублируется нашим, просто прогревается отдельной командой `moonshine:optimize` (если установлен).

## 12. Octane-совместимость

Целевые рантаймы: Swoole, RoadRunner, FrankenPHP. Контракты безопасности:

**Запрещено в `src/`:**
- `static` properties с mutable-state (только `final` константы).
- Глобальное состояние через `app()->instance(...)` без перебиндинга.
- Фасады в любых классах кроме `Console/Commands/` и `MoonShine/`.
- Сохранение `Application`/`Request`/`Router` в singleton-сервисах в виде property после первого вызова.

**Биндинги в `ModuleLoaderServiceProvider`:**

| Сервис | Scope | Причина |
|--------|-------|---------|
| `ModuleLayout` | singleton | immutable, нет state |
| `ModuleRegistryInterface` | singleton | прогрев на boot worker'а, immutable после первого скана |
| `ModuleManifestRepositoryInterface` | singleton | stateless, только I/O |
| `ManifestValidatorInterface` | singleton | stateless |
| `NamespaceResolverInterface` | singleton | кеш PSR-4 — frozen один раз |
| `FeatureRepositoryInterface` | scoped | per-request cache, сбрасывается между запросами |
| `LoaderInterface[]` | singleton | stateless, только зависимости через DI |

**Архитектурный тест (Pest):**

```php
arch('no static properties in src')
    ->expect('DimitrienkoV\\LaravelModules')
    ->not->toHaveStaticProperties();

arch('no facades in src except commands and moonshine')
    ->expect('DimitrienkoV\\LaravelModules')
    ->not->toUse(['Illuminate\\Support\\Facades'])
    ->ignoring([
        'DimitrienkoV\\LaravelModules\\Console\\Commands',
        'DimitrienkoV\\LaravelModules\\MoonShine',
    ]);

arch('feature repository is scoped, not singleton')
    ->expect('DimitrienkoV\\LaravelModules\\Manifest\\FeatureRepository')
    ->not->toBeReadonly();
```

## 13. Тестирование

- **Pest 3 + `pestphp/pest-plugin-arch`** — `tests/Architecture/`:
  - `final readonly` для всех VO в `Manifest/`.
  - `final` для всех Loader'ов и сервисов.
  - все классы в `Loaders/` реализуют `LoaderInterface`.
  - запрет фасадов вне разрешённых директорий.
  - запрет `static` properties.
  - запрет `Illuminate\Database\Eloquent\Model` в `Application/UseCases/*`.
- **PHPUnit 11/12** — `tests/Unit/`:
  - юнит-тесты на каждый Loader через `Module`-фабрику.
  - `ModuleManifestRepository` — atomic write, schema validation, file locking.
  - `TopologicalSorter` — циклы, плоские графы, изолированные модули.
  - `ComposerNamespaceResolver`, `AtomicJsonWriter`, `ModuleLayout`.
- **PHPUnit + Orchestra Testbench** — `tests/Feature/`:
  - команды `modules:install`, `modules:update`, `modules:remove`, `modules:enable`, `modules:disable`, `modules:list`, `modules:optimize`.
  - `FeatureRepository` — повторное чтение в рамках request'а, сброс между request'ами (эмулируется через container scoped-binding).
  - тесты MoonShine пропускаются, если MoonShine не установлен.

Команды (`composer scripts`):

```json
{
  "test:arch":    "pest --filter=Architecture",
  "test:unit":    "phpunit --testsuite=Unit",
  "test:feature": "phpunit --testsuite=Feature",
  "test":         "@test:arch && @test:unit && @test:feature",
  "phpstan":      "phpstan analyse --memory-limit=512M",
  "rector:dry":   "rector process --dry-run",
  "format":       "php-cs-fixer fix --allow-risky=yes"
}
```

CI запускает `phpstan`, `rector:dry`, `format --dry-run`, `test` — все четыре обязательны.

## 14. Правила зависимостей между слоями

```
Console ──┐                              Domain не зависит ни от чего из Laravel
Http   ──┼─→ Application ─→ Domain ←─── Infrastructure
MoonShine ┘                                    │
                                               ↓
                                      Eloquent, HTTP-clients, …
```

- `Domain` зависит только от чистого PHP и Laravel-contracts (Collection, Carbon допустимы).
- `Application` зависит от `Domain` + Laravel-contracts. Здесь UseCase/Action/Query.
- `Infrastructure` зависит от `Domain` (реализует репозитории).
- `Presentation` (Http/Console/MoonShine) — только вызовы UseCase через DI.

PHPStan кастомное правило: запрет импорта `Illuminate\Database\Eloquent\Model` в `Application/UseCases/*`.

## 15. Что выкидываем из 1.x

- `MoonShineLoaderService` с classmap-обходом → заменён родным `$core->autoload($namespace)`.
- `ServiceProviderLoaderService::discoverProviders` с обходом classmap → теперь convention `Providers/*ServiceProvider.php`.
- Дублирующий вызов `ConfigLoaderService::autoload()` в `register()` и `boot()` — фикс архитектурой: только в `boot()` после `ModuleRegistry`.
- Хардкод `App\\` → `ComposerNamespaceResolver`.
- Суффикс `Service` для всего → заменён на `UseCase`/`Action`/`Query`/`Loader`/`Repository`.
- Whitelist-секция `autoload` в `module.json` → pure convention; `key()` на лоадерах → удалён.
- `Console/Schedule.php` (closure-возвращающий файл) → `Routes/console.php` в стиле Laravel 11+.
- Хардкоженые пути типа `$module->path . '/Routes'` в каждом лоадере → `ModuleLayout`.
- `array $values` в публичных API → `FeatureValues` VO.
