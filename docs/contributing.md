[← CLI](cli.md) · [Back to README](../README.md)

# Contributing

Проект является Laravel-пакетом с architecture tests, static analysis, formatter rules и PR checklist.

## Local checks

Перед PR или commit запускайте проверки отдельно:

```bash
composer format
```

```bash
composer phpstan
```

```bash
composer test
```

Для review-oriented dry runs:

```bash
composer format:dry
```

```bash
composer rector:dry
```

Служебные скрипты для диагностики:

| Script | Purpose |
|--------|---------|
| `composer test:bail` | Pest с остановкой на первом падении |
| `composer rector` | Применить Rector-рефакторинги |
| `composer rector:fresh` | Rector со сбросом кэша |
| `composer rector:debug` | Rector dry-run с debug-выводом |
| `composer phpstan:debug` | PHPStan с debug-выводом |
| `composer phpstan:clear` | Очистить кэш PHPStan |

## Test suites

| Script | Purpose |
|--------|---------|
| `composer test:arch` | Pest architecture invariants |
| `composer test:unit` | PHPUnit unit tests |
| `composer test:feature` | Orchestra Testbench feature tests |
| `composer test` | Architecture, unit и feature suites |

## Architecture rules

Важные invariants для нового кода:

- Новые PHP-файлы под `src/`, `tests/` и `stubs/` требуют `declare(strict_types=1);`.
- Runtime code под `src/` должен использовать dependency injection вместо Laravel facades.
- Не добавляйте `dd()`, `dump()`, `var_dump()`, `print_r()`, `exit()` или `die()` под `src/`.
- Пишите `module.json` через public methods `ModuleManifestRepository`.
- Прямой filesystem I/O (`file_get_contents`, `file_put_contents`, `fopen`, `mkdir` и т.д.) разрешён только в инфраструктурных классах: `AtomicFileWriter`, `LocalFilesystem`, `ManifestDocumentReader`, `ModuleRegistryCache`, `FactoryLoader`. Остальной код работает через их abstractions.
- Держите optional integrations guarded по наличию class или interface.
- Документируйте roadmap-функции отдельно от implemented runtime.

## Pull requests

Используйте `.github/PULL_REQUEST_TEMPLATE.md`.

PR title format:

```text
feat(loader): add BroadcastLoader for channels.php
```

Title пишется на английском и следует Conventional Commits. PR description и checklist ведутся на русском.

## Documentation changes

При изменении документации:

- Держите `README.md` как landing page.
- Детальные topic pages размещайте в `docs/`.
- Сохраняйте navigation headers в порядке Documentation table из README.
- Завершайте каждую docs page секцией `See Also`.
- Отделяйте текущий runtime от roadmap items.

## See Also

- [Architecture](architecture.md) - dependency rules и runtime flow.
- [CLI](cli.md) - реализованные команды.
- [Manifest](manifest.md) - manifest write boundary.
