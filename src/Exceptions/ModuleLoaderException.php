<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Exceptions;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use RuntimeException;
use Throwable;

final class ModuleLoaderException extends RuntimeException implements ModuleExceptionInterface
{
    public function __construct(
        public readonly string $loaderClass,
        public readonly string $moduleName,
        public readonly string $modulePath,
        Throwable $previous,
    ) {
        parent::__construct(
            "Module loader [{$loaderClass}] failed for module [{$moduleName}] at [{$modulePath}]: "
                . $previous->getMessage(),
            previous: $previous,
        );
    }

    public static function forLoaderFailure(
        LoaderInterface $loader,
        Module $module,
        Throwable $previous,
    ): self {
        return new self(
            loaderClass: $loader::class,
            moduleName: $module->name,
            modulePath: $module->path,
            previous: $previous,
        );
    }
}
