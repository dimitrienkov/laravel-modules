<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class InvalidConfigurationException extends RuntimeException
{
    public static function forKey(string $configKey, string $reason): self
    {
        return new self("Invalid module configuration [{$configKey}]: {$reason}");
    }
}
