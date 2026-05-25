<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleUpdateException extends RuntimeException
{
    public static function forModule(string $moduleName, string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to update module [{$moduleName}]: {$reason}", previous: $previous);
    }

    public static function nameMismatch(string $expected, string $actual): self
    {
        return new self("Module name mismatch: expected [{$expected}], source contains [{$actual}].");
    }
}
