# Fix Plan: устранение замечаний ревью ветки feature/meta-group-source-origin

**Problem:** Ревью ветки (собственное + 10 `dandy-*` суб-агентов) выявило: дрейф документации (обрезанный пример checksum, отсутствие required `kind` в README), пробелы тестов (нет теста `StringKeyedObject`, не покрыта полная замена origin при update, не прогнан throw-путь label-резолвера), примитивную одержимость (`group` и `version` — сырые строки с дублированием правил), потерю текста исходной ошибки в double-fault `UpdateModuleUseCase`, смешение happy-path и обработки ошибок в `ModulesListCommand`, и набор nits. Блокеров нет.
**Created:** 2026-06-01 17:40 +07
**Status:** ✅ РЕАЛИЗОВАНО 2026-06-01. Все шаги применены, кроме шага 17 (осознанно отменён — см. ниже). Quality gates зелёные: `composer rector` (2 эквивалентных авто-правки) → `composer format` (без изменений) → `composer phpstan` (level max, 0 ошибок) → `composer test` (arch 36 / unit 520 / feature 64, все зелёные). Файл оставлен как traceability artifact (как и предыдущий план).
**Mode:** plan only (исходно). Реализован при повторном запуске `/aif-fix`.

## Глобальные инварианты плана (применять ко ВСЕМ шагам)

- **Никакого fallback и обратной совместимости.** Проект v2.0. Если меняется тип/сигнатура — меняется во всех call site и тестах разом; промежуточных «принимает и string, и VO» перегрузок не вводить.
- **Никаких runtime-логов `[FIX]` в `src/`** (override базового `$aif-fix`; см. skill-context «No Runtime Fix Logging in Package Core»). Диагностическая поверхность — типизированные исключения с контекстом, вывод команды, тесты.
- **Все новые VO:** `declare(strict_types=1);`, `final readonly`, конструктор-DI, без фасадов/хелперов; живут в `src/Manifest/VO/`.
- **Цепочка исключений:** при оборачивании доменного `InvalidArgumentException` из VO в `InvalidManifestException`/`InvalidModuleStateException`/`Module*Exception` всегда передавать исходное как `$previous`.
- **Парсинг внешнего JSON:** closed allow-list ключей; `array_key_exists()` там, где явный `null` ≠ отсутствие ключа (skill-context «Strict State JSON Parsing Fixes»).
- **Quality-pass реализации (НЕ этого запуска):** после кодовых правок прогнать `composer rector` → `composer format` → `composer phpstan` → `composer test` (мутирующие, не dry-run — skill-context «Post-Fix Mutating Quality Pass»). Реализация считается завершённой только при зелёных всех четырёх.

## Analysis

- `composer/semver ^3.4` уже в зависимостях; `Composer\Semver\VersionParser::normalize()` бросает `UnexpectedValueException` на невалидной версии — это валидатор для `Version` VO. `Semver::satisfies(version, constraint)` используется в `TopologicalSorter:105`.
- `version` модуля течёт через: `ManifestMeta::$version`, `ModuleOrigin::$installedVersion`, `TopologicalSorter:105/110`, `ScaffoldModuleUseCase::DEFAULT_VERSION`, `UpdateModuleResult::$oldVersion/$newVersion`, рендер `ModulesListCommand:79`. **Constraint'ы зависимостей (`ModuleDependency::$constraint`) — это диапазоны, НЕ версии; их НЕ трогаем.** `ModuleRegistryCachePayload::$version` — int схемы кэша, НЕ трогаем.
- `group` модуля течёт через: `ManifestMeta::$group`, `ScaffoldModuleConfig::$group`, `MakeModuleCommand --group`, `ScaffoldModuleUseCase::validateGroup`, `ListModulesUseCase` фильтр, `ModulesListCommand` (`--group`, `isValidGroup`, `displayLabel`, `emptyMessage`), `ModuleGroupLabelResolver::displayLabel`, `ManifestFieldReader::MODULE_GROUP_PATTERN`/`assertModuleGroup`. Текст ошибки kebab-case продублирован в `ManifestFieldReader.php:188` и `ModulesListCommand.php:53`.
- Решения пользователя по развилкам: `group` → VO `ModuleGroup`; `version` → VO `Version`; naming `source/origin` → оставить как есть; provenance-DRY (#4) → оставить как есть.

---

## Fix Steps

### Блок A — VO `Version` (замечание #5 + Version VO; поглощает guard непустоты)

1. [x] **Создать `src/Manifest/VO/Version.php`.**
   - `final readonly class Version { public function __construct(public string $value) }`.
   - В конструкторе: вызвать `(new VersionParser())->normalize($this->value)` внутри `try`; на `UnexpectedValueException` бросить `InvalidArgumentException("Version [{$this->value}] is not a valid semantic version.")`. Нормализованную форму **не сохранять** — хранится исходная строка `value` (round-trip в `module.json`/`state.json` обязан совпадать с авторской).
   - Метод `equals(self $other): bool` через сравнение `value` (для полноты VO).
   - **✅ Принято:** `new Version('1.0.0')`, `new Version('1.2.3')`, `new Version('2.0')` проходят; `->value` возвращает исходную строку без нормализации (`'2.0'` остаётся `'2.0'`).
   - **❌ Отклонено:** конструктор хранит нормализованную строку вместо исходной; пустая `''`, `'abc'`, `'1.x.0'` не бросают `InvalidArgumentException`; используется ручной regex вместо `VersionParser` (semver-правила принадлежат composer/semver).

2. [x] **`ManifestMeta`: `$version` → `Version`.**
   - `public Version $version`. В `fromArray`: `$versionRaw = ManifestFieldReader::requiredString($meta, 'version', 'meta', $manifestPath);` затем `try { $version = new Version($versionRaw); } catch (InvalidArgumentException $e) { throw InvalidManifestException::forPath($manifestPath, $e->getMessage(), $e); }`. В `toArray`: `'version' => $this->version->value`.
   - **✅ Принято:** `"version":"1.0.0"` парсится; `toArray()['version'] === '1.0.0'`; невалидная версия → `InvalidManifestException` с `getPrevious()` = доменный `InvalidArgumentException`.
   - **❌ Отклонено:** `toArray` пишет объект/нормализованную форму; потеряна цепочка `$previous`.

3. [x] **`ModuleOrigin`: `$installedVersion` → `Version`.**
   - `public Version $installedVersion`. `forLocal(Version $version)`, `forZip(Version $version, Checksum $checksum)`. В `fromArray`: прочитать `installed_version` как сейчас (string-валидация — первый барьер), затем `new Version($version)` в `try/catch(InvalidArgumentException)` → `InvalidModuleStateException::forPath($statePath, $e->getMessage(), $e)`. В `toArray`: `'installed_version' => $this->installedVersion->value`.
   - **Поглощает замечание #5:** пустую/битую `installed_version` отклоняет `Version` симметрично на write-path (конструкторы) и read-path (`fromArray`). Отдельный non-empty guard НЕ добавляется (избыточен поверх `Version`).
   - **✅ Принято:** `ModuleOrigin::forLocal(new Version('1.0.0'))` works; `state.json` round-trip сохраняет `installed_version` строкой; пустой/битый `installed_version` из `state.json` → `InvalidModuleStateException` с `$previous`; собрать origin с пустой версией невозможно (нет конструктора со строковой версией).
   - **❌ Отклонено:** остался хоть один конструктор/фабрика `ModuleOrigin`, принимающий `string $version`; `installed_version` сериализуется не строкой.

4. [x] **Обновить всех потребителей `version`-строки на `Version`/`->value`.**
   - `TopologicalSorter:105` → `Semver::satisfies($dependency->meta->version->value, $constraint)`; `:110` → передать `$dependency->meta->version->value` в `ModuleDependencyIncompatibleException::forDependency(...)` (сигнатура исключения остаётся `string`).
   - `ScaffoldModuleUseCase`: `version: new Version(self::DEFAULT_VERSION)` при сборке `ManifestMeta`; `ModuleOrigin::forLocal($module->meta->version)` (уже `Version`).
   - `InstallModuleUseCase:89`, `UpdateModuleUseCase:70`: `ModuleOrigin::forZip($candidate->meta->version, $prepared->checksum)` — `meta->version` теперь `Version`, `forZip` принимает `Version`.
   - `ModulesListCommand:79`: `$m->meta->version->value` в строке таблицы.
   - **✅ Принято:** `composer phpstan` (level max) зелёный; `modules:list` печатает версию строкой; topological sort и incompatible-исключение работают как раньше.
   - **❌ Отклонено:** где-то `meta->version` уходит в строковый контекст без `->value` (Stringable-костыль/ошибка типов); `ModuleDependency::$constraint` ошибочно обёрнут в `Version`.

5. [x] **`UpdateModuleResult`: `$oldVersion`/`$newVersion` → `Version`.**
   - `public Version $oldVersion`, `public Version $newVersion` (из `$existingModule->meta->version` / `$candidate->meta->version`). В `ModulesUpdateCommand` рендерить `->value` везде, где печатается версия.
   - **✅ Принято:** вывод `modules:update` показывает старую/новую версию строками; типы DTO честные (`Version`).
   - **❌ Отклонено:** DTO снова смешивает `string`-версию и `Version`; команда печатает `Version`-объект без `->value`.

### Блок B — VO `ModuleGroup` (замечание #3: примитив + дублирование текста ошибки)

6. [x] **Создать `src/Manifest/VO/ModuleGroup.php`.**
   - `final readonly class ModuleGroup { public function __construct(public string $value) }`.
   - Перенести паттерн `/^[a-z0-9]+(-[a-z0-9]+)*$/` и текст ошибки kebab-case **внутрь** VO — единственный владелец правила. На несовпадение: `InvalidArgumentException("Module group [{$this->value}] must be kebab-case: lowercase letters and digits in hyphen-separated segments.")`.
   - `equals(?ModuleGroup $other): bool => $other !== null && $other->value === $this->value`.
   - **✅ Принято:** `'content'`, `'e-commerce'`, `'a1-b2'`, `'1content'` проходят; `'Foo'`, `'foo-'`, `'foo--bar'`, `'-foo'`, `''`, `'my_group'` бросают `InvalidArgumentException`; текст kebab-case в кодовой базе ровно один (grep `hyphen-separated segments` → 1 совпадение в `src/`).
   - **❌ Отклонено:** паттерн/текст остались скопированы где-либо ещё в `src/`; `equals` не null-safe.

7. [x] **Удалить `ManifestFieldReader::assertModuleGroup()` и константу `MODULE_GROUP_PATTERN`.**
   - Однозначное удаление: правило целиком переехало в `ModuleGroup`. `assertModuleName`/`MODULE_NAME_PATTERN` НЕ трогаем.
   - **✅ Принято:** в `ManifestFieldReader` нет `assertModuleGroup` и `MODULE_GROUP_PATTERN`; grep `assertModuleGroup` в `src/` → 0.
   - **❌ Отклонено:** метод/константа оставлены «на всякий случай» или помечены deprecated.

8. [x] **`ManifestMeta`: `$group` → `?ModuleGroup`.**
   - `public ?ModuleGroup $group`. В `fromArray`: `$groupRaw = ManifestFieldReader::optionalString($meta, 'group', 'meta', $manifestPath); $group = $groupRaw === null ? null : new ModuleGroup($groupRaw);` с `try/catch(InvalidArgumentException)` → `InvalidManifestException::forPath($manifestPath, $e->getMessage(), $e)`. В `toArray`: `if ($this->group !== null) { $meta['group'] = $this->group->value; }`.
   - **✅ Принято:** манифест без `group` → `$group === null`, ключ отсутствует в `toArray()`; валидная группа round-trip'ит строку; невалидная → `InvalidManifestException` с `$previous`.
   - **❌ Отклонено:** `toArray` пишет объект; пустой/невалидный `group` молча проходит.

9. [x] **`ScaffoldModuleConfig::$group` → `?ModuleGroup`; валидация группы переезжает в команду.**
   - `public ?ModuleGroup $group = null`.
   - `MakeModuleCommand`: после чтения `--group` построить `?ModuleGroup` — `$group = $groupRaw === null ? null : new ModuleGroup($groupRaw);` в `try/catch(InvalidArgumentException $e) { $this->components->error($e->getMessage()); return self::FAILURE; }` (зеркало блока `--kind`). Передать VO в `ScaffoldModuleConfig`.
   - `ScaffoldModuleUseCase`: **удалить метод `validateGroup()` и его вызов** (строка 56). Это попутно снимает nit про дубль `validateName`/`validateGroup`. `validateName` остаётся.
   - **✅ Принято:** `make:module x --group=Bad` печатает kebab-ошибку + FAILURE до файловых операций; `--group=content` создаёт модуль с `meta.group=content`; `ScaffoldModuleUseCase` без `validateGroup`.
   - **❌ Отклонено:** невалидная группа доходит до скелета/файловых операций; `validateGroup` остался в use-case.

10. [x] **`ListModulesUseCase` + `ModulesListCommand` + `ModuleGroupLabelResolver` на `?ModuleGroup`.**
    - `ListModulesUseCase::execute(?bool, ?ModuleKind, ?ModuleGroup $groupFilter)`. Фильтр: `static fn (Module $m): bool => $groupFilter->equals($m->meta->group)`.
    - `ModulesListCommand`: из `--group` построить `?ModuleGroup` (try/catch(InvalidArgumentException) → `components->error` → FAILURE). **Удалить приватный `isValidGroup()`** — проверка теперь в конструкторе VO. Передать `?ModuleGroup` в use-case. `emptyMessage(?ModuleGroup $groupFilter)` рендерит `$groupFilter->value`. Строка таблицы: `$groupLabels->displayLabel($m->meta->group)`.
    - `ModuleGroupLabelResolver::displayLabel(?ModuleGroup $group)`: `if ($group === null) return '';` далее работать с `$group->value` (код) для lookup и формата `"{$label} ({$group->value})"`.
    - **✅ Принято:** `modules:list --group=Bad` → kebab-ошибка + FAILURE; `--group=missing` (валидный, без модулей) → «No modules found in group [missing].»; `--group=content` фильтрует; колонка Group рендерит `Label (code)`/`code`/пусто как раньше; `isValidGroup` отсутствует.
    - **❌ Отклонено:** в `ListModulesUseCase` сравнение по сырой строке вместо `ModuleGroup::equals`; `isValidGroup` остался; потеряно различие «невалидный формат» vs «валидно, но пусто».

### Блок C — Documentation (замечание #1 + readme/onboarding находки)

11. [x] **`docs/manifest.md:103` — полный 64-символьный checksum.**
    - Заменить обрезанный `"e3b0c44298fc1c149afbf4c8996fb924..."` на реальный 64-символьный lowercase sha256, напр. `"e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"` (sha256 пустого ввода; соответствует `Checksum::PATTERN`).
    - **✅ Принято:** значение проходит `/^[0-9a-f]{64}$/`; нет многоточия/обрезки; соответствует прозе «64 hex, lowercase».
    - **❌ Отклонено:** значение ≠ 64 символов или содержит `...`/префикс алгоритма.

12. [x] **`README.MD` — минимальный `module.json` с required `kind` (и `group`).**
    - В ручной минимальный пример манифеста добавить `"kind": "module"` (required: `ManifestMeta::fromArray` → `requiredString('kind')`); для паритета с `docs/manifest.md` добавить опциональный `"group": "content"` с пометкой «необязательное».
    - **✅ Принято:** скопированный из README минимальный `module.json` проходит `ManifestValidator` без ошибки про отсутствующий `kind`.
    - **❌ Отклонено:** `kind` всё ещё отсутствует в примере.

13. [x] **`docs/configuration.md` — показать секцию `source` в дереве `state.json`; `docs/cli.md` — сверить `api_v1`.**
    - В дерево `state.json` в `configuration.md` добавить опциональный блок `source` (`kind`, `installed_version`, `checksum` для zip) согласованно с `docs/manifest.md`.
    - Сверить, что `docs/cli.md` про versioned API согласован с `config/modules.php` (`routing.types.api_v1` + middleware-группа в `bootstrap/app.php`); при расхождении выровнять `cli.md` под конфиг.
    - **✅ Принято:** дерево `state.json` в `configuration.md` содержит `source`; формулировки `cli.md` про `api_v1`/middleware совпадают с `config/modules.php`.
    - **❌ Отклонено:** осталось расхождение doc↔config по `api_v1`.

### Блок D — Тесты (замечание #2 + пробелы покрытия #8)

14. [x] **Создать `tests/Unit/Support/StringKeyedObjectTest.php`.**
    - Контракт `toStringKeyedObject`: (а) только строковые ключи проходят без изменений; (б) первый integer-ключ вызывает `$onError`; (в) брошенное исключение — ровно тот инстанс, что вернул callback (`assertSame`); (г) пустой массив → пустой массив, `$onError` не вызывается.
    - **✅ Принято:** новый файл существует, кейсы зелёные; проверена идентичность брошенного исключения и срабатывание callback на первом int-ключе.
    - **❌ Отклонено:** утилита проверяется только косвенно через другой класс; нет проверки идентичности исключения.

15. [x] **Тест: update полностью ЗАМЕНЯЕТ origin (роняет поле старого origin).**
    - В `UpdateModuleUseCaseTest`: исходный `state.json` с `source.kind=zip` и checksum `A`; обновление zip'ом с другим содержимым → итоговый `source.checksum` равен новому, а старого `A` в файле НЕТ (assert именно отсутствие старого значения, не только наличие нового).
    - **✅ Принято:** тест падает на гипотетической merge-реализации и проходит на текущей replace (`ModuleOrigin::forZip(...)`).
    - **❌ Отклонено:** проверяется лишь наличие нового checksum без проверки исчезновения старого.

16. [x] **Тест: `modules:list` фейлит на битом `modules.groups` (throw-путь резолвера end-to-end).**
    - В `ModulesListCommandTest`: `config(['modules.groups' => 'not-an-array'])` (или валидная группа с label не-строкой) при наличии модуля в этой группе → команда возвращает FAILURE/печатает `InvalidConfigurationException`-сообщение.
    - **✅ Принято:** throw-ветка `ModuleGroupLabelResolver` подтверждена через реальный вызов `modules:list`.
    - **❌ Отклонено:** кейс не доходит до резолвера (например модуль без группы — label не запрашивается).

17. [ ] **ОТМЕНЕНО при реализации — `$extraEntries` НЕ удалять.**
    - Премиса плана была ошибочной: grep по токену `extraEntries` дал 0, но `ModuleSourcePreparerTest::prepareThrowsWhenSourceContainsStateFile` (`tests/Unit/Application/Support/ModuleSourcePreparerTest.php:123`) передаёт `$extraEntries` **позиционно** (`zipModuleSource($path, $manifest, ['state.json' => ...])`), чтобы проверить инвариант «source-архив с `state.json` отклоняется». Параметр реально используется и охраняет важный инвариант. Удаление ломало этот тест → шаг откатан, `$extraEntries` сохранён. Корректность важнее буквы плана.

18. [x] **Обновить ВСЕ прямые конструкции VO в тестах/фикстурах под `Version`/`ModuleGroup`.**
    - Каждый `new ManifestMeta(..., version: '...', ..., group: '...'?)`, `ModuleOrigin::forLocal('...')`/`forZip('...', ...)`, прямые сборки `UpdateModuleResult` → `new Version('...')` / `new ModuleGroup('...')` / `null`. JSON-фикстуры (`module.json`/`state.json` в `CreatesModuleFiles`) остаются строковыми (парсятся в VO при чтении). Добавить `tests/Unit/Manifest/VO/VersionTest.php` и `ModuleGroupTest.php`.
    - **✅ Принято:** `composer test` (arch+unit+feature) полностью зелёный; нет ошибок типов в тестовых конструкторах; новые VO покрыты юнит-тестами.
    - **❌ Отклонено:** хоть один тест конструирует `ManifestMeta`/`ModuleOrigin` со строковой версией/группой.

### Блок E — Точечные nits с однозначными решениями

19. [x] **`UpdateModuleUseCase` double-fault (строки 101–105): не терять текст исходной ошибки.**
    - В сообщение double-fault добавить `$e->getMessage()` исходной ошибки рядом с `$restoreError->getMessage()` (напр.: `"...restore also failed. Backup remains at [{$backupPath}]. Original error: {$e->getMessage()}. Restore error: {$restoreError->getMessage()}"`). `$previous = $e` оставить (корневая причина — уже корректно).
    - **✅ Принято:** верхнеуровневый `getMessage()` содержит и оригинальную, и restore-ошибку; `getPrevious()` = исходный `$e`.
    - **❌ Отклонено:** `$previous` переключили на `$restoreError`; текст исходной ошибки виден только через unwrap.

20. [x] **`ModulesListCommand::handle` — сузить `try` до `$useCase->execute(...)`.**
    - Парсинг `enabledFilter` (`match(true)`) и ранний `return SUCCESS` на пустом результате вынести из `try`; `try/catch(ModuleExceptionInterface)` обернуть только вызов use-case. Сборку `$rows` и `$this->table(...)` — линейно после успешного `execute`. (Совмещается с шагом 10.)
    - **✅ Принято:** в `try` остаётся только `execute()`; happy-path читается сверху вниз; коды возврата/сообщения не изменились — подтверждает зелёный `ModulesListCommandTest`.
    - **❌ Отклонено:** чистый парсинг опций остался внутри `try`.

21. [x] **`ModuleStateRepository` — приватный helper вместо трёх одинаковых `$onError`-замыканий.**
    - Ввести `private function objectOrFail(...)`, инкапсулирующий `StringKeyedObject::toStringKeyedObject($value, fn () => InvalidModuleStateException::forPath($statePath, $message))`. Переключить call site (`readStateFile`, `extractSource`, `assertJsonObject`). Публичный контракт `StringKeyedObject` не меняется.
    - **✅ Принято:** нет повторяющегося inline-замыкания `static fn (): InvalidModuleStateException => ...` в репозитории; поведение/сообщения идентичны (тесты репозитория зелёные).
    - **❌ Отклонено:** дублирование замыканий осталось; изменился публичный API `StringKeyedObject`.

22. [x] **`ModuleGroupLabelResolver` — читать `modules.groups` один раз в конструкторе.**
    - В конструкторе прочитать `modules.groups` и привести к `private readonly ?array $groups`: `null` (отсутствует) → null; present-and-array → массив; **present-and-non-array → бросить `InvalidConfigurationException::forKey('modules.groups', ...)` сразу** (грубая мискофигурация валит fail-loud детерминированно). Per-group валидация label'а (non-string/blank) остаётся ленивой в `configuredLabel`, сохраняя семантику «битый label конкретной группы валит только при её отображении». Убрать повторное `config->get` + `is_array` на каждую строку.
    - **✅ Принято:** `config->get('modules.groups')` вызывается один раз за жизненный цикл резолвера; non-array map валит при первом использовании; битый label конкретной группы валит только когда группа реально отображается.
    - **❌ Отклонено:** `readonly` нарушен ленивой мутацией свойства; per-label fail-loud стал eager для всех групп (смена семантики); non-array map молча игнорируется.

23. [x] **`FactoryLoader:75-83` — однострочный комментарий про намеренный swallow.**
    - Над веткой, где не-строковый результат предыдущего resolver'а игнорируется и идёт fall-through к `defaultFactoryClassFor`, добавить пояснение: контракт `guessFactoryNamesUsing` требует class-string; не-строка намеренно отбрасывается, fall-through к дефолту — не баг. Кода не менять.
    - **✅ Принято:** комментарий присутствует; поведение/`FactoryLoaderTest` не изменились.
    - **❌ Отклонено:** ради комментария изменена логика делегирования.

### Блок F — Зафиксированные решения «без изменений» (осознанно, по выбору пользователя)

24. [x] **Provenance-DRY (#4) — НЕ извлекать.**
    - Реально дублируется одна строка `ModuleOrigin::forZip($candidate->meta->version, $prepared->checksum)` (Install/Update); state/values в трёх use-case различны. Извлечение даёт индиректность ради ~1 строки. **Решение: оставить как есть**, зафиксировано здесь как осознанное; код не трогаем.
    - **✅ Принято:** в плане явно отмечено как решение; новых коллабораторов сборки state-документа не вводится.

25. [x] **Naming `source`/`origin` — НЕ переименовывать.**
    - `ModuleStateDocument::$source`, `ModuleStateRepository::extractSource()` и локальные `$source` намеренно совпадают с персистентным JSON-ключом `source` в `state.json` (осознанное решение прошлого цикла, есть поясняющий комментарий). **Решение: оставить как есть.**
    - **✅ Принято:** имена не меняются; комментарий-объяснение в `ModuleStateDocument` сохранён.

26. [x] **Прочие naming-nits — НЕ менять (cosmetic, deferred).**
    - `StringKeyedObject::toStringKeyedObject` (статтер), `ModuleStateRepository::assertJsonObject` (assert-возвращающий-значение), `ModuleOrigin::fromArray` (vs `fromState`), пара энумов `ModuleSourceKind`/`ModuleOriginKind` (читаются синонимами) — чистая косметика, переименование = churn без функциональной выгоды и не запрошено. **Решение: не менять**, зафиксировано явно (нет «висящих» нерешённых пунктов ревью).
    - **✅ Принято:** идентификаторы остаются; решение задокументировано.

---

## Files to Modify

**Новые файлы:**
- `src/Manifest/VO/Version.php` (шаг 1) · `src/Manifest/VO/ModuleGroup.php` (шаг 6)
- `tests/Unit/Manifest/VO/VersionTest.php`, `tests/Unit/Manifest/VO/ModuleGroupTest.php` (шаги 1, 6, 18)
- `tests/Unit/Support/StringKeyedObjectTest.php` (шаг 14)

**Изменяемые (src):**
- `src/Manifest/VO/ManifestMeta.php` — version→Version, group→?ModuleGroup (шаги 2, 8)
- `src/Manifest/VO/ModuleOrigin.php` — installedVersion→Version (шаг 3)
- `src/Manifest/Parsing/ManifestFieldReader.php` — удалить assertModuleGroup/MODULE_GROUP_PATTERN (шаг 7)
- `src/Support/TopologicalSorter.php` — `->value` (шаг 4)
- `src/Application/UseCases/ScaffoldModuleUseCase.php` — Version default, удалить validateGroup (шаги 4, 9)
- `src/Application/UseCases/InstallModuleUseCase.php`, `UpdateModuleUseCase.php` — forZip(Version), double-fault message (шаги 4, 5, 19)
- `src/Application/UseCases/ListModulesUseCase.php` — ?ModuleGroup фильтр (шаг 10)
- `src/Application/DTOs/ScaffoldModuleConfig.php` — group→?ModuleGroup (шаг 9)
- `src/Application/DTOs/UpdateModuleResult.php` — old/newVersion→Version (шаг 5)
- `src/Application/Support/ModuleGroupLabelResolver.php` — ?ModuleGroup + memoize map (шаги 10, 22)
- `src/Console/Commands/Modules/MakeModuleCommand.php` — ModuleGroup из --group (шаг 9)
- `src/Console/Commands/Modules/ModulesListCommand.php` — ModuleGroup, удалить isValidGroup, сузить try, version->value (шаги 4, 10, 20)
- `src/Console/Commands/Modules/ModulesUpdateCommand.php` — version->value в выводе (шаг 5)
- `src/Loaders/FactoryLoader.php` — комментарий (шаг 23)
- `src/Manifest/ModuleStateRepository.php` — helper objectOrFail (шаг 21)

**Изменяемые (docs):** `docs/manifest.md` (шаг 11), `README.MD` (шаг 12), `docs/configuration.md` + `docs/cli.md` (шаг 13).

**Изменяемые (tests):** все прямые конструкции ManifestMeta/ModuleOrigin/UpdateModuleResult (шаг 18) + `tests/Support/CreatesSourceArchive.php` (шаг 17) + новые/дополненные кейсы (шаги 14–16).

## Risks & Considerations

- **Объём тестовых правок (шаг 18)** — самый трудоёмкий пункт: смена типов `ManifestMeta`/`ModuleOrigin` ломает каждую прямую конструкцию в тестах. Не пропустить arch-тесты.
- **`ModuleGroupLabelResolver` eager non-array check (шаг 22)** — меняет момент падения для грубо битого `modules.groups` (теперь при первом использовании резолвера). Резолвер инъектируется в `handle` и конструируется всегда → non-array map обязан валить даже без `--group`; зафиксировать это тестом (шаг 16) или пометить как ожидаемое.
- **`Version` хранит исходную строку** — критично для round-trip `module.json`/`state.json`. Любая нормализация при сериализации = регрессия.
- **Контракт персистентного JSON не меняется** — `group`/`version` остаются строками в `module.json`/`state.json`; меняется только PHP-тип. Доки трогаем только в Блоке C.

## Test Coverage (сводно)

- `VersionTest`: валидные/невалидные semver, сохранение исходной строки, `equals`.
- `ModuleGroupTest`: валидный/невалидный kebab-case, `equals` null-safe, единственный источник текста ошибки.
- `StringKeyedObjectTest`: контракт string-ключей + идентичность callback-исключения (шаг 14).
- `UpdateModuleUseCaseTest`: полная замена origin с исчезновением старого checksum (шаг 15); double-fault message содержит обе ошибки (шаг 19).
- `ModulesListCommandTest`: throw-путь резолвера через `modules:list` (шаг 16); `--group` невалидный/пустой/совпадающий (шаг 10).
- `ManifestMetaTest`/`ModuleOriginTest`: round-trip Version/ModuleGroup строкой.

## Successful Acceptance Criteria (агрегат)

- Каждое замечание ревью имеет реализованный ответ (код/доки/тест) ИЛИ явное зафиксированное решение «без изменений» с обоснованием (Блок F). Нет «висящих» пунктов.
- `group` и `version` — типизированные VO (`ModuleGroup`/`Version`); сырых строк-версий/групп в `src/` и тестах не осталось; правила валидации и тексты ошибок имеют ровно одного владельца.
- Документация: пример checksum 64-символьный; README-`module.json` валиден (есть `kind`); `state.json`-дерево показывает `source`; `cli.md` согласован с `config/modules.php`.
- Тесты: `StringKeyedObject` покрыт напрямую; update-replace-origin и throw-путь резолвера покрыты; неиспользуемый `$extraEntries` удалён.
- `composer rector` → `composer format` → `composer phpstan` → `composer test` — все зелёные (мутирующие, не dry-run).

## Unsuccessful Acceptance Criteria (агрегат)

- Любое замечание пропущено без явной отметки/обоснования.
- Введён fallback/BC: дублирующие перегрузки «string|VO», deprecated-алиасы, dual-property адаптеры.
- Добавлены `[FIX]`/`Log::*`/глобальные хелперы/фасады/файловый I/O в запрещённых классах `src/`.
- `Version` сериализуется нормализованной формой (потеря исходной строки).
- Изменён персистентный JSON-контракт `module.json`/`state.json` (ключи/форматы).
- Quality gates не прогнаны, упали, или заменены dry-run после мутирующих правок.
