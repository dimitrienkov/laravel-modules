# Implementation Plan: Octane-совместимость и архитектурные гарантии

Branch: `feature/octane-compatibility`
Created: 2026-06-03

## Settings
- Testing: yes (милестоун ПО СУТИ — фиксация Octane-чистоты тестами)
- Logging: minimal — логи в `src/` НЕ планируются (правило «Package Core Logging Scope»: фидбэк ядра — типизированные исключения, вывод команд, assert'ы тестов)
- Docs: yes (обязательный docs-чекпоинт; `docs/octane.md` — deliverable)

## Roadmap Linkage
Milestone: "Фаза 2 — Octane-совместимость и архитектурные гарантии"
Rationale: План закрывает все открытые пункты милестоуна (arch-тест `FeatureRepository ≠ singleton`, поведенческие worker-reuse тесты, `docs/octane.md`) без прогона реального сервера; частично продвигает пункт «Документация» (добавляет `docs/octane.md`).

## Research Context
Source: `.ai-factory/RESEARCH.md` (Active Summary)

Goal: зафиксировать тестами и документацией уже существующую Octane-чистоту ядра.

Decisions (зафиксированы с пользователем после исследования + adversarial-верификации против docs 13.x и laravel/octane 2.x):
- Ядро уже Octane-clean: нет static/фасадов (arch-тест), нет инъекции `Request`, `FeatureRepository` — `scoped`, singleton-сервисы stateless.
- В `config/octane.php` `flush`/`warm` пакету добавлять НЕЧЕГО и НЕ НУЖНО (осознанно): scoped чистится Octane сам; `ModuleRegistry` в `flush` класть нельзя (рассинхрон с отработавшими лоадерами); snapshot уже «тёплый»; scoped в `warm` был бы багом.
- Ключевой контракт: feature **VALUES** свежие каждый запрос (scoped repo читает `state.json`); **enabled** + набор модулей + артефакты **ЗАМОРОЖЕНЫ** на срок воркера (snapshot построен на boot). Корректно (класс ограничения как `route:cache`), требует `octane:reload` после мутаций — документировать, не «чинить».

Объём (решения пользователя):
- Тесты: **только T1–T6 in-process** (через `forgetScopedInstances()`), без новых dev-deps и реального сервера. T7 (`cerbero90/octane-testbench`) и T8 (e2e под FrankenPHP/Swoole/RoadRunner) — вне scope.
- Arch-гард: **узкий лок** (`FeatureRepository` scoped, не singleton) **+ широкий гард** (нет `Illuminate\Http\Request` в `src/`).
- DI-чистка `Config\Repository`→скаляры в `ModuleStatePaths`/`ModuleDirectoryScanner`/`ModuleDirectoryPaths` — **включена** в план.

Проверенный контракт кода (для однозначности критериев):
- `ModuleManifestRepository::load()` читает state через `stateRepository->readState()` на момент build → `enabled` замораживается в snapshot (`src/Manifest/ModuleManifestRepository.php:52-54`).
- `Module::isEnabled()` возвращает `state.enabled` из замороженного snapshot (`src/Manifest/VO/Module.php:65`); pipeline читает его на boot (`src/Loaders/Pipeline/ModuleLoaderPipeline.php:45`).
- `FeatureRepository` (scoped, `src/Providers/ModuleLoaderServiceProvider.php:328`) читает values свежими через `readValues()`.

## Commit Plan
- **Commit 1** (после задач 1–3): `test: lock octane worker-reuse contract and scoped feature binding`
- **Commit 2** (после задачи 4): `refactor: inject resolved config scalars into path services`
- **Commit 3** (после задач 5–6): `docs: add octane compatibility guide`
- **Commit 4** (после задачи 7): `chore: run rector/format/phpstan/test quality gate`

## Tasks

### Phase 1: Тесты и arch-гарды (фиксация контракта)

- [x] **Task 1: Поведенческий Octane worker-reuse тест** — новый `tests/Feature/OctaneWorkerLifecycleTest.php` (T2 singleton-registry переживает scope reset; T3 snapshot заморожен; T4+T5 dual — values свежие/enabled заморожен в одном воркере; T6 нет роста состояния). Резолв сервисов из контейнера booted-провайдера, граница запроса = `forgetScopedInstances()`, без `modules:optimize`-кэша.
  - ✅ файл проходит `composer test:feature`; метод dual проверяет ОБА факта; падает при `scoped()`→`singleton()`.
  - ❌ остаётся зелёным при `singleton`-биндинге; покрывает только values ИЛИ только enabled; конструирует path-сервисы руками (ломается после Task 4); `sleep()`/зависимость от порядка.

- [x] **Task 2: Регрессионный лок «FeatureRepository scoped, не singleton»** — дополнить `tests/Feature/ModuleLoaderServiceProviderTest.php` методом с reflection-проверкой `Container::$scopedInstances` ⊇ `FeatureRepositoryInterface`. Закрывает roadmap-пункт проверки `FeatureRepository ≠ singleton`, реализуется как feature-level container guard.
  - ✅ метод утверждает scoped через `scopedInstances`; падает при `singleton()`; suite Feature зелёный.
  - ❌ проходит и при `singleton`; помещён в Architecture-suite (нет контейнера); дублирует поведенческий метод без reflection.

- [x] **Task 3: Широкий arch-гард «нет `Illuminate\Http\Request` в `src/`»** — добавить в `tests/Architecture/ArchitectureTest.php` правило `expect('DimitrienkoV\LaravelModules')->not->toUse('Illuminate\Http\Request')` (сейчас 0 вхождений → проходит, фиксирует инвариант).
  - ✅ `composer test:arch` зелёный; падает при добавлении `use Illuminate\Http\Request;` в `src/`.
  - ❌ проходит при наличии `Request` (опечатка FQCN/namespace); ломает зелёный arch из-за легитимного кода.

### Phase 2: DI-чистка (выбранный extra)

- [x] **Task 4: Скалярная инъекция конфига в 3 path-сервиса** — `src/Support/ModuleStatePaths.php` (`modules.paths.state`, `modules.paths.directories`), `src/Application/Support/ModuleDirectoryPaths.php` (`modules.paths.directories`, `modules.paths.backup`), `src/Registry/ModuleDirectoryScanner.php` (`modules.paths.directories`): убрать `Config\Repository` из конструкторов, передавать разрезолвленные скаляры. Обновить 3 биндинга в `ModuleLoaderServiceProvider` (строки 266/286/336) + все call sites, найденные командой `rg -n "new ModuleStatePaths|new ModuleDirectoryScanner|new ModuleDirectoryPaths|ModuleStatePaths\\(|ModuleDirectoryScanner\\(|ModuleDirectoryPaths\\(" tests src`. Атомарно — suite остаётся зелёным.
  - ✅ 0 вхождений `Config\Repository` в трёх файлах; поведение идентично (исключения/дефолты сохранены); `composer test` + `composer phpstan` зелёные без новых `ignoreErrors`; `array` аннотированы `@param list<string>`.
  - ❌ хоть один класс держит `Repository`; изменилось поведение/исключения; просадка type coverage; остался старый сайт конструирования; введён facade/helper/`Request` или класс перестал быть `final readonly`.

### Phase 3: Документация

- [x] **Task 5: `docs/octane.md`** — русская страница в house-style (nav-крошка, H1-фраза, `## See Also`): дуальный контракт (values свежие / enabled+модули+артефакты заморожены), когда нужен `octane:reload`, почему пакет осознанно не трогает `flush`/`warm`, гарантии-тесты. MoonShine reload-триггер — как roadmap. Без BC/миграций.
  - ✅ однозначно описан дуальный контракт; есть раздел про `octane:reload` и про отсутствие `flush`/`warm`; нет противоречий коду.
  - ❌ предлагает `flush`/`warm`/правку `config/octane.php`; утверждает, что `settings.values` требуют reload или `enabled` подхватывается без reload; MoonShine как текущий runtime; англ./не в стиле; упоминания BC.

- [x] **Task 6: Навигация README + AGENTS** (depends on 5) — добавить строку README `| [Octane](docs/octane.md) | Octane worker contract и reload-операционка |` в docs-таблицу `README.md` после `Logging`; добавить строку AGENTS с колонками `Octane`, `docs/octane.md`, `Octane worker contract и reload-операционка` в docs-таблицу `AGENTS.md` после `Logging`. Без дублирования контента.
  - ✅ строка есть в обоих файлах, ссылка рабочая, только строка-ссылка (без копирования тела).
  - ❌ добавлено только в один файл; битая ссылка; скопирован крупный фрагмент `octane.md`.

### Phase 4: Quality gate

- [x] **Task 7: Финальный гейт** (depends on 1–6) — строго по порядку: `composer rector` → `composer format` → `composer phpstan` → `composer test` (правило Post-Fix Quality Step; `:dry` не использовать для применяемого плана).
  - ✅ rector/format идемпотентны (повторный `:dry` чистый); phpstan 0 ошибок без baseline; все 3 suite зелёные.
  - ❌ любой гейт красный; rector/format всё ещё предлагают правки; новые `ignoreErrors`/baseline; неверный порядок (format раньше rector); подмена на `:dry`.

## Проверка на нарушение правил проекта

Сверено против `.ai-factory/rules/*` и `skill-context/aif-plan/SKILL.md` — нарушений нет:

- **No facades/helpers/Request в `src/`** (code-quality, runtime): Task 4 убирает зависимость на `Repository`-объект, не вводя фасадов; Task 3 добавляет гард против `Request`. ✔
- **`final readonly` + `declare(strict_types=1)` + constructor promotion**: Task 4 сохраняет `final readonly` у трёх классов; новые тест-файлы начинаются с `declare(strict_types=1);`. ✔
- **No mutable static / no long-lived `Application`/`Request`/`Router` в singleton** (runtime): план их не вводит; Task 4 уменьшает захват runtime-объекта (`Repository`) в singleton. ✔
- **Package Core Logging Scope** (skill-context): verbose-логи в `src/` не планируются — фидбэк через исключения/вывод/тесты. ✔
- **Test Stub Planning / arch tests для новых structural rules** (skill-context, base): новый инвариант (нет `Request`) покрыт arch-тестом (Task 3); новый контракт поведения — feature-тестами (Task 1, 2). Новых VO/сервисов не вводится → companion-тесты для классов не требуются. ✔
- **Contract-First** (skill-context): новых service boundaries/интерфейсов нет → отдельная contract-задача не нужна. ✔
- **Post-Fix Quality Step** (skill-context): финальная задача гоняет `composer rector` → `composer format` → верификация в точном порядке (Task 7). ✔
- **PHPStan только `src/`, level max, 100% type coverage** (code-quality, feedback): Task 4 требует `@param list<string>` для удержания coverage; тесты не анализируются phpstan, но гоняются `composer test`. ✔
- **Документируй только фактический runtime; roadmap помечай** (base): Task 5 помечает MoonShine reload-триггер как roadmap; без BC/миграций (нет публичной 1.x). ✔
- **PR в Conventional Commits (англ.)**: commit-plan на английском в CC-формате. ✔
