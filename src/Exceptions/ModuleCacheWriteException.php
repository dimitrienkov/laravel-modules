<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleCacheWriteException extends RuntimeException
{
    public static function forPath(string $cachePath, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Unable to write module cache [{$cachePath}]: {$reason}",
            previous: $previous,
        );
    }
}
