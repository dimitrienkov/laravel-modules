<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use RuntimeException;

final class NamespaceResolutionException extends RuntimeException implements ModuleExceptionInterface
{
    public static function missingComposerJson(string $composerPath): self
    {
        return new self("Unable to resolve module namespace: composer.json not found at [{$composerPath}].");
    }

    public static function missingPsr4(string $composerPath): self
    {
        return new self("Unable to resolve module namespace: no autoload.psr-4 roots in [{$composerPath}].");
    }

    public static function unresolvedPath(string $modulePath, string $composerPath): self
    {
        return new self(
            "Unable to resolve namespace for module path [{$modulePath}] from PSR-4 roots in [{$composerPath}].",
        );
    }

    public static function outsideAppPath(string $modulePath, string $appPath): self
    {
        return new self(
            "Module path [{$modulePath}] is outside the application path [{$appPath}]. Modules must reside within app_path().",
        );
    }
}
