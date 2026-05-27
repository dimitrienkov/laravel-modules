# Fix Plan: Post-review cleanup для schema_version + ModuleKind

**Problem:** Code review выявил 7 улучшений: дублирование валидации enum, неинформативные ошибки, стилистические несоответствия, пробелы в тестах.
**Created:** 2026-05-27 17:30

## Analysis

Ревью ветки `feature/schema-version-module-kind` (8 dandy-* агентов + основное ревью) выявило:
- 3 копии паттерна `implode(', ', array_column(ModuleKind::cases(), 'value'))` — DRY violation
- `requiredInt`/`requiredBool` склеивают "ключ отсутствует" и "неверный тип" в одно сообщение
- `ModuleRegistryCachePayload` не включает фактическую версию кеша в ошибку
- `instanceof ModuleKind` вместо `!== null` в `ListModulesUseCase`
- Хардкод маппинга directory→kind в `ScaffoldModuleUseCase::inferKind()`
- Дубликат теста `to_manifest_array_*` в `ModuleTest`
- Отсутствует тест для `schema_version: 0`

Решения по вопросам:
- Двойной парсинг `schema_version` — оставляем как есть (не в scope)
- DRY enum — статический метод `allowedValuesString()` на `ModuleKind`
- Тест-дубликат — проверить и удалить

## Fix Steps

### Task 1: Добавить `ModuleKind::allowedValuesString()` и устранить 3 копии

**Файлы:**
- `src/Manifest/Enums/ModuleKind.php` — добавить метод
- `src/Manifest/VO/ManifestMeta.php` — использовать метод
- `src/Console/Commands/Modules/MakeModuleCommand.php` — использовать метод
- `src/Console/Commands/Modules/ModulesListCommand.php` — использовать метод

**Что делать:**
1. Добавить `public static function allowedValuesString(): string` в `ModuleKind`
2. Заменить `implode(', ', array_column(ModuleKind::cases(), 'value'))` → `ModuleKind::allowedValuesString()` во всех трёх местах

**Приёмка ✅:**
- Метод `allowedValuesString()` существует на `ModuleKind`, возвращает `'module, subsystem, integration'`
- Все три использования `implode(array_column(...))` заменены на вызов метода
- Существующие тесты проходят без изменений (поведение не изменилось)
- `composer phpstan` проходит

**Приёмка ❌:**
- Метод возвращает другой формат (e.g. с кавычками или разделитель `|`)
- Осталась хотя бы одна инлайн-копия `implode(array_column(...))`
- Метод добавлен, но не используется (dead code)

---

### Task 2: Разделить "missing" и "wrong type" в `requiredInt` и `requiredBool`

**Файлы:**
- `src/Manifest/Parsing/ManifestFieldReader.php` — `requiredInt()` и `requiredBool()`

**Что делать:**
Привести к паттерну `optionalInt` — сначала `array_key_exists` → "is required", затем проверка типа → "must be an integer/boolean". Разделить одно условие с `||` на два отдельных `if`.

Текущий паттерн (одинаковый для обоих):
```php
if (! array_key_exists($key, $data) || ! is_int($data[$key])) {
    throw ... "{$context}.{$key} must be an integer.";
}
```

Целевой паттерн:
```php
if (! array_key_exists($key, $data)) {
    throw ... "{$context}.{$key} is required.";
}
if (! is_int($data[$key])) {
    throw ... "{$context}.{$key} must be an integer.";
}
```

**Приёмка ✅:**
- `requiredInt` и `requiredBool` имеют два отдельных `if` с разными сообщениями
- Отсутствующий ключ → `"{context}.{key} is required."`
- Неверный тип → `"{context}.{key} must be an integer."` / `"must be a boolean."`
- Тесты `ManifestFieldReaderTest::required_int_throws_for_missing_key` обновлены — ожидают `"is required"` вместо `"must be an integer"`
- Тесты `ManifestValidatorTest::it_rejects_manifest_without_schema_version` обновлены — ожидают `"is required"`
- Тесты `ManifestMetaTest::it_rejects_missing_kind` — без изменений (kind проходит через `requiredString`, не через `requiredBool/Int`)
- `composer test` проходит полностью
- `composer phpstan` проходит

**Приёмка ❌:**
- Одно условие с `||` осталось в каком-либо из методов
- Тесты ожидают старое сообщение — тест упадёт
- Сообщения не соответствуют формату `"{context}.{key} ..."` (сломана консистентность)
- `requiredString` затронут (у него другая структура, трогать НЕ нужно)

---

### Task 3: Добавить фактическую версию кеша в сообщение об ошибке

**Файл:**
- `src/Registry/VO/ModuleRegistryCachePayload.php` — `fromCachedArray()`

**Что делать:**
Изменить сообщение ошибки с:
```
"module cache version is not supported."
```
на:
```
"module cache version {$actual} is not supported; expected " . self::SUPPORTED_VERSION . "."
```
где `$actual` — `$raw['version'] ?? 'null'`.

**Приёмка ✅:**
- Ошибка содержит фактическую версию И ожидаемую: `"module cache version 3 is not supported; expected 4."`
- Для отсутствующей версии: `"module cache version null is not supported; expected 4."`
- Тест `it_rejects_v3_cache_payload` обновлён — ожидает `"version 3 is not supported; expected 4"`
- Тест `it_throws_for_wrong_cache_version` обновлён аналогично
- `composer test` проходит

**Приёмка ❌:**
- Сообщение не содержит фактическую версию (старый формат)
- Формат расходится с аналогом в `ManifestValidator` (`"schema_version N is not supported; expected M"`)
- Тесты ожидают старое сообщение

---

### Task 4: `instanceof ModuleKind` → `!== null` в ListModulesUseCase

**Файл:**
- `src/Application/UseCases/ListModulesUseCase.php` — строка 30

**Что делать:**
Заменить `if ($kindFilter instanceof ModuleKind)` на `if ($kindFilter !== null)` для единообразия с `$enabledFilter !== null` на строке 23.

**Приёмка ✅:**
- Строка 30 содержит `$kindFilter !== null`
- Стиль проверки единообразен с `$enabledFilter !== null`
- `composer test` проходит
- `composer phpstan` проходит

**Приёмка ❌:**
- Осталось `instanceof ModuleKind`
- Изменена логика `enabledFilter` (не трогать)

---

### Task 5: Вынести маппинг directory→kind из хардкода в `ModuleKind`

**Файлы:**
- `src/Manifest/Enums/ModuleKind.php` — добавить `fromDirectoryName()`
- `src/Application/UseCases/ScaffoldModuleUseCase.php` — использовать вместо `inferKind()`

**Что делать:**
1. Добавить `public static function fromDirectoryName(string $directoryName): self` в `ModuleKind`:
```php
public static function fromDirectoryName(string $directoryName): self
{
    return match ($directoryName) {
        'Integrations' => self::Integration,
        'Subsystems' => self::Subsystem,
        default => self::Module,
    };
}
```
2. В `ScaffoldModuleUseCase` заменить приватный метод `inferKind()` на вызов `ModuleKind::fromDirectoryName(basename($targetRoot))`
3. Удалить приватный метод `inferKind()` из `ScaffoldModuleUseCase`

**Приёмка ✅:**
- `ModuleKind::fromDirectoryName('Integrations')` → `ModuleKind::Integration`
- `ModuleKind::fromDirectoryName('Subsystems')` → `ModuleKind::Subsystem`
- `ModuleKind::fromDirectoryName('Modules')` → `ModuleKind::Module`
- `ModuleKind::fromDirectoryName('Unknown')` → `ModuleKind::Module` (default)
- `ScaffoldModuleUseCase` не содержит `inferKind()`
- Feature-тест `scaffoldsModuleWithDefaultKindInference` проходит
- `composer test` и `composer phpstan` проходят
- Unit-тест для `fromDirectoryName` добавлен в `ModuleKindTest`

**Приёмка ❌:**
- `inferKind()` остался в `ScaffoldModuleUseCase`
- `fromDirectoryName` не покрыт тестом
- Метод бросает исключение вместо default (`Module`) для неизвестных директорий

---

### Task 6: Удалить тест-дубликат `to_manifest_array_returns_immutable_manifest`

**Файл:**
- `tests/Unit/Manifest/VO/ModuleTest.php`

**Что делать:**
Тест `to_manifest_array_returns_immutable_manifest` (строка 120) — дубликат `to_descriptor_array_returns_immutable_manifest_only` (строка 98). Оба вызывают `toDescriptorArray()`, но первый проверяет `schema_version`, а второй — нет. Метод `toManifestArray()` не существует.

Удалить `to_manifest_array_returns_immutable_manifest` целиком (строки 119-137).

**Приёмка ✅:**
- Тест `to_manifest_array_returns_immutable_manifest` удалён
- Тест `to_descriptor_array_returns_immutable_manifest_only` остался и проверяет `schema_version`
- Общее количество тестов уменьшилось на 1
- `composer test` проходит

**Приёмка ❌:**
- Удалён неправильный тест (`to_descriptor_array_*` вместо `to_manifest_array_*`)
- Потеряны проверки, которые были только в удалённом тесте (проверить: все assertions `to_manifest_array_*` есть в `to_descriptor_array_*`)

---

### Task 7: Добавить тест `schema_version: 0` в ManifestValidatorTest

**Файл:**
- `tests/Unit/Manifest/ManifestValidatorTest.php`

**Что делать:**
Добавить тест, проверяющий что `schema_version: 0` отклоняется как unsupported. Технически `0` — валидный integer, но не равен `CURRENT_SCHEMA_VERSION` (1), поэтому должен вызвать `InvalidManifestException` с сообщением о неподдерживаемой версии.

```php
#[Test]
public function it_rejects_zero_schema_version(): void
{
    $this->expectException(InvalidManifestException::class);
    $this->expectExceptionMessage('schema_version 0 is not supported; expected 1');

    $this->validator->validate([
        'schema_version' => 0,
        'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
        'settings' => ['schema' => []],
    ], '/tmp/module.json');
}
```

**Приёмка ✅:**
- Тест существует и проходит
- Сообщение подтверждает: `0` рассматривается как unsupported version, не как ошибка типа
- `composer test` проходит

**Приёмка ❌:**
- Тест отсутствует
- Сообщение ожидает `"must be an integer"` вместо `"is not supported"` (0 — валидный int)

## Files to Modify

| # | Файл | Task | Тип |
|---|------|------|-----|
| 1 | `src/Manifest/Enums/ModuleKind.php` | 1, 5 | MODIFY |
| 2 | `src/Manifest/VO/ManifestMeta.php` | 1 | MODIFY |
| 3 | `src/Console/Commands/Modules/MakeModuleCommand.php` | 1 | MODIFY |
| 4 | `src/Console/Commands/Modules/ModulesListCommand.php` | 1 | MODIFY |
| 5 | `src/Manifest/Parsing/ManifestFieldReader.php` | 2 | MODIFY |
| 6 | `src/Registry/VO/ModuleRegistryCachePayload.php` | 3 | MODIFY |
| 7 | `src/Application/UseCases/ListModulesUseCase.php` | 4 | MODIFY |
| 8 | `src/Application/UseCases/ScaffoldModuleUseCase.php` | 5 | MODIFY |
| 9 | `tests/Unit/Manifest/VO/ModuleTest.php` | 6 | MODIFY |
| 10 | `tests/Unit/Manifest/ManifestValidatorTest.php` | 2, 7 | MODIFY |
| 11 | `tests/Unit/Manifest/Enums/ModuleKindTest.php` | 1, 5 | MODIFY |
| 12 | `tests/Unit/Manifest/Parsing/ManifestFieldReaderTest.php` | 2 | MODIFY |
| 13 | `tests/Unit/Registry/ModuleRegistryCacheTest.php` | 3 | MODIFY |

## Risks & Considerations

- **Task 2** меняет текст ошибок — нужно обновить ВСЕ тесты, которые проверяют `expectExceptionMessage` для `requiredInt`/`requiredBool` missing-key сценариев
- **Task 5** перемещает логику из UseCase в enum — проверить что feature-тесты scaffold всё ещё проходят
- **Task 6** удаляет тест — убедиться что все assertions из удалённого теста покрыты оставшимся тестом
- Ни одна задача не меняет публичный API или JSON-контракт — все изменения internal

## Test Coverage

- Task 1: существующие тесты покрывают (поведение не меняется), добавить тест на `allowedValuesString()`
- Task 2: обновить `ManifestFieldReaderTest` и `ManifestValidatorTest` — новые сообщения
- Task 3: обновить `ModuleRegistryCacheTest` — новый формат сообщения
- Task 5: добавить тесты `fromDirectoryName` в `ModuleKindTest`
- Task 7: новый тест (сам является coverage)
