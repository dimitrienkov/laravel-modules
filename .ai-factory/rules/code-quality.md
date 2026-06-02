# Правила качества кода

> Чистота, читаемость и надёжность кода. Загружаются после `rules/base.md`.

## Правила

- Каждый новый PHP-файл под `src/`, `tests/` и `stubs/` должен начинаться с `declare(strict_types=1);`.
- Concrete classes в `src/` должны быть `final`; VO/DTO/Application classes держи `readonly`, если им не нужен mutable state.
- Используй constructor property promotion для зависимостей и простых data objects.
- Не добавляй `dd()`, `dump()`, `var_dump()`, `print_r()`, `exit()`, `die()` в `src/`.
- Не используй Laravel facades, global helpers и runtime logging через фасад/хелпер (`Log::*`, `logger()`, `info()`) внутри package core. Предпочитай DI, typed exceptions, command output и tests. Инъецированный диагностический слой (`ModuleDiagnosticsInterface`, оборачивающий `Psr\Log\LoggerInterface`) разрешён: это конструкторная зависимость, а не фасад/хелпер.
- Не добавляй mutable static properties или глобальное mutable-state в `src/`.
- Direct filesystem I/O держи в специализированной инфраструктуре (`LocalFilesystem`, atomic writers, document/cache readers), а не в use cases, loaders или commands.
- PHPStan держится на `level: max` без baseline; новые нарушения исправляются в коде.
- PHPStan strict/deprecation rules, bleeding edge и 100% type coverage считаются базовой планкой; `ignoreErrors` добавляй только для документированных vendor/language false positives.
- Не добавляй `--debug`, `--sequential` или принудительный `clear-cache` в default scripts `composer phpstan`, `composer rector`, `composer format`; для диагностики используй отдельные `*:debug`, `*:fresh` или `*:clear` scripts.
- PHP-CS-Fixer форматирует по PER Coding Style 3.0 с risky rules и project-specific rules; не возвращай конфиг на PSR-12/PER-CS 2.x.
- Rector должен сохранять Laravel LSP и optional dependency probes: не типизируй параметры override'ов untyped vendor parents и не заменяй intentional `class_exists('Vendor\\Class')` soft checks на `::class`.
- Держи методы короткими и линейными: если поток требует глубокой вложенности или пошагового дебага для понимания, выделяй named methods/VO/services.
- Предпочитай guard clauses и early return/throw для граничных случаев; избегай `else` после `return` или `throw`.
- Сложные условия выноси в именованные предикаты или переменные; не делай присваивания внутри `if`, `while` и тернарных условий.
- Избегай boolean flags, которые переключают поведение метода; используй отдельные методы, enum/VO/DTO или явный configuration object.
- Не передавай в публичный метод больше трёх смысловых аргументов; при росте сигнатуры вводи DTO/VO, named arguments или fluent builder.
- Повторяющиеся magic strings/numbers с доменным смыслом оформляй как enum, VO или константу рядом с владельцем правила; не создавай константы для очевидных локальных значений.
- Комментарии должны объяснять причину, контракт, инвариант или пример использования; комментарии, пересказывающие код, удаляй или заменяй выразительным именем.
- `catch` должен добавлять контекст, rollback/cleanup или преобразование в typed exception; не проглатывай исключения молча.
- Не оставляй закомментированный или мёртвый код; удаляй — git хранит историю.
- Группируй связанные statements пустыми строками между логическими блоками внутри метода.
- Не переиспользуй переменную для другого типа или смысла в одном scope; одна переменная — один тип и одна роль.
- Предпочитай Tell, don't ask: не вытаскивай состояние объекта, чтобы решить за него — пусть объект инкапсулирует решение.
- Возвращай новые значения вместо изменения аргументов по ссылке (pass-by-reference).
- Предпочитай Command-Query Separation: метод либо меняет состояние (void/self), либо возвращает данные без side effects.
