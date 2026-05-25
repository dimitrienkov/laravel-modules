<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleAlreadyExistsException extends RuntimeException
{
    public static function forName(string $moduleName): self
    {
        return new self("Module [{$moduleName}] already exists in the registry.");
    }

    public static function forPath(string $moduleName, string $path): self
    {
        return new self("Module [{$moduleName}] target path [{$path}] already exists.");
    }
}
