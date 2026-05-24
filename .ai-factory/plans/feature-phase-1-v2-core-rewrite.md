# План реализации: Фаза 1 — ядро v2.0

Ветка: `feature/phase-1-v2-core-rewrite`
Создано: 2026-05-24

## Настройки
- Тестирование: да
- Диагностика: подробная
- Документация: да

Политика диагностики для этого пакета: не добавлять логи времени выполнения через `Log::*`, фасады, `dump()` или шумные записи в логи хост-приложения внутри `src/`. Для каждой задачи "подробная диагностика" означает проверяемые тестами сценарии, понятный вывод консольных команд там, где это уместно, типизированные исключения с полезными сообщениями и временную локальную отладку, удалённую до завершения работы.

## Связь с дорожной картой
Веха: "Фаза 1 — v2.0 ядро (переписать уже реализованное)"
Обоснование: план закрывает цель Фазы 1 из `.ai-factory/ROADMAP.md`: ядро с манифестом как источником правды, `ModuleRegistry`, чтение фичетоглов во время выполнения, конвейер лоадеров, переписанные текущие лоадеры и архитектурные тесты ядра.

## Текущее состояние
- Код времени выполнения всё ещё устроен в стиле 1.x: `src/Services/*LoaderService.php` сами сканируют директории модулей и вызываются через `autoload()`, а не через `LoaderInterface::load(Module)`.
- `ModuleLoaderServiceProvider` сейчас вызывает `ConfigLoaderService::autoload()` и в `register()`, и в `boot()`, регистрирует старые сервисы напрямую и не имеет конвейера через `ModuleRegistry`.
- `ServiceProviderLoaderService` и `MoonShineLoaderService` используют обнаружение через Composer classmap и reflection, хотя v2.0 явно отказывается от этой механики.
- Текущие unit-тесты в `tests/Unit/*LoaderServiceTest.php` ссылаются на старые методы и старый порядок зависимостей; их нужно заменить, а не адаптировать поверх старой архитектуры.
- `composer.json` всё ещё разрешает Laravel `^11` и держит MoonShine/Inertia как обязательные зависимости времени выполнения, хотя архитектура v2.0 требует Laravel 12/13 и опциональные интеграции.

## План коммитов
- Все сообщения коммитов должны быть на английском языке и в формате Conventional Commits.
- **Коммит 1** после задач 1-3: `feat: add v2 manifest contracts and value objects`
- **Коммит 2** после задач 4-6: `feat: add module registry and manifest persistence`
- **Коммит 3** после задач 7-9: `feat: boot modules through the loader pipeline`
- **Коммит 4** после задач 10-11: `test: cover v2 core architecture and loaders`
- **Коммит 5** после задачи 12: `docs: update v2 core runtime documentation`

## Задачи

### Этап 1: Границы пакета и контракты

- [x] Задача 1: Привести зависимости пакета и конфигурацию к границам ядра v2.0.

  Результат: `composer.json` и `composer.lock` поддерживают PHP 8.3+ и Laravel 12/13 для цели v2.0, убирают Laravel 11/Testbench 9 из матрицы Фазы 1, добавляют прямую runtime-зависимость `composer/semver` для проверки зависимостей модулей, а MoonShine/Inertia переводят в опциональные интеграции через `suggest` и dev-зависимости для тестов при необходимости. `config/modules.php` сохраняет только корневые директории модулей и настройки типов роутинга; подпути модуля уходят в `ModuleLayout`, настройки `autoload` в манифест не добавляются.

  Файлы: `composer.json`, `composer.lock`, `config/modules.php`, `README.MD` только если инструкция по установке станет неверной.

  Диагностика: логи времени выполнения не добавлять. Проверяемость обеспечивают `composer validate`, `composer phpstan` и тесты с понятными сообщениями. Опциональная интеграция должна завершаться безопасным ранним выходом или типизированным исключением, а не логированием.

- [x] Задача 2: Добавить публичные контракты и типизированные исключения ядра v2.0.

  Результат: созданы тонкие интерфейсы `LoaderInterface`, `ModuleRegistryInterface`, `ModuleManifestRepositoryInterface`, `FeatureRepositoryInterface`, `NamespaceResolverInterface`, `ManifestValidatorInterface`. Добавлены типизированные исключения времени выполнения для невалидного манифеста, отсутствующего модуля, отсутствующего ключа фичетогла, отсутствующих/отключённых/несовместимых зависимостей, циклов и ошибок записи манифеста.

  Файлы: `src/Contracts/LoaderInterface.php`, `src/Contracts/ModuleRegistryInterface.php`, `src/Contracts/ModuleManifestRepositoryInterface.php`, `src/Contracts/FeatureRepositoryInterface.php`, `src/Contracts/NamespaceResolverInterface.php`, `src/Contracts/ManifestValidatorInterface.php`, `src/Exceptions/*.php`.

  Диагностика: сообщения исключений должны содержать имя/путь модуля и нарушенный инвариант там, где это безопасно. Побочных логов не добавлять; тесты проверяют класс исключения и ключевые фрагменты сообщения.

- [x] Задача 3: Реализовать VO манифеста и нормализацию схемы.

  Результат: созданы `final readonly` объекты `Module`, `ManifestMeta`, `ManifestState`, `ModuleDependencies`, `FeatureSchema`, `FeatureDefinition`, `FeatureValues`. Короткая форма зависимостей вроде `["users"]` нормализуется в `{"users": "*"}`. Секция `autoload` в `module.json` запрещена. `FeatureSchema` валидирует типы `bool`, `int`, `string`, `enum`, defaults, `min/max`, `options` и неизвестные ключи; `FeatureValues` возвращает явные значения с fallback на defaults схемы без записи этих defaults в файл.

  Файлы: `src/Manifest/Module.php`, `src/Manifest/ManifestMeta.php`, `src/Manifest/ManifestState.php`, `src/Manifest/ModuleDependencies.php`, `src/Manifest/FeatureSchema.php`, `src/Manifest/FeatureDefinition.php`, `src/Manifest/FeatureValues.php`, `src/Manifest/ManifestValidator.php`, `tests/Fixtures/Manifests/*.json`, `tests/Support/ModuleFactory.php` при необходимости.

  Зависимости: задача 2.

  Диагностика: ошибки валидации должны быть видны через `InvalidManifestException` и точечные unit-assertions. Логи приложения не использовать. Сообщения валидации держать детерминированными, чтобы вывода PHPStan/тестов хватало для разбора.

### Этап 2: Хранение манифеста и реестр модулей

- [x] Задача 4: Реализовать утилиты поддержки для путей, атомарного JSON, пространств имён и сортировки зависимостей.

  Результат: добавлены `ModuleLayout` как единственный источник подпутей модуля, `AtomicJsonWriter` с `flock` + temp file + `rename`, `ComposerNamespaceResolver` с чтением корневого PSR-4 без хардкода `App\\`, и `TopologicalSorter` с Composer SemVer constraints, ошибками циклов, отсутствующих, отключённых и несовместимых зависимостей.

  Файлы: `src/Support/ModuleLayout.php`, `src/Support/AtomicJsonWriter.php`, `src/Support/ComposerNamespaceResolver.php`, `src/Support/TopologicalSorter.php`, `tests/Unit/Support/*Test.php`.

  Зависимости: задачи 2-3.

  Диагностика: проблемы отсутствующих корней, ошибок записи, несовместимых версий и циклов проявляются через сообщения исключений и unit-assertions. В логи Laravel ничего не писать.

- [x] Задача 5: Реализовать `ModuleManifestRepository` как единственную точку записи `module.json`.

  Результат: реализованы `load()`, validate, hydrate, `save()`, `updateState()` и `updateFeatureValues()` через типизированные VO на границах. Запись идёт только через `AtomicJsonWriter`; `settings.values` валидируются по `FeatureSchema`; JSON пишется с `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`. `save()` сериализует канонический manifest array из VO и не принимает сырые массивы как публичную границу.

  Файлы: `src/Manifest/ModuleManifestRepository.php`, `src/Contracts/ModuleManifestRepositoryInterface.php`, `tests/Unit/Manifest/ModuleManifestRepositoryTest.php`.

  Зависимости: задачи 3-4.

  Диагностика: логи не добавлять. Тесты должны покрыть невалидный JSON, отсутствующий манифест, валидацию схемы, валидацию значений фичетоглов, атомарную запись и ошибки блокировки/записи.

- [x] Задача 6: Реализовать `ModuleRegistry` и формат продакшен-кеша.

  Результат: реестр, совместимый с singleton, один раз сканирует настроенные директории, игнорирует папки без `module.json`, резолвит пространства имён через `NamespaceResolverInterface`, сортирует по зависимостям и читает `bootstrap/cache/modules.php` одним `require`, если кеш есть. Старый кеш `bootstrap/cache/modules-providers.php` только для сервис-провайдеров заменён форматом v2 с сериализованными данными модулей и `load_order`; `modules:optimize-clear` удаляет именно v2-кеш.

  Файлы: `src/Manifest/ModuleRegistry.php`, `src/Contracts/ModuleRegistryInterface.php`, `src/Console/Commands/ModulesOptimizeCommand.php`, `src/Console/Commands/ModulesOptimizeClearCommand.php`, `tests/Unit/Manifest/ModuleRegistryTest.php`, `tests/Feature/ModulesOptimizeCommandTest.php`.

  Зависимости: задачи 4-5.

  Диагностика: консольная команда должна понятно показывать путь кеша и количество модулей. Реестр времени выполнения не логирует. Unit/feature-тесты проверяют попадание в кеш, сканирование при промахе кеша, `modules:optimize-clear`, отсутствие обращения к старому `modules-providers.php` и диагностику порядка зависимостей.

### Этап 3: Чтение фичетоглов во время выполнения и bootstrap-конвейер

- [x] Задача 7: Реализовать `FeatureRepository`, совместимый с Octane.

  Результат: `FeatureRepositoryInterface` предоставляет `get`, `bool`, `int`, `string`. Реализация не `readonly`, биндится как scoped, держит только кеш `FeatureValues` в пределах одного request и перечитывает манифест через `ModuleManifestRepository`, а не через оптимизированный кеш registry, чтобы изменения из админского UI были видны на следующем request. Отсутствующий модуль и отсутствующий ключ фичетогла дают типизированные исключения; отсутствующее значение с default из схемы возвращает default.

  Файлы: `src/Manifest/FeatureRepository.php`, `src/Contracts/FeatureRepositoryInterface.php`, `tests/Unit/Manifest/FeatureRepositoryTest.php`, `tests/Feature/FeatureRepositoryScopedBindingTest.php`.

  Зависимости: задачи 5-6.

  Диагностика: логи времени выполнения не добавлять. Тесты должны явно показать поведение без stale values: два simulated scoped requests, где второй request видит изменённые значения из `module.json`.

- [x] Задача 8: Переписать `ModuleLoaderServiceProvider` под DI bindings и единый конвейер лоадеров.

  Результат: `register()` биндит все сервисы ядра по scope-таблице из архитектуры, регистрирует default-лоадеры через DI/tagged collection и оставляет extension point для кастомных лоадеров без флагов манифеста. Опциональная регистрация MoonShine защищена проверками классов/контейнера. `boot()` публикует package config из `__DIR__ . '/../../config/modules.php'`, регистрирует консольные команды и optimizer hooks, получает `ModuleRegistryInterface::loadOrder()`, сортирует лоадеры по `priority()`, пропускает отключённые модули и вызывает `load($module)` ровно один раз на пару loader/module.

  Файлы: `src/Providers/ModuleLoaderServiceProvider.php`, helpers в `src/Providers` только если реально нужны, `tests/Feature/ModuleLoaderServiceProviderTest.php`.

  Зависимости: задачи 2, 6, 7.

  Диагностика: сервис-провайдер не логирует. Поведение проверяется тестами на bindings, scoped `FeatureRepositoryInterface`, extension point кастомных лоадеров, пропуск отключённых модулей, порядок лоадеров и отсутствие MoonShine.

### Этап 4: Переписывание уже реализованных лоадеров

- [x] Задача 9: Заменить текущие `Services/*LoaderService.php` на реализации `Loaders/*Loader.php` для v2.

  Результат: текущие реализованные лоадеры переведены на `LoaderInterface`: `ConfigLoader`, `RouteLoader`, `MigrationLoader`, `FactoryLoader`, `ServiceProviderLoader`. Каждый лоадер использует `ModuleLayout`, делает ранний выход при отсутствии нужного файла/директории, идемпотентен там, где это наблюдаемо, и не сканирует директории модулей самостоятельно. `RouteLoader` поддерживает `Routes/api/{version}.php` как `api/{version}` плюс плоские route files, а `Routes/inertia.php` регистрирует только при доступной Inertia-интеграции в host-приложении. `ServiceProviderLoader` резолвит providers по namespace модуля и файлам `Providers/*ServiceProvider.php`, без Composer classmap/reflection обхода. Старые классы `src/Services/*LoaderService.php` удалены после того, как сервис-провайдер перестал на них ссылаться.

  Файлы: `src/Loaders/ConfigLoader.php`, `src/Loaders/RouteLoader.php`, `src/Loaders/MigrationLoader.php`, `src/Loaders/FactoryLoader.php`, `src/Loaders/ServiceProviderLoader.php`, удалить `src/Services/ConfigLoaderService.php`, `src/Services/RouteLoaderService.php`, `src/Services/MigrationLoaderService.php`, `src/Services/FactoryLoaderService.php`, `src/Services/ServiceProviderLoaderService.php`, обновить или заменить `tests/Unit/*LoaderServiceTest.php`.

  Зависимости: задачи 4, 8.

  Диагностика: логи времени выполнения не добавлять. Unit-тесты должны проверять ранние выходы, успешные вызовы регистрации, идемпотентный повторный запуск там, где это наблюдаемо, сборку route attributes, optional Inertia guard и решения по versioned route prefixes.

- [x] Задача 10: Реализовать `MoonShineLoader` как опциональный мост к native autoload MoonShine.

  Результат: обнаружение через classmap/reflection заменено тонкой реализацией `LoaderInterface` с `priority() = 90`, которая вызывает native autoload MoonShine core для namespace каждого включённого модуля. Loader создаётся/регистрируется только при наличии MoonShine contracts; пакет должен загружаться без установленного MoonShine.

  Файлы: `src/Loaders/MoonShineLoader.php`, удалить `src/Services/MoonShineLoaderService.php`, `src/Providers/ModuleLoaderServiceProvider.php`, `tests/Unit/Loaders/MoonShineLoaderTest.php`, `tests/Feature/OptionalMoonShineBootTest.php`.

  Зависимости: задачи 6, 8.

  Диагностика: логи не добавлять. Тесты должны показывать поведение опциональной интеграции через mocked core calls и сценарии загрузки без нужных классов.

### Этап 5: Проверки качества и архитектурные тесты

- [x] Задача 11: Заменить legacy-тесты покрытием unit, feature и architecture для v2.

  Результат: архитектурные проверки перенесены в `tests/Architecture/*Test.php`, чтобы их запускал текущий `composer test:arch`; legacy `tests/ArchitectureTest.php` удалён или превращён в совместимый suite. Инварианты Фазы 1 обязательны: `declare(strict_types=1)` в `src/`, `tests/` и `stubs/`, отсутствие debug/termination calls, отсутствие facades вне `Console/Commands` и `MoonShine`, отсутствие mutable static properties, `final readonly` VO classes в `Manifest/`, `final` loaders/services, каждый класс в `Loaders/` реализует `LoaderInterface`, нет зависимости от Eloquent `Model` в `Application/UseCases/*`. Старые unit-тесты, ссылающиеся на удалённые сервисные методы, заменены; общий test support создаёт `Module`/manifest fixtures без дублирования.

  Файлы: `tests/Architecture/*.php`, удалить или перенести `tests/ArchitectureTest.php`, `tests/Pest.php`, `tests/TestCase.php`, `tests/Support/*.php`, `tests/Unit/**/*Test.php`, `tests/Feature/**/*Test.php`, `phpunit.xml.dist`, `phpstan.neon.dist` при необходимости.

  Зависимости: задачи 1-10.

  Диагностика: логи времени выполнения не добавлять. Падения тестов должны содержать полезные имена файлов/классов, чтобы подробная диагностика шла из тестового вывода.

- [x] Задача 12: Прогнать локальные проверки качества и выполнить обязательную проверку документации.

  Результат: выполнены `composer validate`, `composer format`, `composer rector:dry`, `composer phpstan`, `composer test`; все падения исправлены без baseline и без подавления mixed-type debt. Документация обновлена только там, где Фаза 1 меняет публичное поведение пакета: установка/зависимости в README, контракт лоадеров, структура манифеста, optional MoonShine/Inertia и поведение optimize cache.

  Файлы: `README.MD`, `docs/` если страницы документации уже есть или появляются в проверке документации, плюс любые файлы, нужные для исправления проверок качества.

  Зависимости: задачи 1-11.

  Диагностика: вывод команд сохранить как подтверждение в итоговом описании реализации; не добавлять логи времени выполнения ради этой задачи. Любое падение должно быть сведено к конкретному файлу кода, тестов или документации и исправлено до завершения.

## Проверочные команды
- `composer validate`
- `composer format`
- `composer rector:dry`
- `composer phpstan`
- `composer test`
- Ручная сверка архитектуры с `.ai-factory/rules/base.md`, `.ai-factory/rules/manifest.md`, `.ai-factory/rules/loaders.md`, `.ai-factory/rules/runtime.md`, `.ai-factory/rules/testing.md`

## Примечания для реализации
- Не сохранять API 1.x ради обратной совместимости, если он не входит в архитектуру v2.0. Эта фаза прямо описана как переписывание уже реализованного.
- Не добавлять флаги `autoload` в манифест; применимость лоадера определяется файлами и директориями через `ModuleLayout`.
- Не писать `module.json` напрямую через `file_put_contents`; сохранять его может только `ModuleManifestRepository::save()`.
- Держать MoonShine и Inertia опциональными. Загрузка ядра пакета должна работать без этих пакетов в хост-приложении.
- `FeatureRepository` не должен читать `bootstrap/cache/modules.php`; этот кеш только для обнаружения модулей и порядка загрузки.
- Не оставлять изменения `composer.json` без соответствующего обновления `composer.lock`.
- Не создавать коммиты с русскоязычными сообщениями; commit checkpoints выше являются готовыми английскими вариантами.
