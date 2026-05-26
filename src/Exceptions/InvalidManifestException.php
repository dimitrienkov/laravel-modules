<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class InvalidManifestException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forPath(string $manifestPath, string $reason): self
    {
        return new self("Invalid module manifest [{$manifestPath}]: {$reason}");
    }
}
