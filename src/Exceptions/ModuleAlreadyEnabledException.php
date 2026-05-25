<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleAlreadyEnabledException extends RuntimeException
{
    public static function forModule(string $moduleName): self
    {
        return new self("Module [{$moduleName}] is already enabled.");
    }
}
