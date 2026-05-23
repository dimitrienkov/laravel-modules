# Базовые правила проекта

> Конвенции, извлечённые из кода и предпочтений автора. Редактируй по мере необходимости.

## Качество кода

- `declare(strict_types=1);` — обязателен в каждом PHP-файле под `src/` (проверяется `ArchitectureTest::testStrictTypesDeclaration`).
- Никаких `dd()`, `dump()`, `var_dump()`, `print_r()`, `exit()`, `die()` в `src/` (проверяется `ArchitectureTest::testNoDebugAndTerminationFunctionsInCode`).
- Классы по умолчанию `final readonly`, поля приходят через конструкторный property promotion.
- PHPStan: целевой level max (сейчас 5 — поднимаем по мере рефакторинга).
- Стандарт форматирования: PSR-12 + `.php-cs-fixer.dist.php`.

## Архитектура и DI

- Только DI — никаких хелперов, никаких фасадов внутри `src/` пакета.
- `Application` / `Domain` / `Infrastructure` / `Http` / `Console` / `MoonShine` — слои внутри модуля. Зависимости текут от Presentation к Application к Domain. Infrastructure реализует контракты из Domain.
- UseCase оркеструет операцию (входная точка). Action — изменяющая операция (Command pattern). Query — read-only.
- Никаких side effects в конструкторах сервисов; `autoload()` вызывает их сам пакет.

## Соглашения по именованию

- Классы в `Services/` имеют суффикс `Service` (проверяется `ArchitectureTest::testClassesInServicesHaveServicePostfix`). При переходе на UseCase/Action/Query суффикс становится `UseCase`/`Action`/`Query` соответственно.
- Классы в `DTOs/` — `readonly class` (проверяется `ArchitectureTest::testReadonlyClassesInDTOsDirectory`).
- Классы в `Enums/` — `enum` (проверяется `ArchitectureTest::testEnumsInEnumDirectory`).
- Классы в `Providers/` — `extends ServiceProvider` (проверяется `ArchitectureTest::testAllProvidersExtendServiceProvider`).

## Структура пакета

```
src/
├── Console/Commands/        — Artisan-команды пакета
├── Contracts/               — публичные интерфейсы (LoaderInterface, ModuleManifestRepositoryInterface, …)
├── Loaders/                 — реализации LoaderInterface
├── Manifest/                — value objects: Module, ManifestMeta, ManifestState, FeatureSchema
├── Providers/               — ServiceProvider пакета
├── Services/                — устаревший префикс; постепенно вытесняется UseCase/Action/Query
└── Support/                 — утилиты (atomic JSON write, namespace resolver)
```

## Управление модулями приложения

- Корень модулей задаётся в `config/modules.php` (`paths.directories`).
- Namespace резолвится по PSR-4 хост-приложения (читаем корневой `composer.json`).
- `module.json` — обязателен в корне каждого модуля. Без него модуль не виден `ModuleRegistry`.
- Запись в `module.json` — только через `ModuleManifestRepository::save()` (atomic tmp + rename + lock).

## Тестирование

- PHPUnit 11/12 + Orchestra Testbench.
- Unit-тесты для каждого Loader-сервиса (см. `tests/Unit/*Test.php`).
- `tests/ArchitectureTest.php` — архитектурные инварианты (strict_types, отсутствие debug-функций, постфиксы классов).
- Mocking — Mockery; запрещаем `Facade::shouldReceive(...)` внутри пакета (фасады не используем).

## Логирование и ошибки

- Никаких `\Log::*` внутри пакета — пакет не делает побочных эффектов в логи приложения по умолчанию.
- Ошибки — типизированные исключения из `src/Exceptions/` (`InvalidManifestException`, `ModuleDependencyMissingException`, `ModuleNotFoundException`).
- Все исключения наследуют `RuntimeException` с поясняющим сообщением; никаких `\Exception` напрямую.

## Git и CI

- Базовая ветка — `main`.
- Префикс feature-веток — `feature/`.
- Перед коммитом: `composer format && composer phpstan && composer test`.
- Никаких `--no-verify` — если хук падает, чиним причину.
