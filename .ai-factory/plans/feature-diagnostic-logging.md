# Implementation Plan: Настраиваемое диагностическое логирование

Branch: feature/diagnostic-logging
Created: 2026-06-01

## Settings
- Testing: yes (полный набор — unit на новые VO, обновление 15 loader-тестов, whitelist-тест безопасности, arch-тесты, feature-тест с `Log::spy()`)
- Logging: feature-only — НЕ добавлять отдельные dev-DEBUG логи в код; единственное логирование в `src/` — это сам проектируемый диагностический слой (`ModuleDiagnosticsInterface`)
- Docs: yes — обязательный docs-чекпоинт в `/aif-implement` (через `/aif-docs`)

## Roadmap Linkage
Milestone: "Настраиваемое диагностическое логирование" (Фаза 2 — v2.0 расширение)
Rationale: План реализует ровно этот незакрытый milestone — opt-in диагностический слой без фасадов, тихий по умолчанию, на отдельном канале, со структурным whitelisted-контекстом.

## Research Context
Source: `.ai-factory/RESEARCH.md` (Active Summary)

**Goal:** Opt-in диагностический слой, тихий по умолчанию, в отдельный лог-канал, с семантическими уровнями. Настоящее «why» — **field-diagnostics поставленных заказчику модулей** (на проде artisan/profiler недоступны; путь «выставь `MODULES_LOGGING=true`, выстави канал, воспроизведи, пришли `storage/logs/modules.log`»). Покрытие: discovery/scan + invalid roots, cache hit/miss/write/clear, pipeline started/finished + loader applied/skipped/failed с artifacts, lifecycle install/update/remove/enable/disable/scaffold/optimize с rollback/backup boundaries.

**Constraints (forced арх-тестами):**
- Запрещены Laravel facades (`Illuminate\Support\Facades\*`) и bare-хелперы (`logger info app config resolve value report dispatch event`). Логгер обязан быть **инъецированной зависимостью** (`Psr\Log\LoggerInterface`-обёртка), не фасад/хелпер. Канал резолвится `$app->make('log')->channel($name)` (LogManager — метод-вызов, тест не задевает).
- Все concrete-классы `src/` — `final`; VO — `final readonly`; `declare(strict_types=1)`; конструкторная DI; без mutable static (Octane).
- `ARCHITECTURE.md` сейчас ПРЯМО запрещает runtime-логи (dependency-rule ~:114 + Runtime Safety ~:292). Milestone легализует DI-логирование → обе формулировки переписать.

**Decisions (зафиксированы с пользователем в ресёрче):**
- Scope = ТОЛЬКО логи (field-diagnostics). CLI-инспекция (`modules:doctor`/`--verbose`) — Фаза 3. `LoadReport` закладывается как субстрат под будущий doctor, но наружу выходит только логирование.
- Гранулярность лоадеров = **A2**: контракт `LoaderInterface::load(Module): LoadReport` (было `: void`). Логирование централизовано в pipeline.
- `LoadReport`: `status: LoadStatus { Applied | Skipped }`; `artifacts: array<string,list<string>>` (basenames); `reason: ?SkipReason` (только при `skipped`). `applied ⇒ artifacts ≥ 1, reason = null`; `skipped ⇒ artifacts = [], reason != null`.
- `SkipReason` = enum. `failed` = брошенное исключение, НЕ статус. `loader.failed` логируется ДОПОЛНИТЕЛЬНО к `exceptionHandler->report()`.
- Exceptions в этом milestone НЕ трогаем (`ModuleLoaderException` уже несёт `loaderClass/moduleName/modulePath`). Context для `loader.failed` строим в pipeline (в `catch` есть `$loader`+`$module`).
- Лог-канал = именованный канал хоста (`modules.logging.channel`, null → default). Пакет канал не навязывает; сниппет канала — в docs.
- Конфиг = семантический level у каждого события + глобальный порог `modules.logging.level` + тумблеры категорий `events: {discovery, cache, pipeline, lifecycle}`.
- Lifetime логгера = singleton (stateless), null-object = отдельный `NullModuleDiagnostics` (call-sites без `if`).

**Open questions — РЕШЕНЫ в этом плане (по итогам code-разведки):**
1. **Место VO + арх-ignores.** `Loaders/VO/{LoadReport,LoadStatus,SkipReason}`. Арх-тесты `loaders are final and implement LoaderInterface` и `loaders use the Loader suffix` сканируют весь `Loaders\*` (сейчас ignore только `ModuleLoaderPipeline::class`) → добавить `'…\Loaders\VO'` в оба `ignoring([])`.
2. **Порядок цикла.** Pipeline остаётся **loader-outer** (фазовость ломать нельзя). Решение: `pipelineStarted/Finished` summary (N enabled, M loaders, total ms через `hrtime(true)`) + событие `loaderOutcome` на каждый `(loader, module)`.
3. **Null-object.** Отдельный `NullModuleDiagnostics` (final, пустые тела); context-массивы строятся лениво ВНУТРИ `ModuleLogger` после проверки тумблера+порога.
4. **Порог уровня.** `modules.logging.level` фильтрует в основном default(null)-канал; на выделенном канале фильтрует host. Зафиксировать одной строкой в docs.
5. **Финальный `SkipReason`.** По аудиту 15 лоадеров (file:line подтверждены): `NoDirectory` (13 лоадеров), `FileNotFound` (`BroadcastLoader` channels, `ConsoleRouteLoader` console-файл), `RoutesCached` (только `RouteLoader::routesAreCached()`), `NotRunningInConsole` (`CommandLoader`, `ConsoleRouteLoader` — `!runningInConsole()`), `EmptyDirectory`. `integration_unavailable` из ресёрча **исключён**: `RouteLoader` при отсутствии Inertia не скипает лоадер, а лишь не кладёт `routes['inertia']` (per-type `continue`). `FactoryLoader` при `$this->registered` → `applied` (namespace-mapping всегда), НЕ skip. **`EmptyDirectory` применяем ТОЛЬКО к лоадерам, которые сами перечисляют файлы** (Config/Middleware/Observer/Policy/BladeComponent/Factory); path-registering лоадеры (`MigrationLoader`/`EventLoader`/`CommandLoader` — отдают директорию в Laravel без перечисления) скипают лишь на `NoDirectory` и **не вводят искусственный bare-FS `glob`** ради проверки пустоты (нарушит arch-тест direct-FS). Набор финализируется в Task 8.
6. **Инвариант `applied` (разрешён §2.1).** `ServiceProviderLoader` — единственный лоадер **без раннего return** (всегда регистрирует провайдеры из манифеста); при пустом списке провайдеров артефактов 0, но `skipped`-причины нет. → инвариант ослаблен: `applied ⇒ artifacts ≥ 0`, `reason = null`; `skipped ⇒ reason != null, artifacts = []`. `skipped` резервируется строго под отсутствие предусловия (директория/файл/cache/console). Это уточняет (не отменяет) формулировку ресёрча `applied ⇒ artifacts ≥ 1`, которая не учитывала no-op-лоадер.

## Design Decisions (resolved in planning)

- **Единый сегментированный `ModuleDiagnosticsInterface`** в `Contracts` (а не 4 раздельных интерфейса). Обоснование: единственная ответственность — «перевести доменное событие в структурную лог-запись на канале, с гейтингом по конфигу». Сегрегация на 4 интерфейса умножила бы wiring ×4 без выгоды; гейтинг категорий (`events.*`) делается внутри `ModuleLogger`. Методы сгруппированы по секциям discovery/cache/pipeline/lifecycle.
- **`Contracts → Loaders\VO\LoadReport`** — принятый typed-VO паттерн (зеркало уже существующего `Contracts\LoaderInterface → Manifest\VO\Module`); не нарушает «Contracts не зависят от реализаций» (VO — данные). Добавить в ARCHITECTURE.
- **`Support/Logging → Contracts + Psr\Log + Loaders\VO\LoadReport`** — новое разрешённое направление (logging adapter = cross-cutting инфраструктура, канонично для `Support/`). Добавить в ARCHITECTURE.
- **`loaderOutcome(Module, LoaderInterface, LoadReport)`** — один метод вместо `applied`/`skipped` (одинаковый вход `LoadReport`; уровень/сообщение выбирает логгер по `$report->status`). `loaderFailed(...)` отдельно — другой вход (`Throwable`, не `LoadReport`).
- **`duration_ms`** — только на `pipelineFinished` (total, `hrtime(true)`), чтобы не шуметь per-(loader,module).
- **Биндинг** — `ModuleDiagnosticsInterface` как singleton в провайдере: `enabled ? new ModuleLogger($this->app->make('log')->channel($channel), $level, $events) : new NullModuleDiagnostics()`. Все потребители тайпхинтят интерфейс — контейнер резолвит нужную реализацию. **Конфиг читать только через типизированный контракт `$this->app->make(Repository::class)->get('modules.logging…')`** (`Illuminate\Contracts\Config\Repository` — устоявшийся идиом проекта: так уже резолвят `ModuleStatePaths`/`ModuleDirectoryScanner`/`ModuleDirectoryPaths`, а `ConfigLoader` принимает `Repository $config` в ctor). НЕ использовать string-key `$app->make('config')` — расходится с идиомом и даёт `mixed` для PHPStan. bare-хелперы `config()`/`app()` запрещены arch-тестом `src does not use global logging or service-location helpers` (§2.4).
- **Резолюция зависимостей в провайдере (§2.4).** Конфиг — типизированным `$this->app->make(Repository::class)`; значения читаются в провайдере и передаются в `ModuleLogger` **примитивами** (`level`, `events`) — логгер остаётся без связи с фреймворк-конфигом, что делает whitelist-unit-тест (Task 15) тривиальным (конструируется с fake-логгером + массивами). `enabled` читается в провайдере, т.к. выбор `ModuleLogger` vs `NullModuleDiagnostics` — решение композиционного корня. Канал — `$this->app->make('log')->channel($channel)` (возвращает host-сконфигурированный `LogManager` singleton, отдаёт `Psr\Log\LoggerInterface` для `ModuleLogger`); НЕ `make(\Illuminate\Log\LogManager::class)` — тот построил бы новый инстанс мимо singleton-а.
- **Инвариант `LoadReport` (§2.1):** `applied ⇒ artifacts ≥ 0` (артефакты могут быть пусты — напр. `ServiceProviderLoader` без провайдеров), `reason = null`; `skipped ⇒ reason != null, artifacts = []`. `skipped` — только отсутствие предусловия.
- **Модель `artifacts` (§2.3):** basenames там, где лоадер перечисляет файлы; для path-registering лоадеров (`MigrationLoader`/`EventLoader`/`CommandLoader`) артефакт = зарегистрированный относительный путь (а не список basename'ов). Зафиксировать в `LoadReport` PHPDoc.
- **Авторизованная правка `rules/*` (Task 0):** `rules/loaders.md` сейчас фиксирует `load(Module): void`, `rules/code-quality.md` — «runtime logging (`Log::*`)». План легализует DI-логирование и меняет контракт → оба правила синхронизируются отдельным Commit 0 (явная авторизация пользователя; обычный запрет на правку `rules/*` снят для этого плана).

## Log Event Catalog

Канонический перечень событий — единый источник для контракта (Task 3), уровней (Task 5) и таблицы в `docs/logging.md` (Task 18). Каждое событие гейтится своим тумблером `events.*` + глобальным порогом `modules.logging.level`. Context — только whitelisted-скаляры (никаких feature values / секретов / полного manifest).

### `events.pipeline` — отвечает на «какие лоадеры применились к модулю и почему нет»
`loaderOutcome(Module, LoaderInterface, LoadReport)` срабатывает на каждую пару `(loader × module)` и по `LoadReport->status` пишет `applied` либо `skipped`.

| Событие | Уровень | Context (whitelist) | Триггер |
|---|---|---|---|
| `pipeline.started` | debug | `modules_enabled` (N), `loaders` (M) | начало `boot()` |
| `pipeline.loader.applied` | debug | `module`, `loader` (shortname), `artifacts` (`{type:[basenames]}`) | `LoadReport::applied` |
| `pipeline.loader.skipped` | debug | `module`, `loader`, `reason` (`SkipReason->value`) | `LoadReport::skipped` |
| `pipeline.loader.failed` | error | `module`, `loader`, `exception`, `message` | `catch` — **в дополнение** к `exceptionHandler->report()` |
| `pipeline.finished` | debug | `modules_enabled`, `loaders`, `applied`/`skipped`/`failed` counts, `duration_ms` (`hrtime`) | конец `boot()` |

### `events.discovery`
| Событие | Уровень | Context | Триггер |
|---|---|---|---|
| `discovery.root.missing` | warning | `root` (relative) | сконфигурированный root не существует |
| `discovery.root.rejected` | warning | `root`, `reason` | root отклонён (вне base / невалиден) |
| `discovery.module.found` | debug | `module`, `path` (relative) | модуль обнаружен при сканировании |
| `discovery.completed` | debug | `total`, `enabled`, `disabled` | снапшот реестра построен |

### `events.cache`
| Событие | Уровень | Context | Триггер |
|---|---|---|---|
| `cache.hit` | debug | `count` | реестр отдан из `bootstrap/cache/modules.php` |
| `cache.miss` | debug | — | кеша нет/устарел → пересборка |
| `cache.written` | info | `count`, `path` (relative) | кеш-файл записан |
| `cache.cleared` | info | — | кеш очищен |
| `cache.invalid` | warning | `reason` | кеш-файл есть, но не прошёл валидацию |

### `events.lifecycle`
8 mutating-UseCase: `install`/`update`/`remove`/`enable`/`disable`/`scaffold`/`optimize`/`clearCache`. `ListModulesUseCase` (read-only) — **исключён**.

| Событие | Уровень | Context | Триггер |
|---|---|---|---|
| `lifecycle.{op}.started` | info | `module`, `source` kind? | начало операции |
| `lifecycle.{op}.succeeded` | info | `module`, counts | операция завершена |
| `lifecycle.{op}.rolledBack` | warning | `module`, stage/reason | откат операции |
| `lifecycle.backup.created` | warning | `module`, `backup` (relative path) | бэкап перед деструктивной операцией |

## Commit Plan
- **Commit 0** (task 0): `chore(rules): authorize DI diagnostics layer and LoadReport loader contract` — авторизованная синхронизация `rules/loaders.md` + `rules/code-quality.md` (prerequisite; снимает конфликт §1 до изменения кода).
- **Commit 1** (tasks 1–3): `feat(logging): add config, LoadReport VOs and diagnostics contract` — аддитивно, suite зелёный.
- **Commit 2** (tasks 4–6): `feat(logging): add ModuleLogger/NullModuleDiagnostics and provider binding` — по умолчанию биндится Null, suite зелёный.
- **Commit 3** (tasks 7, 8, 9, 14): `refactor(loaders): return LoadReport and centralize pipeline diagnostics` — **атомарный** (смена контракта + 15 лоадеров + pipeline + обновление 15 loader-тестов в одном коммите, иначе suite красный).
- **Commit 4** (tasks 10–12): `feat(logging): instrument discovery, cache and lifecycle` — зелёный.
- **Commit 5** (tasks 13, 15, 16, 17): `test(logging): VO units, whitelist guard, arch and feature coverage`.
- **Commit 6** (tasks 18, 19): `docs(logging): logging guide, ARCHITECTURE rewrite + quality gate`.

## Tasks

> Формат критериев: ✅ **Принято** — необходимое и достаточное для done; ❌ **Не принято** — стоп-факторы (regression / нарушение правила / просадка контракта).

### Phase 0 — Авторизованная синхронизация правил (prerequisite)
- [x] Task 0: Синхронизировать `.ai-factory/rules/loaders.md` (`load(Module): void` → `: LoadReport`; «ранний return при отсутствии файлов» → «ранний `return LoadReport::skipped(<reason>)`»; сохранить «тонкий интерфейс: только `load()` + `priority()`, без manifest-флагов/whitelist») и `.ai-factory/rules/code-quality.md` (уточнить запрет: фасады/глобальные хелперы/`Log::*` запрещены, но **инъецированный `ModuleDiagnosticsInterface` разрешён**). Правка авторизована пользователем для этого плана.
  - ✅ Принято: оба правила отражают новый контракт и DI-логирование; формулировка «тонкий `LoaderInterface`» сохранена; `git diff` затрагивает только эти два файла `rules/*`; `composer test` (rules не входят в suite) не задет.
  - ❌ Не принято: правило `loaders.md` всё ещё `: void` · `code-quality.md` оставляет двусмысленный запрет «runtime logging» без исключения для DI-слоя · задеты другие `rules/*` без необходимости.
<!-- Commit checkpoint: task 0 (chore(rules), prerequisite) -->

### Phase 1 — Конфиг и контракты (contract-first)
- [x] Task 1: Добавить секцию `modules.logging` в `config/modules.php` (`enabled`/`channel`/`level`/`events`) + env-ключи `MODULES_LOGGING`/`MODULES_LOG_CHANNEL`/`MODULES_LOG_LEVEL`; явно прописать `psr/log` в `composer.json` require.
  - ✅ Принято: секция `logging` с `enabled`(env `MODULES_LOGGING`, **default `false`**), `channel`(env `MODULES_LOG_CHANNEL`, default `null`), `level`(env `MODULES_LOG_LEVEL`, default `'debug'`), `events`{`discovery`,`cache`,`pipeline`,`lifecycle` — все 4 bool-тумблера}; `psr/log` в `require`; `composer test` зелёный (merge конфига ничего не ломает).
  - ❌ Не принято: `enabled` по умолчанию `true` · отсутствует любой из 4 тумблеров `events` · нет env-фолбэков · `psr/log` не добавлен в `require`.
- [x] Task 2: Определить VO `Loaders/VO/{LoadStatus(enum Applied|Skipped), SkipReason(enum), LoadReport(final readonly + named ctors applied()/skipped() + инварианты + toArray())}`. Зафиксировать §2.1 (инвариант `applied ⇒ artifacts ≥ 0`) и §2.3 (модель artifacts: basenames | относительный путь) в PHPDoc.
  - ✅ Принято: `LoadStatus(Applied|Skipped)`, `SkipReason` enum, `LoadReport` `final readonly`; named ctors `applied(array $artifacts = [])` / `skipped(SkipReason $reason)`; инвариант форсится в конструкторе (`applied ⇒ reason=null`; `skipped ⇒ reason!=null && artifacts=[]`); `toArray()` отдаёт только whitelisted-скаляры (status, reason?->value, artifact basenames/paths) — без сырого `Module`/values; PHPStan чист.
  - ❌ Не принято: изменяемые свойства · инвариант не проверяется в ctor · `toArray()` пропускает сырой `Module` или feature values · кейс пустого `applied` (§2.1) не учтён.
- [x] Task 3: Определить контракт `Contracts/ModuleDiagnosticsInterface` (секции discovery/cache/pipeline/lifecycle, void-методы, типизированные параметры). (depends on 2)
  - ✅ Принято: интерфейс с методами по 4 секциям, покрывающими ровно события из секции «Log Event Catalog» (discovery×4, cache×5, pipeline — `pipelineStarted`/`loaderOutcome`/`loaderFailed`/`pipelineFinished`, lifecycle — `started`/`succeeded`/`rolledBack`/`backupCreated`); все `void`, параметры типизированы доменными типами (`Module`, `LoaderInterface`, `LoadReport`, `Throwable`, скаляры) — без `array $context`-дыр; `arch('contracts are interfaces')` зелёный.
  - ❌ Не принято: методы принимают сырой манифест/значения фич · контракт импортирует реализацию (`Support\Logging\*`) · есть не-`void` метод-геттер.
<!-- Commit checkpoint: tasks 1-3 -->

### Phase 2 — Реализация диагностики и wiring
- [x] Task 4: `Support/Logging/NullModuleDiagnostics` (final, пустые тела, stateless). (depends on 3)
  - ✅ Принято: реализует все методы `ModuleDiagnosticsInterface` пустыми телами; без полей/состояния; `final`.
  - ❌ Не принято: есть поля или побочная логика · не покрыты все методы контракта.
- [x] Task 5: `Support/Logging/ModuleLogger` (final, оборачивает `Psr\Log\LoggerInterface`; гейтинг по категории + порогу; ленивый whitelisted-context; семантические уровни). (depends on 2, 3)
  - ✅ Принято: `final`, конструктор принимает `Psr\Log\LoggerInterface` + level + events; гейтинг (категория выкл. ИЛИ ниже порога) **до** построения context; context строится лениво и только из whitelist; уровни — строго по таблице «Log Event Catalog» (discovery=debug, invalid root=warning, cache=debug/info, lifecycle started/succeeded=info, rollback/backup=warning, loader.failed=error); не импортирует `Facades\Log`.
  - ❌ Не принято: импорт фасада · context строится до гейта · в context просачиваются feature values/секреты/полный manifest · уровни не семантические.
- [x] Task 6: Биндинг `ModuleDiagnosticsInterface` в `ModuleLoaderServiceProvider` (`registerDiagnosticsBindings()`; канал через `$this->app->make('log')->channel()`; **конфиг через типизированный `$this->app->make(Repository::class)->get()`, §2.4 — без bare `config()`/`app()` и без string-key `make('config')`**). (depends on 1, 4, 5)
  - ✅ Принято: singleton-бинд `enabled ? ModuleLogger(channel,level,events) : NullModuleDiagnostics`; канал и конфиг резолвятся методами (`make('log')->channel()`, `make(Repository::class)->get()` — типизированный контракт, идиом проекта); `level`/`events` читаются в провайдере и передаются примитивами; по умолчанию (`enabled=false`) биндится `NullModuleDiagnostics`; `composer test` зелёный; PHPStan чист; arch `src does not use global logging or service-location helpers` зелёный.
  - ❌ Не принято: bare-хелпер `config(`/`app(` или string-key `make('config')` вместо `make(Repository::class)` в провайдере · логгер не singleton или не stateless · при `enabled=false` биндится не Null.
<!-- Commit checkpoint: tasks 4-6 -->

### Phase 3 — Миграция контракта лоадеров (атомарно)
- [x] Task 7: Сменить `LoaderInterface::load(Module): void` → `: LoadReport`. (depends on 0, 2)
  - ✅ Принято: сигнатура `load(Module): LoadReport`; интерфейс остаётся тонким (`load` + `priority`); `rules/loaders.md` (Task 0) уже согласован; `arch('loaders … implement LoaderInterface')` зелёный.
  - ❌ Не принято: добавлены лишние методы в контракт · `rules/loaders.md` рассинхронизирован с сигнатурой.
- [x] Task 8: Перевести все 15 лоадеров на `LoadReport` (early returns → `skipped(reason)`, success → `applied(artifacts)`); финализировать `SkipReason`. **§2.1:** `ServiceProviderLoader` без провайдеров → `applied([])` (не skip — нет отсутствующего предусловия). **§2.2:** `EmptyDirectory` только для лоадеров, которые сами перечисляют файлы; path-registering (`Migration`/`Event`/`Command`) скипают лишь на `NoDirectory`, **без нового bare-FS `glob`**. `FactoryLoader` при `registered` → `applied`; `RouteLoader` без Inertia — омит `routes['inertia']`, не skip. (depends on 7)
  - ✅ Принято: каждый ранний `return;` заменён на `skipped(<точная причина>)`; success → `applied(artifacts)`; ни один лоадер не нарушает инвариант §2.1; `EmptyDirectory` нигде не вводит прямой FS-I/O в не-allowlisted классах (arch direct-FS зелёный); поведение лоадеров функционально не изменилось (idempotent, фазовость сохранена).
  - ❌ Не принято: лоадер возвращает `applied([])` под видом отсутствия предусловия (или наоборот) · `EmptyDirectory` добавил bare `glob(`/`is_dir(` в запрещённый класс · функциональное поведение загрузки изменилось.
- [x] Task 9: Инструментировать `ModuleLoaderPipeline` (inject diagnostics; `loaderOutcome`/`loaderFailed`+`exceptionHandler->report`; `pipelineStarted/Finished` с `hrtime`); обновить `pipeline()` factory в провайдере. (depends on 6, 8)
  - ✅ Принято: pipeline получает `ModuleDiagnosticsInterface` через ctor; `loaderOutcome(module,loader,report)` на каждый `(loader,module)`; в `catch` — `loaderFailed(...)` **в дополнение** к существующему `exceptionHandler->report()` (не вместо); `pipelineStarted/Finished` с total `hrtime(true)`; `pipelineFinished` несёт счётчики `applied`/`skipped`/`failed`, накопленные в **локальных переменных `boot()`** (не instance-state — pipeline остаётся `final readonly`); loader-outer порядок и изоляция ошибок неизменны; factory в провайдере обновлён.
  - ❌ Не принято: `report()` заменён логом · изменён порядок циклов (сломана фазовость) · `duration_ms` шумит per-(loader,module) · pipeline перестал быть `final readonly`.
- [x] Task 14: Обновить 15 loader unit-тестов под `LoadReport` (Applied+artifacts / Skipped+reason для каждого early-return). (depends on 8)
  - ✅ Принято: для каждого лоадера тест на `applied`+artifacts и на `skipped`+конкретный `SkipReason` для каждого его раннего return; suite зелёный **в этом же коммите** (атомарность).
  - ❌ Не принято: хоть один early-return без теста на его reason · тесты остались на старом `void`-контракте.
<!-- Commit checkpoint: tasks 7, 8, 9, 14 (атомарный) -->

### Phase 4 — Инструментирование потребителей
- [x] Task 10: Discovery — inject diagnostics в `ModuleDirectoryScanner` + `ModuleRegistrySnapshotBuilder` (root-missing/rejected warn, module-discovered/completed debug). (depends on 6)
  - ✅ Принято: оба класса принимают `ModuleDiagnosticsInterface` через ctor (дефолт-резолв = null-object); конструктор без side effects; root-missing/rejected=warning, discovered/completed=debug; существующее поведение discovery без логов не изменилось.
  - ❌ Не принято: конструктор делает side effect · зависимость ломает текущий wiring · логирование меняет результат сканирования.
- [x] Task 11: Cache — inject diagnostics в `Manifest\ModuleRegistry` (hit/miss) + `Registry\ModuleRegistryCache` (written/cleared/invalid); прокинуть через explicit-биндинг кеша. (depends on 6)
  - ✅ Принято: `Manifest\ModuleRegistry` логирует hit/miss, `ModuleRegistryCache` — written/cleared/invalid; биндинги обновлены без нарушения `optimize/list commands do not depend on concrete registry/cache`; suite зелёный.
  - ❌ Не принято: нарушен arch об изоляции команд от конкретного registry/cache · кеш стал источником feature values (нарушение runtime-правила).
- [x] Task 12: Lifecycle — inject diagnostics в 8 целевых UseCase (`Install`/`Update`/`Remove`/`Enable`/`Disable`/`Scaffold`/`Optimize`/`ClearModulesOptimizeCache`; `ListModulesUseCase` read-only — **исключён**). События started/succeeded/rolledBack/backupCreated; только whitelisted-скаляры. (depends on 6)
  - ✅ Принято: 8 mutating-UseCase инструментированы; события lifecycle/rollback/backup на корректных уровнях; в context только whitelisted-скаляры (module name, source kind, counts) — **без feature values/секретов**; `use cases are final readonly` зелёный; `ListModulesUseCase` не тронут.
  - ❌ Не принято: затронут read-only `ListModulesUseCase` · в context попали значения фич/секреты · UseCase перестал быть `final readonly`.
<!-- Commit checkpoint: tasks 10-12 -->

### Phase 5 — Тесты
- [x] Task 13: Unit-тесты VO `LoadReport`/`LoadStatus`/`SkipReason` (construction, инварианты-error-path, `toArray()` — проверка структуры/whitelist; формулировка «round-trip» применима только если есть обратный `fromArray()`, иначе это assertion структуры). (depends on 2)
  - ✅ Принято: тесты на валидную construction, на error-path каждого инварианта (broken `applied`/`skipped` → исключение), на `toArray()` whitelist (нет сырого `Module`/values).
  - ❌ Не принято: нет негативного теста инварианта · `toArray()`-тест не проверяет отсутствие не-whitelisted ключей.
- [x] Task 15: Whitelist security unit-тест `ModuleLogger` (feature values/secrets/full manifest НИКОГДА не в context; гейтинг категории+порога). (depends on 5)
  - ✅ Принято: тест доказывает, что при логировании любого события feature values / секреты / полный manifest не попадают в записанный context; проверены оба гейта (категория выкл. → тишина; level ниже порога → тишина).
  - ❌ Не принято: тест только happy-path без негативного whitelist-кейса · гейтинг не проверен.
- [x] Task 16: Arch-тесты — `ignoring(Loaders\VO)` в `loaders … implement LoaderInterface` и `loaders use the Loader suffix`; новый тест на `Loaders\VO` (`LoadReport` final readonly; `LoadStatus`/`SkipReason` — определить string-backed vs pure и закрепить тестом); новый тест на `ModuleLogger`/`Null` (final + implement интерфейс); подтвердить зелёные facades/global-helpers/static тесты. (depends on 4, 5, 8)
  - ✅ Принято: оба существующих loader-теста игнорируют `Loaders\VO` и зелёные; новый VO-тест фиксирует форму `LoadReport`+enums; новый logging-тест фиксирует `final`+implement; `facades`/`global helpers`/`mutable static` тесты зелёные.
  - ❌ Не принято: забыт `ignoring(Loaders\VO)` (loader-тесты краснеют на `LoadReport`) · enums без зафиксированного контракта backing.
- [x] Task 17: Feature-тест с `Log::spy()` (enabled → записи для discovery/pipeline/lifecycle; disabled → тишина; channel(null)→default). (depends on 9, 10, 11, 12)
  - ✅ Принято: enabled → ожидаемые записи по discovery/pipeline/lifecycle на нужном канале; **disabled → полная тишина** (негативный кейс присутствует); `channel(null)` пишет в default-канал.
  - ❌ Не принято: нет негативного кейса «disabled = тишина» · тест зависит от порядка/времени (нарушение testing-правил).
<!-- Commit checkpoint: tasks 13, 15, 16, 17 -->

### Phase 6 — Документация и качество
- [x] Task 18: Docs — новый `docs/logging.md` (config, env, level-таблица = секция «Log Event Catalog» плана, channel-сниппет `storage/logs/modules.log`, whitelist-гарантия, level-поведение); `docs/configuration.md`; переписать `ARCHITECTURE.md` (:114 dependency-rule + :292 Runtime Safety + новые направления `Contracts→Loaders\VO`, `Support/Logging→Psr\Log`); обновить `AGENTS.md`/`CLAUDE.md` map. (depends on 9, 10, 11, 12)
  - ✅ Принято: `docs/logging.md` покрывает config/env/уровни/сниппет канала/whitelist-гарантию/поведение порога; `ARCHITECTURE.md` больше не запрещает DI-логирование и фиксирует новые направления зависимостей; map в `AGENTS.md`/`CLAUDE.md` обновлён; нет дублирования больших описаний между AI-context файлами (base-правило).
  - ❌ Не принято: `ARCHITECTURE.md` всё ещё запрещает runtime-логи · docs документируют нереализованное как готовое · whitelist-гарантия не описана.
- [x] Task 19: Финальный quality gate — `composer format` → `composer phpstan` → `composer test`, всё зелёное (порядок по `CLAUDE.md`/base-правилу). **§2.5: подтвердить статус `rector`** — по умолчанию `composer rector:dry` (preview), а не apply, чтобы не вносить незапланированный diff в гейт. (depends on 13, 14, 15, 16, 17, 18)
  - ✅ Принято: `composer format`, `composer phpstan` (src, level 8), `composer test` (arch+unit+feature) зелёные, запущены раздельно; `rector:dry` без неожиданных предложений (или apply согласован с пользователем явно).
  - ❌ Не принято: `composer rector` (apply) в гейте внёс незапланированный diff без согласования · шаги склеены в одну команду · хоть один гейт красный.
<!-- Commit checkpoint: tasks 18-19 -->

## Success Signals (из ресёрча)
- `composer test` (arch+unit+feature) + `composer phpstan` + `composer format:dry` зелёные.
- Логи off by default; при `enabled` пишут в отдельный канал с уровнями.
- Unit-тест доказывает: feature values / secrets / полный manifest НИКОГДА не в context.
- `loader.failed` логируется ДОПОЛНИТЕЛЬНО к `exceptionHandler->report()`.
- `skipped`-события несут `SkipReason` → лог отвечает на «почему модуль НЕ подгрузился».
