[← Architecture](architecture.md) · [Back to README](../README.md) · [Feature Toggles →](feature-toggles.md)

# Loaders

Лоадеры — это то, что превращает папки внутри модуля в зарегистрированные
Laravel-артефакты: конфиги, провайдеры, миграции, фабрики, переводы, вьюхи,
Blade-компоненты, listeners, observers, policies, команды, middleware, маршруты и
broadcast-каналы. Каждый лоадер отвечает за один тип артефакта и одно соглашение о
папке. Этот документ — справочник по всем 15 дефолтным лоадерам, их соглашениям и
условиям пропуска, плюс гайд по написанию собственного.

Высокоуровневый обзор pipeline и dependency flow — в [Architecture](architecture.md);
соответствие папок модуля лоадерам с точки зрения автора модуля — в
[Module Structure](module-structure.md). Здесь — поведенческие детали каждого лоадера.

## Как работает загрузка

`ModuleLoaderServiceProvider::boot()` собирает все лоадеры, помеченные тегом
`laravel-modules.loaders`, в `ModuleLoaderPipeline`. Pipeline:

1. **Сортирует лоадеры по `priority()`** (по возрастанию; меньшее значение — раньше).
   Тай-брейк — порядок регистрации, поэтому очередь детерминирована.
2. **Применяет каждый лоадер к каждому включённому модулю** из
   `ModuleRegistryInterface::all()` (порядок модулей — топологический, по графу
   зависимостей). Отключённые модули пропускаются ещё до pipeline.
3. **Изолирует ошибки**: исключение в одном `load()` ловится, считается как `failed`
   и передаётся в `ModuleDiagnosticsInterface` — но не останавливает остальные
   лоадеры или модули.
4. **Собирает `LoadReport` от каждого `(loader × module)`** и скармливает их
   инъецированному `ModuleDiagnosticsInterface` (см. [Logging](logging.md)).

Итоговая `PipelineRunSummary` (число модулей, лоадеров, applied/skipped/failed,
длительность) эмитится одним событием `pipeline.finished`.

## Контракт лоадера

Контракт намеренно тонкий — два метода:

```php
namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface LoaderInterface
{
    public function load(Module $module): LoadReport;

    public function priority(): int;
}
```

- `load(Module $module)` выполняет регистрацию артефактов модуля и возвращает
  структурный `LoadReport` (applied или skipped). Никогда не возвращает `void` —
  отчёт является субстратом диагностики.
- `priority()` определяет место в очереди. Дефолтные значения см. ниже; держите
  кастомные лоадеры в свободных промежутках (например, 11–19 после конфигов).

Все дефолтные лоадеры — `final` с конструкторным DI (без фасадов/хелперов); все,
кроме `FactoryLoader`, ещё и `readonly` — он накапливает мутабельное состояние
(маппинг namespace'ов и однократную регистрацию глобального резолвера, см.
[Поведенческие нюансы](#поведенческие-нюансы)). Зависимости — типизированные
контракты Laravel (`Filesystem`, `Router`, `Repository`, `Application`) и
`ModuleLayout` для резолва путей и namespace'ов.

## Модель отчёта: `LoadReport`

Каждая попытка загрузки заканчивается одним из двух терминальных статусов
(`LoadStatus`):

| Статус | Когда | Несёт |
|--------|-------|-------|
| `Applied` | precondition выполнен; лоадер что-то зарегистрировал (или это безобидный no-op) | `artifacts: array<string, list<string>>` — категория ⇒ список basename'ов или зарегистрированный относительный путь |
| `Skipped` | precondition отсутствует (нет папки/файла/кэш/не консоль) | `reason: SkipReason`; artifacts пустой |

Инварианты enforced в конструкторе: applied не несёт `reason`; skipped несёт `reason`
и не несёт artifacts. Брошенное исключение — **не** статус: изоляция ошибок в
pipeline учитывает его отдельно (`failed`), ортогонально этому enum.

`LoadReport::toArray()` отдаёт whitelisted-проекцию (статус, опциональный reason,
basename'ы artifacts) — безопасно писать в лог-канал.

### Каталог причин пропуска (`SkipReason`)

| Причина | Значение | Когда |
|---------|----------|-------|
| `NoDirectory` | `no_directory` | Соглашательная папка отсутствует (большинство лоадеров). |
| `EmptyDirectory` | `empty_directory` | Папка есть, но в ней нет подходящих файлов. Репортят **только** лоадеры, перечисляющие содержимое сами: Config, Middleware, Observer, Policy, Route. |
| `FileNotFound` | `file_not_found` | Отсутствует один ожидаемый файл (Broadcast `channels.php`, ConsoleRoute `console.php`). |
| `RoutesCached` | `routes_cached` | Host закэшировал маршруты — RouteLoader устраняется. |
| `NotRunningInConsole` | `not_running_in_console` | Console-only лоадер вызван вне CLI (Command, ConsoleRoute). |

Лоадеры, регистрирующие папку/namespace в Laravel без перечисления её содержимого
(Migration, Event, Command, Factory, BladeComponent, ServiceProvider), на пустой
папке всё равно отдают `Applied` — пропускать было нечего, precondition (папка)
выполнен.

## Справочник дефолтных лоадеров

Порядок — по `priority()`. Все пути относительны корня модуля; namespace'ы
резолвятся через `ModuleLayout` из `meta.name`/namespace модуля.

| Priority | Лоадер | Папка / файл | Что делает | Причины пропуска |
|----------|--------|--------------|------------|------------------|
| 10 | `ConfigLoader` | `Config/*.php` | Мёржит каждый файл в host-конфиг под ключом `<module>.<config-name>` | `NoDirectory`, `EmptyDirectory` |
| 20 | `ServiceProviderLoader` | `Providers/*ServiceProvider.php` | Регистрирует каждый provider модуля по namespace | `NoDirectory` (пустая папка ⇒ applied no-op) |
| 30 | `MigrationLoader` | `Database/Migrations/` | Добавляет путь миграций в Laravel migrator | `NoDirectory` |
| 31 | `FactoryLoader` | `Database/Factories/` | Регистрирует guess-резолвер фабрик и маппинг model-namespace ⇒ factory-namespace | `NoDirectory` |
| 32 | `LangLoader` | `Lang/` | Регистрирует translation namespace `<module_name>` | `NoDirectory` |
| 33 | `ViewLoader` | `Resources/views/` | Регистрирует view namespace `<module_name>` | `NoDirectory` |
| 34 | `BladeComponentLoader` | `View/Components/` | Регистрирует Blade-component namespace модуля | `NoDirectory` |
| 35 | `EventLoader` | `Domain/Listeners/` | Добавляет путь в event discovery Laravel | `NoDirectory` |
| 36 | `ObserverLoader` | `Domain/Observers/*Observer.php` | Привязывает observer к одноимённой модели через `Model::observe()` | `NoDirectory`, `EmptyDirectory` |
| 37 | `PolicyLoader` | `Domain/Policies/*Policy.php` | Регистрирует policy для одноимённой модели через `Gate::policy()` | `NoDirectory`, `EmptyDirectory` |
| 40 | `CommandLoader` | `Console/Commands/` | Регистрирует команды модуля через kernel (только в CLI) | `NotRunningInConsole`, `NoDirectory` |
| 45 | `MiddlewareLoader` | `Http/Middleware/*.php` | Регистрирует alias `<module>.<snake_class>` для каждого класса | `NoDirectory`, `EmptyDirectory` |
| 50 | `RouteLoader` | `Routes/<type>.php` | Грузит каждый `<type>` из `modules.routing.types` с его attributes | `RoutesCached`, `NoDirectory`, `EmptyDirectory` |
| 51 | `ConsoleRouteLoader` | `Routes/console.php` | Добавляет console-route path (только в CLI) | `NotRunningInConsole`, `FileNotFound` |
| 52 | `BroadcastLoader` | `Routes/channels.php` | `require`-ит файл broadcast-каналов | `FileNotFound` |

### Поведенческие нюансы

- **`ConfigLoader`** мёржит под namespace модуля (`config('<module>.<file>')`), а не в
  глобальный плоский ключ — конфиги модулей не конфликтуют между собой.
- **`FactoryLoader`** регистрирует глобальный резолвер `Factory::guessFactoryNamesUsing()`
  **один раз**, но накапливает маппинг для каждого модуля с папкой `Database/Factories/`.
  Предыдущий резолвер сохраняется и делегируется для неотносящихся моделей.
- **`ObserverLoader` / `PolicyLoader`** работают по конвенции имён: `PostObserver.php` /
  `PostPolicy.php` → модель `Post` в namespace моделей модуля. Связывание происходит,
  только если оба класса существуют и observer/policy не абстрактный; `ObserverLoader`
  дополнительно требует, чтобы модель была наследником Eloquent `Model`. Несовпадение
  тихо пропускается (файл всё равно попадёт в `applied`-artifacts, но связь не создаётся).
- **`MiddlewareLoader`** строит alias как `<module_name>.<snake_case_класса>` — например
  `blog.ensure_subscribed` для `Blog/Http/Middleware/EnsureSubscribed.php`.
- **`RouteLoader`** полностью config-driven: каждый ключ `modules.routing.types.<type>`
  даёт файл `Routes/<type>.php` с его `prefix`/`middleware`/прочими group-attributes.
  Версионирование — обычный flat-тип (`api_v1` → `Routes/api_v1.php`). Тип `inertia`
  тихо опускается, если Inertia не установлена (остальные типы грузятся, модуль
  отчитывается `applied`). При закэшированных маршрутах лоадер устраняется целиком.
- **`CommandLoader` / `ConsoleRouteLoader`** активны только в CLI — вне консоли
  немедленно отдают `NotRunningInConsole`.

## Собственный лоадер

Реализуйте `LoaderInterface`, верните `LoadReport` и зарегистрируйте класс под тегом
`ModuleLoaderServiceProvider::LOADER_TAG` в service provider host-приложения.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Support;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Filesystem\Filesystem;

final readonly class GraphQlSchemaLoader implements LoaderInterface
{
    public function __construct(
        private Filesystem $filesystem,
    ) {}

    public function load(Module $module): LoadReport
    {
        $dir = $module->path . '/GraphQL';

        if (! $this->filesystem->isDirectory($dir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $files = $this->filesystem->glob($dir . '/*.graphql');

        if ($files === []) {
            return LoadReport::skipped(SkipReason::EmptyDirectory);
        }

        // ... зарегистрируйте схемы в вашем GraphQL-рантайме ...

        return LoadReport::applied(['graphql' => array_map('basename', $files)]);
    }

    public function priority(): int
    {
        // Свободный слот между Config (10) и ServiceProvider (20).
        return 15;
    }
}
```

Регистрация в host-провайдере:

```php
use App\Modules\Support\GraphQlSchemaLoader;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;

public function register(): void
{
    $this->app->singleton(GraphQlSchemaLoader::class);
    $this->app->tag([GraphQlSchemaLoader::class], ModuleLoaderServiceProvider::LOADER_TAG);
}
```

**Гайдлайны:**

- Регистрируйте лоадер как `singleton` (не `bind`) — лоадеры, накапливающие
  состояние между вызовами `load()`, иначе сломаются.
- Возвращайте `LoadReport::skipped(...)` только при отсутствующем precondition;
  фактический сбой регистрации — это исключение (pipeline учтёт его как `failed`),
  а не skip.
- Корень модуля — `$module->path`; namespace'ы и стандартные подпапки резолвьте
  через инъецированный `ModuleLayout`, не хардкодьте сегменты.
- Соблюдайте арх-инварианты пакета: `final readonly`, `declare(strict_types=1)`,
  конструкторный DI, без фасадов/глобальных хелперов.

## See Also

- [Architecture](architecture.md) — pipeline, registry, cache и dependency flow.
- [Module Structure](module-structure.md) — соглашения о папках с точки зрения автора модуля.
- [Configuration](configuration.md) — `modules.routing.types` и прочие опции.
- [Logging](logging.md) — события `pipeline.finished` и диагностика `LoadReport`.
