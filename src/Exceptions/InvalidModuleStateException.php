<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class InvalidModuleStateException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $statePath, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Invalid module state [{$statePath}]: {$reason}", 0, $previous);
    }

    public static function forModule(string $moduleName, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Invalid state for module [{$moduleName}]: {$reason}", 0, $previous);
    }
}
