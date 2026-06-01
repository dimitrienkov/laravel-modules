<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Resolves a module group code into a display label for `modules:list`.
 *
 * Reads the `modules.groups` code → label map lazily and is lenient at display
 * time: malformed config (non-array map, non-string or empty label) is silently
 * ignored and falls back to the bare code, so listing never fails on a bad label.
 * This is a display convention, not contract validation of manifest/state.
 */
final readonly class ModuleGroupLabelResolver
{
    public function __construct(
        private Repository $config,
    ) {
    }

    public function label(?string $group): string
    {
        if ($group === null) {
            return '';
        }

        $mappedLabel = $this->mappedLabel($group);

        return $mappedLabel !== null ? "{$mappedLabel} ({$group})" : $group;
    }

    private function mappedLabel(string $group): ?string
    {
        $groups = $this->config->get('modules.groups');

        if (! \is_array($groups)) {
            return null;
        }

        $label = $groups[$group] ?? null;

        if (! \is_string($label) || trim($label) === '') {
            return null;
        }

        return $label;
    }
}
