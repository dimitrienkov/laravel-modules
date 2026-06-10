# Research

Updated: 2026-06-04 12:00
Status: active

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
**Topic:** Milestone «MoonShine admin-UI» (ROADMAP Фаза 2) — `ModulesResource` + страницы для управления модулями из admin-панели MoonShine v4.

**Goal:** Дать заказчику в MoonShine: список модулей (табы по `ModuleKind`, внутри — таблицы по `meta.group`), включение/выключение (Switcher), удаление (с бэкапом), управление feature flags (динамическая форма по `settings.schema`) и DetailPage с отладочной инфой. MoonShine остаётся ОПЦИОНАЛЬНОЙ зависимостью — без него ядро не ломается.

**Scope этого milestone (подтверждено пользователем):**
- IndexPage: табы `Subsystems` / `Integrations` / `Modules` (по `ModuleKind`), внутри каждого — отдельная `TableBuilder` на каждый `meta.group`, колонки Name(displayName) | Version | Switcher(enabled) | actions; сортировка алфавитная по displayName.
- Toggle enabled через `EnableModuleUseCase` / `DisableModuleUseCase`.
- Delete через `RemoveModuleUseCase` — ТОЛЬКО `RemoveStrategy::Backup` (без выбора Permanent в UI).
- FormPage feature flags: `bool→Switcher`, `int→Number(min/max)`, `enum→Select(options)`, `string→Text`; группировка полей по `settings.schema.*.group`; запись через `ModuleStateRepository::writeValues()` + `FeatureValues::with()`.
- DetailPage: ТОЛЬКО debug-инфа (пути, namespace, version, dependencies, dependents, load order, provenance `ModuleOrigin`/checksum, текущие feature values). БЕЗ чтения логов.

**Отложено в отдельный milestone (НЕ в этом):** Install/Update через zip-upload в UI.

**Constraints (жёсткие):**
- MoonShine ТОЛЬКО v4+ (установлены contracts/core/support 4.10–4.11). Полного `moonshine/laravel` + `moonshine/ui` + `moonshine/crud` в проекте НЕТ — их нужно добавить в `require-dev` (runtime опционален через `suggest`). **Важно:** `CrudResource`, страницы Index/Form/Detail и `AbstractLayout::autoloadMenu()` живут в `moonshine/crud` (тянется транзитивно из `moonshine/laravel`, но указать явно в зависимостях).
- Арх-тесты применяются ко ВСЕМУ `src/`, включая будущий `src/MoonShine/`: запрет `Illuminate\Support\Facades\*`, запрет глобальных хелперов (`app`, `config`, `resolve`, `value`, `event`, `dispatch`, `logger`, `report`, `info`), запрет `Illuminate\Http\Request`, `final readonly`/`strict_types`. → UI-классы пишем строго на конструкторном DI (MoonShine резолвит Resource/Page через контейнер; статические фабрики `Switcher::make()` разрешены, т.к. это `::make`, не глобальный хелпер).
- Язык UI-меток: **translatable через lang-файлы** (`Lang/{locale}/`), не хардкод. (Код/коммиты остаются на английском.)
- `module.json` immutable; mutable state только через `ModuleStateRepository`. Никаких сырых `array` на границе записи — только `FeatureValues`.

**Decisions:**
- **Регистрация ресурса:** пакет регистрирует `ModulesResource` + страницы САМ через `$core->resources()/pages()` в существующем хуке `bootMoonShineIntegration()` (`callAfterResolving(CoreContract)`). Это официальный v4 package-development паттерн, детерминированно, НЕ конфликтует с per-module `autoload()` (тот сканирует namespace'ы host-модулей, а ресурс пакета — в namespace пакета, штатный autoload его не видит → дублирования нет). *Подтверждено:* канонический v4-паттерн — method-injection `CoreContract` прямо в `boot()` сервис-провайдера (`$core->resources([...])->pages([...])`); существующий `callAfterResolving`-хук тоже валиден.
- **Меню:** под конфиг-флагом `modules.moonshine.menu` (default true). **Уточнено по исходникам:** надёжнее добавлять пункт через `menu()` Layout-класса / `autoloadMenu()` + атрибут `#[CanSee('method')]` (или `MenuItem::make(ModulesResource::class)->canSee(fn () => ...)`), чем из `boot()` стороннего провайдера — `MenuManager::flushState()` срабатывает на каждом `RequestHandled`, поэтому `menu()`/`canSee()` пере-вычисляются на запрос (флаг читается динамически). *(финальное подтверждение размещения — на этапе плана)*
- **Guard UX:** когда модуль нельзя выключить/удалить (есть зависимые) — контрол (Switcher/кнопка delete) ЗАБЛОКИРОВАН + tooltip со списком зависимых. Бизнес-логика уже в `ModuleDependencyGuard` (`assertCanDisable` = есть включённые зависимые; `assertCanRemove` = есть любые зависимые) — UI её НЕ дублирует, а превентивно показывает причину (вычисляя зависимых из `registry->all()` + `meta->dependencies->constraintFor()`).
- **Data source / база ресурса:** in-memory коллекция из `ModuleRegistry::all()` (топологически отсортирована), БЕЗ Eloquent. **Решено (подтверждено чтением исходников 4.x): наследуемся от `CrudResource`** — он data-agnostic (пять абстрактных методов типизированы через `DataWrapperContract`, дефолтный caster `MixedDataCaster` оборачивает любой VO/массив; Eloquent-привязка целиком в подклассе `ModelResource`, который мы НЕ берём). Реализуем `getItems()`/`findItem()` против реестра, `save()` через `ModuleStateRepository`, `delete()`/`massDelete()` — стабы; задаём `protected string $casterKeyName = 'name'` (нужно для не-null `getKey()` → row-actions + async-toggle). Так получаем routing/form-pipeline/валидацию/async-таблицу/кнопки «бесплатно». Standalone custom Pages НЕ выбираем (пришлось бы воссоздавать всю CRUD-обвязку руками). Подробности → секция «Углубление: построение IndexPage / FormPage / DetailPage».
- Switcher на index → async-toggle через трейт `UpdateOnPreview` (Switcher extends Checkbox). **Нюанс по исходникам:** дефолтный `updateOnPreview()` бьёт в endpoint `updateField` → `resource->save()`; нам нужен явный `url:`-closure или `onChangeMethod('toggleEnabled', page: $this)` → `Enable/DisableModuleUseCase`, плюс `withUpdateRow($listComponentName)` для перерисовки одной строки. На исключение UseCase показываем toast.

**Open questions (на этап плана):**
- ~~Точная база ресурса: custom `CrudResource` vs набор custom Pages~~ — **РЕШЕНО: `CrudResource`** (data-agnostic; см. Decisions + секцию «Углубление…»).
- ~~Как DetailPage читает свежие feature values~~ — **РЕШЕНО:** `DetailPage::getDetailComponent()` зовёт `findItem()` на каждый рендер; `modules:optimize` никогда не кэширует `settings.values` → отдельная инвалидация не нужна, достаточно чтобы `findItem()` ходил в `ModuleStateRepository` (а не в discovery-кэш).
- Подтвердить вариант меню (авто `autoloadMenu()` + `#[CanSee]` под флагом vs полностью ручная регистрация хостом).
- **Прототип-проверки (после добавления dev-deps):** (1) пагинация non-Eloquent — `MixedDataCaster::paginatorCast()` возвращает `null`, возможно нужен ручной `->paginator()` в `modifyListComponent()` (модулей немного — пагинация может вообще не понадобиться); (2) async-toggle на стабнутом `save()` — что `onChangeMethod`/явный `url:`-closure корректно резолвят per-row endpoint без Eloquent и Switcher в preview-таблице переключается в `defaultMode()`; (3) `casterKeyName='name'` даёт не-null `getKey()` для row-actions/роутинга; (4) module-DTO нужен метод `toArray()` (иначе `(array)`-каст теряет computed/private-поля формы).

**Success signals:** MoonShine-страницы работают при наличии полного MoonShine; без него ядро грузится без ошибок (suggest-зависимость); все арх/unit/feature гейты зелёные; toggle/delete/feature-flags идут строго через UseCase/`writeValues`; заблокированные контролы корректно показывают зависимых; метки локализуемы.

**Next step:** `/aif-plan full` по этому milestone.
<!-- aif:active-summary:end -->

## Ключевые точки интеграции (готовый код)

```
ModuleRegistry::all(): array<Module>            # topo-sorted, in-memory snapshot (singleton)
Module {
  name, displayName, namespace, path, schemaVersion
  meta:     ManifestMeta { kind:ModuleKind, group:?ModuleGroup, version:Version,
                           author?, description?, license?, dependencies:ModuleDependencies }
  state:    ModuleState  { enabled, installedAt, updatedAt }   # из state.json (свежее)
  features: FeatureSchema { def[]:FeatureDefinition{key,type:FeatureType,hasDefault,
                            default,min,max,options[],label?,description?,group?} }
}

UseCases (final readonly, DI, без фасадов):
  EnableModuleUseCase::execute(name): Module                 # граф-валидация (не требует вкл. deps)
  DisableModuleUseCase::execute(name): Module                # guard.assertCanDisable
  RemoveModuleUseCase::execute(name, RemoveStrategy=Backup)  # guard.assertCanRemove
  ListModulesUseCase::execute(?enabled, ?kind, ?group)
  (Install/Update — есть, но в этот milestone НЕ берём)

Состояние/значения:
  ModuleStateRepository::writeValues(Module, FeatureValues)  # единственный путь записи values
  ModuleStateRepository::read(name, Module): ModuleStateDocument { state, values, source? }
  FeatureValues::with(module, key, value, manifestPath): FeatureValues  # иммутабельно
  FeatureValues::get(module, key), ->explicitValues()
  FeatureSchema::all(): array<key,FeatureDefinition>, ->defaults()

Группы/классификация:
  ModuleKind: module | subsystem | integration
  ModuleGroupLabelResolver::displayLabel(?ModuleGroup): string   # "Label (code)" | code | ''
  config modules.groups: code => label

Guard (бизнес-логика запрета):
  ModuleDependencyGuard::assertCanDisable / assertCanRemove  -> DependentModulesExistException
  (зависимые = модули, у кого meta.dependencies->constraintFor(name) !== null)

Lifecycle invalidation:
  LifecycleRegistryInvalidator::flushAndReset()   # cache->forget() + registry->reset()
```

## Находки, влияющие на дизайн

1. **Полного MoonShine нет** — только `contracts`/`core`/`support` (v4.10/4.11). `core` содержит лишь абстрактные `Resource`/`Page`; `autoload()` реализован в `moonshine/laravel` (не установлен). UI-поля/билдеры — в `moonshine/ui`. → добавить `moonshine/laravel`+`moonshine/ui` в require-dev.
2. **Арх-тесты на весь `src/`** (`tests/Architecture/ArchitectureTest.php`): facade-ban (стр.157, без исключений для MoonShine), helper-ban (стр.422+), `Request`-ban (стр.159). MoonShine-namespace исключён только из правил «Loaders/Application не используют MoonShine», но НЕ из facade/helper-банов. → UI на чистом DI.
3. **Логи per-module недёшевы**: диагностическое логирование пишет в Laravel-канал (stack/daily), не в per-module хранилище. → DetailPage без логов (решено), только debug-инфа из registry/state.
4. **Octane/stale snapshot**: `ModuleRegistry` — singleton с in-memory снапшотом; lifecycle UseCases вызывают `flushAndReset()`, снапшот перечитывается. Switcher-состояние читать через registry после reset (свежий state.json).
5. **Регистрация ресурса пакета** не пересекается с per-module `autoload()` (разные namespace'ы) — см. Decisions.

## Углубление: построение IndexPage / FormPage / DetailPage в MoonShine v4

> Углубление к основному `RESEARCH.md`. Каждое утверждение привязано к процитированной сигнатуре/пути из исходников MoonShine v4 (branch `4.x` монорепо + demo-project `4.0`). Где факт не подтверждён чтением исходника — отмечено явно.

### Модель страниц в MoonShine v4 — Resource → Pages

В v4 нет «страницы внутри ресурса как массив полей». Есть **двухуровневая модель**: `Resource` владеет данными и списком классов-страниц, а каждая `Page` — это самостоятельный объект, отвечающий за свой набор компонентов.

Базовый `Resource` вообще не знает про Eloquent — его конструктор принимает только `CoreContract`:

```php
// src/Core/src/Resources/Resource.php
public function __construct(
    CoreContract $core,
) {
    $this->setCore($core);
    $this->booted();
}
```

Страницы объявляются абстрактным методом `pages()`, возвращающим **class-string'и**, которые резолвятся из DI-контейнера:

```php
// src/Core/src/Resources/Resource.php
abstract protected function pages(): array; // list<class-string<PageContract>>

public function getPages(): PagesContract
{
    $this->pages = Pages::make($this->pages())
        ->map(fn (string $page) => $this->getCore()->getContainer()->get($page))
        ->setResource($this);
    return $this->pages;
}
```

#### CrudResource (база) vs ModelResource (Eloquent) vs набор custom Page

`CrudResource` — абстрактный класс, **data-agnostic**. Вся работа с данными — абстрактные методы, типизированные через `DataWrapperContract`/`FieldsContract` и framework-neutral `Illuminate\Contracts\Pagination`, без `Model` и без Eloquent `Builder`:

```php
// src/Crud/src/Resources/CrudResource.php
abstract class CrudResource extends Resource implements
    CrudResourceContract,
    HasQueryTagsContract,
    HasHandlersContract,
    HasFiltersContract,
    HasCrudResponseModifiersContract
{
    abstract public function findItem(bool $orFail = false): ?DataWrapperContract;
    abstract public function getItems(): iterable|Collection|LazyCollection|CursorPaginator|Paginator;
    abstract public function massDelete(array $ids): void;
    abstract public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool;
    abstract public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract;
}
```

Дефолтный caster в `CrudResource` — **не Eloquent**:

```php
// src/Crud/src/Resources/CrudResource.php
public function getCaster(): DataCasterContract
{
    return new MixedDataCaster($this->casterKeyName);
}

public function getDataInstance(): mixed
{
    return [];
}
```

Eloquent-привязка целиком вынесена в подкласс `ModelResource` (Laravel-слой) — именно там появляются `use Illuminate\Database\Eloquent\Model`, свойство `protected string $model`, переопределённый `getCaster()` → `new ModelCaster($this->model)` и реализации `getItems()`/`findItem()` через query builder (в трейте `ResourceModelQuery`: `getItems()` → `$this->getQuery()->get()`, `findItem()` → `$this->getModel()->newQuery()->findOrFail(...)`).

**Вывод по верификации (refuted):** утверждение «CrudResource фундаментально привязан к Eloquent Model» — **опровергнуто**. Model-зависимость — свойство подкласса `ModelResource`, а не базового `CrudResource`. Для in-memory коллекции value-объектов наследуемся от `CrudResource` и реализуем пять абстрактных методов против реестра модулей — это и есть штатная точка расширения.

Дефолтный набор страниц `CrudResource` — три **контрактных** class-string'а, резолвящихся из DI (легко подменить, переопределив `pages()`):

```php
// src/Crud/src/Resources/CrudResource.php
protected function pages(): array
{
    return [
        IndexPageContract::class,
        FormPageContract::class,
        DetailPageContract::class,
    ];
}
```

#### Где живут страницы и их базовые классы

- `IndexPage` — `src/Crud/src/Pages/IndexPage.php`, `protected ?PageType $pageType = PageType::INDEX`, `protected string $component = DefaultListComponent::class`.
- `FormPage` — `src/Crud/src/Pages/FormPage.php`, `PageType::FORM`, `protected string $form = DefaultForm::class`.
- `DetailPage` — `src/Crud/src/Pages/DetailPage.php`, `PageType::DETAIL`, `protected string $component = DefaultDetailComponent::class`.

Цепочка наследования custom-страницы в Laravel: `YourPage → MoonShine\Laravel\Pages\Page → MoonShine\Crud\Pages\Page → MoonShine\Core\Pages\Page`. Все три CRUD-страницы наследуют `CrudPage` (`src/Crud/src/Pages/CrudPage.php`), который даёт `fields()`, `getFields()`, `prepareFields()`, `isAsync()`, `getEmptyModals()`. Единственный обязательный для любой custom Page метод — `protected function components(): iterable` (объявлен абстрактным в `src/Core/src/Pages/Page.php`).

#### IndexPage — как строится components()

`components()` минимальна — она лишь валидирует ресурс и делегирует слоям:

```php
// src/Crud/src/Pages/IndexPage.php
protected function components(): iterable
{
    $this->validateResource();
    return $this->getLayers();
}
```

`getLayers()` склеивает `topLayer() + mainLayer() + bottomLayer()`:

```php
// src/Crud/src/Pages/IndexPage.php
protected function topLayer(): array
{
    $components = [];
    if ($metrics = $this->getMetricsComponent()) {
        $components[] = $metrics;
    }
    return array_merge($components, $this->getTopButtons()); // create + filters/handlers buttons
}

protected function mainLayer(): array
{
    return [
        ...$this->getQueryTagsButtons(),
        ...$this->getItemsComponents(), // → [getListComponent()]
    ];
}
```

Сама `TableBuilder` собирается **не в странице**, а в `DefaultListComponent::__invoke()`, который резолвится из DI по `$this->component`. Точка-хук — `getItemsComponent(iterable $items, FieldsContract $fields): ComponentContract` → пропускает результат через `modifyListComponent()`.

**Поля → колонки.** `getListComponent()` берёт items из ресурса и поля из `getIndexFields()`, оборачивает в `Fragment`:

```php
// src/Crud/src/Concerns/Page/HasListComponent.php
public function getListComponent(bool $withoutFragment = false): ComponentContract
{
    $items = $this->isLazy() ? [] : $this->getResource()->getItems();
    $fields = $this->getResource()->getIndexFields();
    $component = $this->getItemsComponent($items, $fields);
    if ($withoutFragment) {
        return $component;
    }
    return Fragment::make([$component])->name('crud-list');
}
```

Построение таблицы (verbatim):

```php
// src/Crud/src/Pages/PageComponents/DefaultListComponent.php
public function __invoke(IndexPageContract $page, iterable $items, FieldsContract $fields): ComponentContract
{
    $resource = $page->getResource();
    return TableBuilder::make(items: $items)
        ->name($page->getListComponentName())
        ->queryParamPrefix($resource->getQueryParamPrefix())
        ->fields($fields)
        ->cast($resource->getCaster())
        ->buttons($page->getButtons())
        ->when($page->isAsync(), function (TableBuilderContract $table) use ($page): void {
            $table->async(url: fn (): string => $page->getRouter()->getEndpoints()->component(
                name: $table->getName(),
                additionally: $this->getCore()->getRequest()->getRequest()->getQueryParams(),
            ))->pushState();
        })
        ->when($page->isLazy(), function (TableBuilderContract $table) use ($resource): void {
            $table->lazy()->whenAsync(fn (TableBuilderContract $t): TableBuilderContract
                => $t->items($resource->getItems())->withNotFound());
        }, fn (TableBuilderContract $table): TableBuilder => $table->withNotFound())
        ->when(! \is_null($resource->getItemsResolver()), function (TableBuilderContract $table) use ($resource): void {
            $table->itemsResolver($resource->getItemsResolver());
        });
}
```

В index-режиме таблица не editable, поэтому `prepareFields()` форсит `->withoutWrapper()->previewMode()` на каждом поле — колонки рендерятся как read-only превью. Заголовки сортируемых колонок строятся в `resolveHeadRow()` как `Link::make($field->getSortQuery($this->getAsyncUrl()), $field->getLabel())` с `@click.prevent => 'asyncRequest'` при async.

**Пагинация.** `IterableComponent::paginator(PaginatorContract $paginator): static` и `itemsResolver(Closure $resolver)`. Важно: `MixedDataCaster::paginatorCast()` возвращает `null` — автоэкстракции пагинатора для plain-коллекции нет. Для пагинации non-Eloquent данных нужно вручную передать `PaginatorContract` через `->paginator()` (например, в `modifyListComponent()`). **Не подтверждено** чтением полного `resolvePaginator()` для non-Eloquent ветки — проверить на прототипе.

**Кнопки/действия.** Row-actions объявляются в `buttons()`:

```php
// src/Crud/src/Pages/IndexPage.php
protected function buttons(): ListOf
{
    return new ListOf(ActionButtonContract::class, [
        $this->modifyDetailButton($this->getResource()->getDetailButton()),
        $this->modifyEditButton($this->getResource()->getEditButton(isAsync: $this->isAsync())),
        $this->modifyDeleteButton($this->getResource()->getDeleteButton(
            redirectAfterDelete: $this->getResource()->getRedirectAfterDelete(),
            isAsync: $this->isAsync(),
        )),
        $this->modifyMassDeleteButton($this->getResource()->getMassDeleteButton(...)),
    ]);
}
```

Per-row кнопки рендерятся в ячейке `Flex::make([...])->justifyAlign('end')` в конце строки; bulk-кнопки — в footer-строке (`getBulkRow()`), скрываемой/показываемой через Alpine `actionsOpen`.

**Async Switcher в строке (подтверждено, verdict supported).** Switcher — тонкий подкласс Checkbox, наследует трейт `UpdateOnPreview`. Механизм:

```php
// src/UI/src/Traits/Fields/UpdateOnPreview.php
public function updateOnPreview(
    ?Closure $url = null,
    ?ResourceContract $resource = null,
    Closure|bool|null $condition = null,
    array $events = [],
): static {
    // ...
    return $this->setUpdateOnPreviewUrl(
        $url ?? static fn (?DataWrapperContract $data, mixed $value, FieldContract $field): ?string
            => $data?->getKey() ? $router->getEndpoints()->updateField(
                resource: $field->getNowOnResource(),
                extra: ['resourceItem' => $data->getKey(), ...],
            ) : null,
        $events
    );
}

public function withUpdateRow(string $component): static
{
    // ...
    return $this->setUpdateOnPreviewUrl(
        $this->updateOnPreviewUrl,
        events: [AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $component)],
    );
}
```

`setUpdateOnPreviewUrl()` вызывает `onChangeUrl($url, method: HttpMethod::PUT, events)`, что через `onChangeAttributes()` → `AlpineJs::asyncUrlDataAttributes()` эмитит `data-async-method=PUT` + `data-async-events` — то есть XHR, **без полной перезагрузки страницы**. Ключевой нюанс для index-таблицы:

```php
// src/UI/src/Traits/Fields/UpdateOnPreview.php
protected function resolveRender(): Renderable|Closure|string
{
    if ($this->isUpdateOnPreview() && $this->isPreviewMode()) {
        $this->defaultMode(); // в preview-таблице Switcher автоматически становится интерактивным input
    }
    // ... return parent::resolveRender();
}
```

`JsEvent::TABLE_ROW_UPDATED = 'table_row_updated'` — подтверждённый enum-кейс: после PUT перерисовывается одна строка. **Важно:** дефолтный URL ведёт на `updateField` (PUT-роут `column/{resourceUri}/{resourceItem}`), который вызывает `resource->save()`. Для non-Eloquent ресурса со стабнутым `save()` это не персистит — нужен **явный** `url:` closure или `onChangeMethod()`. Готового end-to-end примера async-Switcher в index-таблице в demo нет (реальный `ArticleIndexPage` использует plain `Switcher::make('Active')->sortable()`); жизнеспособность опирается на API, не на пример.

#### FormPage — FormBuilder, поля, save/submit, валидация

`components()` — три шага:

```php
// src/Crud/src/Pages/FormPage.php
protected function components(): iterable
{
    $this->validateResource();
    if (! $this->isItemExists() && $this->getResource()->getItemID()) {
        $this->throw404();
    }
    return $this->getLayers();
}
```

`mainLayer()` возвращает ровно один компонент — `getFormComponent()`, обёрнутый в Fragment с `updateWith()` для async-перезагрузки фрагмента:

```php
// src/Crud/src/Pages/FormPage.php
public function getFormComponent(bool $withoutFragment = false): ComponentContract
{
    $resource = $this->getResource();
    $item = $resource->getCastedData();          // ?DataWrapperContract
    $fields = $this->getResource()->getFormFields();
    $action = $this->getFormAction();            // crud.update (edit) / crud.store (create)
    $isAsync = $this->isAsync();
    if (filter_var($this->getcore()->getRequest()->get('_async_form', false), FILTER_VALIDATE_BOOLEAN)) {
        $isAsync = true;
    }
    $component = $this->getForm($action, $item, $fields, $isAsync);
    if ($withoutFragment) {
        return $component;
    }
    return Fragment::make([$component])
        ->name('crud-form')
        ->updateWith(['resourceItem' => $resource->getItemID()]);
}
```

`getForm()` резолвит `$this->form` (default `DefaultForm::class`) из DI и пропускает через `modifyFormComponent()`. Сама `FormBuilder` (verbatim ключевые звенья):

```php
// src/Crud/src/Pages/PageComponents/DefaultForm.php
return FormBuilder::make($action)
    ->cast($resource->getCaster())
    ->fill($item)
    ->fields([
        ...$fields->when(! \is_null($item), static fn (Fields $fields): Fields
            => $fields->push(Hidden::make('_method')->setValue('PUT')))->toArray(),
    ])
    ->when(! $page->hasErrorsAbove(), fn ($form) => $form->errorsAbove($page->hasErrorsAbove()))
    ->when($isAsync, fn ($formBuilder) => $formBuilder->async(events: array_filter([
        $resource->getListEventName(...),                       // refresh index-таблицы после save
        ! $resource->isItemExists() && $resource->isCreateInModal()
            ? AlpineJs::event(JsEvent::FORM_RESET, $resource->getUriKey()) : null,
    ])))
    ->when($page->isPrecognitive() || ($this->getCore()->getCrudRequest()->isFragmentLoad('crud-form') && ! $isAsync),
        static fn ($form) => $form->precognitive())
    ->name($resource->getUriKey())
    ->class("form-resource-{$resource->getUriKey()}")
    ->submit($this->getCore()->getTranslator()->get('moonshine::ui.save'), ['class' => 'btn-primary btn-lg'])
    ->buttons($page->getFormButtons());
```

**Поток save/submit.** Персистенцию выполняет `$resource->save(DataWrapperContract $item)` внутри контроллера:

```php
// src/Laravel/src/Http/Controllers/CrudController.php
protected function updateOrCreate(MoonShineFormRequest $request): Response
{
    $resource = $request->getResource();
    $item = $resource->getItemOrInstance();
    $resource->setActivePage($resource->getFormPage());
    try {
        $item = $resource->save($resource->getCaster()->cast($item));
    } catch (Throwable $e) {
        return $resource->modifyErrorResponse(...);
    }
    $resource->setItem($item->getOriginal());
    if ($request->ajax() || $request->wantsJson()) {
        // refresh field HTML per selector → htmlData
        return $resource->modifySaveResponse($this->json(
            message: __('moonshine::ui.saved'), data: $data, redirect: ...,
            status: $resource->isRecentlyCreated() ? Response::HTTP_CREATED : Response::HTTP_OK,
        ));
    }
}
```

`save()` абстрактен в `CrudResource` — для in-memory модулей реализуем его сами против реестра, возвращая `DataWrapperContract`. (Eloquent-реализация в `ModelResource::save()` делает `$fields->fill()`, `field->apply()`, `$model->save()` + хуки `beforeCreating/afterSave` — нам не нужна.)

**Валидация / precognition.** Правила берутся из **страницы**, не из ресурса:

```php
// src/Crud/src/Concerns/Page/HasFormValidation.php
protected function rules(DataWrapperContract $item): array { return []; } // override в подклассе FormPage

public function getRules(): array
{
    return $this->rules($this->getResource()->getCaster()->cast(
        $this->getResource()->getItemOrInstance()
    ));
}
```

`getRules()` вызывается из `StoreFormRequest::rules()` / `UpdateFormRequest::rules()`, то есть валидация идёт штатным Laravel request-пайплайном **до** тела контроллера. Precognition — нативный: `CrudController` вешает middleware `HandlePrecognitiveRequests` на store/update, а `FormBuilder` помечается `precognitive()` когда `$page->isPrecognitive()` или non-async fragment-load. Замечание: `validateResource()` в `components()` — это guard рендера страницы, **не** валидация формы.

#### DetailPage — read-only билдер и preview

`components()` — валидация, 404 если item не найден, делегирование слоям. `mainLayer()` оборачивает всё в `Box`:

```php
// src/Crud/src/Pages/DetailPage.php
protected function mainLayer(): array
{
    return [
        Box::make([
            $this->getDetailComponent(),
            LineBreak::make(),
            ...$this->getTopButtons(),
        ]),
    ];
}

public function getDetailComponent(bool $withoutFragment = false): ComponentContract
{
    $resource = $this->getResource();
    $detailComponent = $this->getCore()->getContainer($this->component);
    $component = $this->modifyDetailComponent(
        $detailComponent($this, $resource->getCastedData(), $resource->getDetailFields())
    );
    if ($withoutFragment) {
        return $component;
    }
    return Fragment::make([$component])->name('crud-detail');
}
```

Read-only рендерит **тот же `TableBuilder`** в режиме `vertical + simple + preview` (это единственное место, управляющее layout детали):

```php
// src/Crud/src/Pages/PageComponents/DefaultDetailComponent.php
public function __invoke(DetailPageContract $page, ?DataWrapperContract $item, FieldsContract $fields): ComponentContract
{
    $resource = $page->getResource();
    return TableBuilder::make($fields)
        ->cast($resource->getCaster())
        ->items([$item])
        ->vertical(
            title: $resource->isDetailInModal() ? 3 : 2,
            value: $resource->isDetailInModal() ? 9 : 10,
        )
        ->simple()
        ->preview()
        ->class('table-divider');
}
```

В `vertical()`-режиме это **не `<table>` со строками**, а для каждого поля — `Grid::make([Column(label)->columnSpan(2), Column(field)->columnSpan(10)])->gap(2)`. `preview()` через `prepareFields()` форсит `->withoutWrapper()->previewMode()`:

```php
// src/UI/src/Components/Table/TableBuilder.php
protected function prepareFields(): FieldsContract
{
    $fields = $this->getFields();
    if (! $this->isEditable()) {
        $fields = $fields->onlyFields(withWrappers: true)->map(
            static fn (FieldContract $field): FieldContract => $field->withoutWrapper()->previewMode(),
        );
    }
    return $fields->values();
}
```

Рендер preview-значения поля:

```php
// src/UI/src/Fields/Field.php
public function preview(): Renderable|string
{
    if ($this->isRawMode()) {
        return (string) ($this->toRawValue() ?? '');
    }
    if ($this->isPreviewChanged()) {
        return (string) \call_user_func($this->previewCallback, $this->toValue(), $this);
    }
    $preview = $this->resolvePreview();
    $decorated = $this->previewDecoration($preview); // Badge/Link если настроено
    return $decorated;
}
```

Кастомизация конкретной ячейки — `changePreview(Closure $cb)` (closure получает `($value, $field)`). Подменить весь рендерер — переопределить `protected string $component` на класс с `DefaultDetailComponentContract::__invoke()`. `bottomLayer()` у DetailPage пуст — связанные/outside-поля надо толкать вручную через `pushToLayer()`.

### Non-Eloquent данные — как скормить in-memory коллекцию value-объектов

MoodShine v4 имеет first-class двухконтрактную абстракцию данных (в `src/Contracts/src/Core/TypeCasts/`):

```php
interface DataWrapperContract {
    public function getOriginal(): mixed;
    public function getKey(): int|string|null;
    public function toArray(): array;
}
interface DataCasterContract {
    public function cast(mixed $data): DataWrapperContract;
    public function paginatorCast(mixed $data): ?PaginatorContract;
}
```

Встроенные реализации **не зависят от Eloquent**:

```php
// src/Core/src/TypeCasts/MixedDataCaster.php
final readonly class MixedDataCaster implements DataCasterContract
{
    public function __construct(private ?string $keyName = null) {}
    public function cast(mixed $data): DataWrapperContract
    {
        $key = $this->keyName && $data ? data_get($data, $this->keyName) : null;
        return new MixedDataWrapper($data, $key);
    }
    public function paginatorCast(mixed $data): ?PaginatorContract { return null; }
}

// src/Core/src/TypeCasts/MixedDataWrapper.php
final readonly class MixedDataWrapper implements DataWrapperContract, ArrayAccess
{
    public function __construct(private mixed $data, private string|int|null $key = null) {}
    public function getOriginal(): mixed { return $this->data; }
    public function getKey(): int|string|null { return $this->key; }
    public function toArray(): array {
        if (\is_object($this->data) && method_exists($this->data, 'toArray')) {
            return $this->data->toArray();
        }
        return (array) $this->data;
    }
}
```

И `TableBuilder`, и `FormBuilder` через трейт `HasDataCast` авто-фоллбэчат на `MixedDataCaster`, если `cast()` явно не вызван:

```php
// src/UI/src/Traits/HasDataCast.php
public function castData(mixed $data): DataWrapperContract
{
    if ($data instanceof DataWrapperContract) {
        return $data;
    }
    if (! $this->hasCast()) {
        $this->cast(new MixedDataCaster($this->getCastKeyName()));
    }
    return $this->getCast()->cast($data);
}
```

**Резолв значения поля из произвольной строки** — `data_get()` с dot-notation по `getColumn()` (column дефолтится в `snake_case(label)`):

```php
// src/UI/src/Fields/FormElement.php
protected function prepareFill(array $raw = [], ?DataWrapperContract $casted = null): mixed
{
    $value = data_get(\is_null($casted) ? $raw : $casted->getOriginal(), $this->getColumn(), $default);
    if (\is_null($value) || $value === false || $value instanceof FieldEmptyValue) {
        $value = data_get($raw, $this->getColumn(), $default);
    }
    return $value;
}
```

Для value-объекта `data_get($casted->getOriginal(), 'name')` читает public-свойство; для массива — ключ. Demo-project подтверждает работу TableBuilder на чистых массивах без Eloquent: `TableBuilder::make()->fields([Text::make('IP'), ...])->simple()->items([['ip'=>..., 'email'=>...], ...])`.

**Конкретный рекомендуемый способ для модулей:**
1. Наследуемся от `CrudResource` (не `ModelResource`).
2. Каждый модуль — value-объект с публичными свойствами + методом `toArray()` (тогда `MixedDataWrapper::toArray()` отдаст ровно его форму, а не `(array)` каст).
3. Задать на ресурсе `protected string $casterKeyName = 'name'` (или иной уникальный идентификатор модуля) — чтобы `DataWrapperContract::getKey()` был не-null. Это **обязательно** для row-actions и async-toggle: дефолтный per-row URL строится через `$data->getKey()`.
4. `getItems()` возвращает `iterable`/`Collection` value-объектов из реестра модулей. Дефолтного caster (`MixedDataCaster`) достаточно — override не нужен.

### Маппинг типов фич → поля MoonShine

Общая `make()` для всех полей: `make(Closure|string|null $label = null, ?string $column = null, ?Closure $formatted = null)`. Второй аргумент `$column` — это dot-path в value-объект (по умолчанию `snake_case($label)`).

| FeatureType | Класс поля (путь) | Сигнатура `make()` | Нужные fluent-методы |
|---|---|---|---|
| `bool` | `Switcher` (`src/UI/src/Fields/Switcher.php`, `extends Checkbox`, `view = moonshine::fields.switch`) | `Switcher::make('Enabled', 'enabled')` | в index — `->updateOnPreview(url: fn(?DataWrapperContract $data, mixed $value, FieldContract $field): ?string => ...)` + `->withUpdateRow($listComponentName)`; в form — без доп. методов (рендерится как toggle) |
| `int` | `Number` (`src/UI/src/Fields/Number.php`, `NumberTrait`) | `Number::make('Limit', 'limit')` | `->min(int\|float)`, `->max(int\|float)`, `->step(int\|float)` — ставят и HTML-атрибут, и server-side enforcement в `resolveOnApply()` |
| `enum` | `Select` (`src/UI/src/Fields/Select.php`, `SelectTrait`) | `Select::make('Mode', 'mode')` | `->options(Closure\|array\|Options $data)` — формат `[value => label]`; `->multiple()` (трейт `CanBeMultiple`); `->searchable()`; async — `->async()` + `->asyncOnInit()` |
| `string` | `Text` (`src/UI/src/Fields/Text.php`) | `Text::make('Title', 'title')` | `->placeholder()`, `->mask()`, `->tags(?int $limit)` (multi-value), `->unescape()` (raw HTML) |

Сигнатуры fluent-методов (verbatim):

```php
// src/UI/src/Traits/Fields/NumberTrait.php
public function min(int|float $min): static { $this->min = $min; $this->setAttribute('min', (string) $this->min); return $this; }
public function max(int|float $max): static { $this->max = $max; $this->setAttribute('max', (string) $this->max); return $this; }
public function step(int|float $step): static { $this->step = $step; $this->setAttribute('step', (string) $this->step); return $this; }

// src/UI/src/Traits/Fields/SelectTrait.php
public function options(Closure|array|Options $data): static { $this->options = $data; return $this; }
```

Для **non-Eloquent** Switcher дефолтный URL ведёт на `updateField` (= `resource->save()`), поэтому при стабнутом `save()` передавайте свой `url:`-closure или используйте `onChangeMethod(string $method, ..., ?PageContract $page)`, целящийся в метод страницы.

### Меню и регистрация ресурса/страниц пакетом

Реальный API регистрации — метод-инъекция `CoreContract` в `boot()` сервис-провайдера (без `callAfterResolving`):

```php
// src/Contracts/src/Core/DependencyInjection/CoreContract.php
public function resources(array $data, bool $newCollection = false): static; // list<class-string<ResourceContract>|ResourceContract>
public function pages(array $data, bool $newCollection = false): static;     // list<class-string<PageContract>|PageContract>
public function autoload(?string $namespace = null): static;
```

```php
// src/Core/src/Core.php — append-семантика, $newCollection=true сбрасывает
public function resources(array $data, bool $newCollection = false): static
{
    if ($newCollection) { $this->resources = []; }
    $this->resources = array_merge($this->resources, $data);
    return $this;
}
```

Канонический паттерн из стаба:

```php
// src/Laravel/stubs/MoonShineServiceProvider.stub
public function boot(CoreContract $core): void
{
    $core
        ->resources([MoonShineUserResource::class, MoonShineUserRoleResource::class])
        ->pages([...$core->getConfig()->getPages()]);
}
```

`autoload()` — альтернатива: сканирует namespace на `ResourceContract`/`PageContract` и регистрирует автоматически (demo-project использует `$core->autoload()`).

**Меню.** Singleton `MenuManagerContract`:

```php
// src/Contracts/src/MenuManager/MenuManagerContract.php
public function add(array|MenuElementContract $data): static;
public function remove(Closure $condition): static;
public function addBefore(Closure $before, array|MenuElementContract|Closure $data): static;
public function addAfter(Closure $after, array|MenuElementContract|Closure $data): static;
public function all(?iterable $items = null): MenuElementsContract;
```

Главная точка расширения — `protected function menu(): array` в Layout-классе; `AbstractLayout::__construct()` сам вызывает `$this->getMenuManager()->add($this->menu())`.

```php
// src/MenuManager/src/MenuItem.php
// @method static static make(Closure|MenuFillerContract|string $filler, Closure|string $label = null, string $icon = null, Closure|bool $blank = false)

// src/MenuManager/src/MenuGroup.php
// @method static static make(Closure|string $label, iterable $items, string|null $icon = null)
```

`MenuItem::make(ResourceClass::class)` авто-резолвит label/URL/icon/badge из класса через `MenuFillerContract` (`getTitle()`, `getUrl()`, `getIcon()`, `getBadge()`, `getGroup()`, `isActive()`, `skipMenu()`, `canSee()`, `getPosition()`). `MenuElement::canSee(Closure)` — visibility-гейт.

**Авто-меню по атрибутам.** `autoloadMenu(bool $onlyIcons)` (в `MoonShine\Crud\Layouts\AbstractLayout`) делегирует `MenuAutoloader::resolve()`, который сканирует `getResources()` + non-`CrudPage` страницы и читает атрибуты: `#[Group(label, icon, translatable)]`, `#[Order(int)]`, `#[SkipMenu]`, `#[CanSee(method)]`. **Важно для зависимостей:** `autoloadMenu()` живёт в `moonshine/crud` — пакету понадобятся `moonshine/laravel` + `moonshine/crud` (тянут `crud`), а не только `core`/`contracts`.

### Рекомендация для milestone

**Выбор: `CrudResource` (база) + три custom Page (Index/Form/Detail), а НЕ standalone Page в Resource-оболочке.**

Обоснование на основе кода:
- `CrudResource` data-agnostic (verdict **refuted** для «нужен Model»): пять абстрактных методов против `DataWrapperContract`, дефолтный `MixedDataCaster`. Мы получаем бесплатно: готовый routing (`crud.update`/`crud.store`/`updateField`), form-pipeline (`StoreFormRequest`/`UpdateFormRequest` → `getRules()` → `HandlePrecognitiveRequests`), async-таблицу, row/bulk кнопки, Fragment-перезагрузку — всё это пришлось бы воссоздавать руками в standalone-Page.
- Standalone-Page оправдан только если нет CRUD-семантики; у нас есть list + edit settings + detail — это ровно CRUD-форма страниц.

#### Ответы на open questions исследования

**База ресурса.** `CrudResource` (не `ModelResource`). Реализуем: `getItems()` (из реестра модулей), `findItem(bool $orFail)` (lookup по `casterKeyName`), `save(DataWrapperContract, ?FieldsContract)` (запись settings.values/enabled в `state.json` через существующий `ModuleStateRepository`), `delete()`/`massDelete()` — стабы (модули не удаляются из админки → `return false` / no-op). `getDataInstance()` переопределить, чтобы возвращать пустой module-DTO. `protected string $casterKeyName = 'name'`.

**Как DetailPage читает свежие feature values.** `DetailPage::getDetailComponent()` каждый раз зовёт `$resource->getCastedData()` → `findItem()`, который читает из реестра/`state.json` на лету. Поскольку `modules:optimize` **никогда** не кэширует `settings.values` (см. CLAUDE.md), DetailPage всегда видит актуальные значения без сброса кэша — отдельных мер не требуется; нужно лишь, чтобы `findItem()` ходил в `ModuleStateRepository`, а не в кэш discovery. Async-обновление детали — через `Fragment::make([...])->name('crud-detail')`.

**Меню под флагом.** Использовать `#[CanSee(method)]` на ресурсе (метод возвращает `bool` по фиче), либо `MenuItem::make(ModuleResource::class)->canSee(fn () => ...)` в `menu()`. Поскольку `MenuManager::flushState()` вызывается на каждом `RequestHandled`, `menu()`/`canSee()` пере-вычисляются на каждый запрос — флаг можно читать динамически. Регистрацию самого ресурса под флагом делать в `boot()` пакета: `if ($featureEnabled) { $core->resources([ModuleResource::class]); }`.

#### Краткий скелет (имена классов/методов, без реализации)

```php
// final class ModuleResource extends CrudResource
//   protected string $casterKeyName = 'name';
//   protected function pages(): array { return [ModuleIndexPage::class, ModuleFormPage::class, ModuleDetailPage::class]; }
//   public function getItems(): iterable { /* ModuleRegistry → collection<ModuleData> */ }
//   public function findItem(bool $orFail = false): ?DataWrapperContract { /* registry lookup → getCaster()->cast(...) */ }
//   public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract { /* ModuleStateRepository write */ }
//   public function delete(...): bool { return false; }
//   public function massDelete(array $ids): void {}
//   protected function indexFields(): iterable  { /* Text name, Switcher enabled (+updateOnPreview/onChangeMethod), Text version */ }
//   protected function formFields(): iterable    { /* per settings.schema → Switcher/Number/Select/Text */ }
//   protected function detailFields(): iterable  { /* read-only preview набор */ }

// final class ModuleFormPage extends FormPage
//   protected function rules(DataWrapperContract $item): array { /* валидация settings */ }

// final class ModuleIndexPage extends IndexPage
//   protected function buttons(): ListOf { /* parent::buttons() минус delete/massDelete */ }
//   // async toggle: Switcher::make('Enabled','enabled')->onChangeMethod('toggleEnabled', page: $this)

// final class ModuleDetailPage extends DetailPage {}
```

### Остаточные open questions (проверить на прототипе после dev-deps `moonshine/laravel` + `moonshine/ui` + `moonshine/crud` (^4))

1. **Пагинация non-Eloquent.** `MixedDataCaster::paginatorCast()` возвращает `null`; не подтверждено чтением полного `IterableComponent::resolvePaginator()`, что возврат `LengthAwarePaginator` из `getItems()` авто-детектится. Проверить, нужно ли вручную звать `->paginator()` в `modifyListComponent()` (модулей обычно немного — пагинация может вообще не понадобиться).
2. **Async-toggle Switcher на стабнутом `save()`.** Дефолтный `updateOnPreview()` бьёт в `updateField` → `resource->save()`. Подтвердить, что `onChangeMethod('toggleEnabled', page: $this)` либо явный `url:`-closure корректно резолвят per-row endpoint без Eloquent. Проверить визуальный результат `resolveRender()` (Switcher в preview-таблице переключается в `defaultMode()`).
3. **`getKey()` для row-actions/async.** Подтвердить, что `casterKeyName='name'` делает `DataWrapperContract::getKey()` не-null и роутинг `crud.update`/`updateField` корректно подставляет `resourceItem`.
4. **`toArray()` на value-объекте модуля.** Без метода `toArray()` `MixedDataWrapper` делает `(array) $object` — публичные свойства попадут, но приватные/computed — нет. Добавить `toArray()` в module-DTO и проверить заполнение всех полей формы.
5. **`UriKey::generate()`.** Точное преобразование FQCN → uri-key (предположительно kebab-case basename) не прочитано из исходника — проверить итоговые URL ресурса/страниц.
6. **MenuFiller-методы.** Точные сигнатуры `skipMenu()`/`getGroup()`/`getGroupIcon()`/`getPosition()` (которые перекрывают атрибуты) не прочитаны построчно — верифицировать перед кастомным non-Eloquent ресурсом.
7. **Меню из стороннего SP под флагом.** Не подтверждён порядок boot: `add()` из `boot()` пакета vs `flushState()` на `RequestHandled`. Безопаснее добавлять пункт в `menu()` Layout-класса/через `autoloadMenu()` + `#[CanSee]`, чем из `boot()` стороннего провайдера.
8. **`ModelResource`-путь не читался** — точный паттерн override `getCaster()` для Eloquent не показан (нам он не нужен; `MixedDataCaster` подтверждён как non-Eloquent дефолт).

## Sessions
<!-- aif:sessions:start -->
### 2026-06-04 12:00 — MoonShine v4: глубокий разбор IndexPage/FormPage/DetailPage
What changed:
- Прочитаны исходники MoonShine 4.x (монорепо `moonshine-software/moonshine`: src/Crud, src/Core, src/UI, src/Laravel, src/MenuManager, src/Contracts) + demo-project 4.0. Метод: 9 investigator-агентов + 3 adversarial-верификатора + синтез.
Key notes:
- **CrudResource data-agnostic** (verdict: refuted, что нужен Eloquent): пять абстрактных методов через `DataWrapperContract`, дефолтный `MixedDataCaster`; Eloquent-привязка только в подклассе `ModelResource`. → база ресурса = `CrudResource` (а не custom Pages).
- IndexPage → `DefaultListComponent`(`TableBuilder`); FormPage → `DefaultForm`(`FormBuilder`, persist через `resource->save()`, валидация на странице `rules()`, native precognition); DetailPage → `DefaultDetailComponent`(`TableBuilder` vertical+simple+preview).
- Async Switcher: `updateOnPreview`/`onChangeMethod`/`withUpdateRow`; дефолтный URL бьёт в `updateField` → нужен свой endpoint к UseCase. Маппинг фич: bool→`Switcher`, int→`Number`(min/max/step), enum→`Select`(options), string→`Text`.
- Доп. dev-dep `moonshine/crud` (CrudResource + страницы Index/Form/Detail + `autoloadMenu`).
- Остаточные прототип-проверки см. в Active Summary → Open questions и в секции «Углубление…».
Links (paths, repo moonshine-software/moonshine@4.x):
- src/Crud/src/Resources/CrudResource.php; src/Crud/src/Pages/{IndexPage,FormPage,DetailPage,CrudPage}.php
- src/Crud/src/Pages/PageComponents/{DefaultListComponent,DefaultForm,DefaultDetailComponent}.php
- src/Core/src/TypeCasts/{MixedDataCaster,MixedDataWrapper}.php; src/UI/src/Traits/Fields/UpdateOnPreview.php
- src/Laravel/src/Resources/ModelResource.php (для контраста — НЕ используем)

### 2026-06-04 00:00 — MoonShine admin-UI: разбор и решения
What changed:
- Изучены registry/state/feature слои, lifecycle UseCases, dependency guard, ServiceProvider MoonShine-хук, арх-тесты, установленные MoonShine-пакеты, config.
- Сверена дока MoonShine v4 (context7 /moonshine-software/doc, ветка 4.x): package-development регистрация через `$core->resources()/pages()`, меню через `MenuManagerContract`, `TableBuilder`/`FormBuilder` + async, кастомный non-model resource.
Key notes:
- Подтверждён scope: Toggle + Delete(Backup) + FormPage(feature flags) + DetailPage(debug only). Install/Update — отложены.
- Guard UX = заблокированный контрол + tooltip. UI-метки = translatable lang-файлы.
- Регистрация ресурса самим пакетом (надёжно) + меню под конфиг-флагом — рекомендованный вариант, подтвердить на плане.
- Нужны dev-deps `moonshine/laravel`+`moonshine/ui` ^4; UI строго на DI (арх-баны facades/helpers).
Links (paths):
- src/Providers/ModuleLoaderServiceProvider.php (bootMoonShineIntegration)
- src/MoonShine/MoonShineModuleAutoloader.php
- src/Application/Support/ModuleDependencyGuard.php
- src/Manifest/VO/{Module,FeatureSchema,FeatureDefinition,FeatureValues}.php
- src/Application/UseCases/{Enable,Disable,Remove,List}ModuleUseCase.php
- tests/Architecture/ArchitectureTest.php (facade/helper/Request bans)
- config/modules.php (groups, paths)
<!-- aif:sessions:end -->
