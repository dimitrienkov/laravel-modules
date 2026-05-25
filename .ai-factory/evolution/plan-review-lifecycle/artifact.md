# Ревью плана lifecycle UseCase-классов и Artisan-команд

> Аналитический обзор плана `feature-lifecycle-usecases-commands.md` на предмет архитектурных ограничений, расширяемости и рисков до начала имплементации.

## 1. Соответствие архитектуре

### 1.1 Dependency rules и новый слой Application

План вводит три новые директории: `Application/UseCases/`, `Application/Support/`, `Application/DTOs/`. В текущем `ARCHITECTURE.md` слой `Application` не описан — документ покрывает `Manifest`, `Registry`, `Loaders`, `Support`, `Providers`, `MoonShine`, `Contracts`. Это не ошибка плана — слой `Application` является логичным развитием архитектуры, но его dependency rules необходимо зафиксировать.

**Предлагаемые dependency rules для Application:**

- `Application/UseCases → Contracts + Manifest\VO + Application/Support + Application/DTOs + Support`
- `Application/Support → Contracts + Manifest\VO + Support + Registry`
- `Application/DTOs → ∅` (чистые value objects без зависимостей)
- `Application` не должен зависеть от `Loaders`, `Providers`, `MoonShine`
- `Console/Commands → Application/UseCases + Contracts` (тонкие обёртки)

Текущие разрешённые направления в ARCHITECTURE.md (`Provider → Contracts/Manifest/Registry/Support/Loaders/MoonShine`) нужно дополнить: `Provider → Application` для регистрации bindings и команд.

### 1.2 Контракты ядра

План корректно опирается на существующие контракты:

- **`ModuleManifestRepositoryInterface::updateState(Module, ManifestState): Module`** — используется для записи state при enable/disable/install/update. Реализация (`ModuleManifestRepository`) корректно перечитывает текущие `FeatureValues` и сохраняет всё атомарно через `AtomicJsonWriter`. ✓
- **`ModuleRegistryInterface::find(string): Module`** — используется для поиска модуля в UseCases. Бросает `ModuleNotFoundException`, что покрывает случай несуществующего модуля. ✓
- **`ModuleRegistryCache::forget()`** — удаляет `bootstrap/cache/modules.php`. Используется `LifecycleRegistryInvalidator`. ✓
- **`ModuleRegistry::reset()`** — очищает in-memory кеш. ✓

**Пробел в контракте:** `ModuleManifestRepository::load(string $modulePath)` использует `ApplicationNamespaceResolver` для определения namespace модуля. Resolver принимает только пути внутри `app_path()`. План правильно указывает, что `load()` нельзя использовать для staged/source paths, и предлагает `ManifestDocumentReader` + `ManifestValidatorInterface` для внешних источников. Однако staged path потребует отдельного способа получения meta + state из raw array — без конструирования полного `Module` VO (у которого обязательный `namespace`). Фактически `Module::fromManifest()` принимает `namespace` и `path` как внешние параметры, поэтому `ModuleSourcePreparer` может передать заглушку namespace при создании промежуточного Module для валидации.

### 1.3 Bootstrap flow

План регистрирует новые сервисы как singletons (`ZipExtractor`, `LifecycleRegistryInvalidator`, `ModuleSourcePreparer`) и новые команды в `boot()`. Это соответствует текущему flow:

1. `register()` → singleton bindings
2. `boot()` → команды, publish, pipeline

Новые bindings не конфликтуют с существующими. `LifecycleRegistryInvalidator` зависит от `ModuleRegistryCache` и `ModuleRegistry`, оба уже singleton — нет проблем с lifecycle контейнера.

### 1.4 Вердикт по архитектуре

План **хорошо совместим** с текущей архитектурой. Единственный значимый пробел — отсутствие формализованных dependency rules для нового слоя `Application` в `ARCHITECTURE.md`. Задача 13 (документация) покрывает обновление `ARCHITECTURE.md`, но стоит явно зафиксировать rules до начала кодирования.

---

## 2. Согласованность с ROADMAP

### 2.1 Покрытие задач Фазы 2

| Задача ROADMAP | Покрытие в плане | Статус |
|----------------|-----------------|--------|
| `InstallModuleUseCase` | Задача 8 | ✓ Полное |
| `UpdateModuleUseCase` | Задача 9 | ✓ Полное |
| `RemoveModuleUseCase` | Задача 10 | ✓ Полное |
| `EnableModuleUseCase` | Задача 3 | ✓ Полное |
| `DisableModuleUseCase` | Задача 3 | ✓ Полное |
| `ScaffoldModuleUseCase` | Задача 5 | ✓ Полное |
| `make:module` | Задача 5 | ✓ Полное |
| `modules:list` | Задача 4 | ✓ Полное |
| `modules:enable/disable` | Задача 4 | ✓ Полное |
| `modules:install` | Задача 8 | ✓ Полное |
| `modules:update` | Задача 9 | ✓ Полное |
| `modules:remove` | Задача 10 | ✓ Полное |
| `modules:optimize/clear` | Уже реализовано | ✓ Фаза 1 |

### 2.2 Расхождения

1. **Commit messages на русском** — план коммитов содержит сообщения на русском (`feat(lifecycle): добавить команды включения отключения...`), тогда как DESCRIPTION.md и PR-шаблон требуют Conventional Commits на английском. Это противоречие.

2. **ROADMAP упоминает `InstallHook::handle`** в описании `modules:install` — "валидация манифеста до распаковки, dependency check, `InstallHook::handle`". План не упоминает `InstallHook`. Если это осознанное решение отложить хуки до Фазы 3 (lifecycle events), стоит явно отразить это в плане.

3. **ROADMAP описывает `modules:remove` с откатом миграций** — "откат миграций, перенос в `backup/`". План явно отказывается от авто-отката миграций с напоминанием пользователю. Это разумное решение, но создаёт расхождение с текстом ROADMAP, который нужно обновить.

4. **Blade directive `@feature(...)`** упомянута в ROADMAP Фазы 1/2 как presentation bridge. Не входит в scope этого плана (корректно — это отдельная задача), но стоит убедиться, что она не блокируется lifecycle-изменениями.

### 2.3 Вердикт по ROADMAP

План покрывает все lifecycle-задачи Фазы 2 полностью. Три расхождения (commit messages, InstallHook, auto-rollback миграций) — это осознанные архитектурные решения, которые нужно отразить в обновлённом ROADMAP для синхронизации.

---

## 3. Расширяемость для Фазы 3

### 3.1 Lifecycle events (ModuleInstalled, ModuleEnabled, и т.д.)

План создаёт UseCase-классы как единую точку мутации состояния модуля. Это **идеальная** точка для внедрения событий в Фазе 3: достаточно добавить `event(new ModuleEnabled($module))` после успешной мутации. UseCase не нужно менять структурно — только добавить один вызов.

**Риск:** если MoonShine admin UI (тоже Фаза 2) будет вызывать `ModuleManifestRepository::updateState()` напрямую, минуя UseCases, то events не будут fired. Рекомендация: MoonShine admin UI ОБЯЗАН проходить через UseCases, не через repository напрямую. Это стоит зафиксировать как архитектурное правило.

### 3.2 Manifest signing (Ed25519/GPG)

Signing проверяется в `modules:install` до распаковки. План создаёт `ModuleSourcePreparer` как единый этап подготовки source — сюда можно добавить проверку подписи как дополнительный шаг. `ManifestValidatorInterface` уже используется для валидации — можно расширить контракт или создать отдельный `SignatureValidatorInterface`.

**Нет архитектурных тупиков.** Подпись добавляется как ещё один guard в `ModuleSourcePreparer`.

### 3.3 Marketplace и удалённая поставка

`ModuleSourcePreparer` поддерживает два типа source: zip и directory. Для marketplace нужен третий тип: HTTP/S3 download → temporary zip → extract. Текущий дизайн `ModuleSourcePreparer` не использует strategy pattern для типов source — логика переключения встроена в класс.

**Рекомендация:** Пока не нужен strategy pattern (YAGNI для двух типов), но стоит задокументировать, что добавление нового типа source потребует модификации `ModuleSourcePreparer`. Альтернативно, если хочется подготовить расширяемость сейчас — вынести определение типа source в отдельный метод, который можно override.

### 3.4 modules:pack

План создаёт `ZipExtractor` только для распаковки. `modules:pack` (Фаза 3) — это обратная операция. `ZipExtractor` не становится помехой, но его имя подсказывает, что в будущем понадобится парный `ZipPacker` или объединённый `ZipArchiveManager`. Никакого блокирующего ограничения нет.

### 3.5 Extended feature toggle types

Plan не затрагивает `FeatureSchema`/`FeatureDefinition`/`FeatureValues`. Update UseCase мержит explicit `settings.values` через существующий VO-слой. Новые типы (`multiselect`, `json`, `file`) не блокируются.

### 3.6 Вердикт по расширяемости

**План не создаёт архитектурных тупиков для Фазы 3.** Единственный значимый момент — необходимость обеспечить, чтобы все lifecycle-мутации шли через UseCases (а не напрямую через repository), иначе events в Фазе 3 не будут срабатывать.

---

## 4. Сравнение с аналогами

### 4.1 nwidart/laravel-modules

| Аспект | nwidart/laravel-modules | Данный план |
|--------|------------------------|-------------|
| Хранение state | Центральный `modules_statuses.json` | Per-module `module.json` |
| Dependency management | Нет | Composer SemVer constraints |
| Enable/disable | `Module::enable()`/`disable()` через activator | UseCase + `updateState()` через repository |
| Install | `module:install` из Composer registry | `modules:install` из zip/directory с валидацией |
| Scaffold | `module:make` с генерацией structure | `make:module` со stubs |
| Atomic writes | Нет | `AtomicJsonWriter` с flock + temp + rename |
| Settings/features | Нет | `settings.schema` + `settings.values` в manifest |
| Lifecycle events | Нет в ядре | Не в текущем плане, но подготовлено для Фазы 3 |
| Migration handling | Авто-запуск | Ручной (safer) |

**Вывод:** план значительно превосходит nwidart по безопасности (atomic writes, validation-before-copy, dependency checks), гибкости (per-module settings) и коммерческой пригодности (zip-install для поставки). Подход nwidart с центральным файлом состояний создаёт merge conflicts при командной работе; per-module manifest масштабируется лучше.

### 4.2 caffeinated/modules (archived)

Проект архивирован и больше не поддерживается. Архитектурно примитивнее nwidart: нет dependency management, нет feature toggles, нет atomic writes. Не является релевантным ориентиром для v2.0.

### 4.3 Уникальные преимущества текущего подхода

1. **Manifest-driven с typed VO** — ни один аналог не использует строгую типизацию manifest через value objects.
2. **Staged validation** — install/update валидируют source ДО модификации target. Аналоги не делают staged validation.
3. **Cache invalidation как отдельный concern** — `LifecycleRegistryInvalidator` изолирует сброс кеша. В аналогах кеш сбрасывается ad-hoc.
4. **Rollback-friendly update** — backup + restore при ошибке. Аналоги не предлагают rollback.

---

## 5. Выявленные риски и пробелы

### 5.1 TOCTOU race condition при enable/disable

`EnableModuleUseCase` выполняет check-then-write: проверяет dependencies, затем пишет state. Два параллельных вызова `modules:enable` могут оба пройти проверку и записать state. `AtomicJsonWriter` обеспечивает атомарность одной записи, но не транзакционность check+write.

**Серьёзность:** Низкая для CLI-команд (маловероятно одновременное выполнение), но может стать проблемой для MoonShine admin UI с concurrent users.

**Рекомендация:** Документировать ограничение. Для MoonShine UI рассмотреть flock на уровне UseCase (file-based advisory lock на `module.json`).

### 5.2 ModuleSourcePreparer возвращает массив вместо typed result

План указывает: "Возвращает подготовленный source path и массив manifest через внутренний результат без публичного DTO, чтобы `ScaffoldModuleConfig` оставался единственным DTO". Но `ScaffoldModuleConfig` — это DTO для scaffold-операции, а результат `ModuleSourcePreparer` — это DTO для install/update. Они не конкурируют.

**Рекомендация:** Создать `final readonly class PreparedSource` с полями `string $path` и `array $manifest` (или ещё лучше — `ManifestMeta $meta`). Typed return безопаснее массива и соответствует паттерну `final readonly` VO проекта.

### 5.3 TopologicalSorter reuse для Enable

План говорит: Enable "переиспользует текущую логику `TopologicalSorter`". Но `TopologicalSorter::sort()` принимает весь массив модулей и валидирует **все** enabled-модули, а не один конкретный. Для Enable нужно:

1. Получить текущий `Module` из registry
2. Симулировать его enabled-состояние (через `withState()`)
3. Заменить его в массиве модулей
4. Прогнать `TopologicalSorter::sort()` на обновлённом массиве
5. Если sort прошёл без исключений — dependency check пройден

Это рабочий подход, но требует создания полного массива модулей с подменой одного элемента. Альтернатива — выделить метод `validateDependenciesForModule(Module $module, array $allModules)` в `TopologicalSorter`, чтобы не пересортировать весь граф.

**Рекомендация:** Явно описать в плане, как именно Enable будет проверять dependencies — полная пересортировка или targeted check.

### 5.4 Install rollback при ошибке dependency

План указывает: "install не должен оставить частично скопированный модуль при ошибке". Но механизм rollback не специфицирован:

- Если dependency check выполняется **до** копирования файлов — rollback не нужен (файлы ещё не скопированы).
- Если dependency check выполняется **после** копирования (потому что для полной проверки нужен установленный manifest) — нужен удаление target directory.

Из контекста плана: source валидируется через `ModuleSourcePreparer` ДО копирования, а dependency check также выполняется ДО финального включения. Если это так — rollback для install может быть просто удалением скопированной директории. Стоит явно задокументировать порядок шагов install.

### 5.5 ext-zip dependency

План предлагает два варианта: `ext-zip` в `require` или guard. Если `ext-zip` добавляется в `require`, это становится hard dependency всего пакета, хотя zip-install — лишь одна из функций. Модули можно scaffold'ить и enable/disable без zip.

**Рекомендация:** Использовать `suggest` вместо `require` и runtime guard в `ZipExtractor`. Бросать `ModuleInstallException` с сообщением "ext-zip required for archive installation" при попытке распаковки без расширения.

### 5.6 Производительность при больших модулях

План не анализирует поведение install/update/remove для модулей с большим количеством файлов (сотни миграций, десятки тысяч view-файлов). Конкретные риски:

- **Install из zip:** `ZipExtractor` извлекает все файлы за одну операцию. Для архива с 10,000+ файлов это может занять значительное время; если процесс прервётся, temp directory останется на диске.
- **Update backup:** полное копирование директории модуля перед заменой файлов. Для модулей с тяжёлым `Resources/` (assets, views) backup может быть медленным и занимать значительное место.
- **Remove backup:** аналогичная проблема — перемещение большой директории.

**Серьёзность:** Низкая для типичных модулей (десятки файлов), но значимая для edge case с asset-heavy модулями.

**Рекомендация:** Не усложнять текущую реализацию streaming'ом, но (1) задокументировать ограничение, (2) обеспечить cleanup temp directory при прерывании через `try/finally` в `ZipExtractor` и `ModuleSourcePreparer`, (3) в `modules:install`/`modules:update` показывать progress для крупных операций (Artisan `$this->output->progressBar()`).

### 5.7 Backup path для remove

План упоминает "deterministic backup path с collision-safe suffix", но не специфицирует формат. Вопросы:

- Куда помещается backup? В `storage/app/module-backups/`? В сам `app/Modules/.backup/`?
- Формат: `<name>-<timestamp>`? `<name>-<n>`?
- Очистка: кто и когда удаляет старые backups?

**Рекомендация:** Зафиксировать backup directory в `config/modules.php` (например, `modules.paths.backup_dir`), формат `<name>-<Ymd-His>`, и не предусматривать авто-очистку (ответственность host-приложения).

---

## 6. Рекомендации

### 6.1 До начала имплементации

| # | Рекомендация | Приоритет | Действие |
|---|-------------|-----------|----------|
| R1 | Зафиксировать dependency rules для `Application` слоя | Высокий | Добавить в ARCHITECTURE.md секцию `Application` с разрешёнными/запрещёнными направлениями |
| R2 | Уточнить механизм dependency check для Enable | Высокий | Описать в Задаче 3: полная пересортировка vs. targeted validation method |
| R3 | Создать typed `PreparedSource` result вместо массива | Средний | Добавить `src/Application/Support/PreparedSource.php` как `final readonly` |
| R4 | Обновить commit messages на английский | Средний | Привести план коммитов в соответствие с Conventional Commits на английском |
| R5 | Использовать `suggest` для `ext-zip` вместо `require` | Средний | Runtime guard в `ZipExtractor`, `suggest` в `composer.json` |
| R6 | Зафиксировать backup directory в config | Средний | Добавить `modules.paths.backup` в `config/modules.php` |
| R7 | Явно отложить `InstallHook` до Фазы 3 | Низкий | Добавить заметку в плане и обновить ROADMAP |
| R8 | Задокументировать TOCTOU ограничение | Низкий | Добавить секцию concurrency notes в Задачу 3 |
| R9 | Обновить ROADMAP: remove без авто-отката миграций | Низкий | Синхронизировать текст ROADMAP с решением плана |

### 6.2 Архитектурные правила для MoonShine UI

Зафиксировать правило до начала имплементации MoonShine admin UI:

> Все lifecycle-мутации модуля (enable, disable, install, update, remove) ОБЯЗАНЫ проходить через `Application\UseCases\*UseCase` классы. `ModuleManifestRepository::updateState()` и `saveValues()` напрямую из UI-слоя запрещены. Это обеспечивает единую точку для cache invalidation, dependency checks и будущих lifecycle events.

---

## 7. Влияние на существующие контракты и тесты

### 7.1 Контракты — без изменений

Текущие интерфейсы (`ModuleManifestRepositoryInterface`, `ModuleRegistryInterface`, `LoaderInterface`, `FeatureRepositoryInterface`, `ManifestValidatorInterface`, `NamespaceResolverInterface`) **не требуют изменений**. План корректно использует существующие методы:

- `ModuleManifestRepositoryInterface::updateState()` — для enable/disable/install/update
- `ModuleManifestRepositoryInterface::saveValues()` — для update merge values
- `ModuleRegistryInterface::find()` — для поиска модуля в каждом UseCase
- `ManifestValidatorInterface` — для валидации staged manifests

Единственный пробел — `ModuleManifestRepository::load()` не используется для staged paths (корректное ограничение), но `ManifestDocumentReader::read()` + `ManifestValidatorInterface::validate()` покрывают потребность.

### 7.2 Существующие тесты — совместимость

Текущий тестовый suite (261 тест, 1207 assertions) **не должен сломаться** при добавлении lifecycle-кода:

- **Architecture tests** (`tests/Architecture/ArchitectureTest.php`): Нужно **расширить**, но не модифицировать существующие правила. Новые arch-правила (Задача 12) добавляют assertions для `Application\UseCases\*`, `Application\Support\*`, `Application\DTOs\*`. Существующие правила (`final` classes, no facades, strict types) автоматически распространяются на новый код.
- **Unit tests loaders** (15 loader tests): Не затрагиваются — lifecycle UseCase-классы не модифицируют поведение лоадеров.
- **Unit tests manifest layer** (VO, parsing, validators): Не затрагиваются — UseCase-классы потребляют VO, не модифицируя их.
- **Feature tests optimize commands**: Не затрагиваются — `modules:optimize` / `modules:optimize-clear` не меняются.
- **Feature test scoped binding** (`FeatureRepositoryScopedBindingTest`): Не затрагивается — scoped behaviour не зависит от lifecycle-команд.

**Потенциальный риск:** Если `ModuleLoaderServiceProvider` значительно расширяется (Задача 11 добавляет bindings и command registration), integration tests, которые бутстрапят провайдер, могут потребовать `ext-zip` как зависимость test environment. Рекомендация: использовать runtime guard (R5), чтобы тесты работали без `ext-zip`.

---

## 8. Вердикт

**План хорошо продуман и архитектурно зрелый.** Он корректно опирается на существующие контракты, не создаёт архитектурных тупиков для Фазы 3, и значительно превосходит аналоги по безопасности и robustness.

**Основные сильные стороны:**
- Чёткое разделение на UseCases (бизнес-логика) и Commands (presentation)
- `LifecycleRegistryInvalidator` как единый concern для cache invalidation
- `ModuleSourcePreparer` изолирует подготовку source от бизнес-логики install/update
- Staged validation (проверка ДО модификации target)
- Explicit решение не авто-запускать миграции — safer для production

**Что нужно доработать до начала кодирования:**
- Зафиксировать dependency rules для нового `Application` слоя (R1)
- Уточнить механизм dependency check в Enable UseCase (R2)
- Рассмотреть typed result для ModuleSourcePreparer (R3)
- Исправить language коммит-сообщений (R4)
