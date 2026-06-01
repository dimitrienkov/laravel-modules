<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use Illuminate\Contracts\Config\Repository;

/**
 * Resolves a module group into a display label for `modules:list`.
 *
 * The `modules.groups` code → label map is read once at construction. An absent
 * map (null) and a missing mapping for a present group both fall back to the
 * bare code, but malformed config is fail-loud: a present-but-non-array map is
 * rejected eagerly in the constructor, while a present-but-non-string/blank
 * label for the requested group throws lazily when that group is rendered —
 * `modules.groups` is the only validation point for that map, so silent
 * fallback would hide it.
 */
final readonly class ModuleGroupLabelResolver
{
    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $groups;

    public function __construct(Repository $config)
    {
        $groups = $config->get('modules.groups');

        // Absent config is the default "no labels" case, not malformed — keep it
        // null. A present-but-non-array value is gross misconfiguration and fails
        // loudly here, deterministically, before any group is ever rendered.
        if ($groups !== null && ! \is_array($groups)) {
            throw InvalidConfigurationException::forKey(
                'modules.groups',
                'must be an array mapping group codes to display labels.',
            );
        }

        $this->groups = $groups;
    }

    public function displayLabel(?ModuleGroup $group): string
    {
        if (! $group instanceof ModuleGroup) {
            return '';
        }

        $label = $this->configuredLabel($group->value);

        if ($label === null) {
            return $group->value;
        }

        return "{$label} ({$group->value})";
    }

    private function configuredLabel(string $group): ?string
    {
        if ($this->groups === null || ! \array_key_exists($group, $this->groups)) {
            return null;
        }

        $label = $this->groups[$group];

        if (! \is_string($label) || trim($label) === '') {
            throw InvalidConfigurationException::forKey(
                'modules.groups',
                "label for group [{$group}] must be a non-empty string.",
            );
        }

        return $label;
    }
}
