# Research

Updated: 2026-06-01 19:40
Status: active

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
**Topic:** Настраиваемое диагностическое логирование (ROADMAP Фаза 2, milestone `[ ]`).

**Goal:** Opt-in диагностический слой, тихий по умолчанию, в отдельный лог-канал, с семантическими уровнями. Базовый запрос пользователя: «какие модули найдены, какие подгрузились/какие нет». ROADMAP расширяет до: discovery/scan, invalid roots, cache hit/miss/write/clear, lifecycle-команды с rollback/backup boundaries, pipeline по модулю, loader applied/skipped/failed с artefacts.

**Real "why" (уточнено ресёрчем):** настоящее обоснование milestone — **field-diagnostics поставленных заказчику модулей**, а не локальная разработка. У заказчика на проде artisan/дебаггер/profiler недоступны; практичный путь — «выставь `MODULES_LOGGING=true`, выстави канал, воспроизведи, пришли `storage/logs/modules.log`». Это объясняет акценты: человекочитаемый нарратив + структурный context на ОТДЕЛЬНОМ канале (на дефолтном `debug` discovery в prod исчезает).

**Прецеденты (ресёрч, итог):** прямого аналога «логировать загрузку модулей» в экосистеме нет. nwidart/laravel-modules (глоб. хелперы+фасады, `module:list`), internachi/modular («silent operation + on-demand CLI»: `modules:list/cache`), Laravel core (package discovery = command output, не лог), Symfony (`debug:container`/profiler) — ВСЕ отвечают на «что загрузилось» инспекционными командами, не runtime-логами. Вывод: наш лог-слой — осознанный шаг за пределы нормы, оправданный поставкой модулей; «как у других» не работает как обоснование.

**Constraints (forced арх-тестами и архитектурой):**
- Запрещены Laravel facades (`Illuminate\Support\Facades\*`) и голые хелперы: `logger logs info app config resolve value report dispatch event` (тест `src does not use global logging or service-location helpers`). Регекс ловит только bare-вызовы (`info(`), но НЕ методы (`->info(`).
- → логгер обязан быть **инъецированной зависимостью** (PSR `Psr\Log\LoggerInterface` / обёртка), не фасад/хелпер.
- Все concrete-классы `src/` — `final`; VO — `final readonly`; `declare(strict_types=1)`; конструкторная DI; без raw FS (логгер пишет через Monolog-канал, FS-тест не задевает).
- `Contracts/*` — только интерфейсы.
- `ARCHITECTURE.md` сейчас ПРЯМО запрещает runtime-логи (стр. 114 dependency-rules + стр. 292 «Package core не пишет runtime logs»). Формулировка «через `Log`» = фасад. Milestone легализует DI-логирование → **обе формулировки переписать**.

**Decisions (зафиксированы с пользователем):**
- **Scope milestone = ТОЛЬКО логи (field-diagnostics).** CLI-инспекцию (`modules:doctor` / `--verbose modules:list`) НЕ делаем здесь — Фаза 3. `LoadReport` закладываем как общий субстрат под будущий doctor, но наружу в этом milestone выходит только логирование.
- **Гранулярность лоадеров = A2:** контракт меняется `LoaderInterface::load(Module): LoadReport` (было `: void`). Логирование централизовано в pipeline. Переиспользуемо позже в `modules:doctor`/UI/тестах. BC не волнует.
- **`LoadReport` — финальная форма:** `status: LoadStatus { applied | skipped }` (ровно ДВЕ); `artifacts: array<string,list<string>>` (basenames; map оправдан для мультитиповых loader'ов, напр. RouteLoader `web/api/...`); `reason: ?SkipReason` — заполняется ТОЛЬКО при `skipped`.
  - `applied` ⇒ artifacts ≥ 1, reason = null. `skipped` ⇒ artifacts = [], reason = <почему>.
  - **`SkipReason` = enum** (типобезопасно, фильтруемо, тестируемо). Черновой набор: `no_directory`, `empty_directory`, `file_absent`, `cache_active` (routes/console cached), `integration_unavailable` (напр. inertia route type). Финализировать набор по 15 loader'ам в плане.
  - Граница: «директория есть, но нет файлов» ⇒ `skipped(empty_directory)`.
- **`failed` — это брошенное исключение, НЕ статус `LoadReport`.** Изоляция ошибок pipeline (`try { load } catch { report() + diagnostics.loaderFailed(); continue }`) не меняется. `loader.failed` логируется ДОПОЛНИТЕЛЬНО к `exceptionHandler->report()` (не вместо).
- **Exceptions в этом milestone НЕ трогаем.** `ModuleLoaderException` остаётся как есть (уже несёт `loaderClass/moduleName/modulePath` как public readonly). Context для `loader.failed` строим В PIPELINE — в `catch` уже доступны `$loader` и `$module`. Правка класса исключения не нужна.
  - **Future enhancement (НЕ сейчас, Фаза 3 вместе с doctor):** метод `context(): array` на `ModuleLoaderException` — Laravel `ExceptionHandler` автоматически мёржит его в лог host'а (Sentry/Flare/stack), давая структурный контекст и в трекер заказчика из одной точки. Добавление — НЕ ломающее. PSR-3: исключение класть в `context['exception']`, message статичный с плейсхолдерами.
- **Лог-канал = именованный канал хоста** (`modules.logging.channel='modules'`, null → default). Пакет канал НЕ навязывает — использует по имени; готовый сниппет канала (отдельный файл `storage/logs/modules.log`) кладём в docs.
- **Конфиг events = семантический level у каждого события + глобальный порог `modules.logging.level` + тумблеры по категориям** `events: {discovery, cache, pipeline, lifecycle}`.
- **Lifetime логгера = singleton.** `ModuleLogger` stateless (оборачивает инъецированный channel) → Octane-safe, арх-тест на mutable static проходит.

**Open questions (решить в плане):**
1. **Место `LoadReport` + арх-ignores.** Кладём в `Loaders/VO/LoadReport.php` + `LoadStatus` + `SkipReason`. Прецедент: `Contracts\LoaderInterface` уже импортирует `Manifest\VO\Module` (Contracts↔VO — принятый паттерн, не нарушение «Contracts не зависят от реализаций»). НО арх-тесты `loaders are final and implement LoaderInterface` и `loaders use the Loader suffix` сканируют весь `Loaders\*` → добавить `->ignoring(Loaders\VO\…)` (как уже для `ModuleLoaderPipeline`).
2. **Порядок цикла vs «pipeline start/finish по модулю».** Сейчас pipeline — **loader-outer** (`foreach loader { foreach module }`): фазовая загрузка по всему набору (config всех модулей → providers всех → …). Менять на module-outer НЕЛЬЗЯ (сломается фазовость). → «граница по модулю» не натуральна. Решение: `pipeline.started/finished` summary (N enabled, M loaders, total ms) + событие на каждый `(loader, module)`; per-module агрегат — суммированием при необходимости.
3. **Null-object vs NullLogger.** Рекомендация: отдельный `NullModuleDiagnostics` (call-sites без `if`); контекст-массивы строить лениво ВНУТРИ логгера (overhead при выкл. = вызов метода + пара сравнений). Аллокация `LoadReport` на loader×module остаётся всегда — цена A2, мизерная.
4. **Порог уровня** `modules.logging.level` имеет смысл в основном для дефолтного (null) канала; на выделенном `modules`-канале фильтрует host. Зафиксировать поведение одной строкой в docs/плане.
5. **Финальный набор `SkipReason`** — выверить по всем 15 loader'ам (каждый ранний `return;` → `skipped(<reason>)`).

**Success signals:**
- `composer test` (arch+unit+feature) + `composer phpstan` + `composer format:dry` зелёные.
- Логи off by default; при `enabled` — пишут в отдельный канал с уровнями.
- Unit-тест доказывает: feature values / secrets / полный manifest НИКОГДА не попадают в context (whitelist).
- `loader.failed` логируется ДОПОЛНИТЕЛЬНО к существующему `exceptionHandler->report()` (не вместо).
- `skipped`-события несут `SkipReason` → лог отвечает на «почему модуль НЕ подгрузился».

**Next step:** `/aif-plan full логирование модулей` (full-режим: контракт + 15 лоадеров + конфиг + docs + ARCHITECTURE + arch-тесты + тесты).
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->
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
