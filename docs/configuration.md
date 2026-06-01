[← Manifest](manifest.md) · [Back to README](../README.MD) · [Architecture →](architecture.md)

# Configuration

Пакет публикует `config/modules.php`. Config задаёт root-директории модулей и route loading attributes.

## Публикация config

```bash
php artisan vendor:publish --tag=modules-config
```

## Default config

```php
<?php

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    'paths' => [
        'directories' => [
            'app/Modules',
            'app/Integrations',
            'app/Subsystems',
        ],
        'backup' => storage_path('app/module-backups'),
        'state' => storage_path('app/private/modules'),
    ],
    'groups' => [
        // 'content' => 'Content Management',
        // 'e-commerce' => 'E-Commerce',
    ],
    'routing' => [
        'types' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => [SubstituteBindings::class, ConvertEmptyStringsToNull::class, 'api'],
            ],
            'web' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            'inertia' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
        ],
    ],
];
```

## Module directories

`paths.directories` содержит пути относительно base path host-приложения.

```php
'paths' => [
    'directories' => [
        'app/Modules',
        'app/Integrations',
    ],
],
```

`ModuleDirectoryScanner` сканирует direct child directories и оставляет только директории с `module.json`.

Lifecycle-команды (`make:module`, `modules:install`) принимают `--directory` для явного указания target root. Аргумент должен быть одним из configured roots.

## Backup path

`paths.backup` задаёт директорию для backup при `modules:update` и `modules:remove`. По умолчанию `storage_path('app/module-backups')`. Формат backup: `<name>-<Ymd-His>-<random-hex>`.

## State path

`paths.state` задаёт корневую директорию для mutable state файлов модулей. По умолчанию `storage_path('app/private/modules')`. Каждый модуль получает поддиректорию `{name}/state.json`:

```
storage/app/private/modules/
├── blog/
│   └── state.json
└── users/
    └── state.json
```

`state.json` хранит `enabled`, `installed_at`, `updated_at`, опциональную секцию `source` (provenance) и `settings.values`. `ModuleStatePaths` валидирует, что state root не пересекается с module directories.

## Module groups

`modules.groups` задаёт маппинг кода группы (`meta.group`) на human-readable label в формате `'code' => 'Human Label'`:

```php
'groups' => [
    'content' => 'Content Management',
    'e-commerce' => 'E-Commerce',
],
```

Label используется командой `modules:list` в колонке Group и рендерится как `"Human Label (code)"`. Если для кода нет записи — выводится голый код; маппинг лениентен к ошибкам (не-строковые или пустые labels тихо игнорируются с fallback на код, чтобы листинг не падал). Фильтр `modules:list --group=<code>` всегда работает по коду, а не по label. Коды также зарезервированы под будущий module UI.

## Stubs

```bash
php artisan vendor:publish --tag=modules-stubs
```

Публикует `module-service-provider.stub` и `module.json.stub` в `stubs/modules/`.

## Route types

`RouteLoader` читает `modules.routing.types`. Каждый type соответствует файлу `Routes/<type>.php`.

| Config key | Route file | Notes |
|------------|------------|-------|
| `api` | `Routes/api.php` | Использует API route attributes |
| `web` | `Routes/web.php` | Использует web route attributes |
| `inertia` | `Routes/inertia.php` | Skipped, если Inertia не установлена |

Attributes передаются в Laravel router groups после удаления `null` values.

## Versioned API routes

Файлы в `Routes/api/*.php` загружаются как versioned API routes. Имя файла становится suffix после API prefix.

```text
Routes/api/v1.php -> api/v1
Routes/api/v2.php -> api/v2
```

Loader сортирует эти файлы по имени для deterministic route registration.

## Routes cache

Когда Laravel routes cached, `RouteLoader` завершает работу ранним return:

```php
if ($this->app->routesAreCached()) {
    return;
}
```

Используйте обычный Laravel route cache workflow в production. Module route files участвуют в route registration до сборки cache.

## Optional integrations

Установите Inertia только в host-приложениях, которым нужны `Routes/inertia.php`:

```bash
composer require inertiajs/inertia-laravel
```

Установите MoonShine только когда host-приложению нужна MoonShine autoload integration:

```bash
composer require moonshine/core moonshine/contracts
```

Обе интеграции optional. Пакет включает их только при наличии нужных classes/interfaces.

## See Also

- [Module Structure](module-structure.md) - route files и поддерживаемые пути.
- [Architecture](architecture.md) - provider boot flow.
- [CLI](cli.md) - cache-команды, связанные с изменениями config.
