<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use Composer\Semver\VersionParser;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Validated semantic version of a module.
 *
 * Single-value VO holding the author-written version string. Validation is
 * delegated to composer/semver's VersionParser — the same library that resolves
 * dependency constraints ({@see \DimitrienkoV\LaravelModules\Support\TopologicalSorter})
 * — so the accepted grammar matches what the resolver understands instead of a
 * hand-rolled regex. The original string is preserved verbatim: module.json and
 * state.json must round-trip the author's value, never a normalized form.
 */
final readonly class Version
{
    public function __construct(public string $value)
    {
        try {
            (new VersionParser())->normalize($this->value);
        } catch (UnexpectedValueException $e) {
            throw new InvalidArgumentException("Version [{$this->value}] is not a valid semantic version.", $e->getCode(), previous: $e);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
