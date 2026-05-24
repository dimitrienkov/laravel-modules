<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class ManifestMeta
{
    private const array ALLOWED_KEYS = [
        'name' => true,
        'display_name' => true,
        'version' => true,
        'author' => true,
        'description' => true,
        'license' => true,
        'dependencies' => true,
    ];

    public function __construct(
        public string $name,
        public string $displayName,
        public string $version,
        public ?string $author,
        public ?string $description,
        public ?string $license,
        public ModuleDependencies $dependencies,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function fromArray(array $meta, string $manifestPath): self
    {
        self::assertKnownKeys($meta, $manifestPath);

        $name = self::requiredString($meta, 'name', $manifestPath);

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                'meta.name must be lowercase snake_case (a-z, 0-9, underscore, starting with a letter).'
            );
        }

        $version = self::requiredString($meta, 'version', $manifestPath);
        $displayName = self::optionalString($meta, 'display_name', $manifestPath) ?? $name;
        $author = self::optionalString($meta, 'author', $manifestPath);
        $description = self::optionalString($meta, 'description', $manifestPath);
        $license = self::optionalString($meta, 'license', $manifestPath);

        $dependenciesRaw = $meta['dependencies'] ?? [];
        if (! \is_array($dependenciesRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'meta.dependencies must be an array.');
        }

        return new self(
            name: $name,
            displayName: $displayName,
            version: $version,
            author: $author,
            description: $description,
            license: $license,
            dependencies: ModuleDependencies::fromArray($dependenciesRaw, $manifestPath),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $meta = [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'version' => $this->version,
            'dependencies' => $this->dependencies->toArray(),
        ];

        if ($this->author !== null) {
            $meta['author'] = $this->author;
        }

        if ($this->description !== null) {
            $meta['description'] = $this->description;
        }

        if ($this->license !== null) {
            $meta['license'] = $this->license;
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function assertKnownKeys(array $meta, string $manifestPath): void
    {
        foreach (array_keys($meta) as $key) {
            if (! isset(self::ALLOWED_KEYS[$key])) {
                throw InvalidManifestException::forPath($manifestPath, "meta contains unknown key [{$key}].");
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requiredString(array $data, string $key, string $manifestPath): string
    {
        $value = $data[$key] ?? null;

        if (! \is_string($value) || trim($value) === '') {
            throw InvalidManifestException::forPath($manifestPath, "meta.{$key} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalString(array $data, string $key, string $manifestPath): ?string
    {
        if (! \array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        if (! \is_string($data[$key])) {
            throw InvalidManifestException::forPath($manifestPath, "meta.{$key} must be a string.");
        }

        return $data[$key];
    }
}
