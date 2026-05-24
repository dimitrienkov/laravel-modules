# Phase 2: New Loaders

**Branch:** `feature/phase-2-new-loaders`
**Date:** 2026-05-24
**Base:** `main`

## Settings

| Setting | Value |
|---------|-------|
| Testing | Yes, unit tests per loader |
| Logging | No logging |
| Docs | Warn-only |

## Roadmap Linkage

**Milestone:** "Фаза 2 — Новые лоадеры"
**Rationale:** Первый и самый объёмный пункт Фазы 2. Все 10 лоадеров из roadmap: Lang, View, BladeComponent, Command, ConsoleRoute, Event, Observer, Policy, Middleware, Broadcast. Закрытие этого milestone покрывает весь runtime loader pipeline, после чего `ModuleLayout` полностью обеспечен реализациями.

## Context

### Текущее состояние

Фаза 1 реализовала 5 базовых лоадеров: `ConfigLoader` (10), `ServiceProviderLoader` (20), `MigrationLoader` (30), `FactoryLoader` (31), `RouteLoader` (50). Все следуют единому паттерну:

- `final readonly class` (или `final class` при mutable state)
- Implements `LoaderInterface` (`load(Module): void`, `priority(): int`)
- Constructor injection: `Filesystem`, `ModuleLayout`, + framework-specific contracts
- Early return при отсутствии директории/файла
- Регистрация в `ModuleLoaderServiceProvider::DEFAULT_LOADERS`
- Тегирование через `LOADER_TAG = 'laravel-modules.loaders'`

`ModuleLayout` уже предоставляет почти все необходимые пути: `langDir()`, `viewsDir()`, `bladeComponentsDir()`, `commandsDir()`, `consoleRoutesFile()`, `channelsFile()`, `observersDir()`, `policiesDir()`, `middlewareDir()`. Для `EventLoader` нужно добавить `listenersDir()` с convention `Domain/Listeners`.

### Laravel 12/13 API notes

- Event discovery в Laravel 12/13 включается через `Application::configure()->withEvents()`; стандартный путь — `app/Listeners`, дополнительные пути добавляются через `bootstrap/app.php ->withEvents(discover: [...])`, что внутри использует `Illuminate\Foundation\Support\Providers\EventServiceProvider::setEventDiscoveryPaths()` / `addEventDiscoveryPaths()`.
- Для модулей не нужен отдельный `Providers/EventServiceProvider.php` как обязательный convention. `EventLoader` должен добавлять module listener directories в discovery paths, а обычные provider-файлы остаются ответственностью `ServiceProviderLoader`.
- Console commands и console route files в Laravel 12/13 регистрируются через console kernel (`addCommands()`, `addCommandRoutePaths()`), а не через фасад `Artisan` в runtime-коде пакета.
- В `src/` остаётся запрет на Laravel facades; модульные файлы вроде `Routes/channels.php` или `Routes/console.php` могут использовать Laravel facade API, но package loader должен только подключать/регистрировать эти файлы через DI.

### Паттерн unit-тестов

Все существующие loader-тесты в `tests/Unit/Loaders/` следуют одному шаблону:

- `final class XxxLoaderTest extends TestCase` (PHPUnit, не Orchestra)
- `setUp`: temp dir через `sys_get_temp_dir() . '/laravel-modules-xxx-' . bin2hex(random_bytes(6))`
- `tearDown`: рекурсивное удаление temp dir через `deleteDirectory()`
- `#[Test]` атрибуты, имена `it_does_x_when_y`
- Реальные `Filesystem` + `ModuleLayout`, `ModuleFactory::make()` для VO
- Assertions на side effects (config values, registered namespaces, etc.)

### Priority map

| Loader | Priority | Тип |
|--------|----------|-----|
| ConfigLoader | 10 | existing |
| ServiceProviderLoader | 20 | existing |
| MigrationLoader | 30 | existing |
| FactoryLoader | 31 | existing |
| **LangLoader** | **32** | namespace-based |
| **ViewLoader** | **33** | namespace-based |
| **BladeComponentLoader** | **34** | namespace-based |
| **EventLoader** | **35** | event-discovery |
| **ObserverLoader** | **36** | convention-based |
| **PolicyLoader** | **37** | convention-based |
| **CommandLoader** | **40** | class-discovery |
| **MiddlewareLoader** | **45** | class-discovery |
| RouteLoader | 50 | existing |
| **ConsoleRouteLoader** | **51** | file-require |
| **BroadcastLoader** | **52** | file-require |

MiddlewareLoader (45) должен быть до RouteLoader (50), т.к. роуты ссылаются на middleware aliases.

## Tasks

### Группа A: Namespace-based лоадеры

#### Task 1: LangLoader + LangLoaderTest
- **Files:** `src/Loaders/LangLoader.php`, `tests/Unit/Loaders/LangLoaderTest.php`
- **Priority:** 32
- **DI:** `Filesystem`, `ModuleLayout`, concrete `Illuminate\Translation\Translator` или `Illuminate\Contracts\Translation\Loader`. Не использовать `Illuminate\Contracts\Translation\Translator`: в контракте нет `addNamespace()` / `getLoader()`.
- **Logic:** `$translator->addNamespace(Str::snake($module->name), $langDir)` либо `$loader->addNamespace(Str::snake($module->name), $langDir)`. Namespace = `Str::snake()` по roadmap.
- **Guard:** `$this->filesystem->isDirectory($langDir)` → early return
- **Test:** Создать `Lang/en/messages.php`, вызвать `load()`, проверить namespaces через concrete loader (`$translator->getLoader()->namespaces()` или напрямую `Loader::namespaces()`). Добавить early-return test для отсутствующего `Lang/`.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 2: ViewLoader + ViewLoaderTest
- **Files:** `src/Loaders/ViewLoader.php`, `tests/Unit/Loaders/ViewLoaderTest.php`
- **Priority:** 33
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\Contracts\View\Factory`
- **Logic:** `$viewFactory->addNamespace($module->name, $viewsDir)`
- **Guard:** `$this->filesystem->isDirectory($viewsDir)` → early return
- **Test:** Создать `Resources/views/index.blade.php`, вызвать `load()`, проверить hints через concrete `Illuminate\View\Factory` + finder. Добавить early-return test для отсутствующего `Resources/views/`.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 3: BladeComponentLoader + BladeComponentLoaderTest
- **Files:** `src/Loaders/BladeComponentLoader.php`, `tests/Unit/Loaders/BladeComponentLoaderTest.php`
- **Priority:** 34
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\View\Compilers\BladeCompiler`
- **Logic:** `$blade->componentNamespace($module->namespace . '\\View\\Components', $module->name)`
- **Guard:** `$this->filesystem->isDirectory($bladeComponentsDir)` → early return
- **Test:** Создать `View/Components/` dir, вызвать `load()`, проверить `$blade->getClassComponentNamespaces()` содержит namespace модуля. Добавить early-return test для отсутствующего `View/Components/`.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

### Группа B: Framework-registered route files

#### Task 4: ConsoleRouteLoader + ConsoleRouteLoaderTest
- **Files:** `src/Loaders/ConsoleRouteLoader.php`, `tests/Unit/Loaders/ConsoleRouteLoaderTest.php`
- **Priority:** 51
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\Contracts\Foundation\Application`
- **Logic:** Не делать прямой `require`. Повторить Laravel 12/13 pattern из `ApplicationBuilder::withCommandRouting()`: если приложение в console mode, файл существует, то через `$app->afterResolving(ConsoleKernel::class, ...)` зарегистрировать `$kernel->addCommandRoutePaths([$consoleRoutesFile])`, лучше внутри `$app->booted(...)`.
- **Guard:** `! $app->runningInConsole()` или `! $this->filesystem->exists($consoleRoutesFile)` → early return
- **Test:** Создать `Routes/console.php`, вызвать `load()`, проверить что fake/recording console kernel получил путь через `addCommandRoutePaths()`. Добавить early-return tests для missing file и non-console mode.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 5: BroadcastLoader + BroadcastLoaderTest
- **Files:** `src/Loaders/BroadcastLoader.php`, `tests/Unit/Loaders/BroadcastLoaderTest.php`
- **Priority:** 52
- **DI:** `Filesystem`, `ModuleLayout`
- **Logic:** `require $this->layout->channelsFile($module)` если файл существует
- **Guard:** `$this->filesystem->exists($channelsFile)` → early return
- **Test:** Создать `Routes/channels.php` с side-effect маркером, вызвать `load()`, проверить side-effect. Добавить early-return test для отсутствующего файла.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

### Группа C: Class-discovery лоадеры

#### Task 6: CommandLoader + CommandLoaderTest
- **Files:** `src/Loaders/CommandLoader.php`, `tests/Unit/Loaders/CommandLoaderTest.php`
- **Priority:** 40
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\Contracts\Foundation\Application`
- **Logic:** Glob `commandsDir()/*.php`, отсортировать файлы, для каждого файла вычислить FQCN = `$module->namespace . '\\Console\\Commands\\' . basename($file, '.php')`, проверить `class_exists($fqcn)`, `is_subclass_of($fqcn, Command::class)` и что reflection class не abstract. Собрать валидные команды и зарегистрировать через `$app->afterResolving(ConsoleKernel::class, ...)` + `$kernel->addCommands($commands)`. Не использовать `Artisan` facade в `src/`.
- **Guard:** `! $app->runningInConsole()` или `! $this->filesystem->isDirectory($commandsDir)` → early return
- **Test:** Создать `Console/Commands/` с валидным command-классом и invalid/abstract классами, вызвать `load()`, проверить что fake/recording console kernel получил только валидный command. Добавить early-return tests для missing dir и non-console mode.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 7: MiddlewareLoader + MiddlewareLoaderTest
- **Files:** `src/Loaders/MiddlewareLoader.php`, `tests/Unit/Loaders/MiddlewareLoaderTest.php`
- **Priority:** 45
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\Routing\Router`
- **Logic:** Glob `middlewareDir()/*.php`, отсортировать файлы, для каждого файла вычислить FQCN = `$module->namespace . '\\Http\\Middleware\\' . basename($file, '.php')`, проверить `class_exists($fqcn)`, зарегистрировать `$router->aliasMiddleware($alias, $fqcn)`. Alias = `$module->name . '.' . Str::snake(basename($file, '.php'))` (например `blog.check_age`)
- **Guard:** `$this->filesystem->isDirectory($middlewareDir)` → early return
- **Test:** Создать `Http/Middleware/CheckAge.php`, вызвать `load()`, проверить что `$router->getMiddleware()` содержит alias. Добавить tests для missing dir и PHP-файла без существующего/autoloadable класса.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

### Группа D: Convention-based лоадеры

#### Task 8: EventLoader + EventLoaderTest
- **Files:** `src/Loaders/EventLoader.php`, `src/Support/ModuleLayout.php`, `tests/Unit/Loaders/EventLoaderTest.php`, `tests/Unit/Support/ModuleLayoutTest.php`
- **Priority:** 35
- **DI:** `Filesystem`, `ModuleLayout`
- **Layout:** Добавить `ModuleLayout::listenersDir(Module $module): string`, convention path = `Domain/Listeners`.
- **Scope:** Это loader для module listeners. Отдельный `ListenerLoader`, который вручную требует или регистрирует каждый listener-класс, не нужен: Laravel event discovery сам найдёт public `handle*` / `__invoke` методы после добавления директории в discovery paths.
- **Logic:** Если `Domain/Listeners/` существует, вызвать `Illuminate\Foundation\Support\Providers\EventServiceProvider::addEventDiscoveryPaths($listenersDir)`. Не регистрировать `Providers/EventServiceProvider.php`: обычные provider-файлы остаются ответственностью `ServiceProviderLoader`.
- **Guard:** `$this->filesystem->isDirectory($listenersDir)` → early return
- **Test:** Создать `Domain/Listeners/`, вызвать `load()`, проверить что discovery path попадает в Laravel EventServiceProvider discovery до `discoverEvents()`. Добавить early-return test для отсутствующей директории и сбросить static discovery paths в `tearDown`, чтобы тесты не протекали между собой. Для production event cache помнить: новые/изменённые listeners попадают в cache после стандартного `event:cache` / `optimize`.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 9: ObserverLoader + ObserverLoaderTest
- **Files:** `src/Loaders/ObserverLoader.php`, `tests/Unit/Loaders/ObserverLoaderTest.php`
- **Priority:** 36
- **DI:** `Filesystem`, `ModuleLayout`
- **Logic:** Glob `observersDir()/*Observer.php`, для каждого файла:
  - FQCN observer = `$module->namespace . '\\Domain\\Observers\\' . basename($file, '.php')`
  - Model name = suffix-only strip: удалить только конечный `Observer` из basename, не использовать широкий `str_replace()`
  - FQCN model = `$module->namespace . '\\Domain\\Models\\' . $modelName`
  - Если `class_exists($observerFqcn) && class_exists($modelFqcn) && is_subclass_of($modelFqcn, Model::class)` → `$modelFqcn::observe($observerFqcn)`
  - Молча пропускать если model/observer class не существуют или model не Eloquent model
- **Guard:** `$this->filesystem->isDirectory($observersDir)` → early return
- **Test:** Создать `Domain/Observers/PostObserver.php` + stub Eloquent model, проверить что `observe()` вызван. Добавить tests для missing dir, missing model, missing observer class и non-Eloquent model.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

#### Task 10: PolicyLoader + PolicyLoaderTest
- **Files:** `src/Loaders/PolicyLoader.php`, `tests/Unit/Loaders/PolicyLoaderTest.php`
- **Priority:** 37
- **DI:** `Filesystem`, `ModuleLayout`, `Illuminate\Contracts\Auth\Access\Gate`
- **Logic:** Glob `policiesDir()/*Policy.php`, для каждого файла:
  - FQCN policy = `$module->namespace . '\\Domain\\Policies\\' . basename($file, '.php')`
  - Model name = suffix-only strip: удалить только конечный `Policy` из basename, не использовать широкий `str_replace()`
  - FQCN model = `$module->namespace . '\\Domain\\Models\\' . $modelName`
  - Если `class_exists($policyFqcn) && class_exists($modelFqcn)` → `$gate->policy($modelFqcn, $policyFqcn)`
  - Молча пропускать если model или policy class не существуют
- **Guard:** `$this->filesystem->isDirectory($policiesDir)` → early return
- **Test:** Создать `Domain/Policies/PostPolicy.php` + stub model, проверить что `Gate::policy()` вызван. Добавить tests для missing dir, missing model и missing policy class.
- **Provider:** Добавить FQCN в `DEFAULT_LOADERS`

### Группа E: Интеграция

#### Task 11: Provider registration integration test
- **Files:** `tests/Feature/ModuleLoaderServiceProviderTest.php`
- **Blocked by:** Tasks 1–10
- **Actions:**
  - Добавить feature-test, который после `ModuleLoaderServiceProvider::register()` читает tagged services по `ModuleLoaderServiceProvider::LOADER_TAG`
  - Проверить, что default tagged loaders содержат все 15 FQCN: 5 существующих (`ConfigLoader`, `ServiceProviderLoader`, `MigrationLoader`, `FactoryLoader`, `RouteLoader`) + 10 новых
  - Проверка должна ловить забытый FQCN в `DEFAULT_LOADERS`, а не только факт, что каждый loader имеет unit-test

#### Task 12: Quality gates и финальная интеграция
- **Blocked by:** Tasks 1–11
- **Actions:**
  - `composer format` — форматирование
  - `composer phpstan` — статический анализ level 8
  - `composer rector:dry` — проверка rector правил
  - `composer test` — arch + unit + feature тесты
  - Убедиться что арх-тест `loaders are final and implement LoaderInterface` покрывает все новые лоадеры (правило уже существует, работает через namespace glob)
  - Убедиться что EventLoader не зависит от `Providers/EventServiceProvider.php` и не требует исключений в `ServiceProviderLoader`
  - Исправить все найденные проблемы

## Commit Plan

| Commit | Tasks | Message |
|--------|-------|---------|
| 1 | 1, 2, 3 | `feat(loaders): add Lang, View, and BladeComponent loaders` |
| 2 | 4, 5 | `feat(loaders): add ConsoleRoute and Broadcast loaders` |
| 3 | 6, 7 | `feat(loaders): add Command and Middleware loaders` |
| 4 | 8, 9, 10 | `feat(loaders): add Event, Observer, and Policy loaders` |
| 5 | 11, 12 | `chore: pass quality gates for new loaders` |
