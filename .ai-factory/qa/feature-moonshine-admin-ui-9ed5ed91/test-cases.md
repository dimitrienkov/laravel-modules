## Тест-кейсы: MoonShine Admin UI для управления модулями

> Предусловие для большинства кейсов: хост-приложение с установленным MoonShine v4, `modules.moonshine.enabled=true`, выполнен вход в админку. Подготовлен модуль `blog` со schema: `enable_comments` (bool, default `true`), `max_posts_per_page` (int, min `1`, max `100`, default `20`), `moderation_mode` (enum: `auto`/`manual`/`off`, default `auto`).

---

### Группа A. Запись значений фич (форма + `FeatureValueWriter`)

---

### TC-001: Сохранение валидного override записывает только явные значения

**Приоритет:** High
**Тип:** Positive

**Предусловие:** `state.json` модуля `blog` не содержит секции `settings.values` (все значения = defaults).

**Шаги:**

1. Открыть форму фич модуля `blog` (action «Settings»).
2. Изменить `max_posts_per_page` с `20` на `50`, остальные поля не трогать.
3. Сохранить форму.
4. Открыть `storage/app/private/modules/blog/state.json`.

**Ожидаемый результат:**

В `settings.values` записано только `{"max_posts_per_page": 50}`. Значения `enable_comments` и `moderation_mode` НЕ записаны (остаются defaults в schema). Поле `enabled` не изменилось.

**Тестовые данные:**

```
max_posts_per_page = 50
```

---

### TC-002: Значение, равное default, не записывается как explicit

**Приоритет:** High
**Тип:** Positive

**Шаги:**

1. Открыть форму фич `blog`.
2. Установить `max_posts_per_page = 20` (равно default), `enable_comments = true` (равно default).
3. Сохранить.
4. Проверить `state.json`.

**Ожидаемый результат:**

Эти ключи отсутствуют в `settings.values` (strip defaults — schema является источником истины).

---

### TC-003: Boolean `false` при default `true` сохраняется как явный override

**Приоритет:** High
**Тип:** Positive

**Шаги:**

1. Открыть форму фич `blog` (`enable_comments` default = `true`).
2. Выключить Switcher `enable_comments` (значение `false`).
3. Сохранить.
4. Проверить `state.json`.

**Ожидаемый результат:**

В `settings.values` записано `{"enable_comments": false}` — `false` против default `true` является настоящим override и НЕ отбрасывается (в отличие от очистки не-boolean полей).

---

### TC-004: Частичная отправка формы сохраняет «соседние» override (sibling preservation)

**Приоритет:** High
**Тип:** Positive (регрессионный, commit a8f80d3)

**Предусловие:** В `state.json` модуля `blog` уже есть override: `{"moderation_mode": "manual", "max_posts_per_page": 50}`.

**Шаги:**

1. Открыть форму фич `blog`.
2. Изменить только `max_posts_per_page` с `50` на `30`. Поле `moderation_mode` не трогать.
3. Сохранить.
4. Проверить `state.json`.

**Ожидаемый результат:**

`settings.values` содержит `{"moderation_mode": "manual", "max_posts_per_page": 30}`. Override `moderation_mode` НЕ затёрт и НЕ сброшен к default. (До фикса частичная отправка молча теряла несабмиченные override.)

**Тестовые данные:**

```
Было:  moderation_mode=manual, max_posts_per_page=50
Шлём:  max_posts_per_page=30
Стало: moderation_mode=manual, max_posts_per_page=30
```

---

### TC-005: Очистка поля int возвращает значение к default без HTTP 500

**Приоритет:** High
**Тип:** Positive (регрессионный, commit e0bc770)

**Предусловие:** В `state.json` есть override `{"max_posts_per_page": 50}`.

**Шаги:**

1. Открыть форму фич `blog`.
2. Очистить поле `max_posts_per_page` (пустое значение).
3. Сохранить.

**Ожидаемый результат:**

Форма сохраняется без HTTP 500. Override `max_posts_per_page` удалён из `settings.values`, эффективное значение возвращается к schema default `20`. На detail-странице отображается `20`.

---

### TC-006: Очистка поля enum возвращает к default

**Приоритет:** High
**Тип:** Positive

**Предусловие:** В `state.json` есть override `{"moderation_mode": "off"}`.

**Шаги:**

1. Открыть форму фич `blog`.
2. Очистить поле `moderation_mode` (пустое/null).
3. Сохранить.
4. Проверить `state.json` и detail-страницу.

**Ожидаемый результат:**

Без HTTP 500. Override удалён, эффективное значение = default `auto`.

---

### TC-007: Невалидный bool-токен отклоняется (fail-closed)

**Приоритет:** High
**Тип:** Negative

**Шаги:**

1. Отправить в `save` значение фичи `enable_comments = "maybe"` (через прямой submit / подмену значения поля).
2. Наблюдать результат.

**Ожидаемый результат:**

Значение НЕ коэрсится молча в `false`. Срабатывает валидация формы (`nullable, boolean`) либо строгий `FeatureDefinition::normalize()`, возвращая каноническую ошибку. В `state.json` мусор не записан. (Используется `FILTER_NULL_ON_FAILURE`.)

**Тестовые данные:**

```
enable_comments = "maybe"
```

---

### TC-008: Дробный/экспоненциальный int отклоняется

**Приоритет:** High
**Тип:** Negative

**Шаги:**

1. Отправить `max_posts_per_page = "3.5"`.
2. Повторить с `max_posts_per_page = "1e2"`.

**Ожидаемый результат:**

Оба значения отклонены (не усечены до `3` / `100`). `FILTER_VALIDATE_INT` не принимает дробь/экспоненту → значение проброшено в `normalize()`, который отклоняет с ошибкой. В `state.json` не записано.

**Тестовые данные:**

```
"3.5"  → отклонено
"1e2"  → отклонено
```

---

### TC-009: Int вне диапазона min/max отклоняется

**Приоритет:** High
**Тип:** Negative

**Шаги:**

1. Открыть форму фич `blog`.
2. Ввести `max_posts_per_page = 0` (ниже min `1`), сохранить.
3. Ввести `max_posts_per_page = 101` (выше max `100`), сохранить.

**Ожидаемый результат:**

Оба значения отклоняются валидацией формы (`integer, min:1, max:100`), форма перерисовывается с сообщением об ошибке, значение не записано.

---

### TC-010: Неизвестное значение enum отклоняется

**Приоритет:** High
**Тип:** Negative

**Шаги:**

1. Отправить `moderation_mode = "unknown"` (нет в `auto`/`manual`/`off`).

**Ожидаемый результат:**

Отклонено правилом `Rule::in(['auto','manual','off'])`. Не записано в `state.json`.

---

### TC-011: Enum-опция со значением, содержащим запятую, обрабатывается как одно значение

**Приоритет:** Medium
**Тип:** Positive (edge case)

**Предусловие:** Модуль со schema enum, где одна из опций = `"a,b"`.

**Шаги:**

1. Открыть форму фич модуля.
2. Выбрать опцию `"a,b"`, сохранить.

**Ожидаемый результат:**

Значение `"a,b"` валидно и сохранено целиком. Валидация использует `Rule::in([...])` (массив), а не строку через запятую, поэтому запятая внутри опции не разбивает её на две.

---

### TC-012: `save` никогда не изменяет `enabled`

**Приоритет:** High
**Тип:** Negative (security / mass-assignment)

**Предусловие:** Модуль `blog` в состоянии `enabled=true`.

**Шаги:**

1. При отправке формы фич добавить в payload поле `enabled=false` (попытка mass-assignment).
2. Сохранить.
3. Проверить `state.json`.

**Ожидаемый результат:**

`enabled` остаётся `true`. `ModulesResource::save()` пишет только feature values через `writeValues()`; поле `enabled` через форму установить нельзя (фильтр по префиксу `featureValues.*`).

---

### Группа B. Список модулей и действия (`ModuleIndexPage`)

---

### TC-013: Async включение выключенного модуля

**Приоритет:** High
**Тип:** Positive

**Предусловие:** Модуль `blog` в состоянии `enabled=false`, без блокирующих зависимостей.

**Шаги:**

1. Открыть раздел «Модули».
2. В строке `blog` переключить Switcher в положение «включено».
3. Дождаться async-обновления (без перезагрузки страницы).
4. Проверить `state.json`.

**Ожидаемый результат:**

Вызван `EnableModuleUseCase`, в `state.json` `enabled=true`, `updated_at` обновлён. Строка обновляется без полной перезагрузки (событие `JsEvent::TABLE_ROW_UPDATED`).

---

### TC-014: Async выключение включённого модуля

**Приоритет:** High
**Тип:** Positive

**Предусловие:** `blog` `enabled=true`, без enabled-зависимых.

**Шаги:**

1. Переключить Switcher `blog` в «выключено».
2. Проверить `state.json`.

**Ожидаемый результат:**

Вызван `DisableModuleUseCase`, `enabled=false`. Запись только в `state.json`.

---

### TC-015: Превентивная блокировка выключения при наличии enabled-зависимых

**Приоритет:** Medium
**Тип:** Negative

**Предусловие:** Модуль `users` (enabled) зависит от модуля `blog`. Оба enabled.

**Шаги:**

1. Открыть «Модули».
2. Найти строку `blog`, навести на Switcher disable.

**Ожидаемый результат:**

Переключатель disable заблокирован, tooltip сообщает, что модуль требуется модулем `users` (`admin.guard.disable_blocked` со списком зависимых). `disableBlockers('blog')` возвращает `['Users']`.

---

### TC-016: disabled-зависимый НЕ блокирует выключение, но блокирует удаление

**Приоритет:** Medium
**Тип:** Negative (edge case)

**Предусловие:** Модуль `archive` (disabled) зависит от `blog`. `blog` enabled, других зависимых нет.

**Шаги:**

1. Проверить Switcher disable у `blog`.
2. Проверить кнопку Remove у `blog`.

**Ожидаемый результат:**

Switcher disable у `blog` АКТИВЕН (disabled-зависимый не учитывается в `disableBlockers`). Кнопка Remove у `blog` ЗАБЛОКИРОВАНА с tooltip про `archive` (`removeBlockers` учитывает любых зависимых, включая disabled).

---

### TC-017: Toggle уже включённого/выключенного модуля → типизированное исключение

**Приоритет:** Medium
**Тип:** Negative

**Шаги:**

1. Спровоцировать повторный enable уже включённого модуля (либо состояние рассинхронизировано).

**Ожидаемый результат:**

UseCase выбрасывает типизированное исключение, MoonShine показывает error-toast, состояние в `state.json` не меняется.

---

### TC-018: Удаление модуля использует только стратегию Backup

**Приоритет:** Medium
**Тип:** Positive

**Предусловие:** Модуль `blog` без зависимых.

**Шаги:**

1. Нажать Remove в строке `blog`, подтвердить.
2. Проверить файловую систему и список.

**Ожидаемый результат:**

Вызван `RemoveModuleUseCase` со стратегией `RemoveStrategy::Backup` (создаётся резервная копия, безвозвратного удаления нет). Модуль исчезает из списка, список обновляется без полной перезагрузки.

---

### TC-019: Группировка и сортировка списка

**Приоритет:** Medium
**Тип:** Positive (edge case)

**Предусловие:** ≥2 enabled-модуля + 1 disabled, разные `ModuleKind` (`subsystem`, `integration`, `module`) и `meta.group` (включая модуль без группы); два модуля с одинаковым displayName и разным canonical name.

**Шаги:**

1. Открыть «Модули».
2. Проверить порядок табов, групп и строк.

**Ожидаемый результат:**

- Табы в порядке: Subsystems → Integrations → Modules (`KIND_ORDER`).
- Внутри таба группы: «Без группы» (ungrouped) первой, затем остальные по алфавиту (ksort).
- Внутри группы строки отсортированы по displayName, при равенстве — по canonical name (детерминированный tie-break).
- В каждой строке присутствуют колонки Name / Version / Enabled.

---

### Группа C. Detail-страница (`ModuleDetailPage`)

---

### TC-020: Detail-страница отображает все поля и provenance

**Приоритет:** Low
**Тип:** Positive

**Предусловие:** Модуль `blog`, установленный из zip с provenance (`kind=zip`, `installed_version`, `checksum`), с override `max_posts_per_page=50`.

**Шаги:**

1. Открыть detail-страницу `blog` (action «Details»).

**Ожидаемый результат:**

Read-only страница показывает: name, namespace, version, kind, group, enabled, path, load_order, declared dependencies, computed dependents, эффективные значения фич (override `50` + defaults для остальных), provenance (kind/installed_version/checksum). Логи не запрашиваются. Значения свежие из `state.json`.

---

### TC-021: Fallback «None»/«Без группы» на detail и в списке

**Приоритет:** Low
**Тип:** Positive (edge case)

**Предусловие:** Модуль без зависимостей, без зависимых и без `meta.group`.

**Шаги:**

1. Открыть detail-страницу модуля.
2. Открыть список и найти его группу.

**Ожидаемый результат:**

Dependencies/Dependents отображаются как «None» (`admin.values.none`). В списке модуль попадает в группу «Без группы» (`admin.ungrouped`).

---

### Группа D. Регистрация, gating, регрессия

---

### TC-022: Admin-UI не регистрируется при `enabled=false`

**Приоритет:** High
**Тип:** Negative (gating)

**Шаги:**

1. Установить `modules.moonshine.enabled=false`.
2. Перезапросить страницы админки.

**Ожидаемый результат:**

`ModulesResource` и страницы Index/Form/Detail НЕ зарегистрированы, раздел «Модули» недоступен. При этом per-module autoload-bridge (`MoonShineModuleAutoloader`) продолжает работать. Boot не падает.

---

### TC-023: Boot хост-приложения без MoonShine не падает

**Приоритет:** High
**Тип:** Negative (gating)

**Предусловие:** Окружение без установленного MoonShine (`CoreContract` отсутствует) либо без полного CRUD/UI-стека.

**Шаги:**

1. Загрузить приложение без MoonShine.
2. Выполнить обычный request и Artisan-команду.

**Ожидаемый результат:**

Провайдер загружается без фатальных ошибок автозагрузки. Admin-ресурс/страницы не регистрируются. Базовый loader-pipeline работает. `MoonShineLoader` не присутствует в tagged-pipeline лоадеров.

---

### TC-024: Видимость пункта меню через `modules.moonshine.menu`

**Приоритет:** Low
**Тип:** Positive

**Шаги:**

1. Установить `modules.moonshine.menu=false`, перезагрузить админку.
2. Установить `modules.moonshine.menu=true`, перезагрузить.

**Ожидаемый результат:**

При `false` пункт меню «Модули» скрыт (`#[CanSee]` переоценивается на каждый request), при `true` — виден. Пересборки/кеш-сброса не требуется. Сам ресурс при этом остаётся зарегистрированным (если `enabled=true`).

---

### TC-025: Изменения значений фич применяются без `modules:optimize-clear`

**Приоритет:** Low
**Тип:** Positive (регрессия / Octane safety)

**Предусловие:** Выполнен `modules:optimize` (registry закеширован).

**Шаги:**

1. Через форму фич изменить `max_posts_per_page` на `40`.
2. Без выполнения `modules:optimize-clear` выполнить новый request, читающий значение через `FeatureRepository`.

**Ожидаемый результат:**

Новое значение `40` доступно сразу со следующего request — значения фич читаются из `state.json`, а не из cache (`modules:optimize` не кеширует `settings.values`).

---

### TC-026: Маппинг типов фич в поля формы

**Приоритет:** Low
**Тип:** Positive

**Предусловие:** Модуль со всеми типами фич (bool, int с min/max, enum, string).

**Шаги:**

1. Открыть форму фич модуля.

**Ожидаемый результат:**

bool → Switcher; int → Number с ограничениями min/max; enum → Select со списком опций; string → Text. Подпись поля берётся из перевода; при отсутствии перевода — humanized key (`max_retries` → «Max retries»). Поля сгруппированы по `settings.schema.*.group`, ungrouped-группа первой.

---

### TC-027: Локализация подписей видов модулей и fallback

**Приоритет:** Low
**Тип:** Positive

**Шаги:**

1. Переключить локаль приложения на `ru`, открыть «Модули».
2. Переключить на `en`.
3. Сымитировать отсутствие перевода для одного из видов.

**Ожидаемый результат:**

Подписи табов локализованы (`ru`: «Подсистемы»/«Интеграции»/«Модули»; `en`: «Subsystems»/«Integrations»/«Modules»). При отсутствии перевода возвращается enum value (например, `subsystem`), без рендера массива и без исключения (`ModuleKindLabelResolver`).

---

## Тестовые данные (на основе техник тест-дизайна)

### Positive

* `max_posts_per_page`: `1`, `20` (default), `50`, `100`
* `enable_comments`: `true` (default), `false` (явный override)
* `moderation_mode`: `auto` (default), `manual`, `off`
* enum-опция со значением `"a,b"` (запятая внутри значения)
* Частичная отправка: было `{moderation_mode: manual, max_posts_per_page: 50}`, шлём `max_posts_per_page=30`
* Набор модулей: 2 enabled + 1 disabled, виды `subsystem`/`integration`/`module`, группы (включая без группы), одинаковые displayName с разным canonical name
* Зависимости: `users`(enabled)→`blog`, `archive`(disabled)→`blog`

### Negative

* `enable_comments = "maybe"` (неизвестный bool-токен)
* `max_posts_per_page = "3.5"` / `"1e2"` (дробь/экспонента)
* `max_posts_per_page = 0` / `101` (вне диапазона min/max)
* `moderation_mode = "unknown"` (нет в опциях)
* payload с `enabled=false` в форме фич (попытка mass-assignment)
* Повторный toggle уже включённого/выключенного модуля
* Disable модуля с enabled-зависимыми; Remove модуля с любыми зависимыми
* `modules.moonshine.enabled=false`; окружение без MoonShine
