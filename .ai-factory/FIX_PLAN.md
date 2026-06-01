# Fix Plan: устранение замечаний по manifest/state/list/loaders

**Problem:** В коде накопились повторяющиеся инварианты и мелкие рассинхронизации контрактов: checksum origin проверяется в нескольких местах, string-keyed object логика дублируется, `modules:list --group` ведёт себя слабее `--kind`, group kebab-case допускает некорректные дефисы, `ModuleStateDocument::$origin` сериализуется как `source`, `FactoryLoader` зависит от приватного свойства Laravel без тестового tripwire. Дополнительно есть nits по checksum docblock, writeValues fallback, group labels, сложным условиям и magic version.
**Created:** 2026-06-01 16:54 +07
**Mode:** plan only. После будущей реализации этот план не удалять: обновлять чекбоксы и оставить файл как traceability artifact.

## Analysis

- `ModuleOrigin` держит один доменный инвариант `kind <-> checksum` в трёх местах: `ModuleOriginKind::requiresChecksum()`, конструктор `ModuleOrigin`, `ModuleOrigin::parseChecksum()`. Единственным владельцем требования наличия/отсутствия checksum должен остаться конструктор; `parseChecksum()` должен только читать ключ и валидировать формат значения.
- Одинаковая логика "array with string keys only" есть в `ManifestFieldReader::requiredObject()`, `CachedModuleDescriptor::fromCacheEntry()` и `ModuleStateRepository::requireStringKeys()`. Исключения разные, поэтому общий helper должен принимать callable, который создаёт/бросает нужное typed exception. После введения helper локальные string-keyed loops и wrapper-методы в этих call site не сохраняются.
- `modules:list --kind` fail-fast валидирует enum, а `--group` принимает любую строку. Невалидный формат должен падать явно, а валидная группа без совпадений должна получать отдельное сообщение, чтобы опечатка не выглядела как обычный пустой registry.
- `MODULE_GROUP_PATTERN` допускает `foo-` и `foo--bar`; фактический контракт должен быть сегментным kebab-case.
- Runtime contract state-файла использует JSON key `source`. Чтобы не ломать существующие `state.json` и документацию, ключ `source` остаётся, а внутренняя точка рассинхронизации исправляется переименованием свойства `ModuleStateDocument::$origin` в `$source` с docblock, объясняющим, что это `ModuleOrigin` provenance, а не staging `ModuleSourceKind`.
- `FactoryLoader` вынужденно читает `Factory::$factoryNameResolver` через reflection. Убирать reflection в рамках текущего Laravel API нечем, поэтому нужен unit/arch tripwire, который падает на CI при изменении внутреннего свойства в Laravel.
- Проектный override для `$aif-fix`: не добавлять `[FIX]` runtime logging в `src/`; диагностика должна идти через typed exceptions, command output и тесты.

## Fix Steps

1. [x] Добавить общий helper для string-keyed object логики.
   - Создать `src/Support/StringKeyedObject.php` с `declare(strict_types=1);`, `final readonly`.
   - Добавить public static method `toStringKeyedObject(array $value, callable $onError): array`.
   - Метод не должен зависеть от конкретных exception классов; caller передаёт closure с typed exception для своего контекста.
   - Helper отвечает только за проход по ключам и копирование в `array<string, mixed>`; проверка "значение является JSON object, а не list/scalar/null" остаётся на boundary-методах, где отличаются сообщения и допустимость `null`/`[]`.
   - Перенести текущий комментарий про PHP coercion numeric-string JSON keys в helper.

2. [x] Заменить все локальные string-keyed loops на общий helper.
   - `ManifestFieldReader::requiredObject()` оставить владельцем проверки "ключ существует и значение является JSON object"; после этой проверки сразу вызывать `StringKeyedObject::toStringKeyedObject()` с `InvalidManifestException`.
   - `CachedModuleDescriptor::fromCacheEntry()` после проверки обязательных полей делегирует проверку ключей `manifest` в `StringKeyedObject::toStringKeyedObject()` с `InvalidModuleCacheException`.
   - `ModuleStateRepository` полностью удаляет локальный `requireStringKeys()` и не вводит replacement-wrapper `toStringKeyedObject()`: `readStateFile()`, `extractSource()` и `assertJsonObject()` вызывают общий helper напрямую.
   - В `assertJsonObject()` ввести именованный `$isJsonObject`/guard вместо раздельных условий, но сохранить текущий edge-case только для этого метода: `null` и `[]` возвращают `null`, non-empty list/scalar падают, non-empty object проходит через helper.
   - Не оставлять старые private methods, fallback branches или адаптеры, которые существуют только ради прежней реализации.

3. [x] Убрать дублирование checksum-инварианта в `ModuleOrigin`.
   - `parseChecksum()` оставить только для парсинга: отсутствующий ключ -> `null`, присутствующий не-string -> `InvalidModuleStateException`, некорректный digest -> wrapped `InvalidModuleStateException`.
   - Создание `new self(...)` в `fromArray()` обернуть в `try/catch (InvalidArgumentException $e)` и конвертировать в `InvalidModuleStateException::forPath($statePath, $e->getMessage(), $e)`.
   - Добавить короткий комментарий рядом с catch: доменный invariant живёт в constructor, а `fromArray()` только добавляет state path context.
   - Сохранить различие `array_key_exists()` vs `isset()` для explicit `null`.

4. [x] Ужесточить group pattern.
   - Заменить `MODULE_GROUP_PATTERN` на `/^[a-z0-9]+(-[a-z0-9]+)*$/`.
   - Обновить сообщение, если оно говорит "starting with a letter": новый паттерн допускает начальную цифру, как уже допускается тестом `a1` и предложенным regex.
   - Добавить tests на rejection `foo-`, `foo--bar`, `-foo`, `my_group`, whitespace; valid `content`, `content-tools`, `a1`, `1a` по новому контракту.
   - Обновить docs/AI-context упоминания старого regex только в обычной документации, если они будут затронуты фиксом; `.ai-factory/rules/*` не трогать.

5. [x] Выровнять `modules:list --group`.
   - В `ModulesListCommand` провалидировать `--group` через `ManifestFieldReader::assertModuleGroup($groupFilter, '--group', 'modules:list')` и вернуть `FAILURE` с понятной ошибкой при invalid format.
   - В `ListModulesUseCase` оставить чистую фильтрацию; не заставлять use case знать о CLI error output.
   - Для валидной группы без результатов вывести `No modules found in group [<group>].` вместо общего `No modules found.`
   - Добавить unit tests в `ListModulesUseCaseTest`: два модуля `content` и `null`, фильтр `content` возвращает только grouped module; фильтр `missing` возвращает empty list.
   - Добавить feature tests в `ModulesListCommandTest`: invalid group падает; valid missing group даёт явное group-specific сообщение.

6. [x] Унифицировать boundary name `source`.
   - Переименовать `ModuleStateDocument` promoted property `origin` -> `source`.
   - Обновить constructor usages на именованный аргумент `source: $source` там, где третий параметр неочевиден; новые позиционные вызовы с бывшим `$origin` не добавлять.
   - Обновить чтение/запись в `ModuleStateRepository`: `extractOrigin()` переименовать в `extractSource()`, локальные переменные `$origin` заменить на `$source` на state boundary.
   - Обновить tests, которые читают `$doc->origin`, на `$doc->source`.
   - Не добавлять deprecated alias `$origin`, magic getter, dual-property adapter или другой fallback для старого публичного свойства.
   - Сохранить JSON key `source` и весь текущий state.json формат без миграции.

7. [x] Добавить tripwire для Laravel factory internals.
   - В `tests/Unit/Loaders/FactoryLoaderTest.php` добавить проверку, что `Illuminate\Database\Eloquent\Factories\Factory` имеет property `factoryNameResolver`.
   - Тест должен объяснять, что это intentional reflection dependency for preserving host resolver.
   - Не менять runtime behavior `FactoryLoader`, кроме уже допустимых мелких naming/readability улучшений.

8. [x] Исправить checksum docblock и фабрику checksum from file.
   - В `Checksum` заменить "installed module artifact" на "source archive".
   - Не добавлять `Checksum::ofFile()` и не добавлять `Checksum::fromDigest()` в этом fix: VO остаётся без infrastructure dependency и без лишней factory-обёртки над constructor.
   - В `ModuleSourcePreparer` выделить private method `checksumForArchive(string $sourcePath): Checksum`, чтобы preparer не смешивал staging flow и error message.

9. [x] Явно задокументировать отсутствие integrity verification.
   - Не добавлять `--checksum` в этом fix: это новая lifecycle feature и часть commercial packaging/verification roadmap.
   - В `docs/cli.md` и `docs/manifest.md` явно сказать: текущий checksum фиксирует provenance архива после чтения; сверка с ожидаемым digest/signature не реализована и относится к roadmap verification/signature flow.
   - Проверить, что docs не подают verify как текущий runtime.

10. [x] Исправить `writeValues()` edge-case.
    - Если state file существует, использовать текущий `read()` как сейчас.
    - Если state file отсутствует, сохранять `$module->state`, а не `ModuleState::defaultDisabled()`, чтобы запись values не меняла enabled/timestamps из переданного module object.
    - Добавить unit test: module с `enabled=true` и отсутствующим state file после `writeValues()` остаётся enabled=true в записанном state.json.

11. [x] Ужесточить и переименовать `ModuleGroupLabelResolver`.
    - Переименовать public `label()` -> `displayLabel()` и private `mappedLabel()` -> `configuredLabel()`; обновить command/tests.
    - Если `modules.groups` не array или содержит non-string/blank label для запрошенной группы, бросать `InvalidConfigurationException::forKey('modules.groups', ...)` вместо silent fallback.
    - Missing mapping для валидной группы оставить допустимым fallback на bare group code.
    - Развернуть ternary в early return для читаемости.
    - Обновить unit tests: malformed config теперь throws; missing mapping still falls back.

12. [x] Упростить сложные условия и magic literals.
    - В `ModuleStateRepository::extractSource()`/`assertJsonObject()` ввести именованные `$isJsonObject`/guard clauses.
    - В `ModuleOrigin::parseChecksum()` ветвиться один раз по факту наличия ключа, а invariant оставить constructor.
    - В `ScaffoldModuleUseCase` вынести `'1.0.0'` в private const `DEFAULT_VERSION`.
    - Проверить `ModuleSourceKind` comment; если текст можно прочитать как реализованный runtime beyond `local`/`zip`, переписать его как текущий контракт плюс отдельное roadmap-упоминание.

13. [x] Обновить документацию и tests под переименования.
    - `docs/cli.md`, `docs/manifest.md`, `docs/configuration.md` обновить по group pattern, group label validation, explicit no-verification note.
    - `README.MD` менять только если там есть устаревшие claims по checksum/group.
    - Не трогать `.ai-factory/rules/*`; `.ai-factory/DESCRIPTION.md` и `.ai-factory/ARCHITECTURE.md` менять только если пользователь отдельно попросит синхронизировать AI-context.

14. [x] Прогнать quality gates после implementation.
    - `composer rector`
    - `composer format`
    - `composer phpstan`
    - `composer test`
    - При необходимости точечно до полного suite: relevant PHPUnit/Pest tests для Manifest, Registry, Application/UseCases, Commands, Loaders.

15. [x] После фиксов план не удалять.
    - Отметить выполненные пункты `[x]`.
    - Добавить короткий блок "Implementation Notes" с фактическими decisions и quality gate результатами.
    - Создать self-improvement patch в `.ai-factory/patches/` по workflow, но сохранить этот `FIX_PLAN.md`.

## Files to Modify

- `src/Support/StringKeyedObject.php` — новый helper для string-keyed object conversion.
- `src/Manifest/Parsing/ManifestFieldReader.php` — общий helper, новый group regex, сообщения.
- `src/Registry/VO/CachedModuleDescriptor.php` — общий helper для cached manifest object.
- `src/Manifest/ModuleStateRepository.php` — helper usage, `source` naming, `writeValues()` fallback, readable object predicates.
- `src/Manifest/VO/ModuleOrigin.php` — single checksum invariant owner via constructor; parser only parses.
- `src/Manifest/VO/ModuleStateDocument.php` — property rename `origin` -> `source` with docblock.
- `src/Manifest/VO/Checksum.php` — docblock correction.
- `src/Application/Support/ModuleSourcePreparer.php` — extract archive checksum method.
- `src/Application/UseCases/ListModulesUseCase.php` — production change only if existing filtering needs adjustment for the new tests; otherwise leave as-is and cover current behavior.
- `src/Console/Commands/Modules/ModulesListCommand.php` — group validation, group-specific empty output, resolver method rename.
- `src/Application/Support/ModuleGroupLabelResolver.php` — strict config validation, method renames, early returns.
- `src/Application/UseCases/ScaffoldModuleUseCase.php` — default version constant.
- `tests/Unit/Manifest/Parsing/ManifestFieldReaderTest.php` — group regex and helper behavior.
- `tests/Unit/Manifest/VO/ModuleOriginTest.php` — wrapped constructor invariant expectations still include state path context.
- `tests/Unit/Manifest/ModuleStateRepositoryTest.php` — source property rename, writeValues missing-state behavior, object helper behavior.
- `tests/Unit/Registry/ModuleRegistryCacheTest.php` — cached manifest object helper regression.
- `tests/Unit/Application/UseCases/ListModulesUseCaseTest.php` — group filter unit coverage.
- `tests/Feature/Commands/ModulesListCommandTest.php` — invalid/missing group command behavior.
- `tests/Unit/Application/Support/ModuleGroupLabelResolverTest.php` — strict malformed config tests and method rename.
- `tests/Unit/Loaders/FactoryLoaderTest.php` — `Factory::$factoryNameResolver` tripwire.
- `docs/cli.md`, `docs/manifest.md`, `docs/configuration.md` — docs sync for group/checksum behavior.

## Risks & Considerations

- `ModuleStateDocument::$origin` -> `$source` is an intentional public property rename. Backward compatibility for PHP property reads or named arguments is out of scope for this fix; do not add aliases/adapters. JSON key `source` stays unchanged.
- Strict `modules.groups` validation can make `modules:list` fail when host config is malformed. This is intentional because silent fallback hid the only validation point for that map.
- `--group=missing` remains a successful command with explicit message, not a failure. Invalid group format fails.
- The checksum verification gap is documented as roadmap, not implemented as `--checksum`, to avoid expanding lifecycle feature scope.
- No runtime logging is added in `src/`, by project rule.
- Helper must not introduce forbidden dependencies, facades, mutable static properties, or direct filesystem I/O.

## Test Coverage

- Unit: `ManifestFieldReaderTest` for segment kebab-case and object helper behavior.
- Unit: `ModuleOriginTest` for checksum missing/forbidden/non-string/invalid digest after moving invariant to constructor wrapping.
- Unit: `ModuleStateRepositoryTest` for string-keyed state/source/settings values, `$source` property, and `writeValues()` preserving module state without existing state file.
- Unit: `ModuleRegistryCacheTest` for cached manifest with integer keys.
- Unit: `ListModulesUseCaseTest` for `group='content'` and `group=null` modules.
- Feature: `ModulesListCommandTest` for invalid group failure and valid missing group message.
- Unit: `ModuleGroupLabelResolverTest` for strict malformed config and missing mapping fallback.
- Unit: `FactoryLoaderTest` reflection tripwire for `Factory::$factoryNameResolver`.

## Successful Acceptance Criteria

- All six noticeable remarks and all listed nits have an implemented code/docs/test response, or an explicit scoped decision in "Implementation Notes" explaining why a requested alternative was intentionally not implemented.
- `ModuleOrigin` has one checksum presence/absence owner: constructor. `parseChecksum()` no longer enforces kind-specific presence/absence.
- No duplicated string-keyed object loops remain in the three reported locations.
- `--group` validates format, filters correctly, and reports valid empty group results explicitly.
- Group regex rejects trailing/double/leading hyphens and underscores.
- `state.json` remains backward-compatible with key `source`; internal naming no longer says `origin` at the `ModuleStateDocument` serialization boundary.
- The shared string-keyed object helper is the only implementation of the key-loop; reported call sites do not retain local duplicates, wrappers, or dead fallback code.
- CI catches Laravel `Factory::$factoryNameResolver` removal/rename through a test.
- `writeValues()` no longer silently changes enabled state to false when no state file exists but the passed `Module` has enabled state.
- Docs accurately distinguish provenance checksum from unimplemented integrity verification.
- `composer rector`, `composer format`, `composer phpstan`, and `composer test` pass.
- `.ai-factory/FIX_PLAN.md` remains in place after implementation and has checked-off tasks plus implementation notes.

## Unsuccessful Acceptance Criteria

- Any reported issue is skipped without an explicit implementation note and rationale.
- The fix adds `[FIX]`, `Log::*`, global helper logging, facades, debug calls, or direct filesystem I/O in disallowed `src/` classes.
- `state.json` key is changed from `source` to `origin` without migration/backward compatibility.
- `--group` invalid input still silently returns a generic empty list.
- Valid existing state/cache/manifest objects with string keys regress, or integer-keyed decoded objects are silently accepted.
- Checksum verification is documented as current runtime without being implemented and tested.
- The implementation deletes `FIX_PLAN.md`.
- Quality gates are not run, fail, or are replaced only with dry-run commands after mutating fixes.

## Implementation Notes

**Implemented:** 2026-06-01. All 15 steps applied. `FIX_PLAN.md` retained as traceability artifact per the plan header.

### Decisions

- **Shared helper (Steps 1–2).** `src/Support/StringKeyedObject.php` (`final readonly`, single static `toStringKeyedObject(array, callable(): Throwable)`). It only walks keys and rejects non-string (integer-coerced) keys; the caller passes a closure that builds the context-specific typed exception, so the helper carries no exception dependency. The PHP numeric-key coercion comment moved into the helper. All three reported call sites now delegate: `ManifestFieldReader::requiredObject()` keeps ownership of the "is a JSON object" boundary check then delegates; `CachedModuleDescriptor::fromCacheEntry()` delegates after the required-field check; `ModuleStateRepository` dropped its private `requireStringKeys()` entirely — `readStateFile()`, `extractSource()` and `assertJsonObject()` call the helper directly. Closure messages preserve the exact pre-refactor exception text (`state file must be a JSON object.`, `source must be a JSON object.`, `{field} must be a JSON object.`).
- **Checksum invariant owner (Steps 3, 12).** `ModuleOrigin::parseChecksum()` no longer takes `$kind` and no longer enforces presence/absence — it only parses (absent key → `null`; present non-string → `InvalidModuleStateException`; bad digest → wrapped). The kind↔checksum invariant lives solely in the constructor; `fromArray()` wraps the `new self(...)` in `try/catch (InvalidArgumentException)` and re-throws as `InvalidModuleStateException::forPath()` with state-path context. `array_key_exists` (not `isset`) preserves explicit-null detection: an explicit `null` checksum is now a present-but-non-string parse error (`checksum must be a string when present.`) rather than the old kind-specific message — `ModuleOriginTest` updated accordingly (zip-missing → `requires a checksum`, local-with → `must not carry a checksum`, local explicit-null → `checksum must be a string`).
- **Group pattern (Step 4).** `MODULE_GROUP_PATTERN` → `/^[a-z0-9]+(-[a-z0-9]+)*$/` (segment kebab-case). This intentionally now permits a leading digit (`1content`, consistent with the already-valid `a1`), and rejects leading/trailing/double hyphens and underscores. Message updated (dropped "starting with a letter", kept "must be kebab-case"). `ManifestMetaTest` and `ManifestFieldReaderTest` providers updated: `1content` moved to the valid set; `content-`/`-content`/`foo--bar` added to the invalid set.
- **`--group` alignment (Step 5).** `ModulesListCommand` validates `--group` format via `ManifestFieldReader::assertModuleGroup` (wrapped in a small `isValidGroup()` predicate) and returns `FAILURE` with a command-specific message before touching the registry. A valid group with no matches now prints `No modules found in group [<group>].`. `ListModulesUseCase` filtering is unchanged (it already filtered by group); covered with new unit tests. Resolver call updated to `displayLabel()`.
- **`source` boundary naming (Step 6).** `ModuleStateDocument::$origin` → `$source` (promoted, with docblock clarifying it is `ModuleOrigin` provenance, not staging `ModuleSourceKind`). `ModuleStateRepository` uses `source:` named args and `extractOrigin()` → `extractSource()`. No deprecated alias / dual property / magic getter added. JSON key `source` and the `state.json` format are unchanged — no migration. The `InstallModuleUseCase`/`UpdateModuleUseCase` positional constructor calls keep working unchanged (out of scope, no new positional `$origin` calls added).
- **Factory tripwire (Step 7).** Added `FactoryLoaderTest::laravelFactoryStillExposesStaticFactoryNameResolver()` asserting `Factory::$factoryNameResolver` exists and is static, with a comment explaining it is an intentional reflection dependency. No runtime change to `FactoryLoader`.
- **Checksum docblock + preparer (Step 8).** `Checksum` docblock: "installed module artifact" → "source archive". `ModuleSourcePreparer` gained a private `checksumForArchive()` so the staging flow and the checksum error message no longer interleave. No `Checksum::ofFile()`/`fromDigest()` added — the VO stays I/O-free.
- **No verification (Step 9).** No `--checksum` added. `docs/cli.md` and `docs/manifest.md` now explicitly state the checksum records archive provenance at read time and that integrity/signature verification is unimplemented roadmap.
- **`writeValues()` edge-case (Step 10).** When no `state.json` exists yet, `writeValues()` now persists the passed `Module`'s own state instead of `defaultDisabled()`, so writing values never silently flips `enabled`/timestamps. Covered by `writeValuesPreservesEnabledStateWhenNoStateFileExists`.
- **Strict group labels (Step 11).** `ModuleGroupLabelResolver`: `label()` → `displayLabel()`, `mappedLabel()` → `configuredLabel()`, ternary unfolded to early return. Malformed config is now fail-loud (`InvalidConfigurationException::forKey('modules.groups', …)`): a present-but-non-array map, or a present-but-non-string/blank label **for the requested group**, throws. **Refinement during implementation:** an absent map (`config('modules.groups')` returns `null`) is treated as the default "no labels" case and falls back to the bare code — throwing on `null` broke the common unconfigured path (`listShowsGroupColumn`/`listFiltersByGroup`). Missing mapping for a valid code still falls back. Tests updated to expect throws for malformed-string/array/blank labels and fallback for absent/missing.
- **Magic literals / comments (Step 12).** `ScaffoldModuleUseCase` `'1.0.0'` → `private const DEFAULT_VERSION`. `assertJsonObject()`/`extractSource()` use a named `$isJsonObject` guard. `ModuleSourceKind` comment reworded so `zip` reads as the only implemented format with git/url/registry explicitly roadmap.
- **Docs (Step 13).** Updated `docs/manifest.md` (group regex + provenance-not-verification note), `docs/cli.md` (`--group` validation + group-specific empty message + checksum provenance note), `docs/configuration.md` (group labels are fail-loud, not silently lenient). `.ai-factory/rules/*`, `.ai-factory/DESCRIPTION.md` and `.ai-factory/ARCHITECTURE.md` left untouched per ownership boundary (the `/^[a-z][a-z0-9-]*$/` mentions in AI-context files were intentionally not synced — no explicit request).
- **No `src/` logging.** No `[FIX]`/`Log::*`/global-helper logging added, per project rule and skill-context; diagnostics stay in typed exceptions, command output and tests.

### Quality gates (Step 14)

Run in mutating-first order per project skill-context:

- `composer rector` — done, no changes.
- `composer format` — done, no files changed.
- `composer phpstan` — **No errors** (level max, `src/` only).
- `composer test` — **green**: architecture suite (36), unit (501), feature (63).
