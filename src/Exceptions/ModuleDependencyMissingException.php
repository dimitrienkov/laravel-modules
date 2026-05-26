<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class ModuleDependencyMissingException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forDependency(string $moduleName, string $dependencyName): self
    {
        return new self("Module [{$moduleName}] requires missing dependency [{$dependencyName}].");
    }
}
