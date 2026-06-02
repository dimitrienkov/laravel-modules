[← Logging](logging.md) · [Back to README](../README.md) · [Contributing →](contributing.md)

# CLI

Текущий v2.0 core реализует production cache, lifecycle management, scaffolding и module-aware генераторы.

## Реализованные команды

| Command | Description |
|---------|-------------|
| `modules:optimize` | Собирает `bootstrap/cache/modules.php` |
| `modules:optimize-clear` | Удаляет cached module registry |
| `make:module` | Создаёт структуру нового модуля (опционально component-driven через `--with`) |
| `modules:enable` | Включает модуль с проверкой зависимостей |
| `modules:disable` | Отключает модуль с проверкой reverse dependencies |
| `modules:list` | Показывает таблицу зарегистрированных модулей |
| `modules:install` | Устанавливает модуль из zip-архива |
| `modules:update` | Обновляет модуль с backup и merge settings values |
| `modules:remove` | Удаляет модуль с backup или без |
| `make:*` с `--module` | Module-aware режим native Laravel-генераторов (см. [Module-aware генераторы](#module-aware-генераторы)) |
| `make:use-case` / `make:action` / `make:query` / `make:dto` / `make:vo` | Архитектурные генераторы пакета |

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
php artisan make:module payments --kind=integration --group=billing
php artisan make:module analytics --directory=app/Integrations --overwrite
```

`make:module` создаёт директорию модуля с `module.json`, `state.json`, ServiceProvider stub и базовые поддиректории. Опции:

| Опция | Назначение |
|-------|------------|
| `--kind` | `ModuleKind`: `module`, `subsystem`, `integration`. По умолчанию infer'ится из target root (`Modules→module`, `Integrations→integration`, `Subsystems→subsystem`) |
| `--group` | Логическая группа в kebab-case (`meta.group`); невалидное значение → ошибка с указанием имени модуля и группы |
| `--directory` | Target root из configured `modules.paths.directories` |
| `--disabled` | Создать модуль в отключённом состоянии |
| `--overwrite` | Перезаписать существующий модуль в той же target-директории |
| `--with` | Component-driven скелет: CSV из `application`, `config`, `console`, `database`, `domain`, `http`, `routes`, `views`. Создаётся только выбранное плюс обязательные root/Providers. Неизвестное значение → fail-fast до создания директории; пустой `--with=` → только обязательное |

Без `--with` интерактивный запуск показывает multiselect «что создать?», а `--no-interaction` оставляет текущий минимальный скелет (Config, Console/Commands, Database/Factories, Database/Migrations, Domain/Models, Http/Middleware, Providers, Routes). Scaffold записывает в `state.json` provenance `source.kind = local` (без checksum) и никогда не пишет mutable state в `module.json`.

## Enable / Disable / List

```bash
php artisan modules:enable blog
php artisan modules:disable blog
php artisan modules:list
php artisan modules:list --enabled
php artisan modules:list --disabled
php artisan modules:list --kind=integration
php artisan modules:list --group=billing
```

`modules:enable` проверяет зависимости через `TopologicalSorter` перед включением. `modules:disable` запрещает отключение, если enabled-модули зависят от целевого. Обе команды модифицируют только `state.json`, не трогая `module.json`.

`modules:list` печатает колонки Name, Kind, Group, Display Name, Version, Enabled, Path. Колонка Group рендерится как `"Label (code)"` при наличии маппинга в `modules.groups`, иначе — голый код (пусто для модуля без группы). Фильтры `--kind` и `--group` сужают список по коду; `--enabled` и `--disabled` нельзя комбинировать. Невалидный формат `--group` (не segment kebab-case) падает с явной ошибкой ещё до запроса registry, а валидная группа без совпадений даёт сообщение `No modules found in group [<group>].` вместо общего пустого результата.

## Install

```bash
php artisan modules:install /path/to/module.zip
php artisan modules:install /path/to/module.zip --disabled
php artisan modules:install /path/to/module.zip --directory=app/OtherModules
```

Модуль валидируется до копирования файлов. Команда создаёт `module.json` в target директории и `state.json` в state-хранилище, фиксируя provenance: `source.kind = zip`, `installed_version` и `checksum` архива. `--directory` позволяет указать целевой configured root. Если запись manifest или state падает после копирования, target директория и state автоматически откатываются. После установки нужно запустить `php artisan migrate`.

> `checksum` — это provenance-запись sha256 архива в момент чтения, а не проверка целостности. Команда не сверяет архив с ожидаемым digest или подписью; integrity verification и signature flow относятся к roadmap, а не к текущему runtime.

## Update

```bash
php artisan modules:update blog /path/to/blog-v2.zip
php artisan modules:update blog /path/to/blog-v2.zip --force
```

Update использует Laravel `ConfirmableTrait`; в production окружении добавляйте `--force`. Команда бэкапит текущую директорию, заменяет файлы и мержит `settings.values` в `state.json`: сохраняются explicit values для ключей, которые остались в новой schema. Пропущенные values выводятся с указанием причины (removed from schema / invalid value). Provenance перезаписывается: `source.kind = zip`, новый `installed_version` и `checksum`. Если запись manifest или state падает после замены, модуль автоматически восстанавливается из backup.

## Remove

```bash
php artisan modules:remove blog
php artisan modules:remove blog --force
php artisan modules:remove blog --delete-permanently
php artisan modules:remove blog --force --delete-permanently
```

`modules:remove` тоже использует Laravel `ConfirmableTrait`; в production окружении добавляйте `--force`. Без `--delete-permanently` модуль перемещается в `config('modules.paths.backup')`, а `state.json` копируется в backup и удаляется из state root. С `--delete-permanently` сначала удаляется `state.json`, затем директория модуля; если удаление state не удалось, директория остаётся intact. Remove запрещён, если другие installed модули зависят от удаляемого. Миграции не откатываются автоматически.

## Module-aware генераторы

Опция `--module=<name>` добавляется к native Laravel-генераторам прозрачно: имя и signature команды не меняются, без `--module` поведение байт-в-байт совпадает с host. Имя модуля резолвится через registry-контракт и нечувствительно к регистру (`--module=blog` = `--module=Blog`); неизвестный модуль завершает команду failure-кодом, не создавая partial-файлов. Поскольку модули лежат под `app/` (namespace из `App\`), файл автоматически попадает внутрь модуля.

```bash
php artisan make:model Post --module=blog -mfs
php artisan make:controller PostController --module=blog --model=Post --requests
php artisan make:mail Digest --module=blog --markdown=mail.digest
php artisan make:component Alert --module=blog
```

Поддерживаемые native-команды и их «дом» внутри модуля (22 команды с явным размещением):

| Команда | Размещение в модуле |
|---------|---------------------|
| `make:model` | `Domain/Models` |
| `make:controller` | `Http/Controllers` |
| `make:request` | `Http/Requests` |
| `make:resource` | `Http/Resources` |
| `make:middleware` | `Http/Middleware` |
| `make:event` | `Domain/Events` |
| `make:listener` | `Domain/Listeners` |
| `make:observer` | `Domain/Observers` |
| `make:policy` | `Domain/Policies` |
| `make:job` | `Jobs` |
| `make:mail` | `Mail` (+ Blade в `Resources/views`) |
| `make:notification` | `Notifications` |
| `make:command` | `Console/Commands` |
| `make:rule` | `Rules` |
| `make:cast` | `Casts` |
| `make:channel` | `Broadcasting` |
| `make:provider` | `Providers` |
| `make:exception` | `Exceptions` |
| `make:component` | `View/Components` (+ Blade в `Resources/views/components`) |
| `make:factory` | `Database/Factories` |
| `make:seeder` | `Database/Seeders` |
| `make:migration` | `Database/Migrations` (через native `--path`) |

Под-генераторы наследуют `--module`: `make:model -mfs --module=blog` создаёт model, migration, factory и seeder только внутри модуля, а сгенерированная model ссылается на module factory namespace. `make:controller --requests` кладёт form requests в `Http/Requests` модуля. В module-aware режиме matching-test опции (`--test`, `--pest`, `--phpunit`) отклоняются fail-fast — поставляемые модули не несут host-тестов.

### Архитектурные генераторы пакета

`make:use-case`, `make:action`, `make:query`, `make:dto`, `make:vo` создают `final readonly`-классы в house-style. Без `--module` пишут в host (`app/Application/{UseCases,Actions,Queries,DTOs}`, `app/Domain/VO`), с `--module` — в соответствующий слой модуля.

```bash
php artisan make:use-case PublishPost --module=blog   # Application/UseCases/PublishPostUseCase.php
php artisan make:dto CreatePost --module=blog          # Application/DTOs/CreatePostDto.php
php artisan make:vo Money --module=blog                # Domain/VO/Money.php (без суффикса)
```

Суффикс добавляется автоматически (`UseCase`/`Action`/`Query`/`Dto`) и не дублируется; `make:vo` сохраняет имя без суффикса, как VO ядра (`Version`, `Checksum`).

## Ещё не реализовано

| Command family | Status |
|----------------|--------|
| `make:test --module` | Out of scope — модули не несут собственных тестов |
| Generic `make:class/interface/trait/enum/scope/view/config` с `--module` | Roadmap — у них нет конвенционного «дома» в модуле, остаются host-командами |
| MoonShine-генераторы | Out of scope — артефакты создаёт сам MoonShine |

## See Also

- [Getting Started](getting-started.md) - первая cache-проверка.
- [Architecture](architecture.md) - registry cache flow.
- [Contributing](contributing.md) - local quality gates.
