# Базовые правила проекта

> Конвенции, извлечённые из кода и предпочтений автора. Редактируй по мере необходимости.

## Качество кода

- `declare(strict_types=1);` — обязателен в каждом PHP-файле под `src/` (проверяется `ArchitectureTest::testStrictTypesDeclaration`).
- Никаких `dd()`, `dump()`, `var_dump()`, `print_r()`, `exit()`, `die()` в `src/` (проверяется `ArchitectureTest::testNoDebugAndTerminationFunctionsInCode`).
- Классы по умолчанию `final readonly`, поля приходят через конструкторный property promotion.
- PHPStan: level 8 (max) обязателен; удерживаем без baseline — любое новое нарушение фиксится в коде, не подавляется.
- Стандарт форматирования: PSR-12 + `.php-cs-fixer.dist.php`.

## Архитектура и DI

- Только DI — никаких хелперов, никаких фасадов внутри `src/` пакета.
- `Application` / `Domain` / `Infrastructure` / `Http` / `Console` / `MoonShine` — слои внутри модуля. Зависимости текут от Presentation к Application к Domain. Infrastructure реализует контракты из Domain.
- UseCase оркеструет операцию (входная точка). Action — изменяющая операция (Command pattern). Query — read-only.
- Никаких side effects в конструкторах сервисов; `autoload()` вызывает их сам пакет.

## Соглашения по именованию

- UseCase-классы имеют суффикс `UseCase`, Action-классы — `Action`, Query-классы — `Query`.
- Loader-классы имеют суффикс `Loader` и реализуют `LoaderInterface`.
- Repository-классы имеют суффикс `Repository`; публичные контракты лежат в `Contracts/`.
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
├── Application/             — use cases жизненного цикла
├── Providers/               — ServiceProvider пакета
└── Support/                 — утилиты (atomic JSON write, namespace resolver)
```

## Управление модулями приложения

- Корень модулей задаётся в `config/modules.php` (`paths.directories`).
- Namespace резолвится по PSR-4 хост-приложения (читаем корневой `composer.json`).
- Собственный `composer.json` внутри модуля опционален: он может использоваться для диагностики/упаковки/проверки constraints, но runtime namespace модуля резолвится из корневого PSR-4.
- `module.json` — обязателен в корне каждого модуля. Без него модуль не виден `ModuleRegistry`.
- `meta.dependencies` хранит зависимости как `moduleName => Composer SemVer constraint`; короткая форма списка допустима только как входной sugar и нормализуется в constraint `*`.
- Запись в `module.json` — только через `ModuleManifestRepository::save()` (atomic tmp + rename + lock).

## Генераторы

- Основной публичный API генераторов — Laravel-style `make:* --module=Name`.
- Стандартные Laravel-артефакты создаются стандартными командами с `--module`: `make:model`, `make:controller`, `make:migration`, `make:factory`, `make:request`, `make:resource`, `make:command`, `make:event`, `make:listener`, `make:observer`, `make:policy`, `make:middleware`, `make:enum`.
- Архитектурные генераторы пакета также используют `--module`: `make:use-case`, `make:action`, `make:query`, `make:dto`.
- MoonShine-генераторы не дублировать: MoonShine-артефакты создаются командами самого MoonShine.

## Опциональные интеграции

- MoonShine и Inertia не должны быть обязательными runtime-зависимостями пакета. Держим их в `suggest`/`require-dev`, а загрузку включаем только при наличии классов/контрактов в host-приложении.
- Feature toggles в ядре читаются через `FeatureRepositoryInterface` (`get`, `bool`, `int`, `string`). Blade directive и другие presentation bridges — отдельная интеграционная задача, не замена DI-first API.

## Тестирование

- PHPUnit 11/12 + Orchestra Testbench.
- Unit-тесты для каждого Loader-класса (см. `tests/Unit/*Test.php`).
- `tests/ArchitectureTest.php` — архитектурные инварианты: strict_types, отсутствие debug-функций, слойность, запрет фасадов вне разрешённых integration-слоёв.
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
