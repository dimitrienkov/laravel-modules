[← Architecture](architecture.md) · [Back to README](../README.MD) · [CLI →](cli.md)

# Feature Toggles

Feature toggles - это настройки модулей, хранящиеся в `module.json`. Schema задаёт доступные settings и defaults; values содержат explicit overrides.

## Manifest shape

```json
{
  "settings": {
    "schema": {
      "enable_comments": {
        "type": "bool",
        "default": true
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

`settings.values` не обязан повторять defaults. Missing values fallback'ятся на `settings.schema`.

## Runtime API

Inject `FeatureRepositoryInterface` через Laravel container:

```php
<?php

declare(strict_types=1);

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;

final readonly class PostController
{
    public function __construct(
        private FeatureRepositoryInterface $features,
    ) {
    }

    public function index(): void
    {
        $commentsEnabled = $this->features->getBool('blog', 'enable_comments');
        $perPage = $this->features->getInt('blog', 'max_posts_per_page');
        $mode = $this->features->getString('blog', 'moderation_mode');
    }
}
```

## Methods

| Method | Return type | Behavior |
|--------|-------------|----------|
| `get(string $moduleName, string $key)` | `bool\|int\|string` | Возвращает resolved value |
| `getBool(string $moduleName, string $key)` | `bool` | Требует boolean value |
| `getInt(string $moduleName, string $key)` | `int` | Требует integer value |
| `getString(string $moduleName, string $key)` | `string` | Требует string value |

Typed methods бросают `FeatureTypeMismatchException`, если resolved value type не совпадает с requested type.

## Fresh values

`FeatureRepository` читает feature values через `ModuleManifestRepository::readValues()`.

Он намеренно не читает feature values из `bootstrap/cache/modules.php`. Production registry cache ускоряет module discovery, а feature values остаются актуальными на следующем request.

## Write boundary

Записывайте feature values через `ModuleManifestRepository::saveValues()`. Метод валидирует manifest и использует `AtomicJsonWriter`.

Не пишите `module.json` напрямую из application code.

## Error cases

| Case | Exception |
|------|-----------|
| Module is not registered | `ModuleNotFoundException` |
| Feature key is not defined | `FeatureNotFoundException` |
| Value type не совпадает с typed getter | `FeatureTypeMismatchException` |
| Manifest value fails schema validation | `InvalidManifestException` |

## See Also

- [Manifest](manifest.md) - schema и value format.
- [Architecture](architecture.md) - scoped binding и cache behavior.
- [Configuration](configuration.md) - module discovery settings.
