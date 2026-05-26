<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class CyclicDependencyException extends RuntimeException implements ModuleExceptionInterface
{
    /**
     * @param array<int, string> $cycle
     */
    public static function forCycle(array $cycle): self
    {
        return new self('Module dependency cycle detected: ' . implode(' -> ', $cycle));
    }
}
