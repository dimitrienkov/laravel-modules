<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use RuntimeException;

final class DependentModulesExistException extends RuntimeException
{
    /**
     * @param list<string> $dependentNames
     */
    public static function forDisable(string $moduleName, array $dependentNames): self
    {
        $list = implode(', ', $dependentNames);

        return new self("Cannot disable module [{$moduleName}]: enabled modules depend on it [{$list}].");
    }

    /**
     * @param list<string> $dependentNames
     */
    public static function forRemove(string $moduleName, array $dependentNames): self
    {
        $list = implode(', ', $dependentNames);

        return new self("Cannot remove module [{$moduleName}]: installed modules depend on it [{$list}].");
    }
}
