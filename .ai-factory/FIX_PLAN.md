# Fix Plan: Закрыть хвосты review после runtime-рефакторинга

**Problem:** review текущего кода показал, что большая часть прежнего FIX_PLAN уже реализована, но остались архитектурные хвосты: `ModuleRegistry` всё ещё хранит snapshot как две array-shape переменные, optimize-команды завязаны на concrete runtime-классы вместо application/usecase boundary, а часть проектных правил AI Factory устарела относительно фактического `state.json` runtime. Обратная совместимость не требуется, поэтому BC-only пункты не включаются в работу. `label`, `group`, `description` в `settings.schema` считаются допустимым текущим контрактом.
**Created:** 2026-05-26 20:12

## Analysis

Что найдено во время review:

- `src/Manifest/ModuleRegistry.php` уже использует выделенные `ModuleDirectoryScanner` и `ModuleRegistryCache`, но внутри хранит loaded state как две отдельные nullable array-переменные: `$modules` и `$orderedModules`. Это оставляет в registry array-shape протокол и дублирует инвариант "module map + load order".
- `ModuleRegistry::writeCache()` остаётся public методом concrete registry. Из-за этого `modules:optimize` инжектит `DimitrienkoV\LaravelModules\Manifest\ModuleRegistry` напрямую, а не contract/usecase.
- `modules:optimize-clear` инжектит concrete `ModuleRegistryCache` и `ModuleRegistry`, хотя архитектура задаёт направление `Console/Commands -> Application/UseCases + Contracts`.
- Текущий fail-loud behavior для tagged non-loader services закреплён тестом и полезнее silent ignore. Это не нужно менять; нужно считать это принятым уточнением поведения.
- `FeatureDefinition::fromArray()` не нужно возвращать: проект v2.0 не требует обратной совместимости, а parsing уже корректно вынесен в `FeatureDefinitionFactory`.
- `label`, `description`, `group` уже поддержаны кодом, тестами и документацией manifest. Это допустимое расширение контракта и не является дефектом.
- `.ai-factory/rules/base.md` и `.ai-factory/rules/manifest.md` местами противоречат текущему runtime: упоминают `settings.values`/state в `module.json`, `ModuleManifestRepository::save()` и list-form dependencies. Эти файлы нельзя править обычной fix-задачей без явного `$aif-rules` workflow, поэтому здесь только зафиксировать follow-up.

Root cause:

- После крупного рефакторинга остался смешанный слой orchestration: часть registry/cache операций уже вынесена, но snapshot и optimize use cases не получили отдельной application-level границы.
- Старые AI Factory rules не были синхронизированы после перехода на отдельный `state.json` и запрета list-form dependencies.

Impact scope:

- Registry runtime и production cache generation.
- Artisan optimize commands.
- Feature/architecture tests вокруг command dependencies и registry snapshot.
- Последующая синхронизация AI Factory rules отдельным rules workflow.

## Fix Steps

1. [ ] Зафиксировать актуальный baseline
   - Прочитать текущие `src/Manifest/ModuleRegistry.php`, `src/Registry/ModuleRegistryCache.php`, optimize-команды и связанные тесты.
   - Не менять manifest schema parsing ради BC: `FeatureDefinition::fromArray()` не возвращать.
   - Не убирать `label`, `description`, `group`.
   - Принять fail-loud поведение для invalid tagged loaders как текущее правило.

2. [ ] Добавить typed registry snapshot
   - Добавить `src/Registry/VO/ModuleRegistrySnapshot.php` как `final readonly` value object.
   - Хранить внутри:
     - `array<string, Module> $modules`
     - `array<int, Module> $loadOrder`
   - Добавить named constructor `fromLoadOrder(array $loadOrder)` или аналог, который строит map по module name и явно валидирует duplicate names через typed exception.
   - Добавить методы `all()`, `loadOrder()`, `find(string $name)`, `has(string $name)`, `count()`.
   - Сохранить deterministic order: `all()` возвращает modules в load order, а не случайный порядок map.

3. [ ] Вынести fresh scan/build snapshot из `ModuleRegistry`
   - Добавить `src/Registry/ModuleRegistrySnapshotBuilder.php`.
   - Builder должен зависеть от `ModuleDirectoryScanner`, `ModuleManifestRepositoryInterface`, `TopologicalSorter`.
   - Builder выполняет текущую логику `scan()`:
     - сканирует module directories;
     - грузит manifests;
     - сортирует через `TopologicalSorter`;
     - возвращает `ModuleRegistrySnapshot`.
   - `ModuleRegistry` должен хранить только `?ModuleRegistrySnapshot $snapshot = null`.
   - `ModuleRegistry::ensureLoaded()` должен брать snapshot из cache или builder.
   - Приватный `scan()` и array-shape return из `ModuleRegistry` убрать.

4. [ ] Адаптировать production cache к snapshot без расширения публичного API без нужды
   - Минимальный вариант: оставить `ModuleRegistryCacheInterface::load()` с текущим array-shape, но сразу преобразовывать результат в `ModuleRegistrySnapshot` внутри `ModuleRegistry`.
   - Более чистый вариант допустим только если не ломает architecture rules: добавить в cache concrete helper `loadSnapshot()`/`buildSnapshotPayload()` без протаскивания concrete Registry VO в публичные contracts.
   - `ModuleRegistryCache::write()` может по-прежнему принимать `array<int, Module>` load order; optimize use case будет передавать `$snapshot->loadOrder()`.
   - Проверить, что cache v3 по-прежнему не содержит state и `settings.values`.

5. [ ] Вынести optimize flow в Application use cases
   - Добавить DTO:
     - `src/Application/DTOs/OptimizeModulesResult.php` (`path`, `count`)
     - `src/Application/DTOs/ClearModulesOptimizeCacheResult.php` (`cleared`)
   - Добавить use cases:
     - `src/Application/UseCases/OptimizeModulesUseCase.php`
     - `src/Application/UseCases/ClearModulesOptimizeCacheUseCase.php`
   - `OptimizeModulesUseCase` должен использовать `ModuleRegistrySnapshotBuilder` и `ModuleRegistryCacheInterface`, чтобы писать cache из fresh filesystem scan, а не из уже загруженного cache.
   - `ClearModulesOptimizeCacheUseCase` должен использовать `LifecycleRegistryInvalidator`; если нужен message-level результат "No cache to clear", проверять `ModuleRegistryCacheInterface::exists()` до invalidation.
   - Не добавлять runtime logging; для этого пакета feedback идёт через command output, typed exceptions и tests.

6. [ ] Обновить optimize-команды под command boundary
   - `ModulesOptimizeCommand` должен инжектить `OptimizeModulesUseCase`, а не concrete `ModuleRegistry`.
   - `ModulesOptimizeClearCommand` должен инжектить `ClearModulesOptimizeCacheUseCase`, а не concrete `ModuleRegistryCache`/`ModuleRegistry`.
   - Сохранить текущие CLI messages и exit codes.
   - Добавить или обновить architecture test, который запрещает optimize-командам зависеть от concrete `Manifest\ModuleRegistry` и `Registry\ModuleRegistryCache`.

7. [ ] Обновить tests под новую границу
   - Добавить unit tests для `ModuleRegistrySnapshot`.
   - Добавить unit tests для `ModuleRegistrySnapshotBuilder`.
   - Обновить `tests/Unit/Manifest/ModuleRegistryTest.php`: registry должен работать через snapshot, `reset()` сбрасывает snapshot, cache path остаётся рабочим.
   - Обновить/добавить tests для `OptimizeModulesUseCase`:
     - пишет cache из fresh scan;
     - возвращает path/count;
     - не использует stale loaded cache как источник.
   - Обновить/добавить tests для `ClearModulesOptimizeCacheUseCase`:
     - удаляет cache;
     - сбрасывает registry;
     - корректно сообщает no-op, когда cache отсутствует.
   - Обновить feature tests optimize-команд под usecase boundary.

8. [ ] Применить mechanical quality cleanup
   - Запустить `composer rector:dry` и либо применить безопасные Rector changes через `composer rector`, либо вручную закрыть только релевантные замечания.
   - Особое внимание: не удалять параметры из public methods, если они сознательно оставлены для будущего контракта; иначе принять Rector cleanup.
   - После Rector cleanup запустить formatter.

9. [ ] Проверить quality gates
   - `composer format`
   - `composer phpstan`
   - `composer test`
   - Дополнительно полезно: `composer rector:dry` должен завершаться без proposed changes.

10. [ ] Зафиксировать follow-up для AI Factory rules отдельно
   - Не редактировать `.ai-factory/rules/*` в рамках этой fix-задачи.
   - После code fix запустить отдельный `$aif-rules` или отдельную rules-задачу, чтобы синхронизировать:
     - `module.json` содержит только `meta` и `settings.schema`;
     - mutable state и `settings.values` живут в `state.json`;
     - repository method называется `writeManifest()`, не `save()`;
     - list-form dependencies не поддерживаются в v2.0 core.

## Files to Modify

- `src/Registry/VO/ModuleRegistrySnapshot.php` — новый snapshot VO для registry state.
- `src/Registry/ModuleRegistrySnapshotBuilder.php` — fresh filesystem scan + sort + snapshot assembly.
- `src/Manifest/ModuleRegistry.php` — заменить две nullable array cache-переменные на `?ModuleRegistrySnapshot`; убрать приватный array-shape `scan()`.
- `src/Registry/ModuleRegistryCache.php` — при необходимости добавить helper для snapshot-oriented load/write без state values.
- `src/Contracts/ModuleRegistryCacheInterface.php` — менять только если выбранный вариант не нарушает contract boundary.
- `src/Application/DTOs/OptimizeModulesResult.php` — результат `modules:optimize`.
- `src/Application/DTOs/ClearModulesOptimizeCacheResult.php` — результат `modules:optimize-clear`.
- `src/Application/UseCases/OptimizeModulesUseCase.php` — application orchestration для cache warmup.
- `src/Application/UseCases/ClearModulesOptimizeCacheUseCase.php` — application orchestration для cache clear + registry reset.
- `src/Console/Commands/Modules/ModulesOptimizeCommand.php` — перейти на usecase.
- `src/Console/Commands/Modules/ModulesOptimizeClearCommand.php` — перейти на usecase.
- `src/Providers/ModuleLoaderServiceProvider.php` — зарегистрировать новые builder/usecase bindings только если autowiring недостаточно или нужен явный singleton.
- `tests/Unit/Registry/ModuleRegistrySnapshotTest.php` — новый test suite.
- `tests/Unit/Registry/ModuleRegistrySnapshotBuilderTest.php` — новый test suite.
- `tests/Unit/Manifest/ModuleRegistryTest.php` — обновить ожидания registry snapshot/cache.
- `tests/Unit/Application/UseCases/OptimizeModulesUseCaseTest.php` — новый test suite.
- `tests/Unit/Application/UseCases/ClearModulesOptimizeCacheUseCaseTest.php` — новый test suite.
- `tests/Feature/Commands/ModulesOptimizeCommandTest.php` — обновить command behavior при необходимости.
- `tests/Architecture/ArchitectureTest.php` — добавить точечный guard против concrete optimize dependencies в commands.

## Risks & Considerations

- Важно не начать повторный большой refactor manifest schema: parsing primitives, `FeatureType`, value normalizer и schema labels уже считаются закрытыми.
- `OptimizeModulesUseCase` должен строить cache из fresh scan, иначе `modules:optimize` может законсервировать stale cache.
- `ModuleRegistrySnapshot::all()` должен сохранять ожидаемый order. Если вернуть `array_values($modules)` из map, порядок будет зависеть от способа построения map; лучше явно возвращать `loadOrder`.
- Не протаскивать mutable state или `settings.values` в cache payload.
- Не добавлять Laravel facades или runtime logs в `src/`.
- Если появится желание править `.ai-factory/rules/*`, остановиться и вынести это в `$aif-rules`, потому что проектное правило запрещает менять rules вручную в обычных задачах.

## Test Coverage

- Snapshot:
  - строит map и load order из отсортированных modules;
  - `all()` и `loadOrder()` возвращают deterministic order;
  - `find()` бросает `ModuleNotFoundException`;
  - duplicate module names дают typed manifest/cache exception.
- Snapshot builder:
  - сканирует только configured roots;
  - игнорирует директории без `module.json`;
  - сортирует dependencies через `TopologicalSorter`;
  - не читает optimized cache.
- Optimize use case:
  - пишет cache из fresh snapshot;
  - возвращает корректные path/count;
  - cache payload не содержит state или `settings.values`.
- Clear use case:
  - удаляет cache и сбрасывает registry;
  - idempotent при отсутствующем cache.
- Commands:
  - `modules:optimize` и `modules:optimize-clear` сохраняют CLI output и exit codes;
  - commands не инжектят concrete `ModuleRegistry`/`ModuleRegistryCache`.
- Quality:
  - `composer format`
  - `composer phpstan`
  - `composer test`
  - `composer rector:dry`
