[Back to README](../README.MD) · [Module Structure →](module-structure.md)

# Getting Started

Этот раздел устанавливает пакет в Laravel host-приложение и проверяет реализованный v2.0 core runtime.

## Prerequisites

| Инструмент | Требование |
|------------|------------|
| PHP | 8.3+ |
| Laravel | 12 / 13 |
| Composer | Доступен в host-приложении |

Optional packages:

- `moonshine/core` и `moonshine/contracts` для MoonShine 4 autoload integration.
- `inertiajs/inertia-laravel` для загрузки модульных `Routes/inertia.php`.

## Установка

Установите пакет:

```bash
composer require dimitrienkov0/laravel-modules
```

Опубликуйте config:

```bash
php artisan vendor:publish --tag=modules-config
```

Файл будет опубликован в `config/modules.php`. В нём задаются root-директории модулей и route type attributes.

## Настройка путей

По умолчанию пакет сканирует эти директории host-приложения:

```php
<?php

return [
    'paths' => [
        'directories' => [
            'app/Modules',
            'app/Integrations',
            'app/Subsystems',
        ],
    ],
];
```

Каждая дочерняя директория считается модулем только при наличии `module.json`.

## Минимальный модуль

Текущий пакет не реализует `make:module`. Создайте директорию модуля вручную или через собственный scaffolding host-приложения.

```text
app/Modules/Blog/
|-- Routes/
|   `-- web.php
`-- module.json
```

Минимальный `module.json`:

```json
{
  "meta": {
    "name": "blog",
    "version": "1.0.0"
  },
  "state": {
    "enabled": true
  },
  "settings": {
    "schema": {},
    "values": {}
  }
}
```

## Проверка discovery cache

Соберите production registry cache:

```bash
php artisan modules:optimize
```

Ожидаемый результат:

```text
Caching module registry...
Module registry cached successfully.
```

Очистите cache:

```bash
php artisan modules:optimize-clear
```

Пакет также подключается к Laravel optimizer hooks. `php artisan optimize` включает `modules:optimize`, а `php artisan optimize:clear` включает `modules:optimize-clear`.

## Первая runtime-проверка

При boot Laravel-приложения `ModuleLoaderServiceProvider` создаёт `ModuleLoaderPipeline` и применяет default loaders к enabled-модулям в dependency order.

Текущий runtime загружает только поддерживаемые пути:

- `Config/*.php`
- `Providers/*ServiceProvider.php`
- `Database/Migrations/`
- `Database/Factories/`
- `Routes/api.php`, `Routes/api/*.php`, `Routes/web.php`, `Routes/inertia.php`

## See Also

- [Module Structure](module-structure.md) - поддерживаемые и roadmap-only пути.
- [Manifest](manifest.md) - обязательные поля `module.json`.
- [CLI](cli.md) - реализованные Artisan-команды.
