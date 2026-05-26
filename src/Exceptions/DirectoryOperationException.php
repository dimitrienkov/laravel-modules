<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class DirectoryOperationException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self("Directory operation failed at [{$path}]: {$reason}", previous: $previous);
    }
}
