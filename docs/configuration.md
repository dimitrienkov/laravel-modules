[← Manifest](manifest.md) · [Back to README](../README.md) · [Architecture →](architecture.md)

# Configuration

Пакет публикует `config/modules.php`. Config задаёт root-директории модулей и route loading attributes.

## Публикация config

```bash
php artisan vendor:publish --tag=modules-config
```

## Default config

```php
<?php

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
    'logging' => [
        'enabled' => env('MODULES_LOGGING', false),
        'channel' => env('MODULES_LOG_CHANNEL'),
        'level' => env('MODULES_LOG_LEVEL', 'debug'),
        'events' => [
            'discovery' => true,
            'cache' => true,
            'pipeline' => true,
            'lifecycle' => true,
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
            'inertia' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            // Versioned API is just another config-driven type. Uncomment to load
            // `Routes/api_v1.php` under the `api/v1` prefix with a dedicated
            // `api_v1` middleware group (declare that group in the host app's
            // bootstrap/app.php).
            // 'api_v1' => [
            //     'prefix' => 'api/v1',
            //     'middleware' => ['api_v1'],
            // ],
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

`paths.directories` должен быть непустым списком non-empty строк. Все ключи `modules.paths.*` читаются и валидируются один раз при boot воркера единым владельцем — `ModulePathsConfig`; пустой список, не-строковый или пустой элемент — ошибка конфигурации (`InvalidConfigurationException`) с указанием индекса проблемного элемента.

Lifecycle-команды (`make:module`, `modules:install`) принимают `--directory` для явного указания target root. Аргумент должен быть одним из configured roots.

## Backup path

`paths.backup` задаёт директорию для backup при `modules:update` и `modules:remove`. По умолчанию `storage_path('app/module-backups')`. После merge package config ключ обязателен и должен быть non-empty string path. Относительный путь резолвится в абсолютный относительно base path (через `PathNormalizer`, как и `paths.state`). Формат backup: `<name>-<Ymd-His>-<random-hex>`.

## State path

`paths.state` задаёт корневую директорию для mutable state файлов модулей. По умолчанию `storage_path('app/private/modules')`. После merge package config ключ обязателен и должен быть non-empty string path. Каждый модуль получает поддиректорию `{name}/state.json`:

```
storage/app/private/modules/
├── blog/
│   └── state.json
└── users/
    └── state.json
```

`state.json` хранит `enabled`, `installed_at`, `updated_at`, опциональную секцию `source` (provenance: `kind`, `installed_version` и `checksum` для `zip`-origin) и `settings.values`. Полная структура `source` и пример `state.json` — в [docs/manifest.md](manifest.md). `ModuleStatePaths` резолвит абсолютный путь к каждому `{name}/state.json` из `paths.state` (через `PathNormalizer`); containment-проверки state root относительно module directories в runtime нет.

## Module groups

`modules.groups` задаёт маппинг кода группы (`meta.group`) на human-readable label в формате `'code' => 'Human Label'`:

```php
'groups' => [
    'content' => 'Content Management',
    'e-commerce' => 'E-Commerce',
],
```

Label используется командой `modules:list` в колонке Group и рендерится как `"Human Label (code)"`. Если для кода нет записи — выводится голый код (допустимый fallback). Malformed config — fail-loud: если `modules.groups` не array, либо label для запрашиваемого кода присутствует, но не строка или пустой, `modules:list` падает с `InvalidConfigurationException`. `modules.groups` — единственная точка валидации этого маппинга, поэтому silent fallback на ошибочном label больше не применяется. Фильтр `modules:list --group=<code>` всегда работает по коду, а не по label. Коды также зарезервированы под будущий module UI.

## Logging

`modules.logging` включает opt-in диагностический слой (discovery, cache, loader
pipeline, lifecycle). По умолчанию выключен:

```php
'logging' => [
    'enabled' => env('MODULES_LOGGING', false),
    'channel' => env('MODULES_LOG_CHANNEL'),
    'level' => env('MODULES_LOG_LEVEL', 'debug'),
    'events' => [
        'discovery' => true,
        'cache' => true,
        'pipeline' => true,
        'lifecycle' => true,
    ],
],
```

`enabled` биндит `ModuleLogger` (или `NullModuleDiagnostics` при `false`); `channel`
выбирает лог-канал хоста по имени (`null` → канал по умолчанию); `level` — глобальный
порог уровня; `events` — тумблеры категорий. Полный каталог событий, поведение порога,
сниппет выделенного канала и гарантия whitelist — в [docs/logging.md](logging.md).

## Stubs

```bash
php artisan vendor:publish --tag=modules-stubs
```

Публикует `module-service-provider.stub` и `module.json.stub` в `stubs/modules/`.

## Route types

`RouteLoader` полностью config-driven: он читает `modules.routing.types` и для каждого
объявленного `<type>` загружает файл `Routes/<type>.php` с его attributes. Других
conventions нет — добавить новый route-тип значит добавить ключ в config.

| Config key | Route file | Notes |
|------------|------------|-------|
| `api` | `Routes/api.php` | Использует API route attributes |
| `web` | `Routes/web.php` | Использует web route attributes |
| `inertia` | `Routes/inertia.php` | Skipped, если Inertia не установлена |

Attributes передаются в Laravel router groups после удаления `null` values. Порядок
групп детерминирован и совпадает с порядком ключей в `modules.routing.types`.

## Versioned API routes

Версионирование — это обычный config-driven type, а не отдельная convention.
Объявите профиль (например `api_v1`) с нужным prefix и middleware-группой, и
`RouteLoader` загрузит соответствующий flat-файл `Routes/api_v1.php`.

```php
'routing' => [
    'types' => [
        'api_v1' => [
            'prefix' => 'api/v1',
            'middleware' => ['api_v1'],
        ],
    ],
],
```

```text
Routes/api_v1.php -> api/v1   (middleware: api_v1)
```

Middleware-группу `api_v1` host-приложение объявляет в `bootstrap/app.php`. Для
второй версии добавьте аналогичный тип `api_v2` → `Routes/api_v2.php`.

## Routes cache

Когда Laravel routes cached, `RouteLoader` завершает работу ранним skip-репортом:

```php
if ($this->app->routesAreCached()) {
    return LoadReport::skipped(SkipReason::RoutesCached);
}
```

Используйте обычный Laravel route cache workflow в production. Module route files участвуют в route registration до сборки cache.

## Optional integrations

Установите Inertia только в host-приложениях, которым нужны `Routes/inertia.php`:

```bash
composer require inertiajs/inertia-laravel
```

Лёгкий MoonShine autoload bridge активируется уже при наличии одного `CoreContract`.
Хосту, которому нужен **только** bridge (без полного admin-UI), достаточно
`moonshine/core` + `moonshine/contracts`:

```bash
composer require moonshine/core moonshine/contracts
```

Для полноценного admin-UI (управление модулями из админки) нужен полный стек v4:

```bash
composer require moonshine/moonshine
```

> `moonshine/moonshine` уже `replaces` `moonshine/core` и `moonshine/contracts`, поэтому
> команда из двух пакетов нужна лишь для lightweight-сценария (bridge без CRUD/UI). По
> этой причине `composer.json` пакета в `suggest` указывает только `moonshine/moonshine`.

UI настраивается секцией `modules.moonshine` (`enabled`, `menu`) — детали в
[docs/moonshine.md](moonshine.md).

Обе интеграции optional. Пакет включает их только при наличии нужных classes/interfaces.

## See Also

- [Module Structure](module-structure.md) - route files и поддерживаемые пути.
- [Architecture](architecture.md) - provider boot flow.
- [Logging](logging.md) - диагностический слой и каталог событий.
- [CLI](cli.md) - cache-команды, связанные с изменениями config.
