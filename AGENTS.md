# AGENTS.md

> Карта проекта для AI-агентов. Поддерживай в актуальном виде при существенных изменениях структуры. Детали продукта и архитектуры смотри в `.ai-factory/DESCRIPTION.md` и `.ai-factory/ARCHITECTURE.md`.

## Обзор проекта

`dimitrienkov0/laravel-modules` — manifest-driven Laravel-пакет для модульной архитектуры приложений. Текущий срез v2.0 core реализует обнаружение модулей по `module.json`, типизированный manifest layer, dependency-aware `ModuleRegistry`, loader-pipeline, runtime feature toggles, production cache, lifecycle UseCase-классы и Artisan-команды (`make:module`, `modules:install/update/remove/enable/disable/list`), module-aware режим native-генераторов (`make:* --module`) и архитектурные генераторы (`make:use-case/action/query/dto/vo`), а также опциональный MoonShine autoload bridge.

Полноценный MoonShine admin-UI, lifecycle-события и marketplace-доставка (подпись/удалённый fetch) описаны в roadmap, но не должны документироваться как уже реализованные.

## Технологический стек

- **Язык:** PHP 8.3+
- **Фреймворк:** Laravel 12 / 13
- **Admin-UI (опционально):** MoonShine 4 через `moonshine/core` и `moonshine/contracts`
- **Optional integrations:** Inertia 2 для `Routes/inertia.php`
- **Тесты:** Pest 3 + `pestphp/pest-plugin-arch` для архитектурных тестов; PHPUnit 11/12 + Orchestra Testbench 10/11 + Mockery для unit/feature
- **Качество:** PHPStan 2 + Larastan 3, Rector 2, PHP-CS-Fixer; целевой стиль — PER Coding Style 3.0 с проектными правилами
- **Без БД:** manifest (`module.json`) иммутабелен — хранит только `meta` (включая опциональный `group`) и `settings.schema`; mutable state (`enabled`, timestamps, `settings.values`, source provenance) хранится в `state.json` (`storage/app/private/modules/{name}/`)

## Структура проекта

```
.
├── src/                              # код пакета
│   ├── Application/                  # lifecycle/optimize UseCase-классы, DTO и support-сервисы
│   │   ├── DTOs/                     # results/config objects для команд
│   │   ├── Enums/                    # LifecycleOperation, ModuleSourceKind, RemoveStrategy
│   │   ├── Support/                  # source staging, directory ops, dependency guards, rollback
│   │   └── UseCases/                 # scaffold/install/update/remove/enable/disable/optimize flows
│   ├── Console/
│   │   ├── Commands/Modules/         # Artisan adapters: make:module и modules:* команды
│   │   ├── Commands/Make/            # module-aware make:* subclasses + арх-генераторы (use-case/action/query/dto/vo)
│   │   └── Concerns/                 # ModuleAwareGenerator, ArchitecturalGenerator traits
│   ├── Contracts/                    # публичные интерфейсы ядра
│   ├── Exceptions/                   # типизированные runtime-исключения
│   ├── Loaders/                      # реализации LoaderInterface (load(): LoadReport)
│   │   ├── Pipeline/                 # ModuleLoaderPipeline, оркестрация лоадеров
│   │   └── VO/                       # LoadReport, LoadStatus, SkipReason, PipelineRunSummary
│   ├── Manifest/                     # manifest services, state repository, parser helpers, VO, enums
│   │   ├── Enums/                    # FeatureType, ModuleKind, ModuleOriginKind
│   │   ├── Parsing/                  # фабрики и нормализаторы manifest fields
│   │   └── VO/                       # Module, ManifestMeta, ModuleState, ModuleStateDocument, ModuleOrigin, FeatureValues и другие VO
│   ├── MoonShine/                    # MoonShineModuleAutoloader, optional bridge
│   ├── Providers/                    # ModuleLoaderServiceProvider, ModuleGeneratorCommandsServiceProvider
│   ├── Registry/                     # scanner, snapshot builder, cache и registry VO
│   └── Support/                      # layout/state paths, atomic writers, filesystem, sorter, namespace resolver
│       └── Logging/                  # ModuleLogger, NullModuleDiagnostics (opt-in диагностика)
├── config/
│   └── modules.php                   # дефолтные директории модулей и route types
├── tests/
│   ├── Architecture/                 # Pest arch-инварианты
│   ├── Feature/                      # Testbench feature-тесты provider/commands/bindings
│   ├── Fixtures/                     # JSON fixtures для manifest tests
│   ├── Support/                      # ModuleFactory и test helpers
│   └── Unit/                         # unit-тесты manifest, loaders, registry, support, MoonShine
├── .github/
│   └── PULL_REQUEST_TEMPLATE.md      # PR-шаблон с quality checklist
├── .ai-factory/                      # AI Factory контекст, планы, roadmap, rules
├── .codex/                           # Codex agents/config для проекта
├── .claude/                          # Claude agents/settings для проекта
├── composer.json                     # зависимости, autoload, scripts
├── phpunit.xml.dist                  # suites Architecture/Unit/Feature
├── phpstan.neon.dist                 # PHPStan level 8 + Larastan
├── rector.php                        # Rector rules
└── .php-cs-fixer.dist.php            # formatter rules
```

## Ключевые точки входа

| Файл | Назначение |
|------|-----------|
| `src/Providers/ModuleLoaderServiceProvider.php` | Главный service provider: DI bindings, default loaders, MoonShine bridge, optimizer hooks |
| `src/Loaders/Pipeline/ModuleLoaderPipeline.php` | Сортирует лоадеры по `priority()` и применяет их к enabled-модулям в `ModuleRegistry::all()` |
| `src/Manifest/ModuleRegistry.php` | Реестр модулей: scan/cache, топологическая сортировка, lookup |
| `src/Manifest/ModuleManifestRepository.php` | Чтение и запись иммутабельного manifest (`module.json`): `load()` и `writeManifest()` |
| `src/Manifest/ModuleStateRepository.php` | Чтение/запись mutable state (`state.json`): enabled, timestamps, settings values |
| `src/Manifest/FeatureRepository.php` | Scoped runtime API для feature toggles |
| `src/Registry/ModuleRegistryCache.php` | Формат и чтение `bootstrap/cache/modules.php` |
| `src/Registry/ModuleRegistrySnapshotBuilder.php` | Fresh filesystem scan и сборка snapshot |
| `src/Application/UseCases/ScaffoldModuleUseCase.php` | Создание нового модуля через `make:module` |
| `src/Application/UseCases/InstallModuleUseCase.php` | Установка модуля из `.zip` source |
| `src/Application/UseCases/UpdateModuleUseCase.php` | Обновление модуля с backup и merge values |
| `src/Application/UseCases/RemoveModuleUseCase.php` | Удаление модуля с backup или permanently |
| `config/modules.php` | Корневой конфиг директорий модулей и типов маршрутов |
| `tests/Architecture/ArchitectureTest.php` | Pest arch-инварианты проекта |
| `.github/PULL_REQUEST_TEMPLATE.md` | Единый чеклист PR |

## Реализованный runtime

- `ModuleDirectoryScanner` ищет подпапки с `module.json` в `modules.paths.directories`.
- `ModuleManifestRepository` валидирует manifest и гидратирует VO из `src/Manifest/VO`; содержит только `load()` и `writeManifest()`.
- `ModuleStateRepository` читает/пишет mutable state из `state.json` (`storage/app/private/modules/{name}/`).
- `TopologicalSorter` сортирует модули по `meta.dependencies` и проверяет Composer SemVer constraints.
- `ModuleRegistry` читает `bootstrap/cache/modules.php`, если cache существует, иначе сканирует файловую систему.
- `ModuleLoaderPipeline` запускает 15 default loaders: `ConfigLoader`, `ServiceProviderLoader`, `MigrationLoader`, `FactoryLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader`, `EventLoader`, `ObserverLoader`, `PolicyLoader`, `CommandLoader`, `MiddlewareLoader`, `RouteLoader`, `ConsoleRouteLoader`, `BroadcastLoader`. Pipeline изолирует ошибки: исключение в одном loader не останавливает загрузку остальных. Каждый `load()` возвращает `LoadReport` (`applied`/`skipped`), а pipeline скармливает его диагностике.
- Opt-in диагностика (`modules.logging`, off by default): `ModuleDiagnosticsInterface` биндится как `ModuleLogger` поверх host-канала PSR-3 либо `NullModuleDiagnostics`. Discovery/cache/pipeline/lifecycle инъецируют контракт (дефолт — null-object) и пишут только whitelisted-скаляры. См. `docs/logging.md`.
- `MoonShineModuleAutoloader` подключается только при наличии MoonShine `CoreContract`.
- `FeatureRepositoryInterface` биндится как scoped и читает актуальные `settings.values` из `state.json` через `ModuleStateRepository`, а не из production cache.
- Lifecycle-команды являются тонкими adapters над `Application/UseCases`; install/update валидируют source до копирования, reject'ят source `state.json`, используют backup/rollback boundaries и инвалидируют optimized registry cache после успешной мутации.

## Документация

| Документ | Путь | Описание |
|----------|------|----------|
| README | `README.md` | Landing-page пакета |
| Getting Started | `docs/getting-started.md` | Установка и первая проверка |
| Module Structure | `docs/module-structure.md` | Поддерживаемые пути модулей |
| Manifest | `docs/manifest.md` | Контракт `module.json` |
| Configuration | `docs/configuration.md` | Конфиг и маршруты |
| Architecture | `docs/architecture.md` | Registry, cache, lifecycle, диагностика |
| Feature Toggles | `docs/feature-toggles.md` | Runtime settings API |
| Logging | `docs/logging.md` | Opt-in диагностический слой и каталог событий |
| CLI | `docs/cli.md` | Реализованные команды |
| Contributing | `docs/contributing.md` | Quality gates и PR |

## AI-контекст

| Файл | Назначение |
|------|-----------|
| `AGENTS.md` | Быстрая карта проекта для AI-агентов |
| `.ai-factory/DESCRIPTION.md` | Спецификация продукта и текущего runtime |
| `.ai-factory/ARCHITECTURE.md` | Архитектурные решения и правила зависимостей |
| `.ai-factory/config.yaml` | Настройки AI Factory: язык, пути, git-flow, rules hierarchy |
| `.ai-factory/rules/*.md` | Проектные правила. Обновлять через `$aif-rules`, не вручную в задачах документации |
| `.codex/agents/*.toml` | Project-local Codex agent definitions |
| `.claude/agents/*.md` | Project-local Claude agent definitions |

## Правила для агентов

- **Декомпозируй shell-команды.** Не объединяй через `&&` команды, требующие проверки результата каждой. Некорректно: `git checkout main && git pull`. Корректно: сначала `git checkout main`, затем отдельно `git pull origin main`.
- **Не трогай `.ai-factory/rules/*` без явного запроса.** Для правил используется отдельный workflow `$aif-rules`.
- **Документируй фактический runtime отдельно от roadmap.** Нереализованные lifecycle-команды, генераторы, admin pages и дополнительные лоадеры помечай как roadmap, а не как текущую функциональность.
- **Никаких фасадов и хелперов в `src/`.** Только DI; архитектурные тесты запрещают `Illuminate\Support\Facades`.
- **Никаких `dd()`/`dump()`/`var_dump()`/`print_r()`/`exit()`/`die()` в `src/`.** Архитектурный тест поломается.
- **`declare(strict_types=1);` обязателен** в каждом новом `.php`-файле под `src/`, `tests/` и `stubs/`, если `stubs/` существует.
- **`module.json` иммутабелен в runtime.** Manifest пишется только через `ModuleManifestRepository::writeManifest()`. Mutable state (`enabled`, timestamps, `settings.values`) пишется через `ModuleStateRepository`. Не используй прямой `file_put_contents`.
- **Перед PR/коммитом запускай проверки отдельно:** `composer format`, `composer phpstan`, `composer test`. Для review также полезны `composer format:dry` и `composer rector:dry`.
- **PR title — на английском в Conventional Commits формате.** Остальные секции PR-шаблона ведутся на русском.
