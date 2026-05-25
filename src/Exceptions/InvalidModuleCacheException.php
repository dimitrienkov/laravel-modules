<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class InvalidModuleCacheException extends RuntimeException
{
    public static function forPath(string $cachePath, string $reason): self
    {
        return new self("Invalid module cache [{$cachePath}]: {$reason}");
    }
}
