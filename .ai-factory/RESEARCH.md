# Research

Updated: 2026-06-02 14:26
Status: active

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
**Topic:** Генераторы `make:* --module` (ROADMAP Фаза 2, milestone `[ ]` «Генераторы `make:* --module`»). Предыдущий milestone (диагностическое логирование) завершён 2026-06-01 — его дизайн сохранён в Sessions ниже.

**Goal:** Два семейства генераторов в одном milestone:
1. **Native Laravel-генераторы → module-aware** через опцию `--module` (`make:model Post --module=Blog -mfs`, `make:controller … --module=Blog`, `make:migration … --module=Blog` и т.д.) — ПОЛНЫЙ набор Laravel (~23 команды).
2. **Архитектурные генераторы пакета** в том же стиле: `make:use-case`, `make:action`, `make:query`, `make:dto`, **`make:vo`** (`make:vo` добавлен пользователем сверх roadmap).
MoonShine-генераторы НЕ дублируем (артефакты создаёт сам MoonShine).

**Ключевое преимущество конвенции проекта:** модули лежат ВНУТРИ `app_path()`, namespace выводится из `App\` (`App\Modules\Blog\…`) → модуль уже автозагружаем корневым PSR-4 хоста, БЕЗ отдельного composer-autoload на модуль. Laravel `GeneratorCommand::getPath()` = `app_path()/ + (FQCN − rootNamespace) + .php`. Для namespace-генераторов (model/controller/policy/…) достаточно переопределить ТОЛЬКО `getDefaultNamespace()` → вернуть `App\Modules\Blog\<Subdir>`, и файл сам ляжет в `app/Modules/Blog/<Subdir>/Name.php`. `getPath()` Laravel'а работает как есть. Это главное отличие от InterNACHI/modular, где модуль вне `app/` (`app-modules/blog/src`, namespace `Modules\Blog`) и нужен per-module composer path-repo + полный remap `getPath()`.

**Прецеденты (ресёрч, проверено через GitHub API):**
- **InterNACHI/modular** — ТОЧНЫЙ референс интерфейса `make:* --module`. Механика (воспроизводимая):
  - На каждую native-команду — тонкий subclass (`MakeModel extends ModelMakeCommand`, ~23 шт. в `src/Console/Commands/Make/`), переопределяет только `getDefaultNamespace()` (swap root → module namespace).
  - Общий трейт `ModularizeGeneratorCommand` (отд. пакет `internachi/modularize`): добавляет `--module`, метод `module()`, переопределяет `getDefaultNamespace`/`qualifyClass`/`qualifyModel`/`getPath` и пробрасывает `--module` в под-команды через `call()`.
  - **Shadowing native-команд** = rebind контейнерных алиасов в `ModularizedCommandsServiceProvider::register()`: `$this->app->booted(fn() => Artisan::starting(fn($artisan) => …))` затем `$this->app->singleton('command.model.make', MakeModel::class)` + `singleton(get_parent_class($class), $class)`. Делается в `booted+starting`, чтобы победить независимо от порядка провайдеров. Имя команды (`make:model`) наследуется → интерфейс не меняется. Migration особый: `singleton(MigrateMakeCommand::class, fn($app)=>new MakeMigration($app['migration.creator'], $app['composer']))`.
  - **Без `--module` поведение 100% идентично нативному** (getDefaultNamespace возвращает обычный namespace) → shadow прозрачен и безопасен.
- **nwidart/laravel-modules** — отдельные имена `module:make-model`/`module:make-controller`, глобальные хелперы+фасады. НЕ совпадает с нашим интерфейсом — отвергнут.

**Decisions (зафиксированы с пользователем):**
- **Native-интерфейс = прозрачный override** (как в roadmap): rebind алиасов `command.*.make`, имя команды то же, `make:model Post --module=Blog`. (Альтернативы opt-in-через-конфиг и отдельные `modules:make-*` отклонены.)
- **Охват native = ПОЛНЫЙ набор Laravel (~23):** model, controller, migration, factory, seeder, policy, request, resource, event, listener, job, mail, notification, observer, middleware, command, rule, cast, channel, component, provider, exception, test.
- **Архитектурные генераторы (5):** `make:use-case`, `make:action`, `make:query`, `make:dto`, `make:vo`. Раскладка внутри модуля и суффиксы:
  - `Application/UseCases/<Name>UseCase.php` (`final readonly`)
  - `Application/Actions/<Name>Action.php` (`final readonly`)
  - `Application/Queries/<Name>Query.php` (`final readonly`)
  - `Application/DTOs/<Name>Dto.php` (`final readonly`)
  - `Domain/VO/<Name>.php` — **БЕЗ суффикса** (`final readonly`), совпадает с конвенцией ядра (`<Layer>/VO`, VO без суффикса: `Version`, `Checksum`).
- **Объём = один milestone** (оба семейства: общий трейт + ~23 native-subclass + 5 арх-команд + rebind-провайдер + стабы + тесты).
- **Общий код = TRAIT, не базовый класс.** Арх-тест `all concrete src classes are final` запрещает concrete non-final класс; `abstract` база провалит `toBeFinal()`. Pest `classes()` НЕ трогает traits → trait безопасен. (Roadmap говорит «единый базовый класс» — реализуем как trait, цель DRY достигается.)
- **Резолв модуля только через `ModuleRegistryInterface`** (контракт; концрет `ModuleRegistry`/repos запрещены арх-тестом `console commands do not depend on concrete persistence/registry`). `--module=Blog` → нормализовать в snake → `registry->find()` → `$module->namespace` + `$module->path`. Если модуль не найден — явная ошибка. ApplicationNamespaceResolver используется ТРАНЗИТИВНО (namespace уже посчитан им при scan и лежит в `Module->namespace`) — повторно звать не нужно.

**Constraints (forced арх-тестами — `tests/Architecture/ArchitectureTest.php`):**
- `src does not use Laravel facades` — глобально, БЕЗ исключения для `Console/`. Запланированный в Фазе 1 carve-out не реализован. → в командах нельзя `Illuminate\Support\Facades\*`. `Illuminate\Console\Application::starting()` — это класс, не фасад (`use … as Artisan` как у InterNACHI) → разрешено.
- `src does not use global helpers` (`app(`,`config(`,`resolve(`,`value(`,…) — только `$this->laravel->make(...)` / DI.
- `all concrete src classes are final` → каждая конкретная команда `final`; общий код — trait.
- `artisan commands extend Laravel Command` → OK транзитивно (`*MakeCommand → GeneratorCommand → Command`).
- direct filesystem I/O запрещён вне whitelisted инфра-классов → расширяем Laravel `GeneratorCommand` (запись внутри vendor); наш код НЕ содержит `file_put_contents`/`is_dir`/… токенов.
- `php files under src tests and stubs declare strict types` сканирует `stubs/` → КАЖДЫЙ новый стаб обязан содержать `declare(strict_types=1)`.
- mutable static запрещены → кеш `module()` хранить в instance-property (команда per-invocation), не static.

**Особые случаи путей (нужен remap, `getDefaultNamespace()` недостаточно):**
- `make:migration` — пишет в `database/migrations/` (timestamp, без namespace), особый конструктор (`migration.creator`+`composer`). Вариант A: subclass + remap пути в `Module/Database/Migrations`. Вариант B (проще): транслировать `--module` → нативный `--path=app/Modules/Blog/Database/Migrations` (опция `--path` у make:migration существует). `MigrationLoader` уже регистрирует `Database/Migrations`.
- `make:factory` → `database/factories/`, namespace `Database\Factories\` → remap в `Module/Database/Factories`.
- `make:seeder` → `database/seeders/` → remap (модуль сейчас НЕ имеет `Database/Seeders` в скелете — решить: добавить или класть в Factories-уровень).
- `make:test` → `tests/` → модуль обычно без `tests/`; решить — `Module/tests` или skip из набора.
- `make:component` (Blade) → ДВА файла: класс в `View/Components/` (есть в layout) + view в `resources/views/components/` → remap view в `Module/Resources/views/components`.

**Open questions (решить в плане):**
1. **`--module` обязателен ли для арх-генераторов?** Дефолт-предложение: НЕ обязателен — без `--module` пишем в host `app/Application/UseCases/…` (как native make:* без --module). С `--module` — в модуль. Подтвердить в плане.
2. **Скелет `make:module`:** добавить ли `Application/{UseCases,Actions,Queries,DTOs}` + `Domain/VO` в `ModuleSkeletonBuilder::createDirectories()`. GeneratorCommand сам создаёт каталоги (`makeDirectory`), так что не блокер, но желательно для полноты скелета. + добавить `Database/Seeders`, `tests/` если их генераторы войдут в набор.
3. **`ModuleLayout`:** добавить методы-источники истины для новых путей/namespace (`useCasesDir/Namespace`, `actionsDir/…`, `queriesDir/…`, `dtosDir/…`, `domainVoDir/Namespace`). Команда может строить namespace и напрямую (`$module->namespace.'\\Application\\UseCases'`), но layout нужен для скелета и тестов.
4. **Стабы:** новые в корневом `stubs/` (существующая конвенция; roadmap говорит `src/stubs/`, но фактически `stubs/` в корне — следовать факту). Токены — в стиле Laravel `GeneratorCommand` (`{{ namespace }}`, `{{ class }}`, `{{ rootNamespace }}`) т.к. арх-генераторы расширяют `GeneratorCommand`. Стабы отражают house-style: `declare(strict_types=1)` + `final readonly`. Native-команды используют стабы Laravel (кастомизация заказчиком через `stub:publish`) — свои не плодим.
5. **Laravel 12 vs 13:** конструкторы некоторых `*MakeCommand` (особенно migration) могут различаться между линейками. Rebind migration зависит от сигнатуры установленной версии. Сверить обе линейки в плане (риск brittleness).
6. **Где регистрировать rebind:** отдельный провайдер (`Providers/ModuleGeneratorCommandsProvider`, как у InterNACHI) или внутри `ModuleLoaderServiceProvider`. Делать только в `runningInConsole()` контексте.

**Success signals:**
- `composer test` (arch+unit+feature) + `composer phpstan` + `composer format:dry` зелёные.
- `make:model Post --module=blog` → `app/Modules/Blog/Domain/Models/Post.php` с namespace `App\Modules\Blog\Domain\Models`; БЕЗ `--module` поведение идентично нативному Laravel.
- Под-генераторы (`make:model -mfs --module=blog`) пробрасывают `--module` в factory/migration/seeder.
- `make:use-case Publish --module=blog` → `app/Modules/Blog/Application/UseCases/PublishUseCase.php`, `final readonly`, `declare(strict_types=1)`.
- `make:vo Money --module=blog` → `app/Modules/Blog/Domain/VO/Money.php`, `final readonly`, без суффикса.
- Несуществующий `--module` → внятная ошибка (через `ModuleRegistryInterface`).
- Все новые команды `final`, общий код в trait, новые стабы несут `declare(strict_types=1)`.

**Next step:** `/aif-plan full генераторы make:* --module` (full: trait + ~23 native-subclass + 5 арх-команд + rebind-провайдер + стабы + ModuleLayout/skeleton + docs/cli.md + ROADMAP отметка + arch/unit/feature тесты).
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->
### 2026-06-02 14:26 — Explore: генераторы make:* --module (дизайн)

**What changed:** Исследован milestone «Генераторы `make:* --module`». Изучена кодовая база (scaffold-флоу, `ModuleLayout`, `ApplicationNamespaceResolver`, провайдер, арх-тесты) и механика двух референсных пакетов через GitHub API. Закрыты 5 продуктовых развилок вопросами пользователю. Active Summary переписан с завершённого логирования на генераторы.

**Решения пользователя (этой сессии):**
- Native-интерфейс = **прозрачный override** через rebind алиасов `command.*.make` (как в roadmap), не opt-in-конфиг и не отдельные имена.
- Охват native = **полный набор** Laravel (~23 команды).
- Арх-генераторы = `make:use-case`, `make:action`, `make:query`, `make:dto`, **`make:vo`** (vo добавлен сверх roadmap).
- Раскладка = `Application/{UseCases,Actions,Queries,DTOs}` + суффиксы UseCase/Action/Query/Dto; **`Domain/VO/` без суффикса** (как VO ядра).
- Объём = **один milestone**.

**Key notes (ресёрч/код):**
- Преимущество конвенции: модуль под `app/`, namespace из `App\` → автозагрузка из коробки; для namespace-генераторов достаточно `getDefaultNamespace()`, `getPath()` Laravel работает как есть (в отличие от InterNACHI, где модуль вне `app/` и нужен per-module composer-autoload + полный remap).
- InterNACHI-механика (проверена через API): subclass-на-команду + общий трейт `ModularizeGeneratorCommand` + rebind алиасов в `booted()→Artisan::starting()`, `singleton('command.X.make', MakeX)` + `singleton(get_parent_class(...), MakeX)`. Migration особый (constructor `migration.creator`+`composer`). Без `--module` поведение идентично нативному.
- Арх-тесты диктуют: общий код = **trait** (не abstract база — провалит `all concrete src classes are final`); резолв модуля только через `ModuleRegistryInterface`; нельзя фасады/хелперы даже в Console; новые стабы обязаны нести `declare(strict_types=1)` (тест сканирует `stubs/`).
- Особые пути (нужен remap, не только namespace): migration (`--path` опция уже есть как простой вариант), factory, seeder, test, component(blade view). Часть каталогов отсутствует в скелете модуля (Database/Seeders, tests) — решить в плане.

**Links (paths):**
- ROADMAP milestone: `.ai-factory/ROADMAP.md` (Фаза 2, «Генераторы `make:* --module`», строка 32).
- Ближайший аналог в коде: `src/Application/UseCases/ScaffoldModuleUseCase.php`, `src/Application/Support/ModuleSkeletonBuilder.php`, `src/Console/Commands/Modules/MakeModuleCommand.php`, `stubs/module-service-provider.stub`.
- Источник истины путей/namespace: `src/Support/ModuleLayout.php`; namespace: `src/Support/ApplicationNamespaceResolver.php`; резолв модуля: `src/Contracts/ModuleRegistryInterface.php` (`find/has`), `src/Manifest/VO/Module.php` (`namespace`/`path`).
- Регистрация команд/стабов: `src/Providers/ModuleLoaderServiceProvider.php` (`boot()` `commands([...])`, `publishes(... 'modules-stubs')`).
- Арх-ограничения: `tests/Architecture/ArchitectureTest.php` (тесты `all concrete src classes are final`, `src does not use Laravel facades`, `global … helpers`, `console commands do not depend on concrete …`, `artisan commands extend Laravel Command`, strict-types по `stubs/`).
- Референсы: github.com/InterNACHI/modular (`src/Console/Commands/Make/*`, `src/Support/ModularizedCommandsServiceProvider.php`), github.com/InterNACHI/modularize (`src/ModularizeGeneratorCommand.php`); nwidart.com/laravel-modules (`module:make-*`); Laravel `Illuminate\Console\GeneratorCommand`.

### 2026-06-01 19:29 — Explore: диагностическое логирование (дизайн)

**What changed:** Исследован milestone «Настраиваемое диагностическое логирование». Зафиксированы 3 ключевых решения (A2 LoadReport, именованный канал хоста, семантический level + порог + категории-тумблеры). Выявлено, что архитектура сейчас запрещает runtime-логи и требует правки.

**Key notes:**
- Логирование нигде в `src/` нет — чистый старт (grep подтвердил).
- Целевая форма: `Contracts\ModuleDiagnosticsInterface` ← impl `Support\Logging\ModuleLogger` (final) / `Support\Logging\NullModuleDiagnostics`. Bind в `ModuleLoaderServiceProvider`: enabled? ModuleLogger(channel, level, categories) : NullModuleDiagnostics. Канал резолвится `$app->make('log')->channel($name)` (LogManager, не фасад).
- Потребители (DI, default = null-object): `ModuleDirectoryScanner`/`ModuleRegistrySnapshotBuilder` (discovery + invalid root warn), `ModuleRegistryCache` (hit/miss/write/clear), `ModuleLoaderPipeline` (started/finished + loader outcome), lifecycle UseCases (install/update/remove/enable/disable/scaffold/optimize + rollback/backup).
- `LoaderInterface::load()` → `LoadReport` (applied/skipped + artifacts = ИМЕНА файлов, не содержимое). Каждый ранний `return;` → `return LoadReport::skipped();`.
- Конфиг-черновик: `modules.logging => { enabled(env MODULES_LOGGING=false), channel('modules'|null), level('debug'), events:{discovery,cache,pipeline,lifecycle} }`.
- Семантические уровни: discovery=debug, invalid root=warning, cache.*=debug/info, install=info, rollback/backup=warning, loader.failed=error.
- НЕ логировать: feature values, секреты, полный manifest. Context-whitelist: module, relative path, loader shortname, command, source kind, duration_ms (через `hrtime`), result, counts, artifact basenames.
- Тесты использовать фасады МОГУТ (арх-тест скоупит только `src/`) → feature-тест с `Log::spy()`.

**Links (paths):**
- ROADMAP milestone: `.ai-factory/ROADMAP.md` (Фаза 2, «Настраиваемое диагностическое логирование»).
- Арх-инварианты: `tests/Architecture/ArchitectureTest.php` (тесты `global logging helpers`, `facades`, `loaders … implement LoaderInterface`, `loaders use Loader suffix`).
- Точки интеграции: `src/Loaders/Pipeline/ModuleLoaderPipeline.php`, `src/Registry/ModuleDirectoryScanner.php`, `src/Registry/ModuleRegistrySnapshotBuilder.php`, `src/Registry/ModuleRegistryCache.php`, `src/Providers/ModuleLoaderServiceProvider.php`, `src/Application/UseCases/*`.
- Контракт лоадера: `src/Contracts/LoaderInterface.php` (уже импортирует `Manifest\VO\Module` — прецедент Contracts↔VO).
- Конфиг: `config/modules.php` (секции `paths`, `groups`, `routing` — добавить `logging`).
- Документ к правке: `.ai-factory/ARCHITECTURE.md` (Runtime Safety стр. 292 + dependency-rule стр. 114); новый `docs/logging.md`.

### 2026-06-01 19:40 — Explore: прецеденты + 4 развилки контракта A2 (закрыты)

**What changed:** Веб-ресёрч прецедентов + закрыты 4 развилки через вопросы пользователю. Ключевая переформулировка «why» (field-diagnostics поставленных модулей). Контракт A2 финализирован.

**Решения пользователя (этой сессии):**
- **Scope = только логи.** CLI-инспекция (doctor/--verbose) → Фаза 3. `LoadReport` — субстрат под неё, но наружу сейчас только логи.
- **Exceptions НЕ трогаем** в этом milestone. Context для `loader.failed` строим в pipeline (в `catch` есть `$loader`+`$module`). `context()`-метод на `ModuleLoaderException` — отложенный non-breaking козырь для Фазы 3.
- **`SkipReason` = enum.** `skipped()` несёт причину → прямой ответ на «почему НЕ подгрузился».
- **`failed` = throw, не статус `LoadReport`.** `LoadStatus = applied|skipped`. Изоляция ошибок pipeline неизменна.

**Key notes (ресёрч):**
- Прецедентов «логировать загрузку модулей» в экосистеме нет. nwidart (`module:list`, хелперы+фасады), internachi/modular («silent + CLI»), Laravel core (package discovery = command output), Symfony (`debug:container`/profiler) — все через инспекцию, не логи. У nwidart НЕТ папки `Logging/` (ранний WebFetch галлюцинировал; проверено через GitHub API: `src/` = Activators/Commands/Generators/.../helpers.php).
- Laravel: метод `context(): array` на исключении автоматически мёржится `ExceptionHandler` в лог host'а — точный механизм «прокинуть context в exception» без связывания с нашим логгером. Отложен.
- PSR-3: исключение → `context['exception']`; message статичный + плейсхолдеры; значения context желательно строковые.
- `ModuleLoaderException` уже structured (public readonly `loaderClass/moduleName/modulePath`), но не отдаёт `context()`.

**Links (paths):**
- Контракт: `src/Contracts/LoaderInterface.php` (`load(Module): void` → `: LoadReport`), новые `src/Loaders/VO/{LoadReport,LoadStatus,SkipReason}.php`.
- Исключение: `src/Exceptions/ModuleLoaderException.php` (в этом milestone без изменений).
- Pipeline (точка централизованного лога + catch с контекстом): `src/Loaders/Pipeline/ModuleLoaderPipeline.php`.
- Прецеденты: github.com/nWidart/laravel-modules/tree/master/src; deepwiki.com/InterNACHI/modular; laravel.com/docs/13.x/errors; php-fig.org/psr/psr-3.
<!-- aif:sessions:end -->
