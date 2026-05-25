# Fix Plan: Runtime loader reliability, cache atomicity and public API drift

**Problem:** По итогам `$aif-review` нужно исправить все material findings по `src/`: registration lifecycle для console/MoonShine loaders, атомарность production cache, drift публичных контрактов и manifest rules, слабую диагностику loader failures, тестовые gaps и небольшой bootstrap cleanup.
**Created:** 2026-05-25 12:56 +07

## Analysis

- Локально установлен `laravel/framework v13.11.2`; `orchestra/testbench v11.1.0` также работает поверх Laravel 13. Это главный runtime для проверки, при сохранении совместимости с Laravel 12 API.
- Официальная документация Laravel 13 говорит, что service providers вызываются как `register()` для bindings, затем `boot()` после регистрации всех providers. Документация Artisan 13 рекомендует command discovery через `withCommands()`, а локальный `ApplicationBuilder::withCommands()` реализует это через `afterResolving(ConsoleKernelContract::class)` + `$app->booted(...)`.
- Локальный `Container::afterResolving()` только сохраняет callback для будущих resolution events. Он не вызывает callback для уже resolved abstract. Для этого есть `ServiceProvider::callAfterResolving()`: он регистрирует callback и немедленно вызывает его, если abstract уже resolved.
- В реальном console lifecycle `Application::handleCommand()` сначала делает `$this->make(ConsoleKernelContract::class)`, затем `Console\Kernel::handle()` вызывает `bootstrap()`, и только внутри bootstrap boot'ятся package providers. Значит `CommandLoader`/`ConsoleRouteLoader`, вызываемые из provider `boot()`, регистрируют `afterResolving()` слишком поздно для уже resolved console kernel.
- Дополнительная тонкость: kernel обычно resolved по `Illuminate\Contracts\Console\Kernel`, а текущие loaders слушают `Illuminate\Foundation\Console\Kernel`. Для future callback это может сработать через `instanceof`, но для immediate `resolved()`-проверки нужно проверять именно contract abstract.
- Laravel package helpers (`loadMigrationsFrom`, `loadViewsFrom`, `loadTranslationsFrom`, `loadViewComponentsAs`) не eagerly resolve сервисы в provider boot. Они используют `ServiceProvider::callAfterResolving()`: регистрируют callback и сразу выполняют его, если сервис уже resolved. Текущие `MigrationLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader` получают сервисы в constructor через DI, что преждевременно поднимает `migrator`, `translator`, `view` и `BladeCompiler` и не соответствует package-style lifecycle.
- MoonShine bridge имеет тот же lifecycle-риск: `afterResolving(CoreContract::class)` должен быть заменён на immediate-if-resolved flow. В provider лучше использовать `callAfterResolving(CoreContract::class, ...)`.
- `FactoryLoader` использует Laravel global static `Factory::guessFactoryNamesUsing()`. Это нужно для module factories, но текущая реализация перезаписывает host application's existing factory resolver и fallback'ается только в `Database\Factories\*Factory`. Нужно сохранить package-friendly behavior: module namespaces имеют приоритет, non-module models уходят в предыдущий resolver или в Laravel default resolver.
- `ModuleRegistryCache::write()` пишет `bootstrap/cache/modules.php` напрямую. Для production-critical PHP cache нужен temp file в той же директории, полный write + flush, затем atomic `rename()`. Иначе partial write может сломать следующий `require`.
- Текущий код расходится с проектными контекстами и docs: `FeatureRepositoryInterface` в DESCRIPTION/ARCHITECTURE/docs описан как `get`, `bool`, `int`, `string`; `ModuleManifestRepositoryInterface` как `save`, `updateState`, `updateFeatureValues`; `meta.dependencies` должна принимать list sugar и нормализовать его в `*`. Код сейчас использует `getBool/getInt/getString`, `saveValues()` и запрещает list dependencies.
- Правило проекта запрещает логирование через `\Log::*` внутри пакета. Поэтому вместо `[FIX]` application logs план использует типизированные исключения, richer report context и regression tests как observability mechanism.

## Fix Steps

1. [ ] Добавить общий package-style lifecycle helper.
   - Создать `Support\ContainerLifecycleHooks` или аналогичный маленький сервис с методом `callAfterResolving(string $abstract, callable $callback): void`.
   - Семантика должна повторять `Illuminate\Support\ServiceProvider::callAfterResolving()`: `$app->afterResolving($abstract, $callback)`, затем immediate call через `$app->make($abstract)`, если `$app->resolved($abstract)`.
   - Helper должен принимать `Illuminate\Contracts\Foundation\Application` и не использовать facades/global helpers.
   - Зарегистрировать helper как singleton в `ModuleLoaderServiceProvider`.
   - Использовать этот helper во всех loaders/bridges, которым нужен Laravel deferred service lifecycle, вместо локального копипаста.

2. [ ] Исправить console lifecycle для class-based commands.
   - В `CommandLoader` слушать `Illuminate\Contracts\Console\Kernel` как abstract.
   - В callback проверять, что resolved object является `Illuminate\Foundation\Console\Kernel`, потому что публичные методы `addCommandPaths()` живут на concrete kernel.
   - Использовать shared lifecycle helper из шага 1, а не plain `$app->afterResolving(...)`.
   - Сам `addCommandPaths([$commandsDir])` выполнять через `$app->booted(...)`, как Laravel `ApplicationBuilder::withCommands()`, чтобы попасть до `Console\Kernel::discoverCommands()`.
   - Добавить regression test, где `ConsoleKernelContract::class` уже resolved до вызова loader; текущий код этот сценарий пропускает.

3. [ ] Исправить console lifecycle для closure console routes.
   - Применить тот же helper к `ConsoleRouteLoader`.
   - Сохранять `$app->booted(fn () => $kernel->addCommandRoutePaths([$consoleRoutesFile]))`.
   - Проверить сценарии: kernel resolved до loader; kernel resolved после loader; app уже booted.

4. [ ] Привести resource loaders к Laravel package-style registration.
   - `MigrationLoader`: не инжектить `Migrator` eager; через helper слушать `migrator` и вызывать `$migrator->path($migrationsDir)`, как `loadMigrationsFrom()`.
   - `LangLoader`: не инжектить `Translator` eager; через helper слушать `translator` и вызывать `$translator->addNamespace($module->name, $langDir)`, как `loadTranslationsFrom()`.
   - `ViewLoader`: не инжектить view factory eager; через helper слушать `view` и вызывать `$view->addNamespace($module->name, $viewsDir)`, сохраняя текущую module namespace semantics.
   - `BladeComponentLoader`: не инжектить `BladeCompiler` eager; через helper слушать `BladeCompiler::class` и вызывать `componentNamespace(...)`, как package component autoload.
   - Не менять `ConfigLoader`, `RouteLoader`, `MiddlewareLoader`, `BroadcastLoader`, `ObserverLoader`, `PolicyLoader` без отдельного failing scenario: их текущие Laravel APIs допустимы для provider boot при текущем runtime.

5. [ ] Сделать `FactoryLoader` совместимым с host factory resolver.
   - Сохранять module namespace map как сейчас, но global resolver должен возвращать module factory только для известных module model namespaces.
   - Для non-module models делегировать в resolver, который был установлен до package resolver, либо воспроизводить Laravel default `Database\Factories\...Factory` behavior, если previous resolver отсутствует.
   - Если для previous resolver нужен Reflection к Laravel static state, изолировать это в маленький private/internal support method и покрыть тестом на Laravel 12/13 compatibility.
   - Добавить тест: host задаёт `Factory::guessFactoryNamesUsing()` до `FactoryLoader`, module model резолвится в module factory, обычный app model продолжает резолвиться через host resolver.
   - Сброс static factory state в tests делать через `Factory::flushState()`.

6. [ ] Исправить MoonShine bridge lifecycle.
   - Перенести lifecycle hook в provider boot flow или явно назвать boot-метод, чтобы optional functionality регистрировалась в `boot()`, а не как side effect в `register()`.
   - Использовать shared lifecycle helper из шага 1 для `CoreContract::class`, чтобы bridge сработал и для уже resolved MoonShine core.
   - Оставить `interface_exists(CoreContract::class)` guard, чтобы пакет работал без MoonShine.
   - Сохранить `MoonShineModuleAutoloader` как отдельный optional bridge, не добавлять его в loader tag.

7. [ ] Сделать запись module registry cache атомарной.
   - Добавить общий `Support\AtomicFileWriter` или локальный private atomic writer в `ModuleRegistryCache`.
   - Писать cache content в temp file в `bootstrap/cache`, проверять полный byte count, делать `fflush()`, переносить permissions с существующего cache при наличии, затем `rename($temp, $cachePath)`.
   - Использовать lock file для защиты параллельных `modules:optimize`.
   - `ModuleRegistryCache::load()` должен ловить `Throwable` вокруг `require $cachePath` и превращать parse/runtime failures в `InvalidModuleCacheException` с cache path.

8. [ ] Синхронизировать публичный feature API с docs/architecture.
   - Вернуть в `FeatureRepositoryInterface` методы `bool()`, `int()`, `string()` как canonical API.
   - В `FeatureRepository` реализовать canonical methods и при необходимости оставить `getBool()`, `getInt()`, `getString()` как compatibility aliases без объявления в публичном интерфейсе.
   - Обновить тесты и docs examples только после выбора canonical naming; для текущего проектного контекста canonical naming — `bool/int/string`.

9. [ ] Синхронизировать manifest repository API.
   - Вернуть `ModuleManifestRepositoryInterface::save(Module, FeatureValues): void`.
   - Вернуть `updateFeatureValues(Module, FeatureValues): void` как публичный intent-revealing API для settings writes.
   - Убрать или сделать private/internal `saveValues()`, потому что имя обещает запись только values, а фактически сериализует весь canonical manifest.
   - Обновить call sites и тесты.

10. [ ] Вернуть list sugar для `meta.dependencies`.
   - `ModuleDependencies::fromArray()` снова должен принимать list form `["users"]` и нормализовать в `users => "*"`.
   - Хранить внутри typed `ModuleDependency` VO можно оставить.
   - `toArray()` всегда возвращает canonical object map, чтобы запись manifest/cache не сохраняла list form.
   - Добавить тесты на валидный list sugar, невалидные list entries и round-trip normalization.

11. [ ] Усилить diagnostics в loader pipeline без application logging.
   - Добавить typed exception, например `ModuleLoaderException`, с `loaderClass`, `moduleName`, `modulePath` и previous exception.
   - В `ModuleLoaderPipeline` продолжать изолировать ошибки, но в `ExceptionHandler::report()` передавать enriched exception вместо raw Throwable.
   - Не останавливать остальные modules/loaders после одного падения.

12. [ ] Исправить потерю path context в `AtomicJsonWriter`.
   - Передавать настоящий `$path` в `encode()` и использовать его в `ManifestWriteException::forPath(...)`.
   - Сохранить текущую atomic manifest write semantics.

13. [ ] Валидировать tagged loaders жёстко.
    - В `ModuleLoaderServiceProvider::resolveTaggedLoaders()` заменить молчаливый `instanceof` filter на explicit exception для не-`LoaderInterface`.
    - Использовать существующий `InvalidConfigurationException` или добавить отдельное typed exception, если сообщение должно явно указывать loader tag.
    - Тест должен проверять, что неверно tagged service не исчезает молча.

14. [ ] Убрать пустой bootstrap hook.
    - Удалить `registerRuntimeBindings()` и его вызов из `ModuleLoaderServiceProvider::register()`.
    - Не делать большой readability refactor `RouteLoader`/`TopologicalSorter` в этом fix pass, чтобы не смешивать bugfix с широким cleanup.

15. [ ] Привести документацию/context artifacts в соответствие фактическому runtime только после src/tests.
    - Так как этот `$aif-fix` по умолчанию не владеет `.ai-factory/DESCRIPTION.md`, `.ai-factory/ARCHITECTURE.md`, roadmap и rules, обновление этих файлов делать отдельным явным шагом после implementation или через `$aif-docs`/`$aif-rules`.
    - В рамках фикса можно обновить README/docs, если выбрано canonical API из текущих context artifacts и это нужно для passing onboarding examples.

## Files to Modify

- `src/Loaders/CommandLoader.php` — Laravel 12/13-safe console kernel hook через contract abstract + immediate-if-resolved flow.
- `src/Loaders/ConsoleRouteLoader.php` — тот же lifecycle fix для `Routes/console.php`.
- `src/Loaders/MigrationLoader.php` — package-style deferred registration через `migrator`.
- `src/Loaders/LangLoader.php` — package-style deferred registration через `translator`.
- `src/Loaders/ViewLoader.php` — package-style deferred registration через `view`.
- `src/Loaders/BladeComponentLoader.php` — package-style deferred registration через `BladeCompiler::class`.
- `src/Loaders/FactoryLoader.php` — module factory resolver без поломки host resolver.
- `src/Providers/ModuleLoaderServiceProvider.php` — shared lifecycle helper binding, lifecycle hook для MoonShine, strict tagged loader validation, удалить пустой `registerRuntimeBindings()`.
- `src/MoonShine/MoonShineModuleAutoloader.php` — при необходимости сделать idempotency guard на namespace autoload, если тесты покажут duplicate calls при repeated resolve.
- `src/Registry/ModuleRegistryCache.php` — atomic write, lock, `require` error wrapping.
- `src/Support/ContainerLifecycleHooks.php` или аналог — shared `callAfterResolving` semantics для loaders.
- `src/Support/AtomicJsonWriter.php` — сохранить реальный manifest path при JSON encode failure.
- `src/Support/AtomicFileWriter.php` — если выбран shared writer вместо private implementation в cache.
- `src/Exceptions/ModuleLoaderException.php` — enriched report exception для pipeline failures.
- `src/Exceptions/InvalidConfigurationException.php` или новый exception — invalid loader tag diagnostics.
- `src/Contracts/FeatureRepositoryInterface.php` — восстановить canonical typed getters.
- `src/Manifest/FeatureRepository.php` — canonical methods + optional compatibility aliases.
- `src/Contracts/ModuleManifestRepositoryInterface.php` — восстановить `save()` и `updateFeatureValues()`.
- `src/Manifest/ModuleManifestRepository.php` — canonical save/update methods, убрать misleading `saveValues()` из публичной границы.
- `src/Manifest/VO/ModuleDependencies.php` — list sugar normalization to `*`.
- `tests/Unit/Loaders/CommandLoaderTest.php` — real lifecycle scenarios with contract kernel resolved before loader.
- `tests/Unit/Loaders/ConsoleRouteLoaderTest.php` — real lifecycle scenarios and app boot timing.
- `tests/Unit/Loaders/MigrationLoaderTest.php` — migrator resolved before/after loader, no eager resolution.
- `tests/Unit/Loaders/LangLoaderTest.php` — translator resolved before/after loader, no eager resolution.
- `tests/Unit/Loaders/ViewLoaderTest.php` — view factory resolved before/after loader, no eager resolution.
- `tests/Unit/Loaders/BladeComponentLoaderTest.php` — Blade compiler resolved before/after loader, no eager resolution.
- `tests/Unit/Loaders/FactoryLoaderTest.php` — module resolver priority + host resolver preservation.
- `tests/Unit/Support/ContainerLifecycleHooksTest.php` — future and already-resolved callback semantics.
- `tests/Feature/OptionalMoonShineBootTest.php` — CoreContract resolved before provider hook still autoloads modules.
- `tests/Feature/ModuleLoaderServiceProviderTest.php` — invalid tagged loader fails loudly; removed empty runtime binding assumption.
- `tests/Unit/Registry/ModuleRegistryCacheTest.php` — atomic write behavior, partial/invalid PHP cache wrapping, write failure.
- `tests/Unit/Support/AtomicJsonWriterTest.php` — encode failure message includes target path.
- `tests/Unit/Loaders/Pipeline/ModuleLoaderPipelineTest.php` — failing first module does not block second module or next loaders; report receives contextual exception.
- `tests/Feature/ModulesOptimizeCommandTest.php` — `modules:optimize-clear` resets in-memory registry in same process.
- `tests/Unit/Manifest/FeatureRepositoryTest.php` и `tests/Feature/FeatureRepositoryScopedBindingTest.php` — canonical `bool/int/string` API.
- `tests/Unit/Manifest/ModuleManifestRepositoryTest.php` — canonical `save/updateFeatureValues` API.
- `tests/Unit/Manifest/VO/ModuleDependenciesTest.php` — list sugar normalization.
- `README.MD`, `docs/feature-toggles.md`, `docs/manifest.md` — только если implementation меняет examples or confirms canonical API.

## Risks & Considerations

- **Console kernel abstract:** immediate callback должен проверять `ConsoleKernelContract::class`, не concrete class, иначе уже resolved kernel может быть пропущен.
- **Kernel concrete methods:** `addCommandPaths()` и `addCommandRoutePaths()` не входят в `Illuminate\Contracts\Console\Kernel`; нужен `instanceof Illuminate\Foundation\Console\Kernel` guard.
- **Boot timing:** добавление paths должно произойти после app boot callbacks, но до `Console\Kernel::discoverCommands()`. Локальный Laravel 13 kernel делает `discoverCommands()` после `bootstrapWith(...)`, поэтому `$app->booted(...)` внутри provider boot подходит.
- **Shared lifecycle helper:** immediate callbacks могут выполняться в момент вызова loader. Все callback'и должны быть idempotent или использовать Laravel APIs, которые сами deduplicate paths/namespaces.
- **Resource loader dependencies:** `migrator`, `translator`, `view` и `BladeCompiler` не должны forced-resolve в constructor. Иначе package provider меняет lifecycle host app и может поднимать тяжёлые deferred services в requests/commands, где они не нужны.
- **Factory resolver static state:** `Factory::guessFactoryNamesUsing()` глобален на процесс. Нужно сохранить previous host resolver для non-module models и обязательно сбрасывать state в тестах через `Factory::flushState()`.
- **MoonShine early resolution:** `callAfterResolving(CoreContract::class, ...)` может немедленно вызвать autoload. Убедиться, что registry bindings уже зарегистрированы до hook и что тесты не зависят от подмены registry после hook registration.
- **No application logs:** не добавлять `\Log` или facades. Observability — typed exceptions, exception messages, tests.
- **Public API aliases:** если оставить aliases для `getBool/getInt/getString`, не документировать их как primary API, чтобы не закреплять два равноправных контракта.
- **Atomic writer permissions:** при замене cache-файла не потерять permissions существующего файла; при fresh write использовать sane default.
- **Default missing module directories:** не превращать отсутствие `app/Modules` из default config в hard failure без отдельного strict mode, иначе свежий host app может падать на boot.

## Test Coverage

- Unit: `CommandLoader` и `ConsoleRouteLoader` с kernel resolved до loader, после loader и app already booted.
- Feature/Testbench: `php artisan list` или direct kernel bootstrap видит module commands/routes из временного module directory.
- Unit: shared lifecycle helper повторяет `ServiceProvider::callAfterResolving()` для future и already-resolved services.
- Unit: `MigrationLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader` регистрируют paths/namespaces/components, когда target service resolved до loader и после loader.
- Unit: `FactoryLoader` не ломает host application's previous factory resolver и при этом отдаёт module factories для module models.
- Unit: `ModuleRegistryCache::write()` создаёт валидный PHP cache через temp+rename; повреждённый PHP cache заворачивается в `InvalidModuleCacheException`.
- Unit: `ModuleLoaderPipeline` продолжает загрузку второго module и следующих loaders после exception на первом module.
- Feature: `modules:optimize-clear` очищает file cache и сбрасывает singleton registry state в том же процессе.
- Unit: `AtomicJsonWriter` error path содержит настоящий target path.
- Unit: `FeatureRepositoryInterface` canonical methods `bool/int/string` работают и сохраняют scoped cache behavior.
- Unit: `ModuleManifestRepositoryInterface::save()` и `updateFeatureValues()` валидируют schema и пишут через atomic writer.
- Unit: `ModuleDependencies` принимает list sugar, нормализует в `*`, и serializes canonical map.
- Full gates after implementation: `composer format`, `composer phpstan`, `composer test`; optional review gates: `composer format:dry`, `composer rector:dry`.

## Laravel API References Checked

- Local installed source: `vendor/laravel/framework` v13.11.2.
- `Illuminate\Foundation\Application::handleCommand()` resolves `ConsoleKernelContract` before `Console\Kernel::handle()`.
- `Illuminate\Foundation\Console\Kernel::bootstrap()` runs application bootstrap, then discovers command paths/routes.
- `Illuminate\Foundation\Configuration\ApplicationBuilder::withCommands()` uses `afterResolving(ConsoleKernelContract::class)` and `$app->booted(...)`.
- `Illuminate\Support\ServiceProvider::callAfterResolving()` is the package-provider-safe helper for already-resolved services.
- Laravel package helpers use deferred/immediate callbacks for resource loading: `loadMigrationsFrom()`, `loadViewsFrom()`, `loadTranslationsFrom()`, and Blade component registration.
- `Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing()` installs a process-global factory name resolver; `Factory::flushState()` resets it in tests.
- Official Laravel 13 docs: service provider `register()` is for container bindings; `boot()` is for bootstrapping functionality.
- Official Laravel 13 docs: command directories are registered through `withCommands()` and commands are resolved by the service container when Artisan boots.
