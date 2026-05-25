<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class ModuleStateWriteException extends RuntimeException
{
    public static function forPath(string $statePath, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Failed to write module state [{$statePath}]: {$reason}", 0, $previous);
    }
}
