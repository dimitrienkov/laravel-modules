## Тест-план: MoonShine Admin UI для управления модулями

**Дата:** 2026-06-07
**Ветка / Версия:** feature/moonshine-admin-ui
**Окружение:** local / staging (хост-приложение Laravel 12/13 с установленным MoonShine v4)

---

### 1. Цель тестирования

Проверить корректность опционального admin-UI на базе MoonShine v4 для управления модулями: список с группировкой, async enable/disable, удаление (Backup), редактирование фичетоглов и read-only detail-страница. Особое внимание — записи значений фич в `state.json` (coercion, fail-closed, сохранение sibling-override, очистка полей без HTTP 500) и условиям регистрации UI (gating по конфигу и стеку MoonShine). Подтвердить, что admin-UI не нарушает базовые инварианты пакета: запись только в `state.json`, отсутствие stale-значений, безопасный boot без MoonShine.

---

### 2. Область тестирования

**In Scope — тестируем:**

- Регистрация admin-UI и gating: `modules.moonshine.enabled`, наличие полного CRUD/UI-стека MoonShine, per-module autoload-bridge.
- Список (`ModuleIndexPage`): табы по `ModuleKind`, группы по `meta.group`, сортировка, колонки, превентивная блокировка disable/remove.
- Async-действия: enable/disable (через UseCase), remove (Backup-стратегия), обработка исключений → error-toast.
- Форма фич (`ModuleFormPage` + `ModulesResource::save` + `FeatureValueWriter`): построение полей, validation rules, запись значений, coercion, fail-closed, sibling-override, очистка полей.
- Detail-страница (`ModuleDetailPage`): набор полей, dependents, provenance, эффективные значения фич, fallback «None»/«Без группы».
- Локализация (en/ru) и fallback подписей.
- Видимость пункта меню (`modules.moonshine.menu`, `#[CanSee]`).
- Регрессия: запись только в `state.json`, отсутствие кеширования значений фич, безопасный boot без MoonShine, архитектурные инварианты.

**Out of Scope — не тестируем:**

- Внутренняя реализация самого MoonShine v4 (фреймворк считается доверенным).
- Per-module `MoonShine/` ресурсы внутри отдельного модуля (roadmap, не реализовано).
- Установка/обновление модулей через zip-upload в admin-UI (roadmap).
- Базовый loader-pipeline и lifecycle UseCase-классы сами по себе (покрыты отдельными тест-планами); проверяем только их вызов из admin-UI.

---

### 3. Типы тестирования

| Тип | Приоритет | Область |
|-----|-----------|---------|
| Функциональный | 🔴 High | Запись фич в `state.json`, enable/disable, remove, построение формы |
| Негативный | 🔴 High | Невалидный ввод фич (bad bool/int/enum), очистка полей, toggle уже включённого/выключенного |
| Регрессия | 🟡 Medium | Только `state.json`, отсутствие stale-значений, boot без MoonShine, lifecycle через те же UseCase |
| Edge cases | 🟡 Medium | Частичная отправка формы, enum-опции с запятыми, sibling-override, ties по displayName |
| Security / доступ | 🟡 Medium | save не меняет `enabled`; mass-assignment закрыт; gating регистрации |
| Локализация | 🟢 Low | en/ru подписи, fallback при отсутствии перевода |

---

### 4. Тестовые данные

| Категория | Данные | Назначение |
|-----------|--------|------------|
| Валидные | Модуль с schema: `enable_comments` (bool, default true), `max_posts_per_page` (int, min 1, max 100, default 20), `moderation_mode` (enum: auto/manual/off) | Happy path формы фич |
| Граничные | int = 1 и 100 (границы min/max); string длиной ровно min/max; enum-опция со значением `"a,b"` (запятая) | Edge cases |
| Невалидные | bool = `"maybe"`; int = `"3.5"` / `"1e2"`; int = 0 или 101 (вне диапазона); enum = `"unknown"` | Негативные сценарии |
| Спец-случаи | Частичная отправка: в state есть `driver='file'`, форма шлёт только `retries=3`; очистка поля int/enum/string (`""`/`null`); boolean `false` при default `true` | Sibling-override, очистка, fail-closed |
| Структура списка | 2+ enabled-модуля + 1 disabled, разные `ModuleKind` и `meta.group`, одинаковые displayName с разным canonical name | Группировка/сортировка, превентивная блокировка |
| Зависимости | Модуль B, от которого зависит enabled-модуль A и disabled-модуль C | disableBlockers / removeBlockers |

---

### 5. Предусловия

- [ ] Развёрнуто хост-приложение Laravel 12/13 с установленным полным стеком MoonShine v4 (`composer require moonshine/moonshine`).
- [ ] Создан admin-аккаунт MoonShine, доступна авторизация в админку.
- [ ] Подготовлен набор из ≥3 модулей с разными `kind`, `group`, зависимостями и `settings.schema` (включая bool/int/string/enum, в т.ч. enum-опцию с запятой).
- [ ] Минимум один модуль с уже записанными несколькими override-значениями в `state.json` (для проверки sibling-override).
- [ ] Настроены зависимости между модулями (A→B enabled, C→B disabled).
- [ ] Доступ к содержимому `storage/app/private/modules/{name}/state.json` для верификации записи.
- [ ] Конфиг `modules.moonshine.enabled` и `modules.moonshine.menu` переключаемы.

---

### 6. Критерии приёмки

- [ ] Все 🔴 High тест-кейсы пройдены.
- [ ] Запись фич: только явные override в `state.json`, defaults не записываются, sibling-значения сохраняются при частичной отправке.
- [ ] Невалидный ввод отклоняется (валидация формы / `normalize()`), значение не искажается молча.
- [ ] Очистка любого не-boolean поля не вызывает HTTP 500 и возвращает значение к schema default.
- [ ] save никогда не меняет `enabled`.
- [ ] При `enabled=false` или отсутствии стека MoonShine admin-ресурс/страницы не регистрируются, boot не падает.
- [ ] enable/disable/remove проходят через те же UseCase, что и Artisan-команды, и пишут только `state.json`.
- [ ] Изменения значений фич применяются без `modules:optimize-clear`.
- [ ] `composer test` (arch + unit + feature), `composer phpstan`, `composer format:dry` зелёные.

---

### 7. Риски плана

| Риск | Влияние | Митигация |
|------|---------|-----------|
| UI-тесты MoonShine трудно автоматизировать вручную из-за async/Alpine.js | Medium | Опираться на существующие feature/unit-тесты как supporting verification; ручную проверку вести через реальную форму и инспекцию `state.json` |
| Расхождение превентивной блокировки UI и реального guard в UseCase | Medium | Тестировать оба пути: заблокированную кнопку в UI и прямой вызов UseCase, ожидая одинакового результата |
| Окружение без полного стека MoonShine не позволит проверить UI | High | Подготовить два окружения: с MoonShine и без, либо переключать `enabled` и проверять факт регистрации |
| Сложность воспроизведения «частичной отправки» формы вручную | Medium | Использовать предзаполненный `state.json` с несколькими override и отправлять форму с изменением одного поля |

### 8. Чеклист

| Проверка | Приоритет |
|----------|-----------|
| save пишет только feature values в `state.json`, не трогает `enabled` | High |
| Частичная отправка сохраняет sibling-override | High |
| Fail-closed: «maybe» (bool), «3.5»/«1e2» (int), out-of-range, неизвестный enum — отклоняются | High |
| Очистка int/string/enum → откат к default без HTTP 500 | High |
| boolean `false` при default `true` сохраняется как явный override | High |
| Defaults не записываются как explicit values | High |
| Gating регистрации по `modules.moonshine.enabled` и стеку MoonShine | High |
| Boot хост-приложения без MoonShine не падает; per-module autoload только при `CoreContract` | High |
| enable/disable из UI вызывает Enable/DisableModuleUseCase, пишет только `state.json` | High |
| Toggle уже включённого/выключенного → типизированное исключение → error-toast | Medium |
| Remove использует только `RemoveStrategy::Backup` | Medium |
| disableBlockers учитывает только enabled-зависимые; removeBlockers — любые | Medium |
| Превентивная блокировка кнопок + tooltip со списком зависимых | Medium |
| Список: табы Subsystem→Integration→Module; группы ungrouped-первой+алфавит; строки по displayName, tie по name | Medium |
| Enum-опции с запятыми обрабатываются как одно значение (`Rule::in`) | Medium |
| Маппинг типов: bool→Switcher, int→Number(min/max), enum→Select, string→Text | Medium |
| Detail-страница: все поля, dependents, provenance, fallback «None»/«Без группы» | Low |
| Локализация en/ru + fallback (humanized key / enum value) без исключений | Low |
| Видимость меню через `modules.moonshine.menu` без пересборки | Low |
| Изменения фич применяются без `modules:optimize-clear` (нет stale) | Low |
| Feature-value writes из формы не эмитят diagnostics-события | Low |
| Архитектурные инварианты: MoonShine `final`, Data/Support `final readonly` | Low |
