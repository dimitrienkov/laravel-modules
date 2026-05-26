<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class ModuleArchiveException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $archivePath, string $reason, ?Throwable $previous = null): self
    {
        return new self("Archive operation failed [{$archivePath}]: {$reason}", previous: $previous);
    }

    public static function extensionMissing(): self
    {
        return new self('ext-zip is required for archive operations.');
    }

    public static function zipSlip(string $archivePath, string $entryName): self
    {
        return new self("Archive [{$archivePath}] contains unsafe entry [{$entryName}]: path traversal detected.");
    }
}
