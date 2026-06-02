<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;
use Illuminate\Contracts\Foundation\Application;

final class ApplicationNamespaceResolver implements NamespaceResolverInterface
{
    private ?string $cachedAppPath = null;

    private ?string $cachedAppNamespace = null;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function resolve(string $modulePath): string
    {
        $appPath = $this->appPath();
        $normalizedModulePath = $this->normalizePath($modulePath);

        if (! $this->isWithin($normalizedModulePath, $appPath)) {
            throw NamespaceResolutionException::outsideAppPath($modulePath, $appPath);
        }

        $relativePath = trim(substr($normalizedModulePath, \strlen($appPath)), '/');
        $relativeNamespace = $relativePath === ''
            ? ''
            : '\\' . str_replace('/', '\\', $relativePath);

        return rtrim($this->appNamespace(), '\\') . $relativeNamespace;
    }

    private function appPath(): string
    {
        return $this->cachedAppPath ??= $this->normalizePath($this->app->path());
    }

    private function appNamespace(): string
    {
        return $this->cachedAppNamespace ??= $this->app->getNamespace();
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);
        $normalized = $realPath === false ? $path : $realPath;

        return rtrim(str_replace('\\', '/', $normalized), '/');
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . '/');
    }
}
