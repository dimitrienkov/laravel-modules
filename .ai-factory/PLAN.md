# План: Фаза 0 — Актуализация репозитория и тулинга

> Fast-режим. Источник требований: `.ai-factory/ROADMAP.md` §«Фаза 0 — Актуализация репозитория и тулинга (фундамент)». Архитектурный контекст: `.ai-factory/ARCHITECTURE.md` §1, §13, §15. Описание стека: `.ai-factory/DESCRIPTION.md` §«Технологический стек», §«Качество (обязательно в CI, не опционально)».

- **Дата создания:** 2026-05-23
- **Ветка:** main (fast-режим не создаёт фича-ветку; смена ветки не требуется)
- **Скоуп:** только тулинг и зависимости. Переписывать реализацию ядра (`src/Loaders/*`, `src/Manifest/*` и т.д.) под level 8 в этой фазе **не нужно** — это задача Фазы 1.

## Settings

- **Tests:** smoke-прогон тулинга после установки нового стека (phpstan/rector:dry/format:dry/полный test). Цель — поймать конфликты версий, не вычистить legacy-код.
- **Logging:** verbose. Все шаги пишут в `.ai-factory/tooling-bootstrap.log` (DEBUG-уровень). Сводный summary шага 9 — на INFO. Файл лога — артефакт фазы, в CI публикуется через `actions/upload-artifact@v4` на failure.
- **Docs:** warn-only (`WARN [docs]`). После Фазы 0 ничего публичного не меняется; пакет ещё не имеет `docs/`. Полноценный `docs/`-блок — задача Фазы 2 («Документация и миграция»).

## Roadmap Linkage

- **Milestone:** `Фаза 0 — Актуализация репозитория и тулинга (фундамент)`
- **Rationale:** План закрывает ровно три чекбокса Фазы 0 из `ROADMAP.md`: обновление composer-зависимостей, конфиги инструментов качества, composer scripts + CI matrix. После прогона `/aif-implement` и `/aif-verify` три эти чекбокса в `ROADMAP.md` будут отмечены, milestone — закрыт.

## Контекст из артефактов

- **DESCRIPTION.md** требует: PHP 8.3+, Laravel 11/12/13, Pest 3 + `pest-plugin-arch`, PHPUnit 11/12 + Orchestra Testbench, PHPStan level 8 + larastan, Rector с Laravel-set + type-decl + dead-code, PHP-CS-Fixer (`@PSR12` + проектные правила), `declare(strict_types=1);` обязателен.
- **ARCHITECTURE.md §13** — фиксирует целевые `composer scripts`: `test:arch`, `test:unit`, `test:feature`, `test`, `phpstan`, `rector:dry`, `format`. CI обязан гонять `phpstan`, `rector:dry`, `format --dry-run`, `test` — все четыре required.
- **Текущее состояние:**
  - `composer.json` — Laravel ^11|^12|^13 уже есть, но связка `nunomaduro/larastan ^2 + phpstan/phpstan ^1.12 + rector ^1.2 + rector-laravel ^1.2`, нет Pest.
  - `phpstan.neon.dist` — level 5, подключение через старый `nunomaduro/larastan/extension.neon`.
  - `rector.php` — closure-стиль 1.x; sets без `EARLY_RETURN`.
  - `.php-cs-fixer.dist.php` — без `declare_strict_types`.
  - `phpunit.xml.dist` — один testsuite «Einzelwerk Suite», без разделения на Architecture/Unit/Feature, без Pest bootstrap.
  - Нет `.github/workflows/*` — CI отсутствует.

## CI-матрица (по решению пользователя)

PHP 8.3 + 8.4 × Laravel 12 + 13 = 4 ячейки. Laravel 11 в матрице **не гоняем** — поддержка в composer-constraint остаётся (^11|^12|^13), но CI закрывает только активные ветки.

## Tasks

Задачи нумеруются по `TaskCreate`-ID, зависимости через `blockedBy` уже выставлены.

### Этап 0 — Окружение (предусловие для всего пайплайна)

0. **[x] Поставить PHP 8.3 + PHP 8.4 + Composer 2 в WSL (Ubuntu 24.04 noble) через `ppa:ondrej/php`.** [#0]
   - Контекст: текущий WSL чист — `which php` / `which composer` пусты, `vendor/` отсутствует. Без этого шага не выполнятся #2 (`composer update`) и #9 (локальный smoke).
   - Шаги:
     1. `sudo apt-get update && sudo apt-get install -y software-properties-common ca-certificates lsb-release gnupg unzip curl`.
     2. `sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update`.
     3. Установить оба интерпретатора с расширениями, нужными для testbench/pest/laravel:
        - `sudo apt-get install -y php8.3-cli php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl php8.3-sqlite3 php8.3-curl php8.3-bcmath`.
        - `sudo apt-get install -y php8.4-cli php8.4-mbstring php8.4-xml php8.4-zip php8.4-intl php8.4-sqlite3 php8.4-curl php8.4-bcmath`.
     4. Активный `php` по умолчанию — 8.3 (минимум из support-matrix пакета): `sudo update-alternatives --set php /usr/bin/php8.3`. Переключение на 8.4 — `sudo update-alternatives --set php /usr/bin/php8.4`. CI всё равно гоняет матрицу.
     5. Composer 2 — официальный установщик с проверкой checksum:
        ```bash
        EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
        [ "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" ] || { rm composer-setup.php; echo "ERROR: composer installer checksum mismatch"; exit 1; }
        sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer --2
        rm composer-setup.php
        ```
   - Verification gate (всё должно отдать non-empty и без ошибок):
     - `php -v` → начинается с `PHP 8.3.`
     - `php8.4 -v` → начинается с `PHP 8.4.`
     - `php -m | grep -E '^(mbstring|intl|sqlite3|zip|curl|xml)$'` → шесть строк.
     - `composer --version` → `Composer version 2.x`.
   - Артефакт: блок в `.ai-factory/tooling-bootstrap.log` с тегом `[env]` и выводом четырёх команд выше (INFO-уровень).
   - Без `blockedBy` — это первая задача пайплайна. Изменений в репозитории не делает → отдельного коммита нет (см. Commit Plan).

### Этап A — composer-стек

1. **[x] Обновить `composer.json` — поднять весь стек до v2.0-таргетов.** [#1]
   - Файл: `composer.json`.
   - `require`: оставить как есть (PHP/Laravel/Inertia/MoonShine уже актуальны).
   - `require-dev`: `phpunit/phpunit ^11|^12`, `mockery/mockery ^1.2`, `rector/rector ^2`, `friendsofphp/php-cs-fixer ^3.65`, `driftingly/rector-laravel ^2`, `phpstan/phpstan ^2`, **удалить** `nunomaduro/larastan ^2`, **добавить** `larastan/larastan ^3`, `orchestra/testbench ^9.12|^10|^11`, `pestphp/pest ^3`, `pestphp/pest-plugin-arch ^3`.
   - Лог: DEBUG-diff в `.ai-factory/tooling-bootstrap.log`.

2. **[x] `composer update -W --prefer-dist`, зафиксировать `composer.lock`.** [#2 — blockedBy #0, #1]
   - При конфликтах разруливать руками, без понижения версий из ROADMAP.
   - Если что-то пришлось понизить — `WARN`-запись в лог с указанием библиотеки и причины.
   - Артефакт: обновлённый `composer.lock`.

### Этап B — конфиги тулинга (после успешного update идут параллельно)

3. **Переписать `phpstan.neon.dist` под level 8 + `larastan/larastan/extension.neon`.** [#3 — blockedBy #2]
   - `level: 8`, `paths: src, tests`, `treatPhpDocTypesAsCertain: false`.
   - TODO-якорь с ссылкой на ROADMAP для будущего кастомного правила запрета `Illuminate\Database\Eloquent\Model` в `Application/UseCases/*` (правило **не реализуем** в Фазе 0).
   - Лог: `[phpstan] level=8 extension=larastan/larastan/extension.neon`.

4. **Переписать `rector.php` под Rector 2.x + rector-laravel 2.x.** [#4 — blockedBy #2]
   - API 2.x: `return RectorConfig::configure()->withPaths([...])->withSets([...])->withSkip([...])->withImportNames(...)`.
   - Sets: `LARAVEL_CODE_QUALITY`, `LARAVEL_COLLECTION`, `TYPE_DECLARATION`, `CODE_QUALITY`, `DEAD_CODE`, `EARLY_RETURN`.
   - `paths: src, tests/Architecture`. `skip: tests/*/Fixtures` (на будущее).
   - Лог: `[rector] sets=laravel-quality,laravel-collection,type-decl,code-quality,dead-code,early-return`.

5. **Обновить `.php-cs-fixer.dist.php` под strict-types и расширенный набор правил.** [#5 — blockedBy #2]
   - Сохранить текущий набор; добавить: `declare_strict_types => true`, `single_quote => true`, `phpdoc_align`, `phpdoc_separation`, `ordered_class_elements`, `void_return`, `nullable_type_declaration_for_default_null_value`.
   - Finder: src + tests; если появится `stubs/` — включить через `if (is_dir(__DIR__.'/stubs')) { $finder->in(__DIR__.'/stubs'); }`.
   - `setRiskyAllowed(true)` — оставить.
   - Лог: `[php-cs-fixer] strict_types=enforced`.

6. **Перенастроить `phpunit.xml.dist` на три testsuites + создать `tests/Pest.php`.** [#6 — blockedBy #2]
   - Файлы: `phpunit.xml.dist` (изменить) и `tests/Pest.php` (создать).
   - Testsuites: `Architecture` (`./tests/Architecture`), `Unit` (`./tests/Unit`), `Feature` (`./tests/Feature`). XSD-схему пинуем на `https://schema.phpunit.de/11.5/phpunit.xsd` — PHPUnit 12.x обратно-совместимо читает её, контрибьютор на 11.x не получит warning.
   - Включить `colors=true`, `cacheDirectory=.phpunit.cache`, `failOnRisky=true`, `failOnWarning=true`. `stopOnError`/`stopOnFailure` — выключить (мешает CI собирать полный отчёт).
   - `tests/Pest.php`: `uses(\Orchestra\Testbench\TestCase::class)->in('Unit', 'Feature');` + `declare(strict_types=1);`. **`Architecture`-suite намеренно не цепляем к Testbench** — arch-тесты работают через reflection и не должны бутить Laravel (ускоряет прогон). Реальных тестов в Фазе 0 не добавляем — это будет в Фазе 1 (`Архитектурные тесты на ядро`).
   - Лог: `[phpunit] testsuites=Architecture,Unit,Feature xsd=phpunit/11.5`.

7. **Дополнить `composer.json` scripts.** [#7 — blockedBy #2]
   - Привести scripts к набору ROADMAP §Фаза 0 / ARCHITECTURE §13.
   - Финальный список (помимо текущих `phpstan`, `phpstan:clear`, `rector`, `format`):
     - `test:arch` — `vendor/bin/pest --testsuite=Architecture`.
     - `test:unit` — `vendor/bin/phpunit --testsuite=Unit`.
     - `test:feature` — `vendor/bin/phpunit --testsuite=Feature`.
     - `test` — **массив**, не строка с `&&` (composer-нативный синтаксис, short-circuit на ошибке, шелл-независимо):
       ```json
       "test": ["@test:arch", "@test:unit", "@test:feature"]
       ```
       Заменяет текущий `phpunit --colors=always`.
     - `rector:dry` — оставить.
     - `format:dry` — `vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run --diff` (новый).
   - Лог: `[composer] scripts updated; test=array-form`.

### Этап C — CI и smoke

8. **Создать `.github/workflows/ci.yml`.** [#8 — blockedBy #3, #4, #5, #6, #7]
   - Триггеры: `push: { branches: [main] }`, `pull_request: {}`.
   - Матрица: `php: [8.3, 8.4]`, `laravel: [12, 13]`, `fail-fast: false`.
   - Шаги:
     1. `actions/checkout@v4`.
     2. `shivammathur/setup-php@v2` с `php-version: ${{ matrix.php }}`, `extensions: mbstring,intl,zip,sqlite3`, `coverage: none`.
     3. `actions/cache@v4` для `~/.composer/cache` по ключу `${{ runner.os }}-${{ matrix.php }}-${{ matrix.laravel }}-${{ hashFiles('composer.json') }}`.
     4. `composer require "laravel/framework:^${{ matrix.laravel }}" --no-update`.
     5. `composer update --prefer-dist --no-progress`.
     6. `composer phpstan` — required.
     7. `composer rector:dry` — required.
     8. `composer format:dry` — required.
     9. `composer test` — required (последовательно arch/unit/feature).
   - `actions/upload-artifact@v4` для `.ai-factory/tooling-bootstrap.log` на `if: failure()`.
   - Подписать workflow комментарием со ссылкой на milestone Фазы 0 в `ROADMAP.md`.

9. **Smoke-прогон тулинга локально и фиксация результата.** [#9 — blockedBy #0, #3, #4, #5, #6, #7]
   - Выполнить локально: `composer phpstan`, `composer rector:dry`, `composer format:dry`, `composer test`.
   - **Цель smoke** — не зелёный CI, а корректный boot инструментов. Допустимо: phpstan/rector/php-cs-fixer показывают замечания на legacy-коде `src/` 1.x (это вход для Фазы 1). Недопустимо: фатальные ошибки запуска (`Class not found`, конфликт версий, segfault).
   - Финальный INFO-summary в `.ai-factory/tooling-bootstrap.log`: `[smoke] phpstan: <N issues / boot OK>, rector: <N suggestions / boot OK>, php-cs-fixer: <N diffs / boot OK>, tests: <pass/fail counts>`.
   - Если CI на main исходно зелёный быть не должен (см. цель smoke), оставить в workflow `continue-on-error: false`, но в PR-описании Фазы 1 указать «ожидается красный CI на legacy-коде до переписывания ядра».

## Commit Plan

5+ задач — нужны чекпоинты. Группировка по этапам:

- **(нет коммита) после #0:** Task #0 — провизиние локального окружения, в репозиторий не пишет. Артефакт — `.ai-factory/tooling-bootstrap.log` (gitignored, см. ниже).
- **Commit 1 (после #1, #2):** `chore(deps): bump to phpstan 2, larastan 3, rector 2, pest 3, testbench 11`
- **Commit 2 (после #3, #4, #5, #6, #7):** `chore(tooling): phpstan level 8, rector 2 config, strict_types in php-cs-fixer, three testsuites, pest bootstrap, composer scripts`
- **Commit 3 (после #8, #9):** `ci: github actions matrix php 8.3,8.4 × laravel 12,13 + smoke log`

Каждый коммит — атомарный: build репозитория проходит хотя бы на уровне `composer install` после первого коммита, тулинг бутится после второго, CI зелёный/ожидаемо-красный после третьего.

**Важно:** `.ai-factory/tooling-bootstrap.log` — рантайм-артефакт пайплайна, не подлежит коммиту. Если `.gitignore` ещё не игнорирует `.ai-factory/*.log` — добавить строку в `.gitignore` в рамках Commit 1 (одной строкой, без отдельной задачи).

## После плана

Следующий шаг:

```
/aif-implement
```

Команда подхватит тасклист (#0..#9) и пойдёт по нему с учётом `blockedBy`. Первый шаг — провизиние локального окружения (#0); только после него выполнятся `composer update` (#2) и smoke (#9). После завершения Фазы 0 — отметить три чекбокса в `ROADMAP.md` (через `/aif-verify` или вручную) и стартовать Фазу 1 (`v2.0 ядро`).
