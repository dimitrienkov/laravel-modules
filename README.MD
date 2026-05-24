# Laravel Modules

> Manifest-driven runtime для модулей в Laravel 12/13.

`dimitrienkov0/laravel-modules` помогает Laravel-приложению находить модули в настроенных директориях, валидировать `module.json`, сортировать модули по зависимостям и загружать enabled-модули через предсказуемый loader-pipeline.

Текущий срез v2.0 core покрывает runtime loading, feature values, production registry cache и optional-интеграции MoonShine/Inertia. Lifecycle-команды, генераторы модулей, дополнительные лоадеры и полноценный admin UI остаются roadmap, а не текущим runtime.

## Quick Start

```bash
composer require dimitrienkov0/laravel-modules
php artisan vendor:publish --tag=modules-config
php artisan modules:optimize
```

## Требования

| Компонент | Версия |
|-----------|--------|
| PHP | 8.3+ |
| Laravel | 12 / 13 |
| Optional admin bridge | MoonShine 4 |
| Optional routes bridge | Inertia 2 |

## Возможности

- **Manifest-first modules**: metadata, enabled state, feature schema и feature values живут в `module.json`.
- **Dependency-aware registry**: модули сортируются по `meta.dependencies` через Composer SemVer constraints.
- **Loader pipeline**: config, service providers, migrations, factories и routes загружаются в deterministic priority order.
- **Runtime feature API**: `FeatureRepositoryInterface` читает актуальные `settings.values` из `module.json`.
- **Production cache**: `modules:optimize` пишет `bootstrap/cache/modules.php` для быстрого discovery.
- **Optional bridges**: MoonShine и Inertia активируются только при наличии соответствующих пакетов.

## Пример модуля

```text
app/Modules/Blog/
|-- Config/
|   `-- blog.php
|-- Database/
|   |-- Factories/
|   `-- Migrations/
|-- Providers/
|   `-- BlogServiceProvider.php
|-- Routes/
|   |-- api.php
|   |-- api/
|   |   `-- v1.php
|   |-- web.php
|   `-- inertia.php
`-- module.json
```

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
  "state": {
    "enabled": true
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true
      }
    },
    "values": {
      "enable_comments": false
    }
  }
}
```

## Feature Usage

```php
<?php

declare(strict_types=1);

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;

final readonly class PostController
{
    public function __construct(
        private FeatureRepositoryInterface $features,
    ) {
    }

    public function index(): void
    {
        $commentsEnabled = $this->features->bool('blog', 'enable_comments');
    }
}
```

## Документация

| Раздел | Описание |
|--------|----------|
| [Getting Started](docs/getting-started.md) | Установка и первая проверка |
| [Module Structure](docs/module-structure.md) | Поддерживаемые runtime-пути |
| [Manifest](docs/manifest.md) | Контракт `module.json` |
| [Configuration](docs/configuration.md) | Конфиг и routing options |
| [Architecture](docs/architecture.md) | Registry, cache, loaders, bridges |
| [Feature Toggles](docs/feature-toggles.md) | Runtime settings API |
| [CLI](docs/cli.md) | Реализованные Artisan-команды |
| [Contributing](docs/contributing.md) | Quality gates и PR rules |

## License

MIT
