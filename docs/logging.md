[← Feature Toggles](feature-toggles.md) · [Back to README](../README.md) · [Octane →](octane.md)

# Diagnostic Logging

Opt-in диагностический слой пакета. По умолчанию **тихий**: package core не пишет
ничего, пока host явно не включит логирование. Когда включён — переводит события
discovery, cache, loader pipeline и lifecycle в структурные лог-записи на
выбранном канале хоста.

## Зачем это нужно

Основной сценарий — **field-diagnostics поставленных заказчику модулей**. На проде
заказчика artisan, debugger и profiler обычно недоступны, а вопрос «какие модули
нашлись и почему какой-то не подгрузился» возникает регулярно. Практичный путь:

1. заказчик ставит `MODULES_LOGGING=true` и выставляет канал;
2. воспроизводит проблему;
3. присылает `storage/logs/modules.log`.

Лог отвечает на «что загрузилось, что пропущено и почему» человекочитаемым
нарративом + структурным context, не требуя CLI-доступа.

## Включение

Все настройки живут в секции `modules.logging` (см. `config/modules.php`) и
читаются через env:

```dotenv
MODULES_LOGGING=true          # включить логирование (по умолчанию false)
MODULES_LOG_CHANNEL=modules   # имя лог-канала хоста (по умолчанию null → default)
MODULES_LOG_LEVEL=debug       # глобальный порог уровня (по умолчанию debug)
```

```php
'logging' => [
    'enabled' => env('MODULES_LOGGING', false),
    'channel' => env('MODULES_LOG_CHANNEL'),
    'level' => env('MODULES_LOG_LEVEL', 'debug'),
    'events' => [
        'discovery' => true,
        'cache' => true,
        'pipeline' => true,
        'lifecycle' => true,
    ],
],
```

- **`enabled`** — мастер-тумблер. При `false` контейнер биндит `NullModuleDiagnostics`
  (нулевые накладные расходы), при `true` — `ModuleLogger` поверх выбранного канала.
- **`channel`** — имя канала хоста. Пакет канал не создаёт и не навязывает: он лишь
  использует канал по имени через лог-менеджер хоста (`$app->make('log')->channel($name)`).
  `null` → канал по умолчанию.
- **`level`** — глобальный порог: события с уровнем ниже порога не пишутся.
- **`events`** — тумблеры категорий. Категория проверяется **до** порога и **до**
  построения context.

> **Octane:** секция `modules.logging` читается на boot / старте воркера. Рантайм-
> переключение `modules.logging.*` под Octane не подхватывается на лету — нужен
> `php artisan octane:reload` (или перезапуск воркеров).

## Отдельный лог-канал

Чтобы модульные логи не растворялись в общем `laravel.log`, объявите выделенный
канал в `config/logging.php` хоста и укажите его в `MODULES_LOG_CHANNEL`:

```php
// config/logging.php
'channels' => [
    'modules' => [
        'driver' => 'single',
        'path' => storage_path('logs/modules.log'),
        'level' => 'debug',
    ],
],
```

```dotenv
MODULES_LOGGING=true
MODULES_LOG_CHANNEL=modules
```

Теперь discovery/pipeline/lifecycle события попадают в отдельный
`storage/logs/modules.log` — именно его и просят прислать с прода.

## Поведение порога уровня

`modules.logging.level` — это порог, который применяет **сам `ModuleLogger`** до
записи. Он полезен в первую очередь для канала по умолчанию (`channel = null`), где
модульные debug-события иначе утонули бы в общем потоке. На выделенном канале
дополнительную фильтрацию задаёт уровень самого канала (`'level'` в его конфиге):
эффективный порог — максимум из двух. Практически: для field-diagnostics держите
`MODULES_LOG_LEVEL=debug` и канал на `debug`, чтобы видеть полную картину.

## Каталог событий

Каждое событие гейтится своим тумблером `events.*` и глобальным порогом. Context —
только whitelisted-скаляры (см. гарантию ниже).

> **Отказ невозможно скрыть.** События уровня `error` и выше
> (`pipeline.loader.failed`, `lifecycle.{op}.failed`, `discovery.root.rejected`)
> обходят **и** per-category тумблер, **и** глобальный порог `level`. Пока
> `logging.enabled=true`, отказ loader'а, lifecycle-операции или discovery виден
> всегда: ни `events.pipeline=false`, ни `MODULES_LOG_LEVEL=emergency` его не
> подавят. Поднятие порога приглушает только события **ниже** `error`.

### `events.discovery`

| Событие | Уровень | Context |
|---|---|---|
| `discovery.root.missing` | warning | `directory` |
| `discovery.root.rejected` | error | `directory`, `reason` |
| `discovery.module.found` | debug | `module`, `path` |
| `discovery.completed` | debug | `total`, `enabled`, `disabled` |

`directory` на `discovery.root.*` — относительная директория из
`modules.paths.directories`; `path` на `discovery.module.found` — абсолютный путь
найденного модуля.

`discovery.root.rejected` логируется на **error** (а не warning): это aborting-
условие, которое предшествует брошенному `InvalidConfigurationException`, поэтому
оно не должно подавляться поднятым порогом. Его `reason` сейчас единственный —
`resolves outside app_path()` (корень из `modules.paths.directories` указывает за
пределы `app_path()`). Нефатальный сосед `discovery.root.missing` остаётся на
`warning`.

### `events.cache`

| Событие | Уровень | Context |
|---|---|---|
| `cache.hit` | debug | `count` |
| `cache.miss` | debug | — |
| `cache.written` | info | `count`, `path` |
| `cache.cleared` | info | — |
| `cache.invalid` | warning | `reason`, `exception`? |

### `events.pipeline`

Отвечает на «какие лоадеры применились к модулю и почему нет».

| Событие | Уровень | Context |
|---|---|---|
| `pipeline.started` | debug | `modules_enabled`, `loaders` |
| `pipeline.loader.applied` | debug | `module`, `loader`, `artifacts` |
| `pipeline.loader.skipped` | debug | `module`, `loader`, `reason` |
| `pipeline.loader.failed` | error | `module`, `loader`, `exception`, `message` |
| `pipeline.finished` | debug / warning | `modules_enabled`, `loaders`, `applied`, `skipped`, `failed`, `duration_ms` |

`pipeline.loader.failed` логируется **дополнительно** к `ExceptionHandler::report()` —
host-трекер ошибок по-прежнему получает полное исключение. В context кладётся сам
объект `Throwable` (ключ `exception`), чтобы канал отрендерил stack-trace и цепочку
`$previous`; `message` дублирует его текст для plain-text форматтеров. `duration_ms`
(через `hrtime(true)`) считается только на `pipeline.finished`, без шума на каждой
паре loader × module; при `failed > 0` `pipeline.finished` поднимается с `debug` до
`warning`.

**`reason`** на `pipeline.loader.skipped` — одно из значений `SkipReason`:

| `reason` | Когда |
|---|---|
| `no_directory` | convention-директория модуля отсутствует |
| `empty_directory` | директория есть, но нет подходящих файлов; сообщают только лоадеры, перечисляющие содержимое директории — `ConfigLoader`/`MiddlewareLoader`/`ObserverLoader`/`PolicyLoader` и `RouteLoader` (path-registering лоадеры `Migration`/`Event`/`Command`/`Factory`/`BladeComponent` его не эмитят) |
| `file_not_found` | ожидаемый одиночный файл отсутствует (`Routes/console.php`, `Routes/channels.php`) |
| `routes_cached` | host закешировал маршруты — `RouteLoader` не трогает их |
| `not_running_in_console` | console-only лоадер вне CLI (`CommandLoader`, `ConsoleRouteLoader`) |

`artifacts` на `pipeline.loader.applied` — карта `{type: [имена]}`: basename'ы файлов
для лоадеров, перечисляющих файлы, либо зарегистрированный относительный путь для
path-registering лоадеров (`MigrationLoader`/`EventLoader`/`CommandLoader`).
`ServiceProviderLoader` без провайдеров отдаёт `applied` с пустыми `artifacts` —
это валидный no-op, а не skip.

### `events.lifecycle`

Восемь мутирующих операций: `install`, `update`, `remove`, `enable`, `disable`,
`scaffold`, `optimize`, `clear_cache`. Read-only `modules:list` событий не пишет.

| Событие | Уровень | Context |
|---|---|---|
| `lifecycle.{op}.started` | info | `module`?, `source`? |
| `lifecycle.{op}.succeeded` | info | `module`? |
| `lifecycle.{op}.failed` | error | `module`?, `exception`?, `message`? |
| `lifecycle.{op}.rolled_back` | warning | `module`, `stage` |
| `lifecycle.{op}.backup_created` | info | `module`, `backup` |

`module` отсутствует у глобальных операций (`optimize`, `clear_cache`). `source` —
вид источника (`zip`) для `install`/`update` (единственный поддерживаемый вид входа).
Каждый путь, эмитнувший `started`, закрывается **ровно одним** терминалом —
`succeeded` или `failed`; `failed` (error) несёт объект `exception` и его `message`.
`rolled_back` и `backup_created` — промежуточные маркеры на компенсирующих путях
(backup/восстановление состояния/директории перед re-throw), а не терминалы.

`{op}` соответствует Artisan-команде, которая запускает операцию:

| `{op}` | Команда |
|---|---|
| `scaffold` | `make:module` |
| `install` | `modules:install` |
| `update` | `modules:update` |
| `remove` | `modules:remove` |
| `enable` | `modules:enable` |
| `disable` | `modules:disable` |
| `optimize` | `modules:optimize` |
| `clear_cache` | `modules:optimize-clear` |

> **Парные категории — by design.** Одна физическая команда может породить события
> из двух категорий: `modules:optimize` пишет `lifecycle.optimize.*` **и**
> `cache.written`; `modules:optimize-clear` — `lifecycle.clear_cache.*` **и** (если
> файл существовал) `cache.cleared`. Это намеренно: `lifecycle.*` описывает ход
> операции, `cache.*` — её эффект на кэш-файл. Корреляция — по совпадению времени.

> **Семантика `clear_cache.succeeded`.** `succeeded` означает «операция завершилась
> штатно», а не «кэш-файл удалён». Удаление подтверждает отдельное `cache.cleared`;
> если на момент удаления файла уже нет, `forget()` делает ранний выход без
> `cache.cleared`, но `clear_cache.succeeded` всё равно пишется. (Сама команда
> сначала проверяет наличие кэша, поэтому в типичном сценарии оба события идут в
> паре; расхождение возможно только при гонке.)

## Гарантия whitelist

`ModuleLogger` собирает context вручную из типизированных входов и пишет **только**
whitelisted-скаляры: имя модуля, относительную директорию discovery-roots,
абсолютный путь найденного модуля, относительные пути/basename'ы артефактов,
shortname лоадера, счётчики, `SkipReason->value`, вид источника, объект `Throwable`
на ошибках. В context **никогда** не попадают:

- значения фичетоглов (`settings.values`);
- секреты;
- полный `module.json` / `Module` VO.

Это покрыто unit-тестом (`tests/Unit/Support/Logging/ModuleLoggerTest.php`),
доказывающим, что даже при логировании модуля с feature-схемой её значения не
просачиваются в записанный context.

## Пример записи

`storage/logs/modules.log` (single-channel, формат по умолчанию):

```text
[2026-06-01 12:00:00] modules.DEBUG: pipeline.loader.skipped {"module":"blog","loader":"RouteLoader","reason":"no_directory"}
[2026-06-01 12:00:00] modules.DEBUG: pipeline.finished {"modules_enabled":2,"loaders":15,"applied":7,"skipped":23,"failed":0,"duration_ms":1.84}
```

## See Also

- [Configuration](configuration.md) — секция `modules.logging`.
- [Architecture](architecture.md) — диагностический слой и dependency flow.
- [CLI](cli.md) — lifecycle-команды, эмитящие события.
