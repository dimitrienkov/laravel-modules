<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

/**
 * Short (unqualified) name of a fully-qualified class string — the segment after
 * the final namespace separator, or the whole string when it has no namespace.
 *
 * A single home for the "class basename" one-liner that was duplicated across the
 * logger and the provider/factory loaders. Pure and dependency-free, so it never
 * reaches for Laravel's `class_basename()` global helper (forbidden in package
 * core).
 */
final class ClassName
{
    public static function short(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
