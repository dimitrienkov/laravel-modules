<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class ModuleDependencyIncompatibleException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forDependency(
        string $moduleName,
        string $dependencyName,
        string $requiredConstraint,
        string $installedVersion,
    ): self {
        return new self(
            "Module [{$moduleName}] requires dependency [{$dependencyName}] matching [{$requiredConstraint}], "
            . "installed version is [{$installedVersion}]."
        );
    }
}
