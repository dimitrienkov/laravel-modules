<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;

final class FakeNamespaceResolver implements NamespaceResolverInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $appSubDir = 'app',
        private readonly string $baseNamespace = 'App\\',
    ) {
    }

    public function resolve(string $modulePath): string
    {
        $appPath = $this->normalizePath($this->basePath . '/' . $this->appSubDir);
        $normalizedModulePath = $this->normalizePath($modulePath);

        if ($normalizedModulePath !== $appPath && ! str_starts_with($normalizedModulePath, $appPath . '/')) {
            throw NamespaceResolutionException::outsideAppPath($modulePath, $appPath);
        }

        $relativePath = trim(substr($normalizedModulePath, \strlen($appPath)), '/');
        $relativeNamespace = $relativePath === ''
            ? ''
            : '\\' . str_replace('/', '\\', $relativePath);

        return rtrim($this->baseNamespace, '\\') . $relativeNamespace;
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);
        $normalized = $realPath === false ? $path : $realPath;

        return rtrim(str_replace('\\', '/', $normalized), '/');
    }
}
