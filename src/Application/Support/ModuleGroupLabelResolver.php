<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

/**
 * Resolves a module group code into a display label for `modules:list`.
 *
 * Reads the `modules.groups` code → label map lazily. An absent map (null) and
 * a missing mapping for a valid code both fall back to the bare code, but
 * malformed config is fail-loud: a present-but-non-array map, or a
 * present-but-non-string/blank label for the requested group, throws
 * InvalidConfigurationException — `modules.groups` is the only validation point
 * for that map, so silent fallback would hide it.
 */
final readonly class ModuleGroupLabelResolver
{
    public function __construct(
        private Repository $config,
    ) {
    }

    public function displayLabel(?string $group): string
    {
        if ($group === null) {
            return '';
        }

        $label = $this->configuredLabel($group);

        if ($label === null) {
            return $group;
        }

        return "{$label} ({$group})";
    }

    private function configuredLabel(string $group): ?string
    {
        $groups = $this->config->get('modules.groups');

        // Absent config is the default "no labels" case, not malformed — fall
        // back to the bare code. A present-but-non-array value is malformed.
        if ($groups === null) {
            return null;
        }

        if (! \is_array($groups)) {
            throw InvalidConfigurationException::forKey(
                'modules.groups',
                'must be an array mapping group codes to display labels.',
            );
        }

        if (! \array_key_exists($group, $groups)) {
            return null;
        }

        $label = $groups[$group];

        if (! \is_string($label) || trim($label) === '') {
            throw InvalidConfigurationException::forKey(
                'modules.groups',
                "label for group [{$group}] must be a non-empty string.",
            );
        }

        return $label;
    }
}
