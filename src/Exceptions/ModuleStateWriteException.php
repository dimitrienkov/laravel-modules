<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class ModuleStateWriteException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $statePath, string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to write module state [{$statePath}]: {$reason}", 0, $previous);
    }
}
