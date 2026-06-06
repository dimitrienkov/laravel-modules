# Implementation Plan: MoonShine admin-UI

Branch: `feature/moonshine-admin-ui`
Created: 2026-06-04

`ModulesResource` + Index/Form/Detail страницы для управления модулями из admin-панели MoonShine v4. MoonShine остаётся **опциональной** зависимостью — без него ядро грузится без ошибок.

## Settings
- Testing: **yes** — UI-слой фиксируется тестами: arch-тест нового namespace `src/MoonShine/`, unit на presentation-DTO/`FeatureFieldFactory`/`ModuleDependentsResolver`/label-резолверы, feature на проводку Resource/страниц через провайдер и на `save → writeValues`.
- Logging: **none in `src/`** — правило проекта «Package Core Logging Scope»: verbose-логи в ядре не планируются. Фидбэк UI — типизированные исключения UseCase + MoonShine-тосты + тесты.
- Docs: **yes** — обязательный docs-чекпоинт через `/aif-docs`: `docs/moonshine.md` + строки навигации в README/AGENTS (закрывает открытый roadmap-пункт «Документация» Фазы 2).

## Roadmap Linkage
Milestone: **"Фаза 2 — MoonShine admin-UI"** (`.ai-factory/ROADMAP.md`, единственный точный матч; авто-связано, т.к. milestone существует и совпадает 1:1).
Rationale: План закрывает roadmap-пункт `MoonShine admin-UI` (`ModulesResource` + динамическая форма settings + запись через `ModuleStateRepository`/`FeatureValues`) и частично продвигает пункт «Документация» (добавляет `docs/moonshine.md`). Install/Update через zip-upload в UI — осознанно **вне** этого milestone (отдельная фаза).

## Research Context
Source: `.ai-factory/RESEARCH.md` (Active Summary + «Углубление: построение IndexPage/FormPage/DetailPage»)

**Goal:** Дать заказчику в MoonShine: список модулей (табы по `ModuleKind`, внутри — таблицы по `meta.group`), enable/disable (Switcher), удаление (Backup), управление feature flags (динамическая форма по `settings.schema`) и DetailPage с debug-инфой.

**Подтверждённый scope:**
- IndexPage: табы `Subsystems`/`Integrations`/`Modules` (по `ModuleKind`), внутри каждого — `TableBuilder` на каждый `meta.group`; колонки Name(displayName) | Version | Switcher(enabled) | actions; алфавитная сортировка по displayName.
- Toggle enabled → `EnableModuleUseCase`/`DisableModuleUseCase`.
- Delete → `RemoveModuleUseCase` **только** `RemoveStrategy::Backup` (без Permanent в UI).
- FormPage feature flags: `bool→Switcher`, `int→Number(min/max)`, `enum→Select(options)`, `string→Text`; группировка по `settings.schema.*.group`; запись через `ModuleStateRepository::writeValues()` + `FeatureValues`.
- DetailPage: **только** debug-инфа (пути, namespace, version, dependencies, dependents, load order, provenance `ModuleOrigin`/checksum, текущие feature values). Без чтения логов.

**Жёсткие constraints:**
- MoonShine **только v4+**. В проекте сейчас лишь `moonshine/contracts`+`moonshine/core` (require-dev). `CrudResource`, страницы Index/Form/Detail и `autoloadMenu()` живут в `moonshine/crud` → добавить `moonshine/laravel`+`moonshine/ui`+`moonshine/crud` в `require-dev` (+ `suggest`, runtime опционален).
- Арх-тесты применяются ко **всему** `src/`, включая `src/MoonShine/`: запрет `Illuminate\Support\Facades\*`, запрет глобальных хелперов (`app`,`config`,`resolve`,`value`,`event`,`dispatch`,`logger`,`logs`,`info`,`report`), запрет `Illuminate\Http\Request`, запрет `static`-свойств, прямого FS-I/O, и требование `final` (+`final readonly` для VO). → UI строго на конструкторном DI; `::make`-фабрики (`Switcher::make()` и т.п.) разрешены (это не глобальные хелперы).
- Метки UI — **translatable через lang-файлы**, не хардкод (код/коммиты на английском).
- `module.json` immutable; mutable state только через `ModuleStateRepository`; на границе записи — только `FeatureValues`, никаких сырых `array`.

**Ключевые решения (из исследования):**
- База ресурса — **`CrudResource`** (data-agnostic: пять абстрактных методов через `DataWrapperContract`, дефолтный `MixedDataCaster`; Eloquent — только в подклассе `ModelResource`, его НЕ берём). `casterKeyName = 'name'`.
- Регистрация ресурса+страниц — **самим пакетом** через `$core->resources()/pages()` в существующем хуке `bootMoonShineIntegration()`. Не конфликтует с per-module `autoload()` (разные namespace'ы).
- Меню — вариант **AUTO**: `#[CanSee('…')]` на ресурсе под флагом `modules.moonshine.menu`, опора на host `autoloadMenu()`. `MenuManager::flushState()` на `RequestHandled` → `canSee()` пере-вычисляется на запрос (флаг динамический).
- DetailPage видит свежие values: `findItem()` ходит в `ModuleStateRepository`, а `modules:optimize` **никогда** не кэширует `settings.values` → отдельная инвалидация не нужна.

**Уточнения по фактическому коду пакета (выявлено при разведке — корректируют исследование):**
- **Нет** конфиг-секции `modules.moonshine` — её нужно создать (`enabled`, `menu`, default `true`). Текущий MoonShine-хук гейтит только `interface_exists(CoreContract)`.
- **Нет** собственной translation-инфраструктуры пакета (`loadTranslationsFrom`/`lang/` отсутствуют; `LangLoader` грузит lang host-модулей, не пакета). Нужно добавить `lang/{en,ru}` + регистрацию + `publishes`.
- `ModuleKind` **не имеет** `label()` — нужен `ModuleKindLabelResolver` (translatable). Для групп есть готовый `ModuleGroupLabelResolver::displayLabel(?ModuleGroup)`.
- `FeatureValues::with()` — **инстанс**-метод (`$values->with($module,$key,$value,$manifestPath)`), плюс `FeatureValues::fromArray(array, FeatureSchema, $module, $manifestPath)`. Статического `with()` нет.
- `ModuleStateRepository::writeValues()` пишет **только** `settings.values` и сохраняет текущий `ModuleState`; `enabled` меняется только через `EnableModuleUseCase`/`DisableModuleUseCase` (`writeState()` + `LifecycleRegistryInvalidator`). → `ModulesResource::save()` не должен быть путём toggle-enabled.
- `DependentModulesExistException` **не имеет** `getDependentNames()` — имена зависимых только в message. → UI вычисляет зависимых сам (read-only `ModuleDependentsResolver`) для превентивной блокировки контролов; бизнес-enforcement остаётся в `ModuleDependencyGuard` внутри UseCase.
- `RemoveModuleUseCase::execute($name, RemoveStrategy=Backup)` возвращает `RemoveModuleResult` (не `Module`). `Enable/DisableModuleUseCase::execute($name): Module`.
- `Module` — публичные readonly-свойства (`name,displayName,namespace,path,schemaVersion,meta,state,features`) + методы `isEnabled()`, `manifestPath()`, `toDescriptorArray()`. Плоского `toArray()` под форму нет → нужен presentation-DTO `ModuleAdminDto` с `toArray()`.
- Направление зависимостей зафиксировано arch-тестами: `Application` и `Loaders` **не** зависят от `MoonShine`; разрешено только `MoonShine → Application` (ресурс зовёт UseCase'ы). Весь UI-код — в `src/MoonShine/`.
- Optional bridge сейчас защищён только `interface_exists(CoreContract)`; admin-UI добавит зависимости на `moonshine/crud`/`moonshine/ui`, поэтому existing `MoonShineModuleAutoloader` остаётся под `CoreContract`, а регистрация `ModulesResource` должна быть отдельно защищена наличием полного CRUD/UI stack + `modules.moonshine.enabled=true`.

**Open questions → закрываются в Task 2 (после установки dev-deps):** non-Eloquent пагинация; async-toggle на стабнутом `save()`; `getKey()` через `casterKeyName='name'`; `toArray()` на DTO; `UriKey::generate()` URL; MenuFiller-сигнатуры; boot-порядок меню под флагом; подтверждение `MixedDataCaster` как non-Eloquent дефолта.

### Task 2 — Verified findings (прочитаны установленные исходники MoonShine 4.15.0; спайк read-only, в `src/` ничего не писалось)
Пути относительно `vendor/moonshine/moonshine/`.
1. **`CrudResource` data-agnostic** (`src/Crud/src/Resources/CrudResource.php`): абстрактные `findItem(bool $orFail=false): ?DataWrapperContract`, `getItems(): iterable|Collection|LazyCollection|CursorPaginator|Paginator`, `massDelete(array $ids): void`, `delete(DataWrapperContract,?FieldsContract): bool`, `save(DataWrapperContract,?FieldsContract): DataWrapperContract`. `getCaster()→new MixedDataCaster($this->casterKeyName)`; `getDataInstance()→[]`. **`pages()` по умолчанию отдаёт КОНТРАКТЫ** `IndexPageContract/FormPageContract/DetailPageContract` (резолвятся из контейнера) — переопределяем на свои concrete-страницы. Eloquent-импортов нет.
2. **`casterKeyName` базовый тип = `?string` (default `null`)** → `protected ?string $casterKeyName = 'name';`. `MixedDataCaster::cast()` → `data_get($data,'name')`; для public `name` `getKey()` не-null. (`src/Core/src/TypeCasts/{MixedDataCaster,MixedDataWrapper}.php`).
3. **`MixedDataWrapper::toArray()`** зовёт `$data->toArray()` если есть, иначе `(array)$data` (только public-поля) → DTO `toArray()` обязателен для предсказуемого маппинга.
4. **Страницы наследуются напрямую от `MoonShine\Crud\Pages\{IndexPage,FormPage,DetailPage}`** (concrete, не final). Цепочка `IndexPage→CrudPage→Crud\Pages\Page→Core\Pages\Page` — **НЕ через** `MoonShine\Laravel\Pages\Page`. Обязательный override — `fields()` (page-`fields()` приоритетнее resource-`*Fields()`).
5. **Async-toggle (РЕШЕНО — `onChangeMethod`):** `Switcher::make('Enabled','enabled')->onChangeMethod('toggleEnabled', page: $this, events: [AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $this->getListComponentName())])`. **КРИТИЧНО:** целевой метод страницы ОБЯЗАН нести атрибут `#[MoonShine\Support\Attributes\AsyncMethod]`, иначе `MethodController` бросает `RuntimeException`. Метод DI-вызывается через `$container->call()`. Ключ строки = `resourceItem` → `MoonShineRequest::getItemID()`; новое значение — input по колонке поля (`$request->boolean('enabled')`). Возврат `void` → success-тост + async-refresh; исключение → error-тост. (`src/UI/src/Fields/Field.php::onChangeMethod`; `src/Laravel/src/Http/Controllers/MethodController.php`; `src/Laravel/src/MoonShineRequest.php::getItemID`).
6. **UriKey НЕ срезает суффикс** (`src/Support/src/UriKey.php`: `classBasename()->kebab()`): `ModulesResource→modules-resource`. Для чистого URL — `->alias('modules')` (`WithUriKey::getUriKey()` сперва берёт `getAlias()`).
7. **Меню/`#[CanSee]`:** атрибут `MoonShine\MenuManager\Attributes\CanSee(public string $method)` (TARGET_CLASS). Метод ресурса — `public function <name>(): bool` (без аргументов; результат cast в bool); `MenuAutoloader::canSee()` зовёт `$resolved->{$method}()`. `autoloadMenu()` в `MoonShine\Crud\Layouts\AbstractLayout` сканирует `getResources()`+non-Crud `getPages()`. `MoonShine::flushState()→MenuManager::flushState()` слушает `RequestHandled` (+ Octane) в `MoonShineServiceProvider` → `canSee()` пере-вычисляется на запрос. `MenuFillerContract`: `getTitle/getUrl/getBadge/getGroup/getGroupIcon/isActive/skipMenu/canSee/getIcon/getPosition`.
8. **Форма/валидация:** правила на СТРАНИЦЕ — `protected function rules(DataWrapperContract $item): array` (трейт `HasFormValidation`, уже на `FormPage`), `getRules()` зовётся из Store/UpdateFormRequest. Контроллер: `$item=$resource->save($resource->getCaster()->cast($item)); $resource->setItem($item->getOriginal());` → **`save()` обязан вернуть `DataWrapperContract`** (`return $this->getCaster()->cast($vo)`). Поля — трейт `ResourceWithFields`: protected `indexFields()/formFields()/detailFields()`; `getIndexFields()/...` сперва берут page-`fields()`, потом fallback на resource.
9. **Поля:** базовый `make(Closure|string|null $label=null, ?string $column=null, ?Closure $formatted=null)` (`FormElement`). `Number`(`NumberTrait`): `min(int|float)/max(int|float)/step(int|float)`; `Select`(`SelectTrait`): `options(Closure|array|Options)`; `Switcher extends Checkbox`; `Text extends Field`.
10. **Пагинация — ручная.** `MixedDataCaster::paginatorCast()` всегда `null`; in-memory авто-пагинации нет. Модулей немного → отдаём полную коллекцию из `getItems()` без пагинации.

## Целевая структура (новый код)
```
src/MoonShine/
├── MoonShineModuleAutoloader.php        # существует — не трогаем
├── Resources/ModulesResource.php        # CrudResource: getItems/findItem/save/delete + #[CanSee]
├── Pages/ModuleIndexPage.php            # табы по Kind, таблицы по group, switcher, actions
├── Pages/ModuleFormPage.php             # feature-flags форма + rules()
├── Pages/ModuleDetailPage.php           # debug detail (read-only)
├── Data/ModuleAdminDto.php             # final readonly presentation-DTO + toArray()
└── Support/
    ├── FeatureFieldFactory.php          # FeatureDefinition → MoonShine field
    ├── ModuleKindLabelResolver.php      # ModuleKind → translatable label
    └── ModuleDependentsResolver.php     # read-only зависимые (для guard UX)
lang/{en,ru}/module-loader.php           # translatable метки UI
config/modules.php                       # + секция moonshine { enabled, menu }
docs/moonshine.md                        # документация (docs-чекпоинт)
```

## Commit Plan
- **Commit 1** (Task 1): `chore(deps): add moonshine laravel/ui/crud as dev dependencies`
- **Commit 2** (Task 2): `docs(research): resolve moonshine prototype open questions` *(если спайк-находки фиксируются; иначе спайк выбрасывается и коммита нет)*
- **Commit 3** (Tasks 3–5): `feat(moonshine): add modules resource data adapter and i18n`
- **Commit 4** (Tasks 6–9): `feat(moonshine): build modules index page with async toggle and guards`
- **Commit 5** (Tasks 10–11): `feat(moonshine): add feature-flags form page`
- **Commit 6** (Task 12): `feat(moonshine): add debug detail page`
- **Commit 7** (Tasks 13–14): `feat(moonshine): self-register resource under config flags`
- **Commit 8** (Task 15): `docs(moonshine): document admin UI`
- **Commit 9** (Task 16): `chore: run rector/format/phpstan/test quality gate`

## Tasks

### Phase 0 — Dev-deps и снятие рисков (Contract-First)

- [x] **Task 1: dev-deps MoonShine UI** — добавить `moonshine/laravel`, `moonshine/ui`, `moonshine/crud` (^4) в `require-dev` (CrudResource + Index/Form/Detail + `autoloadMenu` живут в `moonshine/crud`) + `suggest`-записи. `composer update` для новых пакетов, зафиксировать `composer.lock`. Файлы: `composer.json`. Логи: нет.
  - **Коррекция (факт packagist):** `moonshine/laravel` НЕ публикуется отдельным пакетом — это split внутри монорепо-umbrella `moonshine/moonshine`, который `replaces` все sub-пакеты (contracts/core/crud/ui/laravel/menu-manager/asset-manager/…). Поэтому в `require-dev` поставлен один `moonshine/moonshine` (^4) вместо `laravel`+`ui`+`crud`, а прежние standalone `moonshine/contracts`/`moonshine/core` убраны (umbrella их `replaces` → иначе конфликт). `suggest` → один `moonshine/moonshine`. Stack остаётся опциональным (`require-dev`+`suggest`); `interface_exists(CoreContract)` по-прежнему работает (umbrella содержит эти классы). Laravel поднялся до 13.13 транзитивно.
  - ✅ полный стек MoonShine стоит как dev-dep; `composer test:arch` зелёный (vendor не триггерит facade/helper/Request-сканы — они только по `src/`); ядро по-прежнему грузится без MoonShine.
  - ❌ deps в `require` вместо `require-dev`; нет `suggest`; lock не зафиксирован; arch-suite покраснел.

- [x] **Task 2: снятие прототип-рисков** (depends on 1) — на установленных исходниках MoonShine 4.x подтвердить/скорректировать 8 open questions (пагинация non-Eloquent; async-toggle на стабнутом `save()` через `onChangeMethod`/`url:`-closure + `defaultMode()` в preview; `getKey()` через `casterKeyName='name'`; `MixedDataWrapper::toArray()` требует `toArray()` на DTO; `UriKey::generate()` URL; MenuFiller-сигнатуры; boot-порядок меню под флагом; `MixedDataCaster` как non-Eloquent дефолт). Для async-toggle выбрать один конкретный API (`onChangeMethod` или `url:`-closure) и обновить Task 7 до старта Task 3. Артефакт спайка — выбрасывается (никакого мусора в `src/`). Логи: нет.
  - ✅ каждый вопрос получил однозначный ответ; находки дописаны в Research Context этого плана; если фактический API MoonShine отличается от текущих задач, Task 2 вносит конкретные правки в затронутые Task 5/7/13 до старта Task 3; если отличий нет — добавляет explicit note `No task changes required`.
  - ❌ спайк-класс остался в `src/`/`tests/`; вопросы помечены «проверим в проде»; решения противоречат фактическому API.

### Phase 1 — Data adapter, i18n, Resource-скелет

- [x] **Task 3: presentation-DTO `ModuleAdminDto`** (depends on 2) — `final readonly` в `src/MoonShine/Data/`: публичные readonly-поля под все колонки index/detail/form (name, displayName, version-string, kind, group, enabled, namespace, path, dependencies, map текущих feature values, provenance) + явный `toArray(): array` (чтобы `MixedDataWrapper::toArray()` отдал ВСЕ поля). Строится из `Module` (+ свежие state/values). Чистый data-carrier, без MoonShine-зависимостей внутри. Companion unit-тест: конструирование из `Module`, round-trip `toArray()` по всем ключам, edge-кейс (модуль без schema). Файлы: `src/MoonShine/Data/ModuleAdminDto.php`. Логи: нет.
  - ✅ `final readonly`+`strict_types`; `toArray()` покрывает каждый ключ формы/детали; `casterKeyName='name'` даёт не-null `getKey()`; unit-тест зелёный.
  - ❌ полагается на `(array)`-каст (теряет computed); тащит MoonShine-контракты внутрь DTO; нет error-path в тесте.

- [x] **Task 4: translation-инфра + Kind/label-резолверы** (depends on 2) — создать `lang/en/module-loader.php` и `lang/ru/module-loader.php` (ключи: заголовки табов subsystems/integrations/modules, заголовки колонок, действия, guard-тултипы). Зарегистрировать `loadTranslationsFrom($langPath, 'module-loader')` + `$this->publishes([...], 'module-loader-translations')` в `ModuleLoaderServiceProvider::boot()`. Добавить `final readonly ModuleKindLabelResolver` (через инжектированный `Translator`-контракт, **не** `__()`/`trans()`-хелпер); группы — через существующий `ModuleGroupLabelResolver`. Файлы: `lang/{en,ru}/module-loader.php`, `src/MoonShine/Support/ModuleKindLabelResolver.php`, `src/Providers/ModuleLoaderServiceProvider.php`. Тесты: unit (каждый `ModuleKind` → ключ), feature (namespace переводов зарегистрирован). Логи: нет.
  - **Реализация:** lang-файлы названы `lang/{en,ru}/admin.php` (группа `admin`) при namespace `module-loader` → ключи `module-loader::admin.*` (идиоматично, как `moonshine::ui.*`), вместо избыточного `module-loader::module-loader.*`. namespace и publishes-тег `module-loader-translations` — как в плане. Резолвер падает в enum-value при не-string переводе (type-safe для PHPStan max).
  - ✅ метки translatable (en+ru); резолвер на DI-`Translator`; publishes-тег есть; тесты зелёные.
  - ❌ хардкод-строки в UI; использован глобальный `__()`/`trans()`; переводы не зарегистрированы/не публикуются.

- [x] **Task 5: `ModulesResource extends CrudResource`** (depends on 3, 4) — `final class …\MoonShine\Resources\ModulesResource`. Конструкторный DI: `ModuleRegistryInterface`, `ModuleStateRepositoryInterface`, `ModuleKindLabelResolver`, `ModuleGroupLabelResolver`, `Repository`(config). Lifecycle UseCase'ы (`Enable/Disable/Remove`) инжектятся в `ModuleIndexPage`, а не в ресурс; `ListModulesUseCase` не нужен — `getItems()` читает registry через контракт. `protected string $casterKeyName = 'name'`. Реализовать: `pages()`→[Index,Form,Detail]; `getItems()`→`ModuleRegistryInterface::all()`→`ModuleAdminDto` (topo-порядок; алфавит по displayName на уровне таблицы); `findItem()`→lookup + `ModuleStateRepository` для **свежих** values, каст через `getCaster()`; `save()`→запись **только feature values** через `ModuleStateRepository::writeValues()` (`FeatureValues`, без сырого array; `enabled` НЕ меняет — toggle идёт через UseCase из Task 7); `delete()`/`massDelete()`→no-op стабы (`return false`/пусто); `getDataInstance()`→пустой `ModuleAdminDto`. Файлы: `src/MoonShine/Resources/ModulesResource.php`. Тесты: feature (boot провайдера с MoonShine — по образцу `OptionalMoonShineBootTest` — ресурс резолвится, `getItems` маппит реестр, `getKey` не-null), unit на маппинг `getItems`/`findItem`, regression (`save()` не меняет `enabled`, а пишет только `settings.values`). Логи: нет (тосты/исключения/тесты).
  - **Реализация:** конструктор ресурса минимизирован до `CoreContract` + `ModuleRegistryInterface` + `ModuleStateRepositoryInterface` (всё используется в getItems/findItem/save). `ModuleKindLabelResolver`/`ModuleGroupLabelResolver` перенесены в страницы (где реально используются — Task 6/12), `Repository`(config) добавится в ресурс в Task 13 (menuVisible) — чтобы не плодить dead-deps. `save()` читает submitted values через form-поля (`getRequestValue()`, колонки `featureValues.<key>`), стрипает значения, равные дефолту, и зовёт `writeValues(FeatureValues)`. Регресс-тест save проверяет инвариант через пустую `Fields`-коллекцию (writeValues вызван, writeState/writeDocument — нет) без подъёма панели; полное покрытие персиста формы — в Task 11. Тест резолва — через bare `CoreContract`-mock в контейнере (без полного boot MoonShine).
  - **Task 2 коррекция:** `protected ?string $casterKeyName = 'name';` (базовый тип `?string`). `save()` ОБЯЗАН вернуть `DataWrapperContract` → `return $this->getCaster()->cast($dto);` после `writeValues`. Поля ресурса — protected `indexFields()/formFields()/detailFields()` (трейт `ResourceWithFields`); но Switcher с toggle живёт в page-`fields()` (приоритет у страницы) — см. Task 6/7. Для чистого URL `->alias('modules')` (иначе uri-key `modules-resource`).
  - ✅ ресурс резолвится из контейнера; data-agnostic (нет `Model`/Eloquent); `save()` идёт строго через `writeValues(FeatureValues)` и не меняет `enabled`; стабы delete безопасны; `final` + DI без фасадов/хелперов.
  - ❌ наследование от `ModelResource`/`$model`; сырой `array` на границе записи; `save()` используется как toggle-enabled; `config()`/фасад в ресурсе; `getKey()` null.

### Phase 2 — IndexPage (табы, таблицы, async-toggle, guard UX)

- [x] **Task 6: `ModuleIndexPage`** (depends on 5) — `final class …\MoonShine\Pages\ModuleIndexPage extends Crud\Pages\IndexPage`. Переопределить `components()`/layers: внешние табы по `ModuleKind` (Subsystems/Integrations/Modules), внутри таба — отдельный `TableBuilder` на каждый `meta.group` (заголовок через `ModuleGroupLabelResolver`); колонки Name(displayName) | Version | Switcher(enabled) | row-actions; строки сортируются алфавитно по displayName. Только `::make`-фабрики; без фасадов/хелперов/`Request`. Файлы: `src/MoonShine/Pages/ModuleIndexPage.php`. Тесты: feature/unit на структуру табов+таблиц и набор колонок для fixture-реестра. Логи: нет.
  - ✅ табы по Kind; внутри — таблица на group; корректный набор колонок; алфавит по displayName.
  - ❌ один общий список без разбивки; колонки не совпадают со scope; глобальные хелперы.

- [x] **Task 7: async enable/disable Switcher → UseCase** (depends on 6) — Switcher(enabled) на index делает async-toggle через `EnableModuleUseCase`/`DisableModuleUseCase`, инжектированные в `ModuleIndexPage` (НЕ дефолтный `updateField → save()`, он пишет только feature values и не меняет `enabled`). Использовать конкретный API, зафиксированный в Task 2, для вызова метода страницы `toggleEnabled`, который зовёт `…UseCase::execute($name)`, + `withUpdateRow($listComponentName)` для перерисовки строки. На исключение UseCase (например, граф невалиден) — MoonShine-тост с message, без персиста. После toggle реестр перечитывается свежим (`LifecycleRegistryInvalidator` уже внутри UseCase). Файлы: `ModuleIndexPage.php`. Тесты: feature (toggle зовёт нужный UseCase и перерисовывает; исключение → тост; `ModulesResource::save()` не вызывается toggle endpoint'ом). Логи: нет (фидбэк — тост).
  - **Task 2 коррекция (зафиксированный API):** `Switcher::make(label,'enabled')->onChangeMethod('toggleEnabled', page: $this, events: [AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $this->getListComponentName())])`. Метод страницы: `#[\MoonShine\Support\Attributes\AsyncMethod] public function toggleEnabled(MoonShineRequest $request): void` — **атрибут обязателен** (иначе `RuntimeException` в `MethodController`). Имя модуля: `$request->getItemID()`; новое значение: `$request->boolean('enabled')`. На enable/disable исключение UseCase прокидывается → MoonShine ловит и рисует error-тост; success → `void` (тост + async-refresh). UseCase'ы инжектируются в page через DI-конструктор. (NB: `withUpdateRow` — деталь дефолтного `updateOnPreview`; здесь эквивалент — `events:` с `TABLE_ROW_UPDATED`.)
  - ✅ toggle идёт через lifecycle UseCase; одна строка перерисовывается; ошибка → тост; Switcher в preview переключается (`defaultMode()`).
  - ❌ toggle бьёт в стабнутый `save()` (не персистит); полная перезагрузка страницы; исключение «проглочено».

- [x] **Task 8: `ModuleDependentsResolver` + Guard UX** (depends on 6) — `final readonly ModuleDependentsResolver` в `src/MoonShine/Support/`: по имени модуля возвращает displayName-ы зависимых, сканируя `ModuleRegistryInterface::all()` на `meta.dependencies->constraintFor($name) !== null` (для disable — только ВКЛЮЧЁННЫЕ зависимые, как `assertCanDisable`; для remove — ЛЮБЫЕ, как `assertCanRemove`). Результат сортируется детерминированно по displayName/name, чтобы tooltip и тесты не зависели от порядка обхода. В index при наличии зависимых: Switcher/кнопка delete **заблокированы** + tooltip со списком зависимых (translatable). Enforcement остаётся в `ModuleDependencyGuard` внутри UseCase (defense-in-depth, UI не дублирует бизнес-логику). Файлы: `src/MoonShine/Support/ModuleDependentsResolver.php`, `ModuleIndexPage.php`. Тесты: unit (enabled-only vs any, сортировка, пустой кейс), feature (заблокированный контрол + tooltip). Логи: нет.
  - ✅ резолвер различает enabled-only/any; выдаёт стабильный порядок; контрол блокируется превентивно с tooltip; бизнес-логика не продублирована (читает граф, а не повторяет guard).
  - ❌ UI пытается достать имена из exception (их там нет); блокирует не тех; дублирует guard-условия как отдельную «правду».

- [x] **Task 9: delete-действие (Backup-only)** (depends on 8) — row-action delete зовёт `RemoveModuleUseCase::execute($name, RemoveStrategy::Backup)` — **только** Backup, Permanent в UI не выводится. Результат `RemoveModuleResult` → тост success/failure. Контрол delete превентивно блокируется через `ModuleDependentsResolver` (любые зависимые → tooltip), зеркаля `assertCanRemove`. Из `buttons()` index убрать дефолтные `delete`/`massDelete` CrudResource (они ведут в no-op стабы), оставить только этот explicit Backup-delete. Файлы: `ModuleIndexPage.php` (`buttons()`) + метод страницы. Тесты: feature (delete → `RemoveModuleUseCase` с `Backup`; модуль с зависимыми заблокирован). Логи: нет.
  - **Task 2 коррекция:** delete-row-action — `ActionButton::make(...)` с async-методом страницы `#[\MoonShine\Support\Attributes\AsyncMethod] public function removeModule(MoonShineRequest $request)`; имя модуля `$request->getItemID()`. Так же, как toggle, метод нельзя вешать без `#[AsyncMethod]`. Дефолтные delete/massDelete убираются переопределением `buttons()` (см. `IndexPage::buttons()` → выкинуть `getDeleteButton/getMassDeleteButton`).
  - ✅ delete идёт строго `Backup`; Permanent недоступен в UI; зависимые → блок; success/failure-тост.
  - ❌ в UI доступен Permanent; остались дефолтные стаб-кнопки delete; нет блокировки при зависимых.

### Phase 3 — FormPage (feature flags)

- [x] **Task 10: `FeatureFieldFactory`** (depends on 2) — выделить маппинг полей отдельным классом (правило Architecture Splitting): `final readonly FeatureFieldFactory` в `src/MoonShine/Support/` маппит `FeatureDefinition` → поле: `bool→Switcher::make(label,key)`; `int→Number::make(label,key)->min()->max()` (только когда заданы); `enum→Select::make(label,key)->options([value=>label])` из `definition.options`; `string→Text::make(label,key)`. Label из `FeatureDefinition->label` (fallback — humanized key) через translator; column = ключ фичи (dot-path в `ModuleAdminDto`). Группировка полей по `FeatureDefinition->group` (`settings.schema.*.group`). Только `::make`-фабрики. Файлы: `src/MoonShine/Support/FeatureFieldFactory.php`. Тесты (Test Stub): unit на каждый `FeatureType` → ожидаемый класс поля + min/max/options, на группировку и на no-default edge-кейс. Логи: нет.
  - ✅ каждый тип фичи → корректное поле с применёнными min/max/options; группировка по `*.group`; покрыт unit-тестом.
  - ❌ маппинг зашит прямо в страницу (не отдельный класс); пропущены min/max/options; глобальные хелперы.

- [x] **Task 11: `ModuleFormPage`** (depends on 10, 5) — `final class …\Pages\ModuleFormPage extends Crud\Pages\FormPage`. `formFields()` собирается через `FeatureFieldFactory` из `settings.schema` модуля, группировка по `*.group`. `rules(DataWrapperContract $item): array` валидирует settings по `FeatureDefinition` (int min/max, enum options, типы) — нативный `Store/UpdateFormRequest`-пайплайн. Персист: `ModulesResource::save()` превращает поля в `FeatureValues` (`FeatureValues::fromArray(...)` / инстанс `with(...)` — **никакого сырого array** на границе) и зовёт `ModuleStateRepository::writeValues($module, $values)`. Defaults остаются в schema (пишутся только явные override). Файлы: `src/MoonShine/Pages/ModuleFormPage.php` (+ save в ресурсе). Тесты: feature (submit персистит через `writeValues` объектом `FeatureValues`; валидация режет out-of-range int / плохой enum; explicit `null` vs missing-key — правило Provenance/JSON Contract). Логи: нет.
  - ✅ форма по schema с группами; валидация на странице; запись строго через `writeValues(FeatureValues)`; defaults не дублируются в values.
  - ❌ сырой `array` в `writeValues`; валидация в контроллере/ресурсе вместо `rules()`; defaults записаны как explicit values.

### Phase 4 — DetailPage (debug only)

- [x] **Task 12: `ModuleDetailPage`** (depends on 5, 8) — `final class …\Pages\ModuleDetailPage extends Crud\Pages\DetailPage`. `detailFields()` = read-only preview **только** debug-инфы (без чтения логов): пути, namespace, version, dependencies (name⇒constraint) + вычисленные dependents через `ModuleDependentsResolver`, deterministic load order/position (индекс в `ModuleRegistryInterface::all()`), provenance (`ModuleOrigin` kind/installed_version/checksum из секции source `state.json`), текущие feature values. `findItem()` читает свежее из `ModuleStateRepository` на каждый рендер (`modules:optimize` не кэширует `settings.values` → detail всегда показывает live-values, инвалидация не нужна). Обернуть в `Fragment::make([...])->name('crud-detail')`. Файлы: `src/MoonShine/Pages/ModuleDetailPage.php`. Тесты: feature (detail рендерит debug-набор, стабильную load-position и отражает свежие values после `writeValues`). Логи: нет (страница показывает инфо, логи не читает).
  - ✅ показаны пути/namespace/version/deps/dependents/load-order/provenance/values; load-position совпадает с registry order; read-only; свежие values без сброса кэша.
  - ❌ читает/показывает логи; кэшит values; editable-поля на detail.

### Phase 5 — Регистрация, конфиг, меню, arch

- [x] **Task 13: регистрация + `modules.moonshine` + меню** (depends on 6, 11, 12) — добавить секцию `moonshine` в `config/modules.php`: `enabled` (default `true`), `menu` (default `true`) — Config-Driven Convention (гейт через конфиг, не параллельные сканы). В `ModuleLoaderServiceProvider::bootMoonShineIntegration()`: existing per-module autoload bridge остаётся под `interface_exists(CoreContract)` и всегда вызывает `MoonShineModuleAutoloader` при наличии core; регистрация admin-resource — отдельная ветка внутри того же callback: только если полный MoonShine CRUD/UI stack доступен (явные `class_exists(...)` checks для vendor-классов из `moonshine/crud` и `moonshine/ui`) И `modules.moonshine.enabled=true` (через инжектированный `Repository`, **не** `config()`), зарегистрировать `$core->resources([ModulesResource::class])->pages([Index,Form,Detail])`. Проверки vendor-классов выполняются до обращения к `ModulesResource`, чтобы host с `moonshine/core`+`contracts` без CRUD/UI не получал fatal autoload error. Меню — вариант AUTO: `#[CanSee('menuVisible')]` на `ModulesResource`, метод читает `modules.moonshine.menu` через `Repository`; опора на host `autoloadMenu()`. `flushState()` на `RequestHandled` → `canSee` пере-вычисляется на запрос. Файлы: `config/modules.php`, `src/Providers/ModuleLoaderServiceProvider.php`, `ModulesResource.php`. Тесты: feature (autoload bridge работает с CoreContract-only; ресурс регистрируется только при full CRUD stack + `enabled=true`; `enabled=false` не отключает per-module autoload; `canSee` отражает `menu`-флаг; ядро грузится при отсутствии MoonShine). Логи: нет.
  - **Task 2 коррекция:** атрибут меню — `#[\MoonShine\MenuManager\Attributes\CanSee('menuVisible')]` на `ModulesResource`; метод `public function menuVisible(): bool` (без аргументов) читает `modules.moonshine.menu` через инжектированный `Repository`. Full-CRUD-stack гейт — `class_exists()` на vendor-классах из umbrella (напр. `MoonShine\Crud\Resources\CrudResource`, `MoonShine\UI\Fields\Switcher`). uri-key ресурса `modules-resource`, если не задать `->alias('modules')`.
  - ✅ resource/pages регистрируются под full-stack+config флагом; existing `autoload()`-хук цел и не зависит от admin flag; меню под `modules.moonshine.menu`; конфиг читается через DI-`Repository`; без MoonShine/full CRUD — boot чистый.
  - ❌ `config()`-хелпер для флага; регистрация resource/pages при CoreContract-only; `enabled=false` ломает per-module autoload; сломан существующий MoonShine-autoload; меню всегда видно независимо от флага.

- [x] **Task 14: arch-тесты для `src/MoonShine`** (depends on 13) — новая структурная конвенция (UI-namespace) → закрепить Pest-arch (Test Stub). В `tests/Architecture/ArchitectureTest.php`: (a) сфокусированное утверждение, что классы `…\MoonShine` — `final` (+`final readonly` для `Data/`/`Support`-VO); (b) проверить, что существующие баны (`Facades\*`, глобальные хелперы, `Illuminate\Http\Request`, `static`-свойства, FS-I/O) проходят с новыми классами (они применяются ко всему `src/`); (c) сохранить/проверить лок, что слои `Application` и `Loaders` **не** зависят от `MoonShine` (направление — только `MoonShine → Application`). Файлы: `tests/Architecture/ArchitectureTest.php`. Логи: нет.
  - ✅ `composer test:arch` зелёный; новые правила падают при `non-final` UI-классе / фасаде-хелпере-`Request` в `src/MoonShine` / зависимости `Application→MoonShine`.
  - ❌ конвенция не покрыта arch-тестом; правило ложно-зелёное (опечатка FQCN); сломан существующий зелёный arch.

### Phase 6 — Документация и quality gate

- [x] **Task 15: `docs/moonshine.md` + навигация** (depends on 13, 14) — обязательный docs-чекпоинт через `/aif-docs` (закрывает roadmap-пункт). Новый `docs/moonshine.md` в house-style (nav-крошка, H1-фраза, `## See Also`): что делает admin-UI (Index табы по Kind / таблицы по group, async enable/disable, Backup-delete, форма feature flags, debug detail), требуемые host/runtime пакеты для admin-UI (`moonshine/laravel`+`ui`+`crud` ^4) и что MoonShine остаётся ОПЦИОНАЛЬНЫМ (`suggest`; CoreContract-only autoload bridge отдельно от full admin UI), конфиг-флаги (`modules.moonshine.enabled/menu`), поведение меню (`#[CanSee]`+`autoloadMenu`; требование host `autoloadMenu()`), guard UX, заметка про свежие values/optimize. Обновить существующие места, где сейчас указаны только `moonshine/core`+`contracts`, чтобы не оставить устаревшую инструкцию установки: `docs/getting-started.md` и `docs/configuration.md`. Документировать только ФАКТИЧЕСКИЙ runtime; отложенное (zip-upload install/update в UI, i18n schema-меток) пометить roadmap. Строки навигации в `README.md` и `AGENTS.md` (только ссылка, без дублирования контента). Язык ru, код/коммиты en. Без упоминаний BC/миграций. Файлы: `docs/moonshine.md`, `docs/getting-started.md`, `docs/configuration.md`, `README.md`, `AGENTS.md`. Логи: нет.
  - ✅ страница описывает реальный runtime; existing docs не содержат устаревшую CoreContract-only инструкцию для admin UI; отложенное помечено roadmap; nav-строки в обоих файлах рабочие; нет дублирования тела.
  - ❌ документирует zip-upload как текущий runtime; оставляет `moonshine/core moonshine/contracts` как полный admin-UI install; англ./не house-style; битая ссылка; упоминания BC.

- [x] **Task 16: финальный quality gate** (depends on 14, 15) — правило Post-Fix Quality Step, строго по порядку: `composer rector` → `composer format` → `composer phpstan` (только `src/`, level 8 + Larastan, 100% type coverage, без новых `ignoreErrors`/baseline) → `composer test` (arch+unit+feature). `:dry` для применяемого плана НЕ использовать. Починить любые последствия. Файлы: по факту правок rector/format. Логи: нет.
  - ✅ rector/format идемпотентны (повторный `:dry` чистый); phpstan 0 ошибок без baseline; все 3 suite зелёные; milestone готов к `/aif-verify --strict`.
  - ❌ любой гейт красный; rector/format всё ещё предлагают правки; новые `ignoreErrors`/baseline; неверный порядок; подмена на `:dry`.

## Проверка на нарушение правил проекта
Сверено против `.ai-factory/rules/*` и `skill-context/aif-plan/SKILL.md`:
- **No facades/helpers/`Request`/`static`/FS-I/O в `src/`** (code-quality, runtime, arch): весь UI на конструкторном DI; `config()`→инжектированный `Repository`; `__()`→DI-`Translator`; `::make`-фабрики разрешены. Task 14 фиксирует это arch-тестом. ✔
- **`final`/`final readonly` + `strict_types` + constructor promotion**: ресурс/страницы — `final`; DTO/Support-VO — `final readonly`; новые файлы со `strict_types`. ✔
- **`module.json` immutable; запись только через repository; на границе только `FeatureValues`** (manifest, lifecycle): Task 5/11 пишут через `ModuleStateRepository::writeValues(FeatureValues)`, без сырого array и без изменения `enabled`; toggle/delete — через UseCase. ✔
- **Package Core Logging Scope** (skill-context): verbose-логи в `src/` не планируются — фидбэк через исключения/тосты/тесты. ✔
- **Contract-First** (skill-context): Task 2 (снятие рисков) + Task 3 (DTO/caster-контракт) до построения страниц. ✔
- **Architecture Splitting** (skill-context): поля → `FeatureFieldFactory`, зависимые → `ModuleDependentsResolver`, label'ы → `ModuleKindLabelResolver` — роли разделены сразу, не отложены. ✔
- **Test Stub / arch для новых конвенций** (skill-context, base): новые VO/сервисы (`ModuleAdminDto`, `FeatureFieldFactory`, `ModuleDependentsResolver`, `ModuleKindLabelResolver`) — companion unit-тесты (construct + round-trip/маппинг + error-path); новый UI-namespace — arch-тест (Task 14). ✔
- **Provenance/JSON Contract** (skill-context): Task 11 — explicit `null` vs missing-key, запись через `FeatureValues`, defaults не как values. ✔
- **Config-Driven Convention** (skill-context): UI/меню под `modules.moonshine.*`, без параллельных сканов. ✔
- **Post-Fix Quality Step** (skill-context): Task 16 — `composer rector` → `format` → phpstan → test в точном порядке. ✔
- **Документируй фактический runtime; roadmap помечай** (base): Task 15 помечает zip-upload/i18n как roadmap; без BC/миграций. ✔
- **PHPStan только `src/`, level 8, 100% type coverage** (feedback, code-quality): Task 16; тесты не анализируются phpstan, но гоняются `composer test`. ✔
- **PR в Conventional Commits (англ.)**: commit-plan на английском в CC-формате. ✔
