<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;
use Throwable;

final class DirectoryOperationException extends RuntimeException
{
    public static function forPath(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self("Directory operation failed at [{$path}]: {$reason}", previous: $previous);
    }
}
