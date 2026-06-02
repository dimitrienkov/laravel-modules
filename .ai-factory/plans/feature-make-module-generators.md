# План: Генераторы `make:* --module`

**Branch:** `feature/make-module-generators`
**Создан:** 2026-06-02
**Тип:** feature (ROADMAP Фаза 2)

Module-aware режим для native Laravel `make:*` через опцию `--module` + архитектурные
генераторы пакета (`make:use-case/action/query/dto/vo`) в том же стиле. Источник
требований — `.ai-factory/RESEARCH.md` (Active Summary + сессия 2026-06-02).

## Settings

| Параметр | Значение |
|----------|----------|
| Testing | **Да** — генераторы покрываются arch + feature тестами (make:test-генератор при этом исключён) |
| Logging | **Standard** — вывод команд через `$this->components` (info/error); runtime diagnostic logging (`ModuleDiagnosticsInterface`) к генераторам НЕ подключаем |
| Docs | **Да** — обязательный docs-чекпойнт через `/aif-docs` в конце (`docs/cli.md` и др.) |

## Roadmap Linkage

- **Milestone:** «Генераторы `make:* --module`» (ROADMAP Фаза 2, строка 32).
- **Rationale:** План реализует ровно этот milestone целиком (native module-aware
  генераторы + арх-генераторы пакета); по завершении milestone отмечается `[x]`.

## Зафиксированные решения (с пользователем)

1. **Охват native = «перечень с явным домом» (22):** model, controller, migration,
   factory, seeder, policy, request, resource, event, listener, job, mail,
   notification, observer, middleware, command, rule, cast, channel, component,
   provider, exception. **Generic-команды** (`make:class/interface/trait/enum/scope/
   view/config/job-middleware`) остаются нативными host-командами — у них нет
   конвенционного «дома» в модуле.
2. **`make:migration --module` = трансляция в нативный `--path`** (Variant B), а не
   полный subclass с remap `getPath()` — устойчиво к различиям сигнатур L12/13.
3. **Арх-генераторы `--module` = опциональна (host-fallback):** без `--module` пишут в
   host (`app/Application/UseCases/…`, `app/Domain/VO`), с `--module` — внутрь модуля.
4. **`make:test` исключён** из набора (модули, поставляемые заказчику, обычно не несут
   собственные тесты). В module-aware режиме matching-test опции Laravel
   (`--test`, `--pest`, `--phpunit`) не должны создавать host-тесты: их нужно
   fail-fast отклонять понятной ошибкой или явно не принимать в module-aware subclass'ах.
5. **`make:module` — component-driven скелет:** опция `--with=` + интерактивный
   multiselect-вопрос «что создать?», `--no-interaction` → текущий минимальный скелет.
6. **Общий код = trait** (`ModuleAwareGenerator`), не abstract-база (иначе провал
   arch-теста `all concrete src classes are final`).
7. **Резолв модуля только через `ModuleRegistryInterface`** (контракт), rebind в
   отдельном провайдере.

## Корректировки ресёрча (проверено по коду/установленной версии)

> Установлено: **Laravel 13.11.2**, testbench 11.1.0, pest-plugin-arch 3.1.1.
> Поддержка `^12|^13` сохраняется.

1. **Rebind native-команд идёт по FQCN, не по алиасам `command.*.make`.** В Laravel 12/13
   `ArtisanServiceProvider` биндит make-команды как `singleton(ModelMakeCommand::class, …)`,
   старые строковые алиасы (как в описании InterNACHI) отсутствуют. Shadow = rebind
   singleton'а parent-FQCN → наш subclass. Это **устойчивее** прежней схемы.
2. **Arch strict-types тест `.stub` файлы НЕ сканирует** (фильтр `getExtension() !== 'php'`,
   `ArchitectureTest.php:33`). Значит тест НЕ форсит `declare(strict_types=1)` в стабах —
   но стабы всё равно несут declare, т.к. сгенерированный `.php` обязан его иметь по
   house-style. Вывод ресёрча верен, обоснование — нет.
3. **Современный L13 даёт ~31 make-команду** (не ~23): добавлены generic-генераторы без
   «дома» в модуле. Согласовано: берём только перечень с явным домом (~22).
4. **Стабы лежат в корневом `stubs/`** (фактическая конвенция, публикация настроена тегом
   `modules-stubs`), а не в `src/stubs/` как пишет roadmap — следуем факту.
5. **`getStub()` арх-генераторов НЕ использует `file_exists()`/`resolveStubPath()`** —
   эти FS-токены запрещены arch-тестом в `src/`. Возвращаем абсолютный путь к стабу
   пакета напрямую; published-override стабов арх-генераторов — отложенная задача.

## Уточнения после improve (2026-06-02)

1. **`MakeMail` не является чистым namespace-only генератором.** В Laravel 13 `make:mail`
   при `--markdown`/`--view` пишет Blade-файл через `viewPath()`. В module-aware режиме
   view должен попадать в `Resources/views`, иначе команда создаст класс в модуле, а view
   в host-приложении.
2. **`MakeModel` требует явного override внутренних фабрик.** Laravel 13 вызывает
   `make:factory`, `make:migration`, `make:seeder`, `make:controller`, `make:request`,
   `make:policy` из protected `create*()` методов без знания о `--module`. Эти методы
   должны пробрасывать `--module` и не пробрасывать matching-test опции. Дополнительно
   `buildFactoryReplacements()` должен ссылаться на module factory namespace, иначе
   generated model получит `Database\Factories\...Factory` при правильном файле фабрики в модуле.
3. **`MakeController` тоже создаёт под-артефакты.** При `--model`/`--parent` и интерактивном
   подтверждении Laravel может вызвать `make:model`, а при `--requests` вызывает
   `make:request`. Module-aware subclass обязан пробрасывать `--module` в эти вызовы.
4. **`ScaffoldComponent` — input boundary.** `--with=` и interactive multiselect должны
   парситься в string-backed enum, валидировать неизвестные значения до use case, и передаваться
   через `ScaffoldModuleConfig`, а не строковым массивом.
5. **Скелет не должен менять manifest/state contract.** Component-driven `make:module`
   создаёт только директории/провайдер/manifest/state через уже существующие owners:
   `ModuleSkeletonBuilder`, `ModuleManifestRepositoryInterface`, `ModuleStateRepositoryInterface`.
   `module.json` не получает mutable state или generator metadata.
6. **Rebind проверяется по FQCN.** Установленный Laravel 13.11.2 регистрирует make-команды
   singleton'ами по parent FQCN (`ModelMakeCommand::class`, `FactoryMakeCommand::class`, ...),
   а migration — в `MigrationServiceProvider` как `MigrateMakeCommand::class`. План не должен
   полагаться на старые строковые `command.*.make` алиасы.

## Конвенция размещения (module sub-namespace)

`getDefaultNamespace()` при `--module` возвращает `$module->namespace . '\\' . <subdir>`:

| Команда | sub-namespace | Источник истины |
|---------|---------------|-----------------|
| model | `Domain\Models` | `ModuleLayout::modelNamespace` |
| observer | `Domain\Observers` | `ModuleLayout::observerNamespace` |
| policy | `Domain\Policies` | `ModuleLayout::policyNamespace` |
| listener | `Domain\Listeners` | `ModuleLayout::listenersDir` (EventLoader) |
| event | `Domain\Events` | литерал (loader не требует) |
| controller | `Http\Controllers` | литерал |
| request | `Http\Requests` | литерал |
| resource | `Http\Resources` | литерал |
| middleware | `Http\Middleware` | `ModuleLayout::middlewareNamespace` |
| command | `Console\Commands` | литерал (`commandsDir`) |
| provider | `Providers` | `providersDir` |
| job / mail / notification | `Jobs` / `Mail` / `Notifications` | литерал |
| rule / cast / channel / exception | `Rules` / `Casts` / `Broadcasting` / `Exceptions` | литерал |
| component (класс) | `View\Components` | `ModuleLayout::bladeComponentNamespace` |
| component (view) | `Resources/views/components` | remap `writeView()` в `viewsDir` |
| mail (view/markdown) | `Resources/views/mail` или explicit view path внутри `Resources/views` | remap `writeView()`/`writeMarkdownTemplate()` |
| **factory** | `Database\Factories` | **remap getPath()** (`factoriesDir`) |
| **seeder** | `Database\Seeders` | **remap getPath()** (`seedersDir`, новый) |
| **migration** | — | **`--path` = `migrationsDir`** (Variant B) |
| use-case/action/query/dto | `Application\{UseCases,Actions,Queries,DTOs}` | `ModuleLayout` (новые методы) |
| vo | `Domain\VO` (без суффикса) | `ModuleLayout::domainVoNamespace` (новый) |

## Приёмка

### Успешная приёмка

- `php artisan make:model Post --module=blog` и `--module=Blog` резолвят один canonical module
  name и создают
  `app/Modules/Blog/Domain/Models/Post.php` с namespace
  `App\Modules\Blog\Domain\Models`; без `--module` команда остаётся byte-behavior-compatible
  с native Laravel для host-путей и namespace.
- `php artisan make:model Post --module=blog -mfs` создаёт model, migration, factory и seeder
  только внутри `app/Modules/Blog/...`; migration получает `--path` на `Database/Migrations`,
  factory namespace = `App\Modules\Blog\Database\Factories`, seeder path =
  `Database/Seeders`, а generated model с `HasFactory` ссылается на module factory namespace.
- `make:controller PostController --module=blog --model=Post --requests` создаёт controller и
  form requests внутри `Http/*` модуля; model references указывают на
  `Domain\Models\Post`.
- `make:mail Digest --module=blog --markdown=mail.digest` и
  `make:component Alert --module=blog` не пишут views в host `resources/views`; все view-файлы
  лежат в `Resources/views` модуля.
- `make:use-case/action/query/dto/vo` без `--module` пишут в host `app/Application/*` или
  `app/Domain/VO`, а с `--module=blog` — в соответствующий слой модуля; классы содержат
  `declare(strict_types=1)`, `final readonly`, корректный namespace, а `make:vo Money`
  не добавляет суффикс `Vo`.
- Неизвестный `--module` завершает команду с failure-кодом и понятной ошибкой, не создавая
  partial files в host или модуле.
- В module-aware режиме команды с matching-test опциями (`--test`, `--pest`, `--phpunit`)
  не создают host tests; поведение зафиксировано feature-тестом.
- `make:module blog --with=routes,views,domain,application,database` создаёт только выбранные
  component-директории плюс обязательные root/provider/manifest/state; `--no-interaction`
  сохраняет текущий минимальный скелет.
- `make:module` interactive multiselect работает в feature-тесте через prompt mocking,
  а invalid `--with` fail-fast завершается до создания директории.
- Все новые concrete-классы в `src/` — `final`, новые DTO/use cases/support при необходимости
  остаются `readonly`, application enums — string-backed, в `src/` нет facades, global helpers,
  mutable static и прямого FS I/O вне whitelisted infrastructure.
- Документация (`docs/cli.md`, `docs/module-structure.md`, README/ROADMAP/DESCRIPTION при
  необходимости) отделяет реализованные генераторы от будущих roadmap-задач.
- Quality gate зелёный отдельными командами: `composer rector`, `composer format`,
  `composer phpstan`, `composer test`.

### Неуспешная приёмка / стоп-критерии

- Любая module-aware команда создаёт файл вне целевого модуля, кроме явно host-fallback режима
  архитектурных генераторов без `--module`.
- Rebind ломает native поведение без `--module`, меняет имена команд, help/signature native
  команд сверх добавления `--module`, или не подменяет FQCN singleton родительской команды.
- `make:model -mfs --module` создаёт factory/seeder/migration/controller/request/policy в host
  из-за непроброшенного `--module`.
- `make:mail --markdown/--view --module` или `make:component --module` пишет Blade view в host
  `resources/views`.
- Matching-test опции в module-aware режиме приводят к созданию host `tests/*`.
- Unknown module, invalid `--with`, duplicate module или rollback path оставляют partial files
  без явного тестового покрытия.
- Generated model при `--factory/--all` ссылается на host `Database\Factories` вместо
  module factory namespace.
- `module.json` получает mutable state, generator-specific metadata или любые новые top-level keys.
- В `src/` появляются Laravel facades, global helper calls (`app()`, `config()`, `resolve()`, ...),
  mutable static properties, `file_exists()`/`file_put_contents()` и другие запрещённые FS-токены
  вне whitelisted infrastructure.
- Новые `.php`-стабы или generated class stubs не содержат `declare(strict_types=1)`.
- Документация заявляет generic generators, `make:test --module`, MoonShine generators или admin UI
  как реализованные в этом milestone.
- Любой из quality gates (`rector`, `format`, `phpstan`, `test`) падает.

## Arch-инварианты (чек-лист, `tests/Architecture/ArchitectureTest.php`)

- Нет `Illuminate\Support\Facades\*` в `src/` (включая Console). `Illuminate\Console\
  Application::starting()` — класс, не фасад → разрешён (`use … as Artisan`).
- Нет global helpers (`app(`,`config(`,`resolve(`,`logger(`,…) → только `$this->laravel->make()` / DI.
- Все concrete-классы в `src/` — `final`; общий код = **trait** (Pest `classes()` трейты игнорирует).
- Нет mutable static → кеш `module()` в instance-property.
- Нет прямого FS I/O (`mkdir`,`file_put_contents`,`file_exists`,…) вне whitelisted-классов →
  наши команды расширяют `GeneratorCommand` (FS внутри vendor), наш код этих токенов не содержит.
- `console commands do not depend on concrete persistence or registry` → резолв через
  `ModuleRegistryInterface`, не concrete.
- `artisan commands extend Laravel Command` → транзитивно через `*MakeCommand → GeneratorCommand → Command`.
- `application enums are string backed` → `ScaffoldComponent` — string-backed enum.

## Tasks

### Фаза 1 — Фундамент (trait, конвенция, layout, enum)
- **[#1]** ✅ Расширить `ModuleLayout`: namespace/dir арх-слоя (`Application/*`, `Domain/VO`),
  `seedersDir`, `seederNamespace`, mail/component view helpers (`viewsDir` уже есть, добавить
  целевые relative/path helpers при необходимости) + unit-тест всех новых методов.
- **[#2]** ✅ `ScaffoldComponent` string-backed enum (`Application/Enums`) + парсер `--with`
  значений + расширение `ScaffoldModuleConfig` readonly-полем компонентов + unit-тест valid,
  duplicate, empty и unknown values.
- **[#3]** ✅ Трейт `ModuleAwareGenerator` (`Console/Concerns`): `--module`, `module()` через
  `ModuleRegistryInterface` (instance-cache), `getDefaultNamespace()` с host-fallback,
  `qualifyModel()` для `Domain\Models`, hooks `moduleSubNamespace()`/`moduleNamespace()`/
  `modulePath()`, helper для аргументов внутренних `$this->call(...)` с `--module`, и
  guard matching-test опций в module mode.
  Покрыть feature-level через команды; unit-тестировать только чистые helpers, если это не
  требует хрупкого partial mock. _(blocked by: —)_

### Фаза 2 — Native module-aware генераторы + rebind
- **[#4]** ✅ Namespace-only subclass'ы (`Console/Commands/Make/`) для model, request, resource,
  event, listener, job, notification, observer, middleware, command, rule, cast, channel,
  provider, exception, policy и controller class-path. Каждый class `final`, использует trait,
  возвращает точный module sub-namespace, и сохраняет native host fallback. _(blocked by #3)_
- **[#5]** ✅ Под-генераторы с внутренними `$this->call(...)`: override `MakeModel::createFactory/
  createMigration/createSeeder/createController/createFormRequests/createPolicy/
  buildFactoryReplacements` и `MakeController::generateFormRequests/build*Replacements` там,
  где Laravel вызывает `make:model`/`make:request`. Пробрасывать `--module`, корректно
  qualify model в `Domain\Models`, не пробрасывать `--test`/`--pest`/`--phpunit`.
  _(blocked by #3, #4)_
- **[#6]** ✅ Path-remap: `MakeMigration` (`--path` + `--realpath` на `ModuleLayout::migrationsDir`),
  `MakeFactory` (`getPath`, `factoryNamespace`, `qualifyModel`), `MakeSeeder`
  (`getPath`, module seeder namespace). _(blocked by #3, #1)_
- **[#7]** ✅ View-remap: `MakeComponent` (класс + Blade view/anonymous view) и `MakeMail`
  (`--markdown`/`--view`) пишут views в `ModuleLayout::viewsDir`, не используют прямые FS-токены
  в нашем коде, сохраняют `--inline`/no-view host behavior. _(blocked by #3, #1)_
- **[#8]** ✅ `ModuleGeneratorCommandsServiceProvider` — rebind parent FQCN singleton'ов
  (`Illuminate\Foundation\Console\*MakeCommand`, `Illuminate\Routing\Console\*`,
  `Illuminate\Database\Console\*`) + регистрация из главного провайдера только в console context.
  Rebind не полагается на строковые `command.*.make` алиасы; migration wiring повторяет
  `MigrationServiceProvider` constructor deps. _(blocked by #4, #5, #6, #7)_

### Фаза 3 — Архитектурные генераторы пакета
- **[#9]** ✅ House-style стабы `use-case/action/query/dto/vo` в `stubs/` с
  `declare(strict_types=1)`, `final readonly`, корректными Laravel tokens и без published-override
  lookup в `src/`. _(blocked by: —)_
- **[#10]** ✅ Команды `make:use-case/action/query/dto/vo` (host-fallback, module mode,
  `getStub()` без FS, suffix rules: UseCase/Action/Query/Dto, VO без суффикса) + регистрация
  в `commands()`. _(blocked by #1, #3, #9)_

### Фаза 4 — Интерактивный `make:module`
- **[#11]** ✅ Component-driven скелет: `ScaffoldModuleUseCase` + `ModuleSkeletonBuilder` принимают
  список `ScaffoldComponent`, мапят component→dirs, оставляют обязательные root/provider/manifest/
  state, и не пишут mutable state в `module.json`. _(blocked by #1, #2)_
- **[#12]** ✅ `make:module`: опция `--with=` + интерактивный multiselect через Laravel Prompts,
  `--no-interaction` дефолт = текущий минимальный скелет, invalid `--with` fail-fast до use case.
  _(blocked by #2, #11)_

### Фаза 5 — Тесты
- **[#13]** ✅ Arch-тесты: Make-команды final, trait не нарушает boundaries, commands не зависят
  от concrete registry/persistence, no facades/helpers/FS tokens, application enum string-backed.
  _(blocked by #8, #10, #12)_
- **[#14]** ✅ Feature-тесты native генераторов: по одному smoke на каждый из 22 command homes,
  расширенные сценарии для model `-mfs`, controller `--requests`, factory/model references,
  migration path, seeder path, component/mail views, unknown module, matching-test rejection,
  и rebind-transparent behavior без `--module`. _(blocked by #8)_
- **[#15]** ✅ Feature-тесты арх-генераторов и `make:module`: module + host-fallback,
  content `final readonly`/strict, VO без суффикса, `--with` valid/invalid/no-interaction,
  interactive multiselect, rollback/no partial files на ошибках. _(blocked by #10, #12)_

### Фаза 6 — Документация и качество
- **[#16]** ✅ Docs-чекпойнт (`/aif-docs`: `docs/cli.md`, `docs/module-structure.md`) + README/
  ROADMAP/DESCRIPTION sync. Документы явно перечисляют реализованные 22 native homes,
  архитектурные генераторы и исключения (`make:test`, generic make-команды, MoonShine).
  _(blocked by #8, #10, #12)_
- **[#17]** ✅ Quality gate: `composer rector` → `composer format` → `composer phpstan` →
  `composer test` (всё зелёное), плюс при наличии времени отдельный smoke на Laravel 12
  через Testbench 10 или зафиксированный residual risk, если локально доступен только L13.
  _(blocked by #13, #14, #15, #16)_

## Commit Plan

| Checkpoint | Tasks | Сообщение (Conventional Commits, EN) |
|-----------|-------|--------------------------------------|
| 1 | #1, #2, #3 | `feat(generators): add module-aware generator trait, layout paths and scaffold component enum` |
| 2 | #4, #5, #6, #7, #8 | `feat(generators): module-aware native make:* commands via --module with rebind provider` |
| 3 | #9, #10 | `feat(generators): architectural make:use-case/action/query/dto/vo generators` |
| 4 | #11, #12 | `feat(make-module): component-driven scaffolding with --with and interactive prompt` |
| 5 | #13, #14, #15 | `test(generators): arch and feature coverage for module-aware generators` |
| 6 | #16, #17 | `docs(generators): document make:* --module and architectural generators` |

## Открытые риски / на что смотреть при реализации

- **Проброс `--module` в под-генераторы** (`make:model -mfs`) — переопределение `create*`
  методов `ModelMakeCommand` версионно-чувствительно; покрыть feature-тестом для L13.
- **`MakeController --requests/--model/--parent`** — Laravel может создавать model/request
  под-артефакты внутри replacement flow; без override они уйдут в host.
- **`FactoryMakeCommand`/`SeederMakeCommand` `getPath()`** — проверить, как именно они
  вычисляют путь (factories пишут в `database/factories`), и аккуратно переопределить
  на модульный путь без FS-токенов в нашем коде.
- **Тайминг rebind** — биндить в `app->booted` + `Console\Application::starting`, чтобы
  победить независимо от порядка провайдеров; feature-тест на транспарентность native.
- **`ComponentMakeCommand::writeView()`** — remap blade-view-пути; убедиться, что запись
  идёт наследуемым `$this->files` (vendor), а не запрещёнными токенами в нашем `src/`.
- **`MailMakeCommand::writeView()/writeMarkdownTemplate()`** — такой же view remap, иначе
  класс окажется в модуле, а шаблон — в host `resources/views`.
- **Matching-test options** — многие native make-команды используют `CreatesMatchingTest`;
  module-aware режим не должен создавать host tests из-за `--test`/`--pest`/`--phpunit`.
- **PHPStan level 8** на тонких subclass'ах (типы опций/inputs) — частый источник шума.
