<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;
use DimitrienkoV\LaravelModules\Support\StringKeyedObject;

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
     * @param array<array-key, mixed> $entry
     */
    public static function fromCacheEntry(string $name, array $entry, string $cachePath): self
    {
        $path = $entry['path'] ?? null;
        $namespace = $entry['namespace'] ?? null;
        $manifest = $entry['manifest'] ?? null;

        if (! \is_string($path) || ! \is_string($namespace) || ! \is_array($manifest)) {
            throw InvalidModuleCacheException::forPath(
                $cachePath,
                "module cache entry [{$name}] must contain path, namespace and manifest.",
            );
        }

        $manifestObject = StringKeyedObject::toStringKeyedObject(
            $manifest,
            static fn (): InvalidModuleCacheException => InvalidModuleCacheException::forPath(
                $cachePath,
                "module cache entry [{$name}] manifest must be an object.",
            ),
        );

        return new self(
            name: $name,
            path: $path,
            namespace: $namespace,
            manifest: $manifestObject,
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
