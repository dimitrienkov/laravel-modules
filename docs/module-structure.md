[← Getting Started](getting-started.md) · [Back to README](../README.MD) · [Manifest →](manifest.md)

# Module Structure

Модуль - это директория внутри одного из путей `modules.paths.directories`. Пакет обнаруживает модуль только если в директории есть `module.json`.

## Runtime-supported layout

```text
app/Modules/Blog/
|-- Config/
|   `-- blog.php
|-- Database/
|   |-- Factories/
|   `-- Migrations/
|-- Domain/
|   `-- Models/
|-- Providers/
|   `-- BlogServiceProvider.php
|-- Routes/
|   |-- api.php
|   |-- api/
|   |   |-- v1.php
|   |   `-- v2.php
|   |-- web.php
|   `-- inertia.php
`-- module.json
```

Host-приложение может хранить внутри модуля свой application/domain/http код. Пакет интерпретирует только пути, перечисленные ниже.

## Загружаемые пути

| Путь | Loader | Runtime behavior |
|------|--------|------------------|
| `Config/*.php` | `ConfigLoader` | Merges config под ключом `<module>.<config-name>` |
| `Providers/*ServiceProvider.php` | `ServiceProviderLoader` | Регистрирует service providers модуля по namespace |
| `Database/Migrations/` | `MigrationLoader` | Добавляет migration paths в Laravel migrator |
| `Database/Factories/` | `FactoryLoader` | Добавляет module factory namespace guessing |
| `Routes/api.php` | `RouteLoader` | Загружает с attributes из `modules.routing.types.api` |
| `Routes/api/*.php` | `RouteLoader` | Загружает versioned API routes, например `api/v1` |
| `Routes/web.php` | `RouteLoader` | Загружает с attributes из `modules.routing.types.web` |
| `Routes/inertia.php` | `RouteLoader` | Загружает только при установленной Inertia |

Каждый loader проверяет наличие своего пути и завершает работу ранним return, если загружать нечего.

## Namespace resolution

Namespaces модулей вычисляются из Composer PSR-4 autoload host-приложения. Пакет не использует hardcoded `App\\` prefix.

Пример host mapping:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Для `app/Modules/Blog` namespace будет `App\Modules\Blog`.

## Enabled state

Pipeline загружает только enabled-модули:

```json
{
  "state": {
    "enabled": true
  }
}
```

Disabled-модули могут быть обнаружены registry, но default loaders к ним не применяются.

## Roadmap-only пути

`ModuleLayout` уже знает несколько будущих путей, но текущий core не содержит runtime loaders для них:

| Путь | Текущий статус |
|------|----------------|
| `Lang/` | Roadmap |
| `Resources/views/` | Roadmap |
| `View/Components/` | Roadmap |
| `Console/Commands/` | Roadmap |
| `Routes/console.php` | Roadmap |
| `Routes/channels.php` | Roadmap |
| `Domain/Observers/` | Roadmap |
| `Domain/Policies/` | Roadmap |
| `Http/Middleware/` | Roadmap |
| `MoonShine/` admin pages/resources | Roadmap |

Документируйте эти пути как extension points или roadmap, а не как реализованные loaders.

## See Also

- [Getting Started](getting-started.md) - установка и минимальный модуль.
- [Configuration](configuration.md) - настройки route types.
- [Architecture](architecture.md) - место loaders в boot flow.
