<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;

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
        ManifestFieldReader::assertAllowedKeys($meta, self::ALLOWED_KEYS, 'meta', $manifestPath);

        $name = ManifestFieldReader::requiredString($meta, 'name', 'meta', $manifestPath);
        ManifestFieldReader::assertModuleName($name, 'meta.name', $manifestPath);

        $version = ManifestFieldReader::requiredString($meta, 'version', 'meta', $manifestPath);
        $displayName = ManifestFieldReader::optionalString($meta, 'display_name', 'meta', $manifestPath) ?? $name;
        $author = ManifestFieldReader::optionalString($meta, 'author', 'meta', $manifestPath);
        $description = ManifestFieldReader::optionalString($meta, 'description', 'meta', $manifestPath);
        $license = ManifestFieldReader::optionalString($meta, 'license', 'meta', $manifestPath);

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
}
