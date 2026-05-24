<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleDependencyMissingException extends RuntimeException
{
    public static function forDependency(string $moduleName, string $dependencyName): self
    {
        return new self("Module [{$moduleName}] requires missing dependency [{$dependencyName}].");
    }
}
