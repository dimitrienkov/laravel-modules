<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class FeatureNotFoundException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forKey(string $moduleName, string $key): self
    {
        return new self("Feature [{$key}] is not defined for module [{$moduleName}].");
    }
}
