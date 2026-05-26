<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class ModuleInstallException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forSource(string $source, string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to install module from [{$source}]: {$reason}", previous: $previous);
    }

    public static function forModule(string $moduleName, string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to install module [{$moduleName}]: {$reason}", previous: $previous);
    }
}
