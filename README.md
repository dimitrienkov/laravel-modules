# Laravel Modules

> Manifest-driven runtime для модулей в Laravel 12/13.

`dimitrienkov0/laravel-modules` помогает Laravel-приложению находить модули в настроенных директориях, валидировать `module.json`, сортировать модули по зависимостям и загружать enabled-модули через предсказуемый loader pipeline.

Текущий v2.0 core покрывает runtime loading, feature values, production registry cache, lifecycle-команды (`make:module`, `modules:install`, `modules:update`, `modules:remove`, `modules:enable`, `modules:disable`, `modules:list`, `modules:optimize`, `modules:optimize-clear`) и module-aware генераторы: native `make:* --module` для 22 артефактов плюс архитектурные `make:use-case/action/query/dto/vo` (см. [docs/cli.md](docs/cli.md)). Опциональный MoonShine admin-UI управляет модулями из админки ([docs/moonshine.md](docs/moonshine.md)); установка/обновление через zip-upload в UI остаётся roadmap.

## Quick Start

```bash
composer require dimitrienkov0/laravel-modules
php artisan vendor:publish --tag=modules-config
php artisan make:module blog
```

Проверка production cache:

```bash
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

- **Manifest-first modules**: immutable metadata и feature schema живут в `module.json`; mutable state и feature values - в `state.json`.
- **Dependency-aware registry**: `meta.dependencies` сортируются и проверяются через Composer SemVer constraints.
- **Loader pipeline**: 15 convention-based loaders загружают config, providers, migrations, routes, commands, policies, middleware и другие runtime paths.
- **Runtime feature API**: `FeatureRepositoryInterface` читает актуальные `settings.values` из `state.json`.
- **Production cache**: `modules:optimize` кеширует discovery в `bootstrap/cache/modules.php`, но не кеширует state и values.
- **Lifecycle toolkit**: scaffold, install, update, remove, enable и disable работают через UseCase-классы с backup/rollback boundaries.
- **Opt-in диагностическое логирование**: off by default; включается через `MODULES_LOGGING=true` и пишет structured discovery/cache/pipeline/lifecycle события на выбранный канал хоста для field-diagnostics ([docs/logging.md](docs/logging.md)).
- **Optional bridges**: MoonShine и Inertia активируются только при наличии соответствующих пакетов.

## Минимальный модуль

```bash
php artisan make:module blog
```

Команда создаёт структуру модуля, `module.json`, ServiceProvider stub и приватный `state.json`.

Минимальный `module.json` после scaffold:

```json
{
  "schema_version": 1,
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "kind": "module",
    "group": "content",
    "version": "1.0.0"
  },
  "settings": {
    "schema": {}
  }
}
```

`schema_version`, `meta.name`, `meta.kind` и `meta.version` — обязательные; `meta.group` — необязательное (kebab-case группа для отображения в `modules:list`). Полный контракт — в [docs/manifest.md](docs/manifest.md).

`storage/app/private/modules/blog/state.json`:

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00",
  "updated_at": "2026-05-23T14:12:00+00:00",
  "source": {
    "kind": "local",
    "installed_version": "1.0.0"
  },
  "settings": {
    "values": {}
  }
}
```

## Feature Usage

Добавьте feature schema и values, как описано в [docs/feature-toggles.md](docs/feature-toggles.md), затем читайте их через scoped repository:

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
        $commentsEnabled = $this->features->getBool('blog', 'enable_comments');
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
| [MoonShine](docs/moonshine.md) | Опциональный admin-UI управления модулями |
| [Architecture](docs/architecture.md) | Registry, cache, loaders, lifecycle |
| [Loaders](docs/loaders.md) | Справочник лоадеров и написание своего |
| [Feature Toggles](docs/feature-toggles.md) | Runtime settings API |
| [Logging](docs/logging.md) | Opt-in диагностический слой и каталог событий |
| [Octane](docs/octane.md) | Octane worker contract и reload-операционка |
| [CLI](docs/cli.md) | Реализованные Artisan-команды |
| [Contributing](docs/contributing.md) | Quality gates и PR rules |

## License

MIT
