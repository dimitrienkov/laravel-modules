<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Support\ModuleFileNames;

final readonly class Module
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $namespace,
        public string $path,
        public int $schemaVersion,
        public ManifestMeta $meta,
        public ModuleState $state,
        public FeatureSchema $features,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public static function fromManifest(
        string $path,
        string $namespace,
        array $manifest,
        string $manifestPath,
        ModuleState $state,
    ): self {
        $schemaVersion = ManifestFieldReader::requiredInt($manifest, 'schema_version', 'manifest', $manifestPath);

        $metaRaw = ManifestFieldReader::requiredObject($manifest, 'meta', $manifestPath);

        $settingsRaw = $manifest['settings'] ?? null;
        if (! \is_array($settingsRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings must be an object.');
        }

        $schemaRaw = $settingsRaw['schema'] ?? [];
        if (! \is_array($schemaRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings.schema must be an object.');
        }

        /** @var array<string, mixed> $metaRaw */
        $meta = ManifestMeta::fromArray($metaRaw, $manifestPath);
        /** @var array<string, mixed> $schemaRaw */
        $features = FeatureSchema::fromArray($schemaRaw, $manifestPath);

        return new self(
            name: $meta->name,
            displayName: $meta->displayName,
            namespace: $namespace,
            path: $path,
            schemaVersion: $schemaVersion,
            meta: $meta,
            state: $state,
            features: $features,
        );
    }

    public function isEnabled(): bool
    {
        return $this->state->enabled;
    }

    public function manifestPath(): string
    {
        return $this->path . '/' . ModuleFileNames::MANIFEST;
    }

    public function withState(ModuleState $state): self
    {
        return new self(
            name: $this->name,
            displayName: $this->displayName,
            namespace: $this->namespace,
            path: $this->path,
            schemaVersion: $this->schemaVersion,
            meta: $this->meta,
            state: $state,
            features: $this->features,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDescriptorArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'meta' => $this->meta->toArray(),
            'settings' => [
                'schema' => $this->features->toArray(),
            ],
        ];
    }

}
