[← Module Structure](module-structure.md) · [Back to README](../README.MD) · [Configuration →](configuration.md)

# Manifest

Каждый модуль должен иметь `module.json` в корне. Manifest является источником правды для metadata, enabled state, feature schema и explicit feature values.

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
  "state": {
    "enabled": true,
    "installed_at": "2026-05-23T14:12:00+00:00",
    "updated_at": "2026-05-23T14:12:00+00:00"
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
    },
    "values": {
      "enable_comments": false,
      "max_posts_per_page": 50
    }
  }
}
```

## Разрешённые top-level ключи

| Ключ | Обязателен | Назначение |
|------|------------|------------|
| `meta` | Да | Module identity, version и dependencies |
| `state` | Да | Runtime enabled state и optional timestamps |
| `settings` | Да | Feature schema и explicit values |

Неизвестные top-level ключи invalid. Legacy-секция `autoload` явно запрещена.

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

Dependencies можно задавать object-формой:

```json
{
  "dependencies": {
    "users": "^1.5"
  }
}
```

Short list form нормализуется в wildcard constraints:

```json
{
  "dependencies": ["users"]
}
```

Нормализованный результат эквивалентен:

```json
{
  "dependencies": {
    "users": "*"
  }
}
```

## `state`

| Поле | Обязательно | Назначение |
|------|-------------|------------|
| `enabled` | Да | Управляет применением default loaders к модулю |
| `installed_at` | Нет | Installation timestamp |
| `updated_at` | Нет | Update timestamp |

State writes должны идти через `ModuleManifestRepository::updateState()`.

## `settings.schema`

Поддерживаемые feature types:

| Type | Value shape |
|------|-------------|
| `bool` | Boolean |
| `int` | Integer |
| `string` | String |
| `enum` | String из `options` |

`settings.schema` задаёт defaults. `settings.values` хранит только explicit overrides.

## `settings.values`

Runtime values валидируются против schema при чтении и записи. Missing values берут default из schema без записи default-значений в файл.

Manifest writes должны идти через `ModuleManifestRepository::save()` или публичные update-методы repository. Прямой `file_put_contents()` может обойти validation и atomic write behavior.

## See Also

- [Feature Toggles](feature-toggles.md) - чтение feature values в runtime.
- [Architecture](architecture.md) - manifest repository и registry flow.
- [Module Structure](module-structure.md) - где лежит `module.json`.
