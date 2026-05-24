<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class FeatureTypeMismatchException extends RuntimeException
{
    public static function forType(string $moduleName, string $key, string $expectedType, string $actualType): self
    {
        return new self(
            "Feature [{$key}] for module [{$moduleName}] must be [{$expectedType}], [{$actualType}] returned."
        );
    }
}
