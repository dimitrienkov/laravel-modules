# Laravel Modules

## Обзор

`dimitrienkov0/laravel-modules` — пакет для построения модульных Laravel-приложений, ориентированный на сценарий **коммерческой поставки модулей заказчикам**. Каждый модуль — самостоятельная единица с собственным манифестом (`module.json`), фичетоглами, миграциями, роутами, командами, расписанием, переводами, представлениями, MoonShine-ресурсами и опциональными зависимостями от других модулей.

Пакет не подменяет архитектуру приложения и не требует от модуля собственного `composer.json` — namespaces резолвятся из корневого PSR-4. Собственный `composer.json` модуля допускается как опциональная метаинформация для диагностики, упаковки и проверки PHP-зависимостей, но не является обязательным runtime-механизмом. MoonShine интегрируется как опциональный admin-UI для управления модулями и фичетоглами.

## Целевые сценарии

- Команда разрабатывает несколько модулей и продаёт их разным заказчикам.
- Один заказчик получает базовый набор модулей, второй — расширенный.
- Внутри модуля есть фичетоглы и настройки, которые заказчик меняет через MoonShine.
- Модули доставляются как zip-архивы или подкладкой в директорию модулей; устанавливаются командой `modules:install`.
- Часть приложений использует MoonShine, часть — нет; пакет работает в обоих сценариях.
- Часть приложений использует Inertia, часть — нет; `Routes/inertia.php` загружается только как optional-интеграция при наличии нужных классов/middleware в host-приложении.
- Хост-приложение может работать как под классическим `php-fpm`, так и под `laravel/octane` (Swoole/RoadRunner/FrankenPHP) — пакет не должен порождать утечек памяти и state-leak между запросами.

## Ключевые возможности

### Module как first-class объект
- Манифест `module.json` в корне каждого модуля.
- Структура манифеста: `meta` (имя, версия, автор, описание, зависимости), `state` (enabled, дата установки), `settings.schema` (схема фичетоглов от автора модуля), `settings.values` (текущие значения у заказчика).
- Нет секции `autoload`: какие аспекты грузить — определяется наличием соответствующих директорий и файлов в модуле (convention over configuration).
- `ModuleRegistry` — единая точка доступа к списку модулей, их состоянию и метаданным.
- `ModuleManifestRepository` — атомарная запись JSON с валидацией схемы.
- Топологическая сортировка модулей по `meta.dependencies`; зависимости поддерживают Composer SemVer constraints (`{"users": "^1.5"}`), короткая форма `["users"]` нормализуется в `{"users": "*"}`. Модуль с отключённой или несовместимой зависимостью не загружается.

### Loader-pipeline
Один проход по `ModuleRegistry`, каждый Loader получает `Module`-объект и сам решает, есть ли у него работа (проверяет наличие своей директории/файла через `ModuleLayout`). Никаких whitelist-флагов в манифесте, никакого `key()`-метода. Список лоадеров:
- `RouteLoader` — `Routes/api/{version}.php`, `Routes/web.php`, `Routes/inertia.php`. Версии API через подпапки автоматически дают префикс `api/{version}`.
- `MigrationLoader` — `Database/Migrations/`.
- `FactoryLoader` — резолв `Database/Factories/` по соглашению Eloquent.
- `ConfigLoader` — `Config/*.php` с merge в Laravel-конфиг.
- `LangLoader` — `Lang/{locale}/*.php` через `loadTranslationsFrom`, namespace = `Str::snake($module->name)`.
- `ViewLoader` — `Resources/views/` через `loadViewsFrom`, namespace = тот же.
- `CommandLoader` — class-based Artisan-команды из `Console/Commands/`.
- `ConsoleRouteLoader` — `Routes/console.php` (Artisan-closures и `Schedule::command(...)` в актуальном Laravel-стиле).
- `EventLoader` — связки событий и слушателей из `Providers/EventServiceProvider.php` или auto-discovery по соглашению.
- `ObserverLoader` — `Domain/Observers/` по соглашению `Model → ModelObserver`.
- `PolicyLoader` — `Domain/Policies/` по соглашению `Model → ModelPolicy`.
- `BladeComponentLoader` — `View/Components/` через `Blade::componentNamespace`.
- `MiddlewareLoader` — `Http/Middleware/` с named-регистрацией.
- `BroadcastLoader` — `Routes/channels.php`.
- `ServiceProviderLoader` — `Providers/*ServiceProvider.php`.
- `MoonShineLoader` — вызов родного `$core->autoload($module->namespace)`, без classmap-магии.

Все пути модуля инкапсулированы в `ModuleLayout` — `final readonly` сервис: `routesDir($module)`, `migrationsDir($module)`, `langDir($module)` и т.д. Перебиндить через service container, если модель размещения в каком-то проекте отличается.

### Управление жизненным циклом модуля
Artisan-команды:
- `make:module <Name>` — генерация полного скелета.
- `modules:install <zip|path>` — распаковка, проверка зависимостей, install hook, миграции.
- `modules:update <Name> <zip|path>` — бэкап `settings.values`, обновление кода, миграции, восстановление values.
- `modules:remove <Name>` — `migrate:rollback` модуля, удаление папки.
- `modules:enable <Name>` / `modules:disable <Name>` — изменение `state.enabled` в манифесте.
- `modules:list` — таблица модулей: имя, версия, статус, зависимости.
- `modules:optimize` / `modules:optimize-clear` — кеш обнаружения для prod.

Генераторы:
- `make:module <Name>` создаёт skeleton модуля.
- Стандартные Laravel-команды получают module-aware режим через `--module`: `make:model Post --module=Blog -mfs`, `make:controller PostController --module=Blog`, `make:migration create_posts_table --module=Blog`, `make:factory PostFactory --module=Blog`, `make:seeder PostSeeder --module=Blog`, `make:request StorePostRequest --module=Blog`, `make:resource PostResource --module=Blog`, `make:command SyncPostsCommand --module=Blog`, `make:event PostPublished --module=Blog`, `make:listener SendPostNotification --module=Blog`, `make:observer PostObserver --module=Blog`, `make:policy PostPolicy --module=Blog`, `make:middleware EnsureBlogEnabled --module=Blog`, `make:enum PostStatus --module=Blog`.
- Архитектурные команды пакета используют тот же стиль: `make:use-case PublishPost --module=Blog`, `make:action CreatePost --module=Blog`, `make:query ListPosts --module=Blog`, `make:dto PostData --module=Blog`.
- MoonShine-артефакты создаются средствами MoonShine; пакет не дублирует его генераторы.

### MoonShine admin-UI (опционально)
При наличии MoonShine пакет регистрирует:
- `ModulesResource` — список модулей: имя, версия, описание, переключатель enabled/disabled, действия `install/update/remove`.
- `ModuleSettingsPage` — динамическая форма по `settings.schema` каждого модуля для редактирования `settings.values`.
- Источник правды — `module.json`. UI пишет туда через `ModuleManifestRepository`, передавая `FeatureValues`-VO (никаких сырых array на границе).

### Feature toggles
- Ядро: DI-first API через `FeatureRepositoryInterface`: `get`, `bool`, `int`, `string`.
- Presentation-интеграции: Blade directive `@feature('blog.enable_comments')` и optional bridge для UI/Blade-слоя.
- Gate/Policy bridge не входит в ядро: авторизация и product configuration не смешиваются.
- `FeatureRepository` читает актуальный `module.json`, а не production cache, чтобы изменения из MoonShine применялись со следующего request'а без `modules:optimize-clear`.

### Архитектура модуля
Скелет, генерируемый `make:module`:
```
Modules/Blog/
├── Application/
│   ├── UseCases/
│   ├── Actions/
│   └── Queries/
├── Domain/
│   ├── Models/
│   ├── DTOs/
│   ├── Enums/
│   ├── Events/
│   ├── Observers/
│   └── Policies/
├── Infrastructure/
│   └── Repositories/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Console/
│   └── Commands/
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Routes/
│   ├── api/{version}.php
│   ├── web.php
│   ├── inertia.php
│   ├── console.php           # Artisan-closures + Schedule
│   └── channels.php
├── Config/
├── Lang/{en,ru}/
├── Resources/views/
├── View/Components/
├── MoonShine/
│   ├── Resources/
│   └── Pages/
├── Providers/
└── module.json
```
Полная структура слоёв: `Application` (UseCase/Action/Query), `Domain` (модели и доменные сущности), `Infrastructure` (репозитории, внешние интеграции), `Http`/`Console`/`MoonShine` (presentation).

## Технологический стек

- **PHP:** 8.3+
- **Laravel:** 12 / 13
- **Octane:** Swoole / RoadRunner / FrankenPHP — поддерживается first-class
- **PHP-стандарт:** `declare(strict_types=1);` обязателен во всех `.php`-файлах пакета; `final` по умолчанию, `readonly` где применимо
- **Подходы:** UseCase + Action + Query, всё через DI, без хелперов и фасадов внутри пакета
- **Передача данных между слоями:** только DTO/VO (final readonly), `array` — внутри метода, на границе — никогда
- **PHPDoc:** только когда даёт информацию, недоступную из типов (generics, причины workaround'а); пишется на английском
- **Admin-UI:** MoonShine 4 (опционально, через `afterResolving(CoreContract::class)`)
- **Optional integrations:** MoonShine 4 и Inertia 2 не входят в обязательные runtime-зависимости; они должны быть в `suggest`/`require-dev` и активироваться через `class_exists`/container checks.
- **Pretty-print/normalize:** `json_encode` с `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`
- **Тесты:**
  - Pest 3 + `pestphp/pest-plugin-arch` — архитектурные тесты (`tests/Architecture/`)
  - PHPUnit 11/12 + Orchestra Testbench — unit и feature (`tests/Unit/`, `tests/Feature/`)
- **Качество (обязательно в CI, не опционально):**
  - PHPStan level 8 + larastan, `treatPhpDocTypesAsCertain: false`
  - Rector с Laravel-set + type-declaration + dead-code
  - PHP-CS-Fixer (`@PSR12` + проектные правила)
- **Тип-безопасность:** generics-аннотации PHPStan, no-mixed правило

## Принципы

1. **Один проход по модулям** — `ModuleRegistry` сканирует папки один раз; лоадеры получают готовые `Module`-объекты.
2. **JSON как источник правды** — `module.json` хранит и метаданные, и состояние, и значения фичетоглов. Без БД.
3. **Атомарная запись** — `ModuleManifestRepository::save()` пишет через temp file + rename + file lock.
4. **Convention over configuration** — структура папок диктует поведение; манифест нужен только для метаданных и фичетоглов. Никакого whitelist-конфига загрузки.
5. **Тонкие контракты** — `LoaderInterface` имеет только `load(Module)` и `priority()`; ни ключей, ни регистрационной магии.
6. **MoonShine и Inertia — опциональны** — пакет полностью работает без MoonShine/Inertia; интеграции включаются только при наличии соответствующих классов в host-приложении.
7. **Namespace из composer.json** — никакого хардкода `App\\`, читаем PSR-4 хост-приложения.
8. **Кеш для prod** — `modules:optimize` сохраняет `bootstrap/cache/modules.php` с готовым registry; `ModuleRegistry` читает оттуда без сканирования диска.
9. **Octane-safe** — никакой статики, никакого in-memory mutable-state на singleton-сервисах; runtime-чтение фичетоглов — через `FeatureRepository` со scoped-биндингом и mtime-инвалидацией.

## Нефункциональные требования

- **Производительность:** при включённом кеше — ноль операций FS на bootstrap; без кеша — один проход `glob` по корневым директориям модулей.
- **Безопасность установки:** `modules:install` валидирует структуру архива и манифест по JSON-схеме до распаковки.
- **Атомарность:** запись `module.json` через `tmp + rename`; миграции и установка в транзакции, где возможно.
- **Совместимость:** Laravel 12/13, PHP 8.3+. MoonShine 4+ и Inertia 2 — soft-зависимости (`suggest`, проверка через `class_exists`/container checks).
- **Octane-совместимость:**
  - Сервисы пакета — singleton при отсутствии mutable-state, scoped (`$this->app->scoped()`) для runtime-сервисов с per-request кешем (`FeatureRepository`).
  - Никаких статических полей, никакого глобального состояния.
  - `ModuleRegistry` прогревается один раз на worker; `FeatureRepository` читает `module.json` максимум один раз на request с инвалидацией по mtime.
  - Покрытие отдельным архитектурным тестом: запрет `static` свойств в `src/`, запрет фасадов вне `Console/Commands/`.
- **Расширяемость:** все лоадеры реализуют общий контракт `LoaderInterface`; разработчик приложения может зарегистрировать собственные лоадеры через сервис-провайдер пакета.
- **Тестируемость:** все сервисы — `final` (где возможно `final readonly`) с DI; ноль глобальных хелперов, ноль фасадов внутри пакета.
