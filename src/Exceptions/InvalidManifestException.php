<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;
use Throwable;

final class InvalidManifestException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $manifestPath, string $reason, ?Throwable $previous = null): self
    {
        return new self("Invalid module manifest [{$manifestPath}]: {$reason}", previous: $previous);
    }
}
