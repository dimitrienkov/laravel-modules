[← Diagnostic Logging](logging.md) · [Back to README](../README.md) · [CLI →](cli.md)

# Octane Compatibility

Пакет Octane-clean **by design** и не требует никакой дополнительной настройки под
[Laravel Octane](https://laravel.com/docs/octane). В `src/` нет фасадов, global
helpers и mutable static (закреплено arch-тестами), `Request` нигде не инъектится в
конструкторы, singleton-сервисы stateless, а per-request состояние фичетоглов живёт
в `scoped`-биндинге, который Octane сбрасывает сам на границе запроса.

Эта страница описывает **рантайм-контракт под Octane** и операционку вокруг него —
прежде всего когда нужен `octane:reload` и почему пакет осознанно не трогает
`flush`/`warm` в `config/octane.php`.

## Дуальный контракт под Octane

Под Octane воркер поднимает провайдер **один раз** и переиспользует контейнер между
запросами. Отсюда ключевое разделение, которое нужно держать в голове:

| Поверхность | Что это | Жизненный цикл под Octane |
|---|---|---|
| Значения фичетоглов (`settings.values`) | то, что вернёт `FeatureRepository::get*()` | **свежие каждый запрос** |
| Флаг `enabled` модуля | включён/выключен ли модуль | **заморожен на срок воркера** |
| Набор обнаруженных модулей | результат discovery | **заморожен на срок воркера** |
| Загруженные артефакты (routes, configs, providers, …) | то, что применили лоадеры на boot | **заморожены на срок воркера** |

**Значения свежие.** `FeatureRepositoryInterface` зарегистрирован как `scoped` и на
каждом новом скоупе перечитывает `state.json` через
`ModuleStateRepositoryInterface::readValues()`. Octane сбрасывает scoped-инстансы на
`OperationTerminated` (`forgetScopedInstances()`), поэтому правка `settings.values`
видна **со следующего запроса без всякого reload** — ровно так же, как в обычном
PHP-FPM. Это и есть смысл существования отдельного `state.json`: значения меняются
без перестроения снапшота и без сброса кэша discovery (`modules:optimize`).

**Структура заморожена.** Снапшот реестра (`ModuleRegistry`, singleton) строится
**один раз** на boot воркера: `ModuleLoaderPipeline::boot()` дёргает
`registry->all()`, а `ModuleManifestRepository::load()` в этот момент читает
`state.enabled` и зашивает его в снапшот. Сами лоадеры (routes, configs, providers,
migrations, …) тоже отрабатывают только на boot. Поэтому в пределах воркера флаг
`enabled`, набор модулей и загруженные артефакты **не меняются**, даже если на диске
их поправили.

Это корректное ограничение **того же класса, что `route:cache` / `config:cache`**:
структура фиксируется на старте ради скорости, а её изменение требует явного шага.

## Когда нужен `octane:reload`

Перезапуск воркеров (`php artisan octane:reload`) нужен после любой мутации
**замороженной** поверхности — то есть всего, что меняет снапшот:

- `modules:enable` / `modules:disable` — смена `enabled`;
- `modules:install` / `modules:update` / `modules:remove` — смена набора модулей;
- `modules:optimize` / `modules:optimize-clear` — перестроение/сброс кэша discovery;
- ручная правка `module.json` или `state.enabled`.

`octane:reload` **не нужен** после правки `settings.values` (значения фичетоглов) —
они подхватываются на следующем запросе сами.

> **Диагностическое логирование.** Секция `modules.logging` тоже читается на старте
> воркера: рантайм-переключение `modules.logging.*` под Octane требует
> `octane:reload` (см. [Diagnostic Logging](logging.md)).

`--max-requests` (дефолт 500 задаёт внешний пакет [`laravel/octane`](https://laravel.com/docs/octane),
а не этот пакет) периодически перезапускает воркер как страховка от утечек памяти —
это не замена `octane:reload` и на контракт выше не влияет.

## Почему пакет не трогает `flush` / `warm`

В `config/octane.php` пакету добавлять в `flush`/`warm` **нечего и не нужно** — это
осознанное решение, а не пропуск:

- **`scoped` чистит сам Octane.** `FeatureRepository` сбрасывается на
  `OperationTerminated` автоматически; ручной `flush` для него избыточен.
- **`ModuleRegistry` нельзя класть в `flush`.** Снапшот строится на boot вместе с
  отработавшими лоадерами. Сброс реестра между запросами рассинхронизировал бы
  «свежий» снапшот с уже зарегистрированными (и не перезапускаемыми) маршрутами,
  провайдерами и конфигами — классический stale-state баг.
- **`warm` ничего не даёт.** Снапшот уже «тёплый» — он строится на boot воркера, а не
  лениво на первом запросе. Помещать сюда `scoped`-репозиторий было бы прямой ошибкой
  (он обязан быть per-request).

Иными словами, инъекция `Request`/`config`/контейнера в конструкторы singleton'ов —
типичный Octane-анти-паттерн — в ядре отсутствует, поэтому компенсировать его через
`flush`/`warm` не требуется.

## Что гарантируют тесты

Контракт зафиксирован тестами и arch-гардами, чтобы регресс ловился локально без
реального сервера:

- **`tests/Feature/OctaneWorkerLifecycleTest.php`** — поведенческий worker-reuse:
  singleton-реестр переживает сброс скоупа, снапшот сохраняет идентичность между
  запросами, значения свежие / `enabled` заморожен в пределах одного воркера, нет
  роста состояния между запросами. Граница запроса моделируется через
  `forgetScopedInstances()`.
- **`tests/Feature/ModuleLoaderServiceProviderTest.php`** — регрессионный лок: через
  reflection проверяется, что `FeatureRepositoryInterface` зарегистрирован именно как
  `scoped` (а не `singleton`). Деградация в singleton ломает тест.
- **`tests/Feature/FeatureRepositoryScopedBindingTest.php`** — правка `state.values`
  видна на следующем скоупе.
- **`tests/Architecture/ArchitectureTest.php`** — инварианты: нет фасадов, нет
  mutable static, нет инъекции `Illuminate\Http\Request` в `src/`.

## Admin UI (roadmap)

Будущий MoonShine admin UI сможет менять `enabled` модулей по HTTP **внутри**
воркера. Поскольку лоадеры повторно не запускаются, такой UI обязан после мутации
триггерить `octane:reload` (или эквивалентный перезапуск воркеров) — иначе изменение
не вступит в силу до следующего reload. Это пункт roadmap, а не текущий runtime.

## See Also

- [Feature Toggles](feature-toggles.md) — scoped repository и per-request значения.
- [Architecture](architecture.md) — registry, snapshot, loader pipeline, lifecycle.
- [Diagnostic Logging](logging.md) — `modules.logging` тоже читается на старте воркера.
- [CLI](cli.md) — команды, после которых нужен `octane:reload`.
