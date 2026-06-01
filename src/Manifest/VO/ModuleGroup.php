<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use InvalidArgumentException;

/**
 * Validated logical group of a module.
 *
 * Single-value VO holding a kebab-case group code (lowercase letters and digits
 * in hyphen-separated segments). The group is purely presentational — it drives
 * `modules:list` grouping and the `modules.groups` label map, never the loader
 * pipeline or dependency resolution. This VO is the single owner of the
 * kebab-case grammar and its error text: no other class re-implements them.
 */
final readonly class ModuleGroup
{
    private const string PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public function __construct(public string $value)
    {
        if (! preg_match(self::PATTERN, $this->value)) {
            throw new InvalidArgumentException(
                "Module group [{$this->value}] must be kebab-case: lowercase letters and digits in hyphen-separated segments.",
            );
        }
    }

    public function equals(?ModuleGroup $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}
