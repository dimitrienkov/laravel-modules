<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleSourceException extends RuntimeException
{
    public static function forPath(string $sourcePath, string $reason, ?Throwable $previous = null): self
    {
        return new self("Invalid module source [{$sourcePath}]: {$reason}", previous: $previous);
    }

    public static function unsupportedType(string $sourcePath): self
    {
        return new self("Unsupported module source type [{$sourcePath}]: expected a directory or .zip file.");
    }
}
