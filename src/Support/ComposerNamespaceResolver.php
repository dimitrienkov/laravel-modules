<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;
use JsonException;

final readonly class ComposerNamespaceResolver implements NamespaceResolverInterface
{
    public function __construct(
        private string $basePath,
    ) {
    }

    public function resolve(string $modulePath): string
    {
        $composerPath = $this->basePath . '/composer.json';
        $roots = $this->psr4Roots($composerPath);
        $normalizedModulePath = $this->normalizePath($modulePath);

        usort(
            $roots,
            static fn (array $left, array $right): int => \strlen($right['path']) <=> \strlen($left['path'])
        );

        foreach ($roots as $root) {
            if (! $this->isWithin($normalizedModulePath, $root['path'])) {
                continue;
            }

            $relativePath = trim(substr($normalizedModulePath, \strlen($root['path'])), '/');
            $relativeNamespace = $relativePath === ''
                ? ''
                : '\\' . str_replace('/', '\\', $relativePath);

            return rtrim($root['namespace'], '\\') . $relativeNamespace;
        }

        throw NamespaceResolutionException::unresolvedPath($modulePath, $composerPath);
    }

    /**
     * @return array<int, array{namespace: string, path: string}>
     */
    private function psr4Roots(string $composerPath): array
    {
        if (! is_file($composerPath)) {
            throw NamespaceResolutionException::missingComposerJson($composerPath);
        }

        try {
            /** @var array<string, mixed> $composer */
            $composer = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw NamespaceResolutionException::missingPsr4($composerPath);
        }

        $psr4 = $composer['autoload']['psr-4'] ?? null;
        if (! \is_array($psr4) || $psr4 === []) {
            throw NamespaceResolutionException::missingPsr4($composerPath);
        }

        $roots = [];
        foreach ($psr4 as $namespace => $paths) {
            if (! \is_string($namespace)) {
                continue;
            }

            foreach ($this->normalizePsr4Paths($paths) as $path) {
                $roots[] = [
                    'namespace' => $namespace,
                    'path' => $this->normalizePath($this->basePath . '/' . $path),
                ];
            }
        }

        if ($roots === []) {
            throw NamespaceResolutionException::missingPsr4($composerPath);
        }

        return $roots;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePsr4Paths(mixed $paths): array
    {
        if (\is_string($paths)) {
            return [$paths];
        }

        if (! \is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, 'is_string'));
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
