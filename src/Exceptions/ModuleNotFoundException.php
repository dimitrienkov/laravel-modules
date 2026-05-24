<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleNotFoundException extends RuntimeException
{
    public static function forName(string $moduleName): self
    {
        return new self("Module [{$moduleName}] was not found.");
    }

    public static function forPath(string $modulePath): self
    {
        return new self("Module path [{$modulePath}] does not contain a module.json manifest.");
    }
}
