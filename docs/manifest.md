[← Module Structure](module-structure.md) · [Back to README](../README.MD) · [Configuration →](configuration.md)

# Manifest

Каждый модуль должен иметь `module.json` в корне. Manifest иммутабелен — является источником правды для metadata и feature schema. Mutable state (`enabled`, timestamps, `settings.values`) хранится отдельно в `state.json` (см. [State file](#state-file)).

## Полный пример

```json
{
  "meta": {
    "name": "blog",
    "display_name": "Blog",
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
| `meta` | Да | Module identity, version и dependencies |
| `settings` | Да | Feature schema (только `schema`, без `values`) |

Неизвестные top-level ключи invalid. Legacy-секции `autoload` и `state` явно запрещены — state хранится в `state.json`.

## `meta`

| Поле | Обязательно | Назначение |
|------|-------------|------------|
| `name` | Да | Canonical module name для registry и feature API |
| `display_name` | Нет | Human-readable name; fallback на `name` |
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
| `settings` | Нет | Object с `values` — explicit feature overrides |

State writes должны идти через `ModuleStateRepository`. Lifecycle-команды модифицируют только `state.json`, не трогая `module.json`.

## `settings.schema`

Поддерживаемые feature types:

| Type | Value shape |
|------|-------------|
| `bool` | Boolean |
| `int` | Integer |
| `string` | String |
| `enum` | String из `options` |

`settings.schema` в `module.json` задаёт defaults. `settings.values` в `state.json` хранит только explicit overrides.

## `settings.values` (в `state.json`)

Runtime values хранятся в `state.json` и валидируются против schema из `module.json` при чтении и записи. Missing values берут default из schema без записи default-значений в файл.

State writes должны идти через `ModuleStateRepository::saveValues()` или `updateState()`. Прямой `file_put_contents()` может обойти validation и atomic write behavior.

## See Also

- [Feature Toggles](feature-toggles.md) - чтение feature values в runtime.
- [Architecture](architecture.md) - manifest repository и registry flow.
- [Module Structure](module-structure.md) - где лежит `module.json`.
