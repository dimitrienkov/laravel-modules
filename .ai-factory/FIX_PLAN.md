# Fix Plan: Архитектурное разделение runtime, manifest schema и MoonShine-интеграции

**Problem:** текущий пакет уже работает как компактный modular monolith, но несколько классов совмещают слишком много ответственностей. Это затруднит развитие `settings.schema`, lifecycle-команд, module registry cache и optional MoonShine auto-discovery. Отдельно текущая MoonShine-регистрация зависит от того, был ли `CoreContract` уже bound в момент `register()`, хотя проектная архитектура требует `afterResolving(CoreContract::class)`.
**Created:** 2026-05-24 18:06

## Analysis

Что найдено в коде:

- `src/Manifest/FeatureDefinition.php` совмещает DTO, parser, whitelist validation, type-specific rules, value normalization и serialization. Это главный риск для будущего расширения schema UI: новые типы полей, видимость, секции, help text, casts, nullable/array values быстро превратят класс в монолит.
- `src/Manifest/ManifestValidator.php`, `ManifestMeta.php`, `ManifestState.php`, `ModuleDependencies.php` и `FeatureDefinition.php` повторяют низкоуровневые проверки array/object/string/allowed keys. Это не критичный баг сейчас, но дальнейшее расширение манифеста будет дублировать ошибки.
- `src/Manifest/ModuleRegistry.php` совмещает lazy runtime cache, сканирование директорий, чтение optimized cache, сборку load order и cache payload. Это мешает отдельно развивать install/update/remove invalidation и production cache.
- `src/Providers/ModuleLoaderServiceProvider.php` одновременно wiring-класс, loader registry, optional integration gate и runner pipeline. Это усложнит добавление новых лоадеров и регистрацию кастомных pipeline-расширений.
- `src/Loaders/MoonShineLoader.php` уже вызывает нативный `$core->autoload($module->namespace)`, а установленный `moonshine/contracts` 4.10.0 подтверждает сигнатуру `autoload(?string $namespace = null): static`. Но provider регистрирует loader только если `CoreContract` уже bound, что может пропустить MoonShine, если он резолвится позже.
- `src/Services` пустой. Его лучше удалить или не использовать; для новых компонентов предпочтительнее явные namespace по роли (`Manifest/Schema`, `Manifest/IO`, `Registry`, `Loaders/Pipeline`, `MoonShine`), а не generic `Services`.
- Рабочая копия уже содержит несохранённые изменения в manifest/runtime/tests. Реализация должна читать актуальные файлы перед каждым edit и не откатывать чужие изменения.

Внешние аналоги и выводы:

- `nwidart/laravel-modules` позиционирует module как Laravel package-like единицу и автоматически регистрирует module service provider, чтобы routes/migrations/views/translations/commands обнаруживались без ручной настройки. В старой документации есть отдельная cache-настройка для множества `module.json`. Это подтверждает ценность registry/cache как отдельной границы, но не повод добавлять manifest autoload whitelist.
- `akaunting/laravel-module` и Akaunting docs используют commercial/app-store сценарий: module похож на Laravel package, имеет `module.json`, providers, requires/settings и install flow. Это близко к целевому сценарию проекта, но у текущего пакета лучше сохранить JSON source of truth без БД.
- `internachi/modular` делает другой tradeoff: Composer path repositories + Laravel package discovery, легче для организации кода, но сам README говорит, что для third-party modules с dynamic enable/disable больше подходит `laravel-modules`. Значит текущий пакет не должен уходить полностью в Composer package discovery, но может учесть `modules:cache`, `modules:sync` и module-aware `make:*`.
- Laravel Pennant отделяет feature definition/checking/storage/testing и поддерживает drivers/cache/scope. Для этого пакета не нужно заменять module settings на Pennant, но стоит заимствовать идею маленьких границ: definition, repository, values, type/cast normalization, test helpers.
- Spatie Laravel Settings показывает полезные идеи для typed settings: casts, repositories, cache, auto-discovery, clear-cache. Для текущего пакета это аргумент в пользу `FeatureType`/`FeatureValueNormalizer`/`FeatureFieldFactory`, а не разрастания `FeatureDefinition`.
- MoonShine 4 docs говорят, что autoload pages/resources выключен по умолчанию и включается вызовом `autoload()`. Docs также фиксируют configurable `dir`/`namespace`, а локальный контракт поддерживает `autoload(?string $namespace = null)`, поэтому module namespace autoload является корректной интеграционной точкой.

Источники:

- https://laravelmodules.com/docs/13/getting-started/introduction
- https://nwidart.com/laravel-modules/v6/basic-usage/configuration
- https://akaunting.com/hc/docs/developers/modules/
- https://packagist.org/packages/internachi/modular
- https://laravel.com/docs/11.x/pennant
- https://github.com/spatie/laravel-settings
- https://getmoonshine.app/en/docs/4.x/model-resource/index
- https://getmoonshine.app/en/docs/4.x/configuration
- https://laravel.com/docs/10.x/packages

Root cause / suspected root cause:

- Архитектура описывает более зрелый package runtime, чем текущий код. Несколько классов пока выполняют роль "склеить минимальный MVP", поэтому границы для schema evolution, registry cache и optional integrations ещё не выделены.
- MoonShine optional integration реализована как eager container-bound check, а должна быть lifecycle hook integration.
- Manifest schema пока типизирована строками (`bool`, `int`, `string`, `enum`) и приватными static helper-методами, поэтому добавление UI-полей или rich values приведёт к каскадным изменениям.

Impact scope:

- Runtime boot пакета: service provider, loader pipeline, module registry cache.
- Manifest parsing/validation: все VO манифеста и тесты `tests/Unit/Manifest/*`.
- Optional MoonShine behavior: `MoonShineLoader`, provider tests, optional dependency tests.
- Lifecycle future work: install/update/remove/list/enable/disable должны опираться на выделенный registry/cache invalidation и manifest writer.

## Fix Steps

1. [ ] Зафиксировать текущий baseline перед изменениями
   - Прочитать актуальный `git diff -- src tests composer.json`, потому что рабочая копия уже dirty.
   - Запустить targeted baseline при возможности: `composer test:unit` и `composer test:feature` или минимально manifest/provider tests.
   - Ничего не откатывать без отдельного запроса.

2. [ ] Выделить manifest parsing primitives
   - Добавить небольшой internal helper в `src/Manifest/Parsing/ManifestFieldReader.php` или аналогичный namespace.
   - Ответственность: `requiredObject`, `optionalObject`, `requiredString`, `optionalString`, `optionalBool`, `optionalInt`, `assertAllowedKeys`, object-vs-list edge case.
   - Использовать typed exceptions `InvalidManifestException::forPath(...)`.
   - Не менять публичный manifest format.
   - Перенести повторяющиеся проверки из `ManifestValidator`, `ManifestMeta`, `ManifestState`, `ModuleDependencies`, `FeatureDefinition`.

3. [ ] Разделить feature schema model и normalization
   - Оставить `FeatureDefinition` final readonly value object.
   - Вынести сборку из array в `FeatureDefinitionFactory` или `FeatureDefinitionParser`.
   - Вынести value normalization в `FeatureValueNormalizer`.
   - Ввести `FeatureType` enum или final constants class для `bool`, `int`, `string`, `enum`; выбрать вариант, который проходит PHPStan level 8 и не усложняет serialization.
   - Сохранить существующий публичный API `FeatureDefinition::fromArray()` как thin delegating factory, если это дешевле для обратной совместимости тестов.
   - Подготовить расширяемость для будущих schema keys: `ui`, `nullable`, `help`, `placeholder`, `visibility`, `cast`, но не добавлять эти ключи сейчас без отдельной задачи.

4. [ ] Вынести registry scan/cache в отдельные компоненты
   - Добавить `ModuleDirectoryScanner`: читает `modules.paths.directories`, проверяет `module.json` через `ModuleLayout`, сортирует пути.
   - Добавить `ModuleRegistrySnapshot`: хранит `modules` map и `loadOrder` list, чтобы убрать array-shapes из `ModuleRegistry`.
   - Добавить `ModuleRegistryCache` или `ModuleRegistryCacheRepository`: `cachePath`, `buildPayload`, `load`, `validatePayload`.
   - Оставить `ModuleRegistryInterface` без расширения, если нет прямой необходимости.
   - Подготовить метод invalidation для будущих lifecycle-команд, но не подключать install/update/remove пока они не реализуются.

5. [ ] Вынести loader pipeline из service provider
   - Добавить `ModuleLoaderPipeline` с ответственностью: получить `ModuleRegistryInterface`, получить iterable/tagged loaders, отсортировать по `priority()`, запустить loaders-first/modules-second, пропустить disabled modules.
   - Service provider должен остаться wiring-классом: bindings, publishes, commands, optimizes, optional integrations.
   - Сохранить `LoaderInterface` тонким: только `load(Module): void` и `priority(): int`.
   - Проверить, что `FactoryLoader` остаётся singleton, иначе потеряет накопленные namespace mappings.

6. [ ] Исправить optional MoonShine integration lifecycle
   - Регистрировать `MoonShineLoader` без hard failure, только если интерфейс/класс MoonShine доступны.
   - Использовать `$this->app->afterResolving(CoreContract::class, ...)`, чтобы module MoonShine Resources/Pages автоподгружались, когда MoonShine установлен и core реально появился.
   - Не вызывать `$app->make(CoreContract::class)` самостоятельно, если MoonShine не установлен или core не bound.
   - Убедиться, что без MoonShine не ломаются migration/config/provider/factory/route loaders.
   - Сохранить вызов `$core->autoload($module->namespace)` для каждого enabled module, так как контракт поддерживает namespace argument.

7. [ ] Подготовить lifecycle foundation без преждевременной реализации всех команд
   - Добавить маленький `ModuleCacheInvalidator` или метод в cache-компоненте, который удаляет optimized cache.
   - Использовать его в текущих optimize-clear tests или оставить как dependency для будущих enable/disable/install/update/remove.
   - В план следующей реализации вынести lifecycle commands: `modules:list`, `modules:enable`, `modules:disable`, `modules:install`, `modules:update`, `modules:remove`.
   - Не добавлять zip install/update в этот refactor, если это резко расширяет scope.

8. [ ] Обновить DI bindings
   - Зарегистрировать новые internal components в `ModuleLoaderServiceProvider`.
   - Убедиться, что singleton/scoped правила соответствуют runtime rules:
     - immutable/stateless components: singleton;
     - per-request caches: scoped;
     - no mutable static state.

9. [ ] Обновить тесты
   - Unit tests для `ManifestFieldReader`, `FeatureDefinitionFactory`, `FeatureValueNormalizer`.
   - Unit tests для `ModuleDirectoryScanner`, `ModuleRegistrySnapshot`, `ModuleRegistryCache`.
   - Feature tests для `ModuleLoaderPipeline`: порядок, disabled modules, non-loader tagged services ignored.
   - Feature tests для MoonShine:
     - provider boots without MoonShine classes/core binding;
     - loader registered/executed via `afterResolving(CoreContract::class)`;
     - late CoreContract binding/resolution still triggers module autoload;
     - no duplicate autoload on repeated provider boot if behavior is observable.
   - Architecture tests при необходимости: no generic `src/Services`, no facades/helpers, no static mutable state, readonly manifest VOs preserved.

10. [ ] Проверить quality gates
    - `composer format`
    - `composer phpstan`
    - `composer test`
    - Если полный набор временно слишком широк из-за текущей dirty baseline, минимум: targeted tests по изменённым компонентам + отметить остаточный риск.

## Files to Modify

- `src/Manifest/FeatureDefinition.php` — оставить VO и публичную совместимость, вынести parsing/normalization.
- `src/Manifest/FeatureSchema.php` — перейти на новый parser/factory.
- `src/Manifest/FeatureValues.php` — использовать новый normalizer.
- `src/Manifest/ManifestValidator.php` — убрать повтор primitive validation, оставить orchestration.
- `src/Manifest/ManifestMeta.php` — использовать shared field reader.
- `src/Manifest/ManifestState.php` — использовать shared field reader.
- `src/Manifest/ModuleDependencies.php` — использовать shared identifier/dependency validation.
- `src/Manifest/ModuleRegistry.php` — оставить registry facade, вынести scanner/cache/snapshot.
- `src/Providers/ModuleLoaderServiceProvider.php` — обновить bindings, pipeline boot, MoonShine afterResolving.
- `src/Loaders/MoonShineLoader.php` — при необходимости сменить форму с `LoaderInterface` на dedicated optional integration runner или оставить loader, если pipeline model остаётся единым.
- `src/Contracts/*` — менять только если существующие контракты реально не покрывают новые границы; не расширять публичный API без необходимости.
- `tests/Unit/Manifest/*` — обновить и расширить tests parsing/normalization.
- `tests/Unit/Support/*` или новый `tests/Unit/Manifest/*Registry*` — тесты scanner/cache/snapshot.
- `tests/Feature/ModuleLoaderServiceProviderTest.php` — pipeline/provider wiring.
- `tests/Feature/OptionalMoonShineBootTest.php` — late resolving и absence behavior.
- `tests/Architecture/*` — инварианты, если добавляются новые namespace conventions.

## Risks & Considerations

- Главный риск — случайно изменить публичный manifest format. Нельзя добавлять `autoload` или переносить source of truth из `module.json`.
- Нельзя превращать MoonShine в обязательную runtime dependency. `composer.json` должен оставить MoonShine в `suggest`/`require-dev`.
- Нельзя добавлять фасады или `\Log::*` в `src`; проектное правило запрещает глобальные side effects. Для observability использовать typed exceptions, command output и тесты. Если нужна debug-информация для фикса, она должна быть временной или CLI-only, без runtime logs.
- `ModuleRegistry` singleton хранит in-memory snapshot; feature values должны оставаться scoped/current через `FeatureRepository`, а не через optimized cache.
- В `src/Providers/ModuleLoaderServiceProvider.php` легко сломать ordering: loaders-first, modules-second, priority ascending.
- `FactoryLoader` stateful by design; его lifecycle должен остаться singleton.
- `afterResolving(CoreContract::class)` может сработать после основного module boot. Это нормально для MoonShine, если loader сам читает enabled modules из registry и вызывает `autoload`.
- Удаление пустого `src/Services` возможно только если это не конфликтует с внешними инструментами; иначе оставить до отдельной cleanup-задачи.

## Test Coverage

- Manifest schema:
  - unknown keys still fail;
  - empty `{}` object accepted where expected;
  - bool/int/string/enum normalization unchanged;
  - UTF-8 string length still character-based;
  - defaults and explicit values serialization unchanged.
- Registry:
  - scans only configured roots;
  - ignores non-string config entries;
  - includes only dirs with `module.json`;
  - sorted deterministic paths;
  - cache payload version validation;
  - cache `load_order` references missing module fails;
  - duplicate module names still fail through dependency/load-order path.
- Pipeline:
  - loaders execute by priority before moving to next loader;
  - disabled modules skipped;
  - tagged non-loader ignored;
  - no duplicate execution on normal provider boot.
- MoonShine:
  - package boots without MoonShine installed/bound;
  - when `CoreContract` resolves, each enabled module namespace is passed to `autoload`;
  - disabled modules are not autoloaded;
  - late core binding/resolution works;
  - MoonShine absence does not affect migrations/routes/config/service providers.
- Quality:
  - `composer format`
  - `composer phpstan`
  - `composer test`

## Implementation Notes

- Делать refactor в небольших коммитоподобных порциях: manifest primitives, feature schema, registry cache, pipeline, MoonShine.
- После каждого блока запускать targeted tests, чтобы проще локализовать regressions.
- Не смешивать этот refactor с полным lifecycle feature implementation. Для install/update/remove/list лучше создать отдельный feature plan после стабилизации registry/cache boundaries.
- Если во время реализации выяснится, что `FeatureDefinition::fromArray()` тяжело сохранить без service locator/static dependency, оставить parsing внутри класса на один шаг и вынести только `FeatureValueNormalizer`; затем вернуться ко factory вторым small refactor.
