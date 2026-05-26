<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class ModuleCacheWriteException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $cachePath, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Unable to write module cache [{$cachePath}]: {$reason}",
            previous: $previous,
        );
    }
}
