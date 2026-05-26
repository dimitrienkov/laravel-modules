<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class ModuleAlreadyEnabledException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forModule(string $moduleName): self
    {
        return new self("Module [{$moduleName}] is already enabled.");
    }
}
