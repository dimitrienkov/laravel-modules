# Laravel Modules

## Обзор

`dimitrienkov0/laravel-modules` — Laravel-пакет для manifest-driven модульной архитектуры. Пакет загружает модули из настроенных директорий хост-приложения, читает `module.json`, строит dependency-aware registry, применяет loader-pipeline и предоставляет runtime API для feature toggles без БД.

Текущий реализованный срез — **v2.0 core**: контракты, manifest VO, registry/cache, 15 лоадеров (полный loader pipeline), scoped `FeatureRepository`, optimizer-команды, lifecycle UseCase-классы и Artisan-команды (`make:module`, `modules:install/update/remove/enable/disable/list/optimize/optimize-clear`), support-сервисы (`ZipExtractor`, `ModuleSourcePreparer`, `ModuleDependencyGuard`, `ModuleDirectoryOperations`, `LifecycleRegistryInvalidator`, `ModuleDirectoryPaths`, `ModuleSkeletonBuilder`, `PartialModuleRollback`, `ModuleStatePaths`), optional bridges для MoonShine/Inertia и module-aware генераторы (native `make:* --module` для 22 артефактов через `ModuleAwareGenerator` trait + rebind parent-FQCN singleton'ов; архитектурные `make:use-case/action/query/dto/vo`; component-driven `make:module --with`). Полноценный MoonShine admin-UI остаётся roadmap-задачей следующих фаз.

## Целевые сценарии

- Команда поставляет разные наборы модулей разным заказчикам.
- Модуль является самостоятельной директорией с `module.json`, кодом, миграциями, конфигами, роутами и feature settings.
- `module.json` — immutable manifest, содержит только `meta` и `settings.schema`. Mutable state (`enabled`, `installed_at`, `updated_at`) и explicit feature values хранятся отдельно в `state.json` в приватном хранилище хоста (`storage/app/private/modules/{module-name}/state.json`).
- Хост-приложение включает/отключает модули через `state.enabled` в `state.json`.
- Фичетоглы и настройки определяются в `settings.schema` (immutable, в `module.json`), а их runtime-значения читаются из `state.json` через `ModuleStateRepositoryInterface`.
- Production-приложение может прогреть registry через `modules:optimize`, а runtime feature values и state остаются актуальными со следующего request (читаются из `state.json`, не из cache).
- Хост может работать без MoonShine и Inertia; optional integrations активируются только при наличии соответствующих пакетов.
- Пакет должен оставаться совместимым с Octane: без глобального mutable-state и без stale values между request.
- Source-модули НЕ ДОЛЖНЫ содержать `state.json` — он создаётся и управляется хост-приложением.

## Текущий статус

### Реализовано

- Manifest contract: immutable `module.json` с обязательным top-level `schema_version` (integer, strict-fail), `meta` (включая обязательный `kind` и опциональный `group`) и `settings.schema`; секции `state`, `settings.values` и `autoload` запрещены в manifest.
- Отдельное state-хранилище: `state.json` в `storage/app/private/modules/{module-name}/` содержит `enabled`, `installed_at`, `updated_at`, `settings.values` и опциональную секцию `source` (provenance: `kind`, `installed_version`, `checksum`).
- Value objects и parser layer: `Module`, `ManifestMeta`, `ModuleState`, `ModuleStateDocument`, `ModuleDependencies`, `ModuleDependency`, `ModuleOrigin`, `FeatureSchema`, `FeatureDefinition`, `FeatureValues`. Enums: `FeatureType`, `ModuleKind` (`module`, `subsystem`, `integration`), `ModuleOriginKind` (`local`, `zip`).
- `ModuleManifestRepository` с методами `load()` и `writeManifest()` — единственная точка чтения/валидации/записи immutable `module.json`.
- `ModuleStateRepositoryInterface` и `ModuleStateRepository` — чтение/запись mutable `state.json` (read, readState, readValues, writeDocument, writeState, writeValues, delete, moveToBackup, exists).
- `ModuleStatePaths` — резолвинг путей state-файлов из конфига `modules.paths.state`.
- `ModuleDirectoryScanner`, `ModuleRegistry`, `ModuleRegistryCache`, `TopologicalSorter`, `ApplicationNamespaceResolver`.
- Support-утилиты: `AtomicFileWriter` (shared atomic write), `AtomicJsonWriter` (JSON-обёртка), `ContainerLifecycleHooks` (safe `callAfterResolving`), `ModuleLayout` (пути и namespaces модуля), `ModuleFileNames` (константы имён файлов), `ModulePermissions` (константы прав доступа), `PathNormalizer` (нормализация путей), `ZipExtractor`.
- Loader pipeline с 15 реализованными лоадерами: `ConfigLoader`, `ServiceProviderLoader`, `MigrationLoader`, `FactoryLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader`, `EventLoader`, `ObserverLoader`, `PolicyLoader`, `CommandLoader`, `MiddlewareLoader`, `RouteLoader`, `ConsoleRouteLoader`, `BroadcastLoader`.
- `FeatureRepositoryInterface` с методами `get`, `getBool`, `getInt`, `getString`; реализация биндится как scoped. `FeatureRepository` читает values через `ModuleStateRepositoryInterface::readValues()`.
- Команды `modules:optimize` и `modules:optimize-clear`, интегрированные с Laravel optimizer hooks.
- Lifecycle UseCase-классы и Artisan-команды: enable/disable/settings writes меняют только `state.json`; scaffold/install/update пишут immutable descriptor через `ModuleManifestRepositoryInterface::writeManifest()`.
- Registry cache (v4) кеширует только manifest descriptors (`meta` + `settings.schema`) и `load_order`; state и values НЕ кешируются — читаются свежими из `state.json` при каждом request.
- Optional MoonShine bridge через `MoonShineModuleAutoloader`.
- Optional Inertia routes: `Routes/inertia.php` загружается только при наличии Inertia.
- Pest architecture suite и PHPUnit unit/feature coverage для ядра.
- PR-шаблон `.github/PULL_REQUEST_TEMPLATE.md` с quality checklist.

### Roadmap, не текущий runtime

- MoonShine resources/pages для управления модулями и settings forms.
- Packaging/marketplace/signature flow для коммерческой поставки.
- Lifecycle events (`ModuleInstalled`, `ModuleEnabled`, etc.).

## Технологический стек

- **PHP:** 8.3+
- **Laravel:** 12 / 13
- **Runtime dependencies:** `composer/semver`, `psr/log`, `ext-mbstring`, `ext-zip`
- **Optional integrations:** MoonShine 4, Inertia 2
- **Manifest storage:** immutable `module.json` (meta + schema) + mutable `state.json` (state + values) в `storage/app/private/modules/`, без БД
- **Dependency management между модулями:** Composer SemVer constraints в `meta.dependencies`
- **Кодовый стиль:** PER Coding Style 3.0 как целевой стандарт; PHP-CS-Fixer хранит применяемый formatter profile
- **Статический анализ:** PHPStan 2 level 8 + Larastan 3, `treatPhpDocTypesAsCertain: false`
- **Рефакторинг:** Rector 2 + Laravel rules
- **Тесты:**
  - Pest 3 + `pestphp/pest-plugin-arch` для `tests/Architecture/`
  - PHPUnit 11/12 + Orchestra Testbench 10/11 для `tests/Unit/` и `tests/Feature/`
  - Mockery для test doubles

## Manifest contract

Каждый модуль должен иметь `module.json` в корне. `module.json` — immutable: разрешены только top-level ключи `schema_version`, `meta` и `settings` (только `schema`). Ключи `state`, `settings.values`, `autoload` и неизвестные ключи считаются ошибкой.

```json
{
  "schema_version": 1,
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "kind": "module",
    "group": "content",
    "description": "Corporate blog with comments",
    "version": "1.0.0",
    "author": "Acme Studio",
    "license": "proprietary",
    "dependencies": {
      "users": "^1.5",
      "media": ">=1.4 <3.0"
    }
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
    }
  }
}
```

Mutable state и explicit feature values хранятся в отдельном файле `storage/app/private/modules/blog/state.json`:

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00",
  "updated_at": "2026-05-23T14:12:00+00:00",
  "source": {
    "kind": "zip",
    "installed_version": "1.0.0",
    "checksum": "e3b0c44298fc1c149afbf4c8996fb924..."
  },
  "settings": {
    "values": {
      "enable_comments": false,
      "max_posts_per_page": 50
    }
  }
}
```

Правила:

- `schema_version` — обязательный top-level integer, версия формата manifest. Текущая версия: `1`. Неизвестная версия → `InvalidManifestException` (strict-fail, без fallback).
- `meta.name` — canonical module name; `display_name` опционален и fallback'ается на `name`.
- `meta.kind` — обязательный, backed string enum `ModuleKind` (`module`, `subsystem`, `integration`). Чисто презентационный: не влияет на loader pipeline, dependency resolution или enable/disable. При scaffold infer'ится из целевой директории (`Modules→module`, `Integrations→integration`, `Subsystems→subsystem`).
- `meta.group` — опциональный string, kebab-case (`/^[a-z][a-z0-9-]*$/`). Логическая группировка модулей для отображения (`modules:list`) и конфигурации (`modules.groups`). Не влияет на loader pipeline и dependency resolution.
- `meta.dependencies` принимает только объект `moduleName => Composer constraint`; wildcard constraint записывается явно как `"*"`.
- `settings.schema` поддерживает типы `bool`, `int`, `string`, `enum`.
- `settings.values` в `state.json` хранит только явные override-значения; defaults остаются в schema (`module.json`) и не записываются как values.
- `source` — опциональная секция в `state.json`. `ModuleOrigin` VO содержит `kind` (enum `ModuleOriginKind`: `local`, `zip`), `installed_version` и опциональный `checksum`. Записывается lifecycle UseCase-классами (scaffold, install, update) для фиксации provenance. Не влияет на loader pipeline и registry cache.
- `FeatureValues` валидирует значения против schema при чтении и записи.
- Source-модуль НЕ ДОЛЖЕН содержать `state.json` — он принадлежит приватному хранилищу хоста.
- `state.json` управляется через `ModuleStateRepositoryInterface`; enable/disable/settings writes модифицируют только `state.json`, а scaffold/install/update пишут `module.json` через `ModuleManifestRepositoryInterface::writeManifest()`.
- Конфиг `modules.paths.state` задаёт корневую директорию state-хранилища (по умолчанию `storage/app/private/modules`).

## Loader pipeline

`ModuleLoaderServiceProvider` регистрирует default loaders как tagged services и при boot создаёт `ModuleLoaderPipeline`. Pipeline сортирует лоадеры по `priority()` и запускает каждый loader для каждого enabled-модуля из `ModuleRegistryInterface::all()`, где порядок уже dependency-aware.

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
| `RouteLoader` | 50 | `Routes/<type>.php` для каждого `modules.routing.types` (`Routes/api.php`, `Routes/web.php`, `Routes/inertia.php`, …) |
| `ConsoleRouteLoader` | 51 | `Routes/console.php` через `addCommandRoutePaths()` (deferred) |
| `BroadcastLoader` | 52 | `Routes/channels.php` broadcast channels (deferred до boot) |

MoonShine autoload не является `LoaderInterface`: это отдельный optional bridge, который регистрируется через package-style `callAfterResolving(CoreContract::class)` и вызывает `$core->autoload($module->namespace)` для enabled-модулей.

## Routing

Route loading управляется `config/modules.php`:

- `RouteLoader` полностью config-driven: для каждого `<type>` из `modules.routing.types` грузится `Routes/<type>.php` с его prefix/middleware.
- Версионирование — это обычный flat-тип: профиль `api_v1` (`prefix => 'api/v1'`, `middleware => ['api_v1']`) → `Routes/api_v1.php`. Middleware-группу `api_v1` host-приложение объявляет в `bootstrap/app.php`.
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

Registry cache (v4) кеширует manifest descriptors (`meta` + `settings.schema`), path, namespace и `load_order`. State и feature values НЕ кешируются — при загрузке из cache `ModuleRegistryCache` пересобирает `Module` из descriptor и дочитывает актуальный state из `state.json` через `ModuleStateRepositoryInterface::readState()`.

`modules:optimize` пишет cache payload с версией формата (v4), serialized manifest descriptors и `load_order`. `modules:optimize-clear` удаляет cache и сбрасывает in-memory registry.

## Feature toggles

Публичный API:

- `FeatureRepositoryInterface::get(string $moduleName, string $key): bool|int|string`
- `getBool(...)`
- `getInt(...)`
- `getString(...)`

`FeatureRepository` биндится через `$this->app->scoped()`. В пределах одного request он кеширует `FeatureValues` по имени модуля, но при новом request перечитывает values из `state.json` через `ModuleStateRepositoryInterface::readValues()`. Production registry cache не используется для feature values, поэтому изменения settings в `state.json` применяются без `modules:optimize-clear`.

## Архитектура модуля в host-приложении

Текущий core имеет runtime behavior для следующих частей модуля:

```
app/Modules/Blog/
├── Console/
│   └── Commands/
├── Config/
│   └── blog.php
├── Database/
│   ├── Factories/
│   └── Migrations/
├── Domain/
│   ├── Listeners/
│   ├── Models/
│   ├── Observers/
│   └── Policies/
├── Http/
│   └── Middleware/
├── Lang/
├── Providers/
│   └── BlogServiceProvider.php
├── Resources/
│   └── views/
├── Routes/
│   ├── api.php
│   ├── api/
│   │   ├── v1.php
│   │   └── v2.php
│   ├── channels.php
│   ├── console.php
│   ├── web.php
│   └── inertia.php
├── View/
│   └── Components/
└── module.json
```

State хранится отдельно от модуля:

```
storage/app/private/modules/blog/
└── state.json
```

`ModuleLayout` формирует все runtime-пути модуля, а `ModuleStatePaths` резолвит пути state-файлов. Loader applicability остаётся convention-based: loader делает ранний return, если нужного файла или директории нет.

## Нефункциональные требования

- **Octane safety:** runtime-сервисы с per-request cache должны быть scoped; singleton-сервисы не должны накапливать mutable runtime state между request.
- **Атомарность:** запись `module.json` и `state.json` идёт через `AtomicJsonWriter` (делегирует в `AtomicFileWriter`) с lock, temp file, `rename`; production cache пишется через lock/temp/flush/atomic rename.
- **Детерминированность:** manifest schema, dependencies, feature definitions и cache payload сортируются там, где это важно для стабильного результата.
- **Безопасность manifest:** неизвестные ключи запрещены; `autoload` запрещён как legacy-механика; `state` и `settings.values` запрещены в `module.json`.
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
