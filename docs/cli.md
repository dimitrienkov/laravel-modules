[← Feature Toggles](feature-toggles.md) · [Back to README](../README.MD) · [Contributing →](contributing.md)

# CLI

Текущий v2.0 core реализует production cache команды для module discovery.

## Реализованные команды

| Command | Description |
|---------|-------------|
| `modules:optimize` | Собирает `bootstrap/cache/modules.php` |
| `modules:optimize-clear` | Удаляет cached module registry |

## Build cache

```bash
php artisan modules:optimize
```

Expected output включает:

```text
Caching module registry...
Module registry cached successfully.
```

Команда сканирует configured module directories, валидирует manifests, резолвит namespaces, сортирует modules и пишет cache payload.

## Clear cache

```bash
php artisan modules:optimize-clear
```

Expected output при наличии cache:

```text
Clearing cached module registry...
Module registry cache cleared.
```

Expected output, если cache нет:

```text
Clearing cached module registry...
No cache to clear.
```

## Laravel optimizer hooks

Service provider подключает команды к Laravel optimizer flow:

| Laravel command | Module command |
|-----------------|----------------|
| `php artisan optimize` | `modules:optimize` |
| `php artisan optimize:clear` | `modules:optimize-clear` |

## Ещё не реализовано

Эти команды являются roadmap items и не должны документироваться как текущий runtime:

| Command family | Status |
|----------------|--------|
| `make:module` | Roadmap |
| `modules:install`, `modules:update`, `modules:remove` | Roadmap |
| `modules:enable`, `modules:disable`, `modules:list` | Roadmap |
| Laravel generators with `--module` | Roadmap |

Создавайте директории модулей вручную или через tooling host-приложения, пока scaffolding commands не реализованы.

## See Also

- [Getting Started](getting-started.md) - первая cache-проверка.
- [Architecture](architecture.md) - registry cache flow.
- [Contributing](contributing.md) - local quality gates.
