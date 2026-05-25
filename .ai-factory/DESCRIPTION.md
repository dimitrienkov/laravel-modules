# Laravel Modules

## Обзор

`dimitrienkov0/laravel-modules` — Laravel-пакет для manifest-driven модульной архитектуры. Пакет загружает модули из настроенных директорий хост-приложения, читает `module.json`, строит dependency-aware registry, применяет loader-pipeline и предоставляет runtime API для feature toggles без БД.

Текущий реализованный срез — **v2.0 core**: контракты, manifest VO, registry/cache, базовые лоадеры, scoped `FeatureRepository`, optimizer-команды и optional bridges для MoonShine/Inertia. Команды установки/обновления модулей, module-aware генераторы, дополнительные лоадеры и полноценный MoonShine admin-UI остаются roadmap-задачами следующих фаз.

## Целевые сценарии

- Команда поставляет разные наборы модулей разным заказчикам.
- Модуль является самостоятельной директорией с `module.json`, кодом, миграциями, конфигами, роутами и feature settings.
- Хост-приложение включает/отключает модули через `state.enabled` в manifest.
- Фичетоглы и настройки читаются из `settings.schema` и `settings.values` без отдельной таблицы БД.
- Production-приложение может прогреть registry через `modules:optimize`, а runtime feature values остаются актуальными со следующего request.
- Хост может работать без MoonShine и Inertia; optional integrations активируются только при наличии соответствующих пакетов.
- Пакет должен оставаться совместимым с Octane: без глобального mutable-state и без stale values между request.

## Текущий статус

### Реализовано

- Manifest contract: `meta`, `state`, `settings.schema`, `settings.values`; секция `autoload` запрещена.
- Value objects и parser layer: `Module`, `ManifestMeta`, `ManifestState`, `ModuleDependencies`, `FeatureSchema`, `FeatureDefinition`, `FeatureValues`.
- `ModuleManifestRepository` как единственная точка чтения/валидации/атомарной записи `module.json`.
- `ModuleDirectoryScanner`, `ModuleRegistry`, `ModuleRegistryCache`, `TopologicalSorter`.
- Loader pipeline с 15 реализованными лоадерами: `ConfigLoader`, `ServiceProviderLoader`, `MigrationLoader`, `FactoryLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader`, `EventLoader`, `ObserverLoader`, `PolicyLoader`, `CommandLoader`, `MiddlewareLoader`, `RouteLoader`, `ConsoleRouteLoader`, `BroadcastLoader`.
- `FeatureRepositoryInterface` с методами `get`, `bool`, `int`, `string`; реализация биндится как scoped.
- Команды `modules:optimize` и `modules:optimize-clear`, интегрированные с Laravel optimizer hooks.
- Optional MoonShine bridge через `MoonShineModuleAutoloader`.
- Optional Inertia routes: `Routes/inertia.php` загружается только при наличии Inertia.
- Pest architecture suite и PHPUnit unit/feature coverage для ядра.
- PR-шаблон `.github/PULL_REQUEST_TEMPLATE.md` с quality checklist.

### Roadmap, не текущий runtime

- `make:module`, `modules:install`, `modules:update`, `modules:remove`, `modules:enable`, `modules:disable`, `modules:list`.
- Module-aware генераторы `make:* --module`.
- MoonShine resources/pages для управления модулями и settings forms.
- Packaging/marketplace/signature flow для коммерческой поставки.

## Технологический стек

- **PHP:** 8.3+
- **Laravel:** 12 / 13
- **Runtime dependencies:** `composer/semver`, `ext-mbstring`
- **Optional integrations:** MoonShine 4, Inertia 2
- **Manifest storage:** `module.json`, без БД
- **Dependency management между модулями:** Composer SemVer constraints в `meta.dependencies`
- **Кодовый стиль:** PER Coding Style 3.0 как целевой стандарт; PHP-CS-Fixer хранит применяемый formatter profile
- **Статический анализ:** PHPStan 2 level 8 + Larastan 3, `treatPhpDocTypesAsCertain: false`
- **Рефакторинг:** Rector 2 + Laravel rules
- **Тесты:**
  - Pest 3 + `pestphp/pest-plugin-arch` для `tests/Architecture/`
  - PHPUnit 11/12 + Orchestra Testbench 10/11 для `tests/Unit/` и `tests/Feature/`
  - Mockery для test doubles

## Manifest contract

Каждый модуль должен иметь `module.json` в корне. Разрешены только top-level ключи `meta`, `state`, `settings`; неизвестные ключи и `autoload` считаются ошибкой.

```json
{
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "description": "Corporate blog with comments",
    "version": "1.0.0",
    "author": "Acme Studio",
    "license": "proprietary",
    "dependencies": {
      "users": "^1.5",
      "media": ">=1.4 <3.0"
    }
  },
  "state": {
    "enabled": true,
    "installed_at": "2026-05-23T14:12:00+00:00",
    "updated_at": "2026-05-23T14:12:00+00:00"
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true,
        "label": "Enable comments"
      },
      "max_posts_per_page": {
        "type": "int",
        "default": 20,
        "min": 1,
        "max": 100
      },
      "moderation_mode": {
        "type": "enum",
        "default": "auto",
        "options": ["auto", "manual", "off"]
      }
    },
    "values": {
      "enable_comments": false,
      "max_posts_per_page": 50
    }
  }
}
```

Правила:

- `meta.name` — canonical module name; `display_name` опционален и fallback'ается на `name`.
- `meta.dependencies` принимает объект `moduleName => Composer constraint`; короткая list-форма нормализуется в `moduleName => "*"`.
- `state.enabled` обязателен; `installed_at` и `updated_at` опциональны.
- `settings.schema` поддерживает типы `bool`, `int`, `string`, `enum`.
- `settings.values` хранит только явные override-значения; defaults остаются в schema и не записываются как values.
- `FeatureValues` валидирует значения против schema при чтении и записи.

## Loader pipeline

`ModuleLoaderServiceProvider` регистрирует default loaders как tagged services и при boot создаёт `ModuleLoaderPipeline`. Pipeline сортирует лоадеры по `priority()` и запускает каждый loader для каждого enabled-модуля в `ModuleRegistry::loadOrder()`.

| Loader | Priority | Что загружает |
|--------|----------|---------------|
| `ConfigLoader` | 10 | `Config/*.php`, merge в ключ `<module>.<config-name>` |
| `ServiceProviderLoader` | 20 | `Providers/*ServiceProvider.php` по namespace модуля |
| `MigrationLoader` | 30 | `Database/Migrations/` через Laravel migrator path |
| `FactoryLoader` | 31 | `Database/Factories/` через `Factory::guessFactoryNamesUsing()` |
| `LangLoader` | 32 | `Lang/` translation namespace `<module_name>` |
| `ViewLoader` | 33 | `Resources/views/` view namespace `<module_name>` |
| `BladeComponentLoader` | 34 | `View/Components/` Blade component namespace |
| `EventLoader` | 35 | `Domain/Listeners/` event discovery paths |
| `ObserverLoader` | 36 | `Domain/Observers/*Observer.php` → matching `Domain/Models/` |
| `PolicyLoader` | 37 | `Domain/Policies/*Policy.php` → matching `Domain/Models/` |
| `CommandLoader` | 40 | `Console/Commands/` через `addCommandPaths()` |
| `MiddlewareLoader` | 45 | `Http/Middleware/` aliases `<module>.<snake_name>` |
| `RouteLoader` | 50 | `Routes/api.php`, `Routes/api/*.php`, `Routes/web.php`, `Routes/inertia.php` |
| `ConsoleRouteLoader` | 51 | `Routes/console.php` через `addCommandRoutePaths()` (deferred) |
| `BroadcastLoader` | 52 | `Routes/channels.php` broadcast channels (deferred до boot) |

MoonShine autoload не является `LoaderInterface`: это отдельный optional bridge, который регистрируется через `afterResolving(CoreContract::class)` и вызывает `$core->autoload($module->namespace)` для enabled-модулей.

## Routing

Route loading управляется `config/modules.php`:

- `modules.routing.types.api` задаёт prefix/middleware для `Routes/api.php` и versioned routes.
- `Routes/api/v1.php` получает prefix `api/v1`, `Routes/api/v2.php` — `api/v2`.
- `Routes/web.php` загружается по настройкам `web`.
- `Routes/inertia.php` загружается только если установлен Inertia.
- Если Laravel routes cached, `RouteLoader` выходит ранним return.

## Module registry и production cache

`ModuleRegistry`:

1. Если существует `bootstrap/cache/modules.php`, читает cache одним `require`.
2. Иначе сканирует `modules.paths.directories` через `ModuleDirectoryScanner`.
3. Для каждой директории с `module.json` вызывает `ModuleManifestRepository::load()`.
4. Резолвит namespace через `Application::getNamespace()` и `Application::path()`.
5. Сортирует модули через `TopologicalSorter`.

`modules:optimize` пишет cache payload с версией формата, serialized manifest descriptors и `load_order`. `modules:optimize-clear` удаляет cache и сбрасывает in-memory registry.

## Feature toggles

Публичный API:

- `FeatureRepositoryInterface::get(string $moduleName, string $key): bool|int|string`
- `bool(...)`
- `int(...)`
- `string(...)`

`FeatureRepository` биндится через `$this->app->scoped()`. В пределах одного request он кеширует `FeatureValues` по имени модуля, но при новом request перечитывает values из `module.json` через `ModuleManifestRepository::readValues()`. Production registry cache не используется для feature values, поэтому изменения settings применяются без `modules:optimize-clear`.

## Архитектура модуля в host-приложении

Текущий core имеет runtime behavior для следующих частей модуля:

```
app/Modules/Blog/
├── Config/
│   └── blog.php
├── Database/
│   ├── Factories/
│   └── Migrations/
├── Domain/
│   └── Models/
├── Providers/
│   └── BlogServiceProvider.php
├── Routes/
│   ├── api.php
│   ├── api/
│   │   ├── v1.php
│   │   └── v2.php
│   ├── web.php
│   └── inertia.php
└── module.json
```

`ModuleLayout` уже содержит методы для будущих module subpaths (`Lang`, `Resources/views`, `View/Components`, `Console/Commands`, `Routes/console.php`, `Routes/channels.php`, observers, policies, middleware), но пока нет соответствующих runtime loaders в текущем core.

## Нефункциональные требования

- **Octane safety:** runtime-сервисы с per-request cache должны быть scoped; singleton-сервисы не должны накапливать mutable runtime state между request.
- **Атомарность:** запись `module.json` идёт через `AtomicJsonWriter` с lock, temp file, `rename`.
- **Детерминированность:** manifest schema, dependencies, feature definitions и cache payload сортируются там, где это важно для стабильного результата.
- **Безопасность manifest:** неизвестные ключи запрещены; `autoload` запрещён как legacy-механика.
- **Расширяемость:** новые loaders добавляются через `LoaderInterface` и service container tag `ModuleLoaderServiceProvider::LOADER_TAG`.
- **DI-first:** в `src/` не используются Laravel facades и глобальные helpers для runtime-логики.
- **Тестируемость:** архитектурные инварианты закреплены Pest arch suite.

## Quality gates

Локальные проверки проекта:

```bash
composer format
composer phpstan
composer test
```

Дополнительно для review:

```bash
composer format:dry
composer rector:dry
```

`composer test` запускает `test:arch`, `test:unit`, `test:feature`. PR должен использовать `.github/PULL_REQUEST_TEMPLATE.md`: title в Conventional Commits формате на английском, описание и чеклист — на русском.

## Архитектура

Подробные dependency rules, runtime flow и примеры расширения описаны в `.ai-factory/ARCHITECTURE.md`.

**Паттерн:** Modular Laravel Runtime с Manifest Layer и Loader-Pipeline.
