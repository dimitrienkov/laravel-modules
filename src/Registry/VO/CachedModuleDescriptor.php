<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;

final readonly class CachedModuleDescriptor
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
        public array $manifest,
    ) {
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function fromCacheEntry(string $name, array $entry, string $cachePath): self
    {
        if (
            ! \is_string($entry['path'] ?? null)
            || ! \is_string($entry['namespace'] ?? null)
            || ! \is_array($entry['manifest'] ?? null)
        ) {
            throw InvalidModuleCacheException::forPath(
                $cachePath,
                "module cache entry [{$name}] must contain path, namespace and manifest.",
            );
        }

        return new self(
            name: $name,
            path: $entry['path'],
            namespace: $entry['namespace'],
            manifest: $entry['manifest'],
        );
    }

    /**
     * @return array{path: string, namespace: string, manifest: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'namespace' => $this->namespace,
            'manifest' => $this->manifest,
        ];
    }
}
