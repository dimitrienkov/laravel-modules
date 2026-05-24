<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class FeatureNotFoundException extends RuntimeException
{
    public static function forKey(string $moduleName, string $key): self
    {
        return new self("Feature [{$key}] is not defined for module [{$moduleName}].");
    }
}
