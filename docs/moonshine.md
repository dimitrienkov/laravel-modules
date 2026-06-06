[← Configuration](configuration.md) · [Back to README](../README.md) · [Feature Toggles →](feature-toggles.md)

# MoonShine Admin UI

Пакет поставляет **опциональный** admin-UI поверх [MoonShine 4](https://moonshine-laravel.com)
для управления модулями из админки: список модулей, включение/выключение, удаление
с бэкапом, редактирование feature-флагов и read-only debug-страница. Без MoonShine
ядро грузится как обычно — UI просто не регистрируется.

## Что умеет UI

- **Index** — внешние табы по `ModuleKind` (Subsystems / Integrations / Modules), а
  внутри каждого таба отдельная таблица на каждую `meta.group`. Колонки: имя
  (displayName), версия, переключатель `enabled`; строки отсортированы по алфавиту.
- **Async enable/disable** — `Switcher` в строке вызывает `EnableModuleUseCase` /
  `DisableModuleUseCase` без перезагрузки страницы. Это **не** дефолтный CRUD-save:
  он пишет только feature-значения и не трогает `enabled`.
- **Удаление (Backup-only)** — строковое действие зовёт `RemoveModuleUseCase` строго
  со стратегией `RemoveStrategy::Backup`. Permanent в UI не выводится.
- **Guard UX** — если у модуля есть зависимые, переключатель/удаление превентивно
  блокируются с tooltip-списком зависимых. Это только UX: бизнес-enforcement
  остаётся в `ModuleDependencyGuard` внутри UseCase'ов.
- **Form (feature flags)** — поля строятся динамически из `settings.schema` модуля и
  группируются по `settings.schema.*.group`: `bool → Switcher`, `int → Number`
  (с `min`/`max`), `enum → Select`, `string → Text`. Валидация — на странице
  (`rules()`); запись — через `ModuleStateRepository::writeValues()` объектом
  `FeatureValues`. Дефолты остаются в схеме: персистятся только явные override'ы.
- **Detail (debug)** — read-only превью: пути, namespace, версия, зависимости и
  вычисленные зависимые, порядок загрузки, provenance (`source` из `state.json`) и
  текущие значения фич. Страница **не читает логи**.

## Требуемые пакеты

Admin-UI требует полный стек MoonShine v4 в host-приложении:

```bash
composer require moonshine/moonshine
```

`moonshine/moonshine` — umbrella-пакет: он `replaces` под-пакеты
(`contracts`/`core`/`crud`/`ui`/`laravel`/`menu-manager`/…), которые нужны для
`CrudResource`, страниц Index/Form/Detail и `autoloadMenu()`. Сам MoonShine остаётся
**опциональной** зависимостью (`suggest`): пакет добавляет admin-ресурс только когда
полный CRUD/UI-стек доступен.

> Лёгкий per-module autoload bridge (`MoonShineModuleAutoloader`) работает уже при
> наличии одного `CoreContract` и **не зависит** от полного admin-UI — это разные
> ветки интеграции.

## Конфигурация

Секция `modules.moonshine` в `config/modules.php`:

```php
'moonshine' => [
    'enabled' => true, // регистрировать ModulesResource и его страницы
    'menu' => true,    // показывать ресурс в авто-меню MoonShine
],
```

- `enabled = false` отключает регистрацию admin-ресурса и страниц, **не затрагивая**
  per-module autoload bridge.
- `menu` читается через `#[CanSee('menuVisible')]` на ресурсе. MoonShine сбрасывает
  состояние меню на `RequestHandled`, поэтому флаг пере-вычисляется на каждый запрос —
  смена значения вступает в силу без пересборки.

Оба флага читаются через инжектированный `Repository`, а не через `config()`-хелпер.

## Меню

Ресурс опирается на host-овский `autoloadMenu()` в layout MoonShine — отдельную
регистрацию пункта меню добавлять не нужно. URI-ключ ресурса — `modules`
(через `->alias('modules')`).

## Свежие значения и кэш

`Detail`/`Form` всегда показывают актуальные данные: `findItem()` читает `state.json`
на каждый рендер, а `modules:optimize` **никогда** не кэширует `settings.values`.
Поэтому отдельная инвалидация кэша после правки фич не нужна.

## Roadmap

Вне текущего runtime (помечено как будущие фазы):

- установка/обновление модулей через zip-upload прямо в UI;
- i18n меток feature-схемы из `settings.schema.*.label`.

## See Also

- [Feature Toggles](feature-toggles.md) — feature schema, values и scoped repository.
- [Configuration](configuration.md) — `modules.moonshine.*` и прочие опции.
- [Architecture](architecture.md) — registry, loader pipeline, lifecycle UseCase'ы.
- [CLI](cli.md) — те же lifecycle-операции из консоли.
