[← Getting Started](getting-started.md) · [Back to README](../README.md) · [Manifest →](manifest.md)

# Module Structure

Модуль - это директория внутри одного из путей `modules.paths.directories`. Пакет обнаруживает модуль только если в директории есть `module.json`.

## Runtime-supported layout

```text
app/Modules/Blog/
|-- Config/
|   `-- blog.php
|-- Console/
|   `-- Commands/
|       `-- PublishPostCommand.php
|-- Database/
|   |-- Factories/
|   `-- Migrations/
|-- Domain/
|   |-- Listeners/
|   |-- Models/
|   |-- Observers/
|   `-- Policies/
|-- Http/
|   `-- Middleware/
|-- Lang/
|-- Providers/
|   `-- BlogServiceProvider.php
|-- Resources/
|   `-- views/
|-- Routes/
|   |-- api.php
|   |-- api_v1.php
|   |-- web.php
|   |-- inertia.php
|   |-- console.php
|   `-- channels.php
|-- View/
|   `-- Components/
`-- module.json
```

Host-приложение может хранить внутри модуля свой application/domain/http код. Пакет интерпретирует только пути, перечисленные ниже.

## Загружаемые пути

| Путь | Loader | Priority | Runtime behavior |
|------|--------|----------|------------------|
| `Config/*.php` | `ConfigLoader` | 10 | Merges config под ключом `<module>.<config-name>` |
| `Providers/*ServiceProvider.php` | `ServiceProviderLoader` | 20 | Регистрирует service providers модуля по namespace |
| `Database/Migrations/` | `MigrationLoader` | 30 | Добавляет migration paths в Laravel migrator |
| `Database/Factories/` | `FactoryLoader` | 31 | Добавляет module factory namespace guessing |
| `Lang/` | `LangLoader` | 32 | Регистрирует translation namespace `<module_name>` |
| `Resources/views/` | `ViewLoader` | 33 | Регистрирует view namespace `<module_name>` |
| `View/Components/` | `BladeComponentLoader` | 34 | Регистрирует Blade component namespace |
| `Domain/Listeners/` | `EventLoader` | 35 | Добавляет event discovery paths |
| `Domain/Observers/` | `ObserverLoader` | 36 | Регистрирует observers для matching моделей |
| `Domain/Policies/` | `PolicyLoader` | 37 | Регистрирует policies для matching моделей |
| `Console/Commands/` | `CommandLoader` | 40 | Регистрирует command paths через Laravel kernel |
| `Http/Middleware/` | `MiddlewareLoader` | 45 | Регистрирует middleware aliases `<module>.<snake_name>` |
| `Routes/<type>.php` | `RouteLoader` | 50 | Загружает для каждого `<type>` из `modules.routing.types` с его attributes (config-driven). Версионирование — обычный flat-тип, напр. `api_v1` → `Routes/api_v1.php` (prefix `api/v1`) |
| `Routes/api.php` | `RouteLoader` | 50 | Загружает с attributes из `modules.routing.types.api` |
| `Routes/web.php` | `RouteLoader` | 50 | Загружает с attributes из `modules.routing.types.web` |
| `Routes/inertia.php` | `RouteLoader` | 50 | Загружает только при установленной Inertia |
| `Routes/console.php` | `ConsoleRouteLoader` | 51 | Регистрирует console routes через kernel (deferred до boot) |
| `Routes/channels.php` | `BroadcastLoader` | 52 | Загружает broadcast channels (deferred до boot) |

Каждый loader проверяет наличие своего пути и завершает работу ранним return, если загружать нечего. Модули должны находиться внутри `app_path()` Laravel-приложения.

## Namespace resolution

Namespaces модулей вычисляются из `Application::getNamespace()` и `Application::path()` Laravel-приложения. Модули должны находиться внутри `app_path()`.

Для `app/Modules/Blog` при стандартном `App\\` namespace модуля будет `App\Modules\Blog`.

Кастомный `modules.paths.directories` должен указывать директории внутри `app_path()`.

## Enabled state

Pipeline загружает только enabled-модули. Enabled-state хранится в `state.json`, а не в `module.json`:

```text
storage/app/private/modules/blog/state.json
```

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00"
}
```

Disabled-модули обнаруживаются registry, но default loaders к ним не применяются. `make:module` автоматически создаёт `state.json` с `enabled: true` (или `false` при `--disabled`).

Эти поддиректории — «дома» для module-aware генераторов: `make:model Post --module=blog` кладёт класс в `Domain/Models`, `make:controller … --module=blog` — в `Http/Controllers`, и так далее (полная таблица из 22 размещений — в [CLI → Module-aware генераторы](cli.md#module-aware-генераторы)). Архитектурные слои `Application/{UseCases,Actions,Queries,DTOs}` и `Domain/VO` создаются генераторами `make:use-case/action/query/dto/vo` по требованию, а `make:module --with=…` позволяет scaffold'ить только нужные поддиректории.

## Roadmap-only пути

| Путь | Текущий статус |
|------|----------------|
| `MoonShine/` **внутри** host-модуля (per-module resources/pages) | Roadmap |

> Roadmap-only здесь — это per-module `MoonShine/` ресурсы **внутри** отдельного модуля:
> генераторы и runtime их пока не поддерживают. Package-level admin UI управления
> модулями (`ModulesResource` + страницы Index/Form/Detail) уже **реализован** —
> см. [docs/moonshine.md](moonshine.md).

## See Also

- [Getting Started](getting-started.md) - установка и минимальный модуль.
- [Configuration](configuration.md) - настройки route types.
- [Architecture](architecture.md) - место loaders в boot flow.
