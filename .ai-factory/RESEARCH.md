# Research

Updated: 2026-06-01 15:22
Status: active

## Active Summary (input for $aif-plan)
<!-- aif:active-summary:start -->
Topic: RouteLoader routing config, Laravel default route groups, and API version routing.
Goal: Упростить `modules.routing` так, чтобы модульные routes работали как Laravel routes, но при этом нормально поддерживали частый project pattern с versioned middleware groups вроде `api_v1`.
Constraints:
- Explore-mode: application code не менялся; это только исследование.
- Laravel 12/13 routing грузит `web` через middleware group `web`, `api` через middleware group `api` и prefix `api`.
- В Laravel 13.11.2 `ConvertEmptyStringsToNull` находится в global middleware stack, а `SubstituteBindings` уже входит в стандартные `web` и `api` groups.
- Текущий пакет поддерживает `Routes/api/v1.php` как built-in versioned API path.
- Текущий пакет поддерживает `Routes/api_v1.php` только если host/package config содержит `modules.routing.types.api_v1`; в default config такого type нет.
Decisions:
- Не удалять `modules.routing.types` полностью: он полезен для custom route profiles вроде `api_v1`.
- Убрать из default config конкретные Laravel middleware classes (`SubstituteBindings::class`, `ConvertEmptyStringsToNull::class`) и оставить middleware group aliases (`api`, `web`, `api_v1`).
- Предпочесть единую model: `modules.routing.types.<type>` -> `Routes/<type>.php`.
- Считать `Routes/api_v1.php` / `Routes/api_v2.php` более логичным pattern для versioned API, потому что middleware group `api_v1`/`api_v2` задаётся явно через тот же type.
- Специальная поддержка `Routes/api/*.php` выглядит лишней: она добавляет вторую convention-ветку и не умеет естественно применять middleware group `api_v1`.
- Для Laravel-like behavior default должен быть: `api` => `prefix: api`, `middleware: ['api']`; `web`/`inertia` => `middleware: ['web']`.
Open questions:
- Должен ли default config включать `api_v1` как пример/commented profile или только документацию?
- Можно ли удалить `Routes/api/*.php` behavior сразу в v2.0, или нужен deprecation path?
Success signals:
- Host-приложение может определить group `api_v1` в `bootstrap/app.php`, а модульные route files используют её без дублирования Laravel internals.
- Default config не содержит конкретных классов middleware из Laravel core.
- Docs показывают единую модель `Routes/<type>.php` и пример `api_v1`.
- Тесты фиксируют config-driven flat route type `api_v1`; отдельная ветка `Routes/api/*.php` удалена или явно deprecated.
Next step: Запустить `$aif-plan` на изменение routing config/RouteLoader/docs/tests.
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->
### 2026-06-01 15:13 — Routing config and API versioning
What changed:
- Исследован текущий `RouteLoader`, `ModuleLayout`, default config и unit-тесты.
- Уточнено: `Routes/api_v1.php` не является default convention, но может грузиться через добавленный config key `modules.routing.types.api_v1`.
- Уточнено: `Routes/api/v1.php` является отдельным built-in convention и всегда наследует attributes от type `api`.

Key notes:
- `RouteLoader::routeFiles()` сначала проходит по `array_keys(modules.routing.types)` и для каждого type ищет `Routes/<type>.php`.
- `ModuleLayout::routeFile($module, $type)` буквально строит путь `Routes/{$type}.php`; значит type `api_v1` соответствует файлу `Routes/api_v1.php`.
- `RouteLoader::versionedApiRoutes()` отдельно ищет только `Routes/api/*.php`; filename `v1.php` превращается в suffix `api/v1`, но middleware берётся из attributes `api`.
- Default config содержит только types `api`, `web`, `inertia`; поэтому `Routes/api_v1.php` из коробки не загрузится.
- Текущий default `api.middleware` содержит `SubstituteBindings::class`, `ConvertEmptyStringsToNull::class`, `'api'`; это дублирует Laravel internals и может конфликтовать с host-level middleware customization.
- Laravel 13.11.2: `ConvertEmptyStringsToNull` находится в global middleware; стандартные groups `web` и `api` уже содержат `SubstituteBindings`.

Potential implementation direction:
- Default config:
  ```php
  'routing' => [
      'types' => [
          'api' => [
              'prefix' => 'api',
              'middleware' => ['api'],
          ],
          'web' => [
              'prefix' => null,
              'middleware' => ['web'],
          ],
          'inertia' => [
              'prefix' => null,
              'middleware' => ['web'],
          ],
          // 'api_v1' => [
          //     'prefix' => 'api/v1',
          //     'middleware' => ['api_v1'],
          // ],
      ],
  ];
  ```
- If auto-version-group behavior is desired for `Routes/api/v1.php`, add a planned extension:
  - Detect version `v1`.
  - Candidate middleware group: `api_v1`.
  - If Router has this group, use `['api_v1']`; otherwise keep `['api']`.
  - Keep prefix `api/v1`.

Links (paths):
- `src/Loaders/RouteLoader.php:51` route file collection logic.
- `src/Loaders/RouteLoader.php:70` nested `Routes/api/*.php` versioning logic.
- `src/Support/ModuleLayout.php:116` config type to `Routes/<type>.php` path mapping.
- `config/modules.php:24` current default routing config.
- `tests/Unit/Loaders/RouteLoaderTest.php:36` current test covers `Routes/api/v1.php`, not `Routes/api_v1.php`.
- Laravel docs: https://laravel.com/docs/12.x/routing
- Laravel API: https://api.laravel.com/docs/13.x/Illuminate/Foundation/Configuration/ApplicationBuilder.html
- Laravel API: https://api.laravel.com/docs/12.x/Illuminate/Foundation/Configuration/Middleware.html
### 2026-06-01 15:22 — Prefer one route type model over nested API version files
What changed:
- Уточнён design direction после вопроса про `Routes/api/v1.php`.
- Вывод: отдельная поддержка `Routes/api/*.php` была convenience для prefix-versioning, но она хуже ложится на проектный pattern с `api_v1` middleware group.
- Более простая модель: все HTTP route files живут плоско в `Routes/` и соответствуют config type.

Key notes:
- `Routes/api/v1.php` был добавлен в Phase 1 rewrite как built-in versioned API route support: filename `v1.php` превращается в prefix suffix `api/v1`.
- Эта ветка наследует attributes от `api`, поэтому не применяет `api_v1` без дополнительной магии.
- Добавлять auto-detection `api_v1` для `Routes/api/v1.php` усложняет loader и делает две competing conventions.
- Единое правило проще документировать и тестировать:
  ```text
  modules.routing.types.api     -> Routes/api.php
  modules.routing.types.api_v1  -> Routes/api_v1.php
  modules.routing.types.web     -> Routes/web.php
  modules.routing.types.inertia -> Routes/inertia.php
  ```
- `console.php` и `channels.php` остаются исключениями не потому, что route type model плохая, а потому что они не являются обычными HTTP routes и уже обслуживаются отдельными loaders.

Preferred implementation direction:
- Удалить специальный scan `Routes/api/*.php` из `RouteLoader`.
- Оставить `RouteLoader` полностью config-driven: только `Routes/<type>.php`.
- Default config:
  ```php
  'routing' => [
      'types' => [
          'api' => [
              'prefix' => 'api',
              'middleware' => ['api'],
          ],
          'web' => [
              'prefix' => null,
              'middleware' => ['web'],
          ],
          'inertia' => [
              'prefix' => null,
              'middleware' => ['web'],
          ],
          // Example for host apps:
          // 'api_v1' => [
          //     'prefix' => 'api/v1',
          //     'middleware' => ['api_v1'],
          // ],
      ],
  ];
  ```
- Update docs from `Routes/api/v1.php` examples to `Routes/api_v1.php` examples.
- Update tests so route versioning is covered through config-driven `api_v1`, not nested `Routes/api/*.php`.

Links (paths):
- `.ai-factory/plans/feature-phase-1-v2-core-rewrite.md:120` original note that introduced `Routes/api/{version}.php`.
- `.ai-factory/ROADMAP.md:20` roadmap entry documenting current versioned route behavior.
- `src/Loaders/RouteLoader.php:70` nested API branch to remove/deprecate.
- `docs/configuration.md:122` docs section to rewrite.
- `docs/module-structure.md:64` structure table entry to rewrite.
<!-- aif:sessions:end -->
