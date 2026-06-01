<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use Throwable;

/**
 * Converts a decoded JSON array into a guaranteed string-keyed object.
 *
 * PHP coerces numeric-string JSON keys to integers, so a non-list array can
 * still carry integer keys — JSON `{"1": ...}` decodes to `[1 => ...]`. This
 * helper rejects any integer key instead of trusting the array type. The caller
 * owns the failure mode: `$onError` builds the context-specific typed exception
 * (manifest, cache or state), so this helper stays free of any exception class.
 */
final readonly class StringKeyedObject
{
    /**
     * @param array<array-key, mixed> $value
     * @param callable(): Throwable   $onError
     *
     * @return array<string, mixed>
     */
    public static function toStringKeyedObject(array $value, callable $onError): array
    {
        $object = [];

        foreach ($value as $key => $item) {
            if (! \is_string($key)) {
                throw $onError();
            }

            $object[$key] = $item;
        }

        return $object;
    }
}
