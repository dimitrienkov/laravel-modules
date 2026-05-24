# AGENTS.md

> Карта проекта для AI-агентов. Поддерживай в актуальном виде при существенных изменениях структуры. Детали смотри в `.ai-factory/DESCRIPTION.md` и `.ai-factory/ARCHITECTURE.md`.

## Обзор проекта

`dimitrienkov0/laravel-modules` — Laravel-пакет для модульной архитектуры приложений: автозагрузка модулей, манифест-based управление, MoonShine admin-UI, генераторы кода. Целевой сценарий — коммерческая поставка модулей заказчикам с фичетоглами и установкой через CLI.

## Технологический стек

- **Язык:** PHP 8.3+
- **Фреймворк:** Laravel 12 / 13
- **Admin-UI (опционально):** MoonShine 4
- **Тесты:** PHPUnit 11/12 + Orchestra Testbench + Mockery
- **Качество:** PHPStan (larastan), Rector, PHP-CS-Fixer
- **Без БД:** состояние модулей хранится в `module.json` каждого модуля

## Структура проекта

```
.
├── src/                          # код пакета
│   ├── Console/Commands/         # Artisan-команды пакета (make:module, modules:*, make:* --module)
│   ├── Contracts/                # публичные интерфейсы (LoaderInterface и т.п.)
│   ├── Loaders/                  # реализации LoaderInterface (Route, Migration, ...)
│   ├── Manifest/                 # Module, ManifestMeta, FeatureSchema, ManifestRepository
│   ├── Application/              # use cases жизненного цикла
│   ├── Providers/                # ModuleLoaderServiceProvider
│   └── Support/                  # утилиты (atomic JSON, PSR-4 resolver)
├── config/
│   └── modules.php               # дефолтная конфигурация пакета
├── tests/
│   ├── ArchitectureTest.php      # архитектурные инварианты
│   ├── TestCase.php              # базовый TestCase с Mockery
│   └── Unit/                     # юнит-тесты лоадеров
├── .ai-factory/                  # AI Factory контекст
│   ├── DESCRIPTION.md            # видение продукта
│   ├── ARCHITECTURE.md           # архитектурные решения
│   ├── config.yaml               # настройки AI Factory
│   └── rules/base.md             # правила кодинга
├── .claude/                      # Claude Code контекст: skills, agents
├── composer.json                 # зависимости пакета
├── phpunit.xml.dist              # конфиг тестов
├── phpstan.neon.dist             # конфиг PHPStan
├── rector.php                    # конфиг Rector
└── .php-cs-fixer.dist.php        # конфиг форматтера
```

## Ключевые точки входа

| Файл | Назначение |
|------|-----------|
| `src/Providers/ModuleLoaderServiceProvider.php` | Главный сервис-провайдер пакета: регистрирует все лоадеры и Artisan-команды |
| `config/modules.php` | Корневой конфиг: пути к директориям модулей, типы роутинга |
| `composer.json` | Объявление пакета и его зависимостей |
| `phpunit.xml.dist` | Конфигурация тестов |

## Документация

| Документ | Путь | Описание |
|----------|------|----------|
| README | `README.MD` | Установка и краткий гайд по пакету |
| Описание продукта | `.ai-factory/DESCRIPTION.md` | Видение, фичи, дорожная карта 2.0 |
| Архитектура | `.ai-factory/ARCHITECTURE.md` | Слои, контракты, диаграммы, примеры кода |

## AI-контекст

| Файл | Назначение |
|------|-----------|
| `AGENTS.md` | Карта проекта для AI-агентов (этот файл) |
| `.ai-factory/DESCRIPTION.md` | Спецификация продукта |
| `.ai-factory/ARCHITECTURE.md` | Архитектурные решения и паттерны |
| `.ai-factory/config.yaml` | Настройки AI Factory (язык, пути, git-флоу) |
| `.ai-factory/rules/base.md` | Правила кодинга и инварианты |

## Правила для агентов

- **Декомпозируй shell-команды.** Не объединяй через `&&` команды, требующие проверки результата каждой. Пример некорректного: `git checkout main && git pull`. Корректно: сначала `git checkout main`, затем отдельно `git pull origin main`.
- **Никаких фасадов и хелперов в `src/`.** Только DI. Это базовое правило проекта.
- **Никаких `dd()`/`dump()`/`var_dump()` в `src/`.** Архитектурный тест поломается.
- **`declare(strict_types=1);` обязателен** в каждом новом файле под `src/`.
- **Перед коммитом:** `composer format && composer phpstan && composer test`.
- **Запись в `module.json`** — только через `ModuleManifestRepository::save()`, никогда напрямую через `file_put_contents`.
