# AGENTS.md

> Карта проекта для AI-агентов. Поддерживай в актуальном виде при существенных изменениях структуры. Детали продукта и архитектуры смотри в `.ai-factory/DESCRIPTION.md` и `.ai-factory/ARCHITECTURE.md`.

## Обзор проекта

`dimitrienkov0/laravel-modules` — manifest-driven Laravel-пакет для модульной архитектуры приложений. Текущий срез v2.0 core реализует обнаружение модулей по `module.json`, типизированный manifest layer, dependency-aware `ModuleRegistry`, loader-pipeline, runtime feature toggles, production cache и опциональный MoonShine autoload bridge.

Команды жизненного цикла установки/обновления модулей, генераторы `make:* --module` и полноценный MoonShine admin-UI описаны в roadmap, но не должны документироваться как уже реализованные.

## Технологический стек

- **Язык:** PHP 8.3+
- **Фреймворк:** Laravel 12 / 13
- **Admin-UI (опционально):** MoonShine 4 через `moonshine/core` и `moonshine/contracts`
- **Optional integrations:** Inertia 2 для `Routes/inertia.php`
- **Тесты:** Pest 3 + `pestphp/pest-plugin-arch` для архитектурных тестов; PHPUnit 11/12 + Orchestra Testbench 10/11 + Mockery для unit/feature
- **Качество:** PHPStan 2 + Larastan 3, Rector 2, PHP-CS-Fixer; целевой стиль — PER Coding Style 3.0 с проектными правилами
- **Без БД:** состояние модулей и feature values хранятся в `module.json`

## Структура проекта

```
.
├── src/                              # код пакета
│   ├── Console/Commands/Modules/     # реализованные команды modules:optimize, modules:optimize-clear
│   ├── Contracts/                    # публичные интерфейсы ядра
│   ├── Exceptions/                   # типизированные runtime-исключения
│   ├── Loaders/                      # реализации LoaderInterface
│   │   └── Pipeline/                 # ModuleLoaderPipeline, оркестрация лоадеров
│   ├── Manifest/                     # manifest services, parser helpers, VO, enums
│   │   ├── Enums/                    # FeatureType
│   │   ├── Parsing/                  # фабрики и нормализаторы manifest fields
│   │   └── VO/                       # Module, ManifestMeta, FeatureValues и другие VO
│   ├── MoonShine/                    # MoonShineModuleAutoloader, optional bridge
│   ├── Providers/                    # ModuleLoaderServiceProvider
│   ├── Registry/                     # ModuleDirectoryScanner, ModuleRegistryCache
│   └── Support/                      # ModuleLayout, AtomicFileWriter, AtomicJsonWriter, ContainerLifecycleHooks, TopologicalSorter, ApplicationNamespaceResolver
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
| `src/Loaders/Pipeline/ModuleLoaderPipeline.php` | Сортирует лоадеры по `priority()` и применяет их к enabled-модулям в `ModuleRegistry::loadOrder()` |
| `src/Manifest/ModuleRegistry.php` | Реестр модулей: scan/cache, топологическая сортировка, lookup |
| `src/Manifest/ModuleManifestRepository.php` | Единственная точка чтения/валидации/записи `module.json` |
| `src/Manifest/FeatureRepository.php` | Scoped runtime API для feature toggles |
| `src/Registry/ModuleRegistryCache.php` | Формат и чтение `bootstrap/cache/modules.php` |
| `config/modules.php` | Корневой конфиг директорий модулей и типов маршрутов |
| `tests/Architecture/ArchitectureTest.php` | Pest arch-инварианты проекта |
| `.github/PULL_REQUEST_TEMPLATE.md` | Единый чеклист PR |

## Реализованный runtime

- `ModuleDirectoryScanner` ищет подпапки с `module.json` в `modules.paths.directories`.
- `ModuleManifestRepository` валидирует manifest и гидратирует VO из `src/Manifest/VO`.
- `TopologicalSorter` сортирует модули по `meta.dependencies` и проверяет Composer SemVer constraints.
- `ModuleRegistry` читает `bootstrap/cache/modules.php`, если cache существует, иначе сканирует файловую систему.
- `ModuleLoaderPipeline` запускает 15 default loaders: `ConfigLoader`, `ServiceProviderLoader`, `MigrationLoader`, `FactoryLoader`, `LangLoader`, `ViewLoader`, `BladeComponentLoader`, `EventLoader`, `ObserverLoader`, `PolicyLoader`, `CommandLoader`, `MiddlewareLoader`, `RouteLoader`, `ConsoleRouteLoader`, `BroadcastLoader`. Pipeline изолирует ошибки: исключение в одном loader не останавливает загрузку остальных.
- `MoonShineModuleAutoloader` подключается только при наличии MoonShine `CoreContract`.
- `FeatureRepositoryInterface` биндится как scoped и читает актуальные `settings.values` из `module.json`, а не из production cache.

## Документация

| Документ | Путь | Описание |
|----------|------|----------|
| README | `README.MD` | Landing-page пакета |
| Getting Started | `docs/getting-started.md` | Установка и первая проверка |
| Module Structure | `docs/module-structure.md` | Поддерживаемые пути модулей |
| Manifest | `docs/manifest.md` | Контракт `module.json` |
| Configuration | `docs/configuration.md` | Конфиг и маршруты |
| Architecture | `docs/architecture.md` | Registry, cache, loaders |
| Feature Toggles | `docs/feature-toggles.md` | Runtime settings API |
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
- **Пиши `module.json` только через `ModuleManifestRepository::saveValues()` или `updateState()`.** Не используй прямой `file_put_contents` для manifest writes.
- **Перед PR/коммитом запускай проверки отдельно:** `composer format`, `composer phpstan`, `composer test`. Для review также полезны `composer format:dry` и `composer rector:dry`.
- **PR title — на английском в Conventional Commits формате.** Остальные секции PR-шаблона ведутся на русском.
