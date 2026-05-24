# Laravel Modules

Manifest-driven module loader and lifecycle toolkit for Laravel.

Each module is a self-contained directory with its own `module.json` manifest, feature toggles, migrations, routes, config, views, and optional dependencies on other modules. The package loads modules through a single-pass loader pipeline and manages the full lifecycle via Artisan commands.

## Requirements

- PHP 8.3+
- Laravel 12 / 13
- Octane-compatible (Swoole, RoadRunner, FrankenPHP)

## Installation

```bash
composer require dimitrienkov0/laravel-modules
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=modules-config
```

This creates `config/modules.php` where you configure module root directories and routing types.

## Module Structure

Generate a new module scaffold:

```bash
php artisan make:module Blog
```

This creates:

```
app/Modules/Blog/
+-- Application/
|   +-- UseCases/
|   +-- Actions/
|   +-- Queries/
+-- Domain/
|   +-- Models/
|   +-- DTOs/
|   +-- Enums/
|   +-- Events/
|   +-- Observers/
|   +-- Policies/
+-- Infrastructure/
|   +-- Repositories/
+-- Http/
|   +-- Controllers/
|   +-- Requests/
|   +-- Resources/
|   +-- Middleware/
+-- Console/
|   +-- Commands/
+-- Database/
|   +-- Migrations/
|   +-- Factories/
|   +-- Seeders/
+-- Routes/
|   +-- api/
|   +-- web.php
|   +-- inertia.php
|   +-- console.php
|   +-- channels.php
+-- Config/
+-- Lang/
+-- Resources/views/
+-- View/Components/
+-- Providers/
+-- MoonShine/
+-- module.json
```

## Manifest (`module.json`)

Every module requires a `module.json` in its root:

```json
{
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "description": "Corporate blog with comments",
    "version": "1.0.0",
    "author": "Acme Studio",
    "license": "proprietary",
    "dependencies": {
      "users": "^1.5",
      "media": ">=1.4 <3.0"
    }
  },
  "state": {
    "enabled": true,
    "installed_at": "2026-05-23T14:12:00+00:00",
    "updated_at": "2026-05-23T14:12:00+00:00"
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true,
        "label": "Enable comments"
      },
      "max_posts_per_page": {
        "type": "int",
        "default": 20,
        "min": 1,
        "max": 100
      },
      "moderation_mode": {
        "type": "enum",
        "default": "auto",
        "options": ["auto", "manual", "off"]
      }
    },
    "values": {
      "enable_comments": false,
      "max_posts_per_page": 50
    }
  }
}
```

**Key rules:**
- `meta.dependencies` uses Composer SemVer constraints. Short form `["users"]` normalizes to `{"users": "*"}`.
- `settings.schema` defines feature toggle types (`bool`, `int`, `string`, `enum`). Authors set these; customers only edit `settings.values`.
- `settings.values` stores the customer's overrides. Missing keys fall back to schema defaults at runtime without writing defaults to the file.
- There is no `autoload` section. What gets loaded is determined by convention (presence of directories/files in the module).

## Loader Pipeline

On boot, `ModuleLoaderServiceProvider` runs a single pass over all enabled modules in dependency-sorted order. Each loader checks if it has work to do (via `ModuleLayout` directory/file checks) and skips silently if not:

| Loader | What it loads | Priority |
|--------|--------------|----------|
| `ConfigLoader` | `Config/*.php` | 10 |
| `ServiceProviderLoader` | `Providers/*ServiceProvider.php` | 20 |
| `MigrationLoader` | `Database/Migrations/` | 30 |
| `FactoryLoader` | `Database/Factories/` | 31 |
| `RouteLoader` | `Routes/api/{version}.php`, `Routes/web.php`, etc. | 50 |
| `MoonShineLoader` | MoonShine autoload for module namespace | 90 |

Loaders are sorted by `priority()` (lower loads first). Custom loaders can be registered by implementing `LoaderInterface`:

```php
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\Module;

final readonly class MyCustomLoader implements LoaderInterface
{
    public function load(Module $module): void
    {
        // your logic
    }

    public function priority(): int
    {
        return 80;
    }
}
```

## Feature Toggles

Read feature toggle values at runtime via `FeatureRepositoryInterface`:

```php
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;

final readonly class PostController
{
    public function __construct(
        private FeatureRepositoryInterface $features,
    ) {}

    public function index()
    {
        $perPage = $this->features->int('blog', 'max_posts_per_page');
        $commentsEnabled = $this->features->bool('blog', 'enable_comments');
        // ...
    }
}
```

Available methods: `get(module, key): mixed`, `bool(module, key)`, `int(module, key)`, `string(module, key)`.

`FeatureRepository` reads `module.json` directly (not the optimize cache), so admin-UI changes take effect on the next request without running `modules:optimize-clear`.

## Artisan Commands

| Command | Description |
|---------|-------------|
| `make:module <Name>` | Generate a full module scaffold |
| `modules:optimize` | Cache module discovery to `bootstrap/cache/modules.php` |
| `modules:optimize-clear` | Remove the module discovery cache |

Standard Laravel generators support `--module` to target a specific module:

```bash
php artisan make:model Post --module=Blog -mfs
php artisan make:controller PostController --module=Blog
php artisan make:use-case PublishPost --module=Blog
php artisan make:action CreatePost --module=Blog
php artisan make:query ListPosts --module=Blog
php artisan make:dto PostData --module=Blog
```

## Production Cache

Run `php artisan modules:optimize` to cache the full module registry into `bootstrap/cache/modules.php`. When the cache exists, `ModuleRegistry` loads it with a single `require` instead of scanning the filesystem.

Clear it with `php artisan modules:optimize-clear`.

Laravel's built-in `optimize` / `optimize:clear` commands automatically include the module cache through the optimizer hooks.

## Optional Integrations

### MoonShine

MoonShine 4 integration activates automatically when `moonshine/core` is installed. The package does not require MoonShine at runtime; add it via:

```bash
composer require moonshine/core moonshine/contracts
```

The `MoonShineLoader` calls MoonShine's native `$core->autoload()` for each enabled module's namespace.

### Inertia

If `inertiajs/inertia-laravel` is installed, `RouteLoader` loads `Routes/inertia.php` files. Without Inertia, those files are silently skipped.

## Configuration

After publishing, edit `config/modules.php`:

```php
return [
    'paths' => [
        'directories' => [
            'app/Modules',       // scan these directories for module.json
            'app/Integrations',
        ],
    ],
    'routing' => [
        'types' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => ['api'],
            ],
            'web' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
        ],
    ],
];
```

Module namespaces are resolved from the host application's `composer.json` PSR-4 autoload configuration. No hardcoded `App\\` prefix.

## License

MIT
