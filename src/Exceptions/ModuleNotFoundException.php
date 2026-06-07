<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class ModuleNotFoundException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forName(string $moduleName, ?Throwable $previous = null): self
    {
        return new self("Module [{$moduleName}] was not found.", previous: $previous);
    }

    public static function forPath(string $modulePath, ?Throwable $previous = null): self
    {
        return new self("Module path [{$modulePath}] does not contain a module.json manifest.", previous: $previous);
    }

    public static function noSelection(?Throwable $previous = null): self
    {
        return new self('No module was selected.', previous: $previous);
    }
}
