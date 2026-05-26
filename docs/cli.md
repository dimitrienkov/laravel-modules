[← Feature Toggles](feature-toggles.md) · [Back to README](../README.MD) · [Contributing →](contributing.md)

# CLI

Текущий v2.0 core реализует production cache, lifecycle management и scaffolding команды.

## Реализованные команды

| Command | Description |
|---------|-------------|
| `modules:optimize` | Собирает `bootstrap/cache/modules.php` |
| `modules:optimize-clear` | Удаляет cached module registry |
| `make:module` | Создаёт структуру нового модуля |
| `modules:enable` | Включает модуль с проверкой зависимостей |
| `modules:disable` | Отключает модуль с проверкой reverse dependencies |
| `modules:list` | Показывает таблицу зарегистрированных модулей |
| `modules:install` | Устанавливает модуль из директории или zip-архива |
| `modules:update` | Обновляет модуль с backup и merge settings values |
| `modules:remove` | Удаляет модуль с backup или без |

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

## Scaffold

```bash
php artisan make:module blog
php artisan make:module user_auth --disabled
php artisan make:module analytics --directory=app/Integrations --force
```

`make:module` создаёт директорию модуля с `module.json`, `state.json`, ServiceProvider stub и базовые поддиректории. `--disabled` создаёт модуль в отключённом состоянии. `--force` перезаписывает существующий модуль.

## Enable / Disable / List

```bash
php artisan modules:enable blog
php artisan modules:disable blog
php artisan modules:list
php artisan modules:list --enabled
php artisan modules:list --disabled
```

`modules:enable` проверяет зависимости через `TopologicalSorter` перед включением. `modules:disable` запрещает отключение, если enabled-модули зависят от целевого. Обе команды модифицируют только `state.json`, не трогая `module.json`.

## Install

```bash
php artisan modules:install /path/to/module-directory
php artisan modules:install /path/to/module.zip
php artisan modules:install /path/to/module.zip --disabled
php artisan modules:install /path/to/module-directory --directory=app/OtherModules
```

Модуль валидируется до копирования файлов. Команда создаёт `module.json` в target директории и `state.json` в state-хранилище. `--directory` позволяет указать целевой configured root. Если запись manifest или state падает после копирования, target директория и state автоматически откатываются. После установки нужно запустить `php artisan migrate`.

## Update

```bash
php artisan modules:update blog /path/to/blog-v2
php artisan modules:update blog /path/to/blog-v2.zip
```

Update бэкапит текущую директорию, заменяет файлы и мержит `settings.values` в `state.json`: сохраняются explicit values для ключей, которые остались в новой schema. Пропущенные values выводятся с указанием причины (removed from schema / invalid value). Если запись manifest или state падает после замены, модуль автоматически восстанавливается из backup.

## Remove

```bash
php artisan modules:remove blog
php artisan modules:remove blog --force
php artisan modules:remove blog --force --delete-permanently
```

`--force` пропускает confirmation prompt. Без `--delete-permanently` модуль перемещается в `config('modules.paths.backup')`. С `--delete-permanently` сначала удаляется директория модуля, затем `state.json`; если удаление директории не удалось, state остаётся intact. Remove запрещён, если другие installed модули зависят от удаляемого. Миграции не откатываются автоматически.

## Ещё не реализовано

| Command family | Status |
|----------------|--------|
| Laravel generators with `--module` | Roadmap |

## See Also

- [Getting Started](getting-started.md) - первая cache-проверка.
- [Architecture](architecture.md) - registry cache flow.
- [Contributing](contributing.md) - local quality gates.
