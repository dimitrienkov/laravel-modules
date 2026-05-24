<!--
  Title (EN): используйте Conventional Commits формат.
  Примеры:
    feat(loader): add BroadcastLoader for channels.php
    fix(manifest): prevent data loss on concurrent writes
    refactor(registry): extract ModuleCollection value object
    docs: update module lifecycle documentation
    test(arch): add Octane-safety architecture tests
    chore(deps): bump larastan to v3
-->

## Описание

<!-- Опишите что изменилось и зачем. Сфокусируйтесь на мотивации, а не на перечислении файлов — diff покажет что именно изменилось. -->

## Тип изменения

- [ ] Bug fix — исправление ошибки
- [ ] New feature — новая функциональность
- [ ] Refactoring — рефакторинг без изменения поведения
- [ ] Breaking change — обратно несовместимое изменение
- [ ] Documentation — только документация
- [ ] Chore — зависимости, CI, тулинг

## Связанные issues

<!-- Closes #123, Fixes #456, Relates to #789 -->

## Чеклист

- [ ] `composer test` проходит (Pest 3 arch + PHPUnit unit/feature)
- [ ] `composer phpstan` проходит (level 8)
- [ ] `composer format:dry` проходит (PHP-CS-Fixer)
- [ ] `composer rector:dry` не показывает новых замечаний
- [ ] `declare(strict_types=1)` в каждом новом `.php`-файле
- [ ] Обратная совместимость сохранена (или breaking change описан выше)
- [ ] Документация обновлена (если применимо)
