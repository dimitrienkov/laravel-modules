<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleDependencyDisabledException extends RuntimeException
{
    public static function forDependency(string $moduleName, string $dependencyName): self
    {
        return new self("Module [{$moduleName}] requires disabled dependency [{$dependencyName}].");
    }
}
