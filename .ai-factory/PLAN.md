# Шаблон Pull Request

**Дата:** 2026-05-24
**Режим:** fast

## Settings

- Testing: no
- Logging: n/a
- Docs: no

## Задачи

### Фаза 1: Создание или актуализация шаблона PR

- [x] **Task 1: Создать или актуализировать `.github/PULL_REQUEST_TEMPLATE.md`**
  - Создать директорию `.github/`, если её ещё нет
  - Создать или обновить файл шаблона PR в чеклист-формате, не удаляя уже полезные существующие секции
  - Title — на английском (заполняется автором PR, подсказка в HTML-комментарии)
  - Все секции описания — на русском
  - Секции: Описание, Тип изменения (чекбоксы), Связанные issues, Чеклист качества
  - Чеклист включает реальные команды проекта: `composer test`, `composer phpstan` (level 8), `composer format:dry`, `composer rector:dry`
  - Чеклист также включает: документация при необходимости, обратная совместимость, `declare(strict_types=1)` для новых PHP-файлов
  - HTML-комментарии-подсказки для автора PR
  - Учтён стек проекта: Pest 3 для architecture suite, PHPUnit 11/12 для unit/feature, PHPStan level 8, Rector, PHP-CS-Fixer, strict_types
  - Файл: `.github/PULL_REQUEST_TEMPLATE.md`

## После плана

```
/aif-implement
```
