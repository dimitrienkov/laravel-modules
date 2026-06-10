## Сводка изменений

**Commits:** 5
**Изменённых файлов:** 48 (из них ключевая логика — ~20 файлов в `src/MoonShine/`, `src/Providers/`)
**Уровень риска:** 🟡 Средний

---

### Что изменилось

В пакет добавлен **опциональный admin-UI для управления модулями на базе MoonShine v4**. Администратор хост-приложения получает раздел «Модули» в админке MoonShine, где может:

- просматривать список модулей, сгруппированный по виду (`ModuleKind`) и логической группе (`meta.group`);
- включать/выключать модули прямо из списка (async-переключатель), при этом срабатывают lifecycle UseCase-классы (`EnableModuleUseCase` / `DisableModuleUseCase`);
- удалять модуль (только стратегия `Backup` — резервное копирование, без безвозвратного удаления);
- редактировать значения фичетоглов модуля через динамически построенную форму;
- открывать read-only debug-страницу модуля (namespace, путь, версия, зависимости, зависимые модули, load order, provenance, значения фич).

UI **полностью опционален**: он регистрируется только при наличии полного CRUD/UI-стека MoonShine v4 и при включённом флаге `modules.moonshine.enabled`. Без MoonShine или при `enabled=false` админка не регистрируется и фатальных ошибок автозагрузки не возникает.

Дополнительно две правки уже внутри ветки исправили дефекты формы фич:
- `e0bc770` — устранён HTTP 500 при очистке поля фичефлага в форме;
- `a8f80d3` — сохранение «соседних» override-значений при частичной отправке формы и fail-closed поведение при некорректном вводе.

---

### Затронутые области

| Компонент | Тип изменения | Описание |
|-----------|---------------|----------|
| `src/MoonShine/Resources/ModulesResource.php` | Добавлено | CRUD-ресурс поверх registry/state (не Eloquent). `getItems`/`findItem`/`save`; `delete`/`massDelete` — безопасные no-op |
| `src/MoonShine/Pages/ModuleIndexPage.php` | Добавлено | Список: табы по `ModuleKind`, таблицы по `meta.group`, async enable/disable, remove (Backup) |
| `src/MoonShine/Pages/ModuleFormPage.php` | Добавлено | Форма фичетоглов: поля и validation rules из `settings.schema` |
| `src/MoonShine/Pages/ModuleDetailPage.php` | Добавлено | Read-only debug-страница модуля |
| `src/MoonShine/Data/ModuleAdminDto.php` | Добавлено | Плоский DTO для admin-представления модуля; `featureColumn`/`featureKeyFromColumn`/`effectiveValues` |
| `src/MoonShine/Support/FeatureFieldFactory.php` | Добавлено | Маппинг типов фич (bool/int/enum/string) в поля MoonShine + группировка |
| `src/MoonShine/Support/FeatureValueWriter.php` | Добавлено | Запись значений фич в `state.json`: coercion, fail-closed, сохранение sibling-override |
| `src/MoonShine/Support/ModuleDependentsResolver.php` | Добавлено | Расчёт зависимых модулей для блокировки disable/remove |
| `src/MoonShine/Support/ModuleIndexGrouping.php` | Добавлено | Детерминированная группировка/сортировка строк списка |
| `src/MoonShine/Support/ModuleKindLabelResolver.php` | Добавлено | Локализованные подписи видов модулей |
| `src/Providers/ModuleLoaderServiceProvider.php` | Изменено | Условная регистрация admin-ресурса/страниц (gating по стеку MoonShine + конфигу) |
| `config/modules.php` | Изменено | Новая секция `modules.moonshine` (`enabled`, `menu`) |
| `lang/en/admin.php`, `lang/ru/admin.php` | Добавлено | Переводы для admin-UI (заголовки, виды, колонки, guard-сообщения, provenance) |
| `tests/Feature/MoonShine/*`, `tests/Unit/MoonShine/*` | Добавлено | 16 тест-файлов покрытия admin-UI |
| `tests/Architecture/ArchitectureTest.php` | Изменено | Инварианты: MoonShine-классы `final`, Data/Support — `final readonly`; loaders/application не зависят от UI-интеграций |
| `docs/moonshine.md` | Добавлено | Документация по включению и использованию admin-UI |

---

### Доказательства (Evidence)

| Находка | Доказательство |
|---------|----------------|
| Gating admin-UI по конфигу и стеку | `ModuleLoaderServiceProvider::registerMoonShineAdminResource()` гейтится наличием `CoreContract`, классов `MOONSHINE_ADMIN_STACK` (`CrudResource`, `Switcher`, `CanSee`) и флагом `modules.moonshine.enabled`; тест `tests/Feature/MoonShine/ModuleAdminRegistrationTest.php` |
| save пишет только feature values, никогда не enabled | `ModulesResource::save()` → `ModuleStateRepository::writeValues()`; тест `tests/Unit/MoonShine/Resources/ModulesResourceTest.php` («save writes only feature values, never enables/disables») |
| Удаление только Backup | `ModuleIndexPage::removeModule()` вызывает `RemoveModuleUseCase` со стратегией `RemoveStrategy::Backup`; тест `ModuleIndexPageActionsTest.php` |
| Превентивная блокировка disable/remove | `ModuleDependentsResolver::disableBlockers()` (только enabled-зависимые) и `removeBlockers()` (любые зависимые); тест `ModuleDependentsResolverTest.php` |
| Сохранение sibling-override при частичной отправке | `FeatureValueWriter::write()` стартует из `state->readValues()->explicitValues()`; commit `a8f80d3`; тест `FeatureValueWriterTest.php` («preserves unrelated existing overrides on partial submit») |
| Fail-closed на плохой ввод | `FILTER_NULL_ON_FAILURE` для bool, `FILTER_VALIDATE_INT` для int; невалидное значение пробрасывается в строгий `FeatureDefinition::normalize()`; commit `a8f80d3`; тест «rejects unrecognized bool tokens» / «rejects fractional/exponent integer strings» |
| Очистка не-boolean поля → откат к default без 500 | `ModuleFormPage::rulesFor()` дополнен min/max для string + `FILTER_NULL_ON_FAILURE` для bool; commit `e0bc770`; `FeatureValueWriter` сбрасывает очищенное не-boolean поле к default |
| Свежесть значений (нет stale) | `ModulesResource::findItem()` читает `state.json` на каждый рендер; `modules:optimize` не кеширует `settings.values` |
| Enum-опции с запятыми | validation использует `Rule::in([options])`, а не строку через запятую; тест `ModuleFormPageTest.php` |
| Меню реагирует на конфиг без пересборки | `#[CanSee('menuVisible')]` + `modules.moonshine.menu`, переоценка на каждый request |

---

### Риски

🔴 **Критичные (обязательно проверить):**

- **Корректность записи фич в `state.json`.** Частичная отправка формы не должна затирать чужие override; невалидный ввод (out-of-range int, неизвестный enum, «maybe» для bool) должен отклоняться, а не молча искажаться. Это переписанная логика (commit `a8f80d3`) — высокий регрессионный риск.
- **Очистка поля фичи не должна вызывать HTTP 500** и должна возвращать значение к schema default (commit `e0bc770`). Покрыть все типы: int, string, enum (и подтвердить, что boolean ведёт себя иначе — `false` остаётся явным override).
- **Gating регистрации admin-UI.** При `modules.moonshine.enabled=false` или отсутствии полного стека MoonShine ресурс/страницы не регистрируются; per-module autoload-bridge работает независимо. Ошибочная регистрация = падение boot в хост-приложении без MoonShine.
- **save никогда не меняет `enabled`.** Форма фич не должна включать/выключать модуль (mass-assignment закрыт префиксом `featureValues.*`).

🟡 **Средние (желательно проверить):**

- **Превентивная блокировка disable/remove** при наличии зависимых модулей: `disableBlockers` учитывает только enabled-зависимые, `removeBlockers` — любые (включая disabled). Несоответствие UI и реального guard в UseCase приведёт к рассинхрону UX и фактического поведения.
- **Async enable/disable** через `EnableModuleUseCase`/`DisableModuleUseCase`: исключения (нарушение зависимостей, повторный toggle уже включённого/выключенного) должны давать error-toast и ничего не сохранять.
- **Группировка и сортировка списка**: табы в порядке `Subsystem → Integration → Module`; группы — `ungrouped` первой, затем по алфавиту (ksort); строки — по displayName, затем по canonical name. Сценарии с 2+ модулями обязательны.
- **Локализация** (en/ru): подписи видов, колонок, guard-сообщений, provenance; fallback при отсутствии перевода (humanized key / enum value), без рендера массива и без исключений.

🟢 **Низкие (можно проверить):**

- Detail-страница: отображение всех полей, provenance (kind/installed_version/checksum), «None»/«Без группы» fallback.
- Видимость пункта меню через `modules.moonshine.menu`.
- Маппинг типов фич в поля формы (Switcher/Number/Select/Text) и humanized-label fallback (`max_retries` → «Max retries»).
- Feature-value writes из формы намеренно НЕ эмитят diagnostics-события (в отличие от lifecycle-операций).

---

### Рекомендации по тестированию

**Первый приоритет:**

- [ ] Запись фич в `state.json`: coercion типов, strip defaults, сохранение sibling-override при частичной отправке.
- [ ] Fail-closed на невалидном вводе (bad bool token, дробный/экспоненциальный int, out-of-range, неизвестный enum) — отклонение, не искажение.
- [ ] Очистка не-boolean поля → откат к default без HTTP 500; boolean `false` остаётся явным override.
- [ ] Gating регистрации: `enabled=true/false`, наличие/отсутствие стека MoonShine.
- [ ] save не меняет `enabled`; enabled нельзя установить через форму фич.

**Регрессия:**

- [ ] Lifecycle enable/disable из admin-UI пишет только `state.json` и проходит через те же UseCase, что и Artisan-команды.
- [ ] Удаление модуля = только Backup-стратегия; список обновляется без перезагрузки.
- [ ] Boot хост-приложения без MoonShine не падает (per-module autoload-bridge работает только при `CoreContract`).
- [ ] `modules:optimize` не кеширует значения фич — изменения применяются со следующего request без `optimize-clear`.
- [ ] Архитектурные инварианты: MoonShine-классы `final`, Data/Support — `final readonly`; loaders/application не зависят от UI-интеграций.
