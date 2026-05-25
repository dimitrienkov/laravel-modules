<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleRemoveException extends RuntimeException
{
    public static function forModule(string $moduleName, string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to remove module [{$moduleName}]: {$reason}", previous: $previous);
    }
}
