[← Module Structure](module-structure.md) · [Back to README](../README.md) · [Configuration →](configuration.md)

# Manifest

Каждый модуль должен иметь `module.json` в корне. Manifest иммутабелен — является источником правды для metadata и feature schema. Mutable state (`enabled`, timestamps, `settings.values`) хранится отдельно в `state.json` (см. [State file](#state-file)).

## Полный пример

```json
{
  "schema_version": 1,
  "meta": {
    "name": "blog",
    "display_name": "Blog",
    "kind": "module",
    "group": "content",
    "description": "Corporate blog with comments",
    "version": "1.0.0",
    "author": "Acme Studio",
    "license": "proprietary",
    "dependencies": {
      "users": "^1.5",
      "media": ">=1.4 <3.0"
    }
  },
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true,
        "label": "Enable comments"
      },
      "max_posts_per_page": {
        "type": "int",
        "default": 20,
        "min": 1,
        "max": 100
      },
      "moderation_mode": {
        "type": "enum",
        "default": "auto",
        "options": ["auto", "manual", "off"]
      }
    }
  }
}
```

## Разрешённые top-level ключи

| Ключ | Обязателен | Назначение |
|------|------------|------------|
| `schema_version` | Да | Версия формата manifest (integer). Текущая поддерживаемая — `1` |
| `meta` | Да | Module identity, version и dependencies |
| `settings` | Да | Feature schema (только `schema`, без `values`) |

`schema_version` — обязательный top-level integer. Текущая поддерживаемая версия — `1`; неизвестное значение → `InvalidManifestException` (strict-fail, без fallback). Неизвестные top-level ключи invalid. Legacy-секции `autoload` и `state` явно запрещены — state хранится в `state.json`.

## `meta`

| Поле | Обязательно | Назначение |
|------|-------------|------------|
| `name` | Да | Canonical module name для registry и feature API |
| `display_name` | Нет | Human-readable name; fallback на `name` |
| `kind` | Да | Backed enum `ModuleKind`: `module`, `subsystem`, `integration`. Презентационный — не влияет на loader pipeline, dependency resolution или enable/disable. При scaffold infer'ится из target-директории (`Modules→module`, `Integrations→integration`, `Subsystems→subsystem`) |
| `group` | Нет | Логическая группа, segment kebab-case (`/^[a-z0-9]+(-[a-z0-9]+)*$/`): lowercase буквы и цифры в сегментах через одиночный дефис, без ведущего/двойного/хвостового дефиса. Используется для отображения в `modules:list` и маппинга `modules.groups`; не влияет на pipeline или dependency resolution |
| `description` | Нет | Описание модуля |
| `version` | Да | Version для dependency checks |
| `author` | Нет | Author string |
| `license` | Нет | License string |
| `dependencies` | Нет | Map `moduleName => Composer SemVer constraint` |

Dependencies задаются только object-формой:

```json
{
  "dependencies": {
    "users": "^1.5"
  }
}
```

List-form dependencies не являются валидным manifest contract в v2.0 core. Для wildcard constraint указывайте `"*"` явно.

## State file

Mutable state хранится отдельно от manifest в `state.json`:

```
storage/app/private/modules/{name}/state.json
```

Путь настраивается через `modules.paths.state` (см. [Configuration](configuration.md)).

### Полный пример `state.json`

```json
{
  "enabled": true,
  "installed_at": "2026-05-23T14:12:00+00:00",
  "updated_at": "2026-05-23T14:12:00+00:00",
  "source": {
    "kind": "zip",
    "installed_version": "1.0.0",
    "checksum": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  },
  "settings": {
    "values": {
      "enable_comments": false,
      "max_posts_per_page": 50
    }
  }
}
```

### Разрешённые top-level ключи `state.json`

| Поле | Обязательно | Назначение |
|------|-------------|------------|
| `enabled` | Да | Управляет применением default loaders к модулю |
| `installed_at` | Нет | Installation timestamp |
| `updated_at` | Нет | Update timestamp |
| `source` | Нет | Provenance установленного модуля (см. [Source provenance](#source-provenance)) |
| `settings` | Нет | Object с `values` — explicit feature overrides |

### Source provenance

Опциональная секция `source` фиксирует происхождение установленного модуля. Записывается lifecycle UseCase-классами (scaffold, install, update) через `ModuleOrigin` VO и не влияет на loader pipeline или registry cache.

| Поле | Обязательно | Назначение |
|------|-------------|------------|
| `kind` | Да | Backed enum `ModuleOriginKind`: `local` (scaffold) или `zip` (install/update из архива) |
| `installed_version` | Да | `meta.version` на момент установки/обновления |
| `checksum` | Зависит от `kind` | sha256-хеш архива (64 hex, lowercase). Обязателен для `zip`, отсутствует для `local` |

`local`-origin не несёт `checksum`, `zip`-origin обязан его иметь — инвариант проверяется при чтении `state.json`; нарушение → `InvalidModuleStateException`. Секция опциональна: `state.json` без `source` валиден (origin = `null`).

> **Provenance, не integrity verification.** `checksum` фиксирует sha256 zip-архива в момент его чтения при install/update — это запись происхождения, а не проверка целостности. Текущий runtime не сверяет архив или установленные файлы с ожидаемым digest или подписью. Verification/signature flow относится к packaging/marketplace roadmap, а не к реализованному поведению.

State writes должны идти через `ModuleStateRepository`. Команды `enable` и `disable`, а также запись feature values через repository/API, модифицируют только `state.json`. Команды `install`, `scaffold` и `update` создают или переписывают `module.json` через `writeManifest()` и создают `state.json`.

Если `state.json` отсутствует, `ModuleStateRepository` возвращает default disabled state и пустые explicit values. Это позволяет registry видеть модуль, но loader pipeline пропускает его до явного включения.

Source-модуль не должен содержать `state.json`. `ModuleSourcePreparer` отклоняет `.zip` source с таким файлом, потому что runtime state создаётся и переносится только host-приложением.

## `settings.schema`

Поддерживаемые feature types:

| Type | Value shape |
|------|-------------|
| `bool` | Boolean |
| `int` | Integer |
| `string` | String |
| `enum` | String из `options` |

Каждое feature-определение поддерживает ключи (неизвестный ключ → `InvalidManifestException`):

| Key | Обязателен | Описание |
|-----|------------|----------|
| `type` | Да | Один из `bool`, `int`, `string`, `enum` |
| `default` | Нет | Default value; нормализуется по type и constraints при чтении manifest |
| `min` / `max` | Нет | Только для `int` (диапазон значения) и `string` (длина в UTF-8 символах); для `bool` и `enum` запрещены. `min` не может превышать `max` |
| `options` | Для `enum` | Непустой список уникальных непустых строк; для остальных типов запрещён |
| `label` | Нет | Человекочитаемое имя фичи для UI; на runtime не влияет |
| `description` | Нет | Описание фичи для UI и документации; на runtime не влияет |
| `group` | Нет | Группировка фич для UI; на runtime не влияет |

`settings.schema` в `module.json` задаёт defaults. `settings.values` в `state.json` хранит только explicit overrides.

## `settings.values` (в `state.json`)

Runtime values хранятся в `state.json` и валидируются против schema из `module.json` при чтении и записи. Missing values берут default из schema без записи default-значений в файл.

State writes должны идти через `ModuleStateRepository::writeValues()` или `writeState()`. Прямой `file_put_contents()` может обойти validation и atomic write behavior.

## See Also

- [Feature Toggles](feature-toggles.md) - чтение feature values в runtime.
- [Architecture](architecture.md) - manifest repository и registry flow.
- [Module Structure](module-structure.md) - где лежит `module.json`.
