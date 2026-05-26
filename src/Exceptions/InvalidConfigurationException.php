<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class InvalidConfigurationException extends RuntimeException implements ModuleExceptionInterface
{
    public static function forKey(string $configKey, string $reason): self
    {
        return new self("Invalid module configuration [{$configKey}]: {$reason}");
    }
}
