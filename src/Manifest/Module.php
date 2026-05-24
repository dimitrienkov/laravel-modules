<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class Module
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $namespace,
        public string $path,
        public ManifestMeta $meta,
        public ManifestState $state,
        public FeatureSchema $features,
        public FeatureValues $values,
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
    ): self {
        $metaRaw = $manifest['meta'] ?? null;
        if (! \is_array($metaRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'meta must be an object.');
        }

        $stateRaw = $manifest['state'] ?? null;
        if (! \is_array($stateRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'state must be an object.');
        }

        $settingsRaw = $manifest['settings'] ?? null;
        if (! \is_array($settingsRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings must be an object.');
        }

        $schemaRaw = $settingsRaw['schema'] ?? [];
        if (! \is_array($schemaRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings.schema must be an object.');
        }

        $valuesRaw = $settingsRaw['values'] ?? [];
        if (! \is_array($valuesRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings.values must be an object.');
        }

        /** @var array<string, mixed> $metaRaw */
        $meta = ManifestMeta::fromArray($metaRaw, $manifestPath);
        /** @var array<string, mixed> $stateRaw */
        $state = ManifestState::fromArray($stateRaw, $manifestPath);
        /** @var array<string, mixed> $schemaRaw */
        $features = FeatureSchema::fromArray($schemaRaw, $manifestPath);
        /** @var array<string, mixed> $valuesRaw */
        $values = FeatureValues::fromArray($valuesRaw, $features, $manifestPath);

        return new self(
            name: $meta->name,
            displayName: $meta->displayName,
            namespace: $namespace,
            path: $path,
            meta: $meta,
            state: $state,
            features: $features,
            values: $values,
        );
    }

    public function isEnabled(): bool
    {
        return $this->state->enabled;
    }

    public function manifestPath(): string
    {
        return $this->path . '/module.json';
    }

    public function withState(ManifestState $state): self
    {
        return new self(
            name: $this->name,
            displayName: $this->displayName,
            namespace: $this->namespace,
            path: $this->path,
            meta: $this->meta,
            state: $state,
            features: $this->features,
            values: $this->values,
        );
    }

    public function withFeatureValues(FeatureValues $values): self
    {
        return new self(
            name: $this->name,
            displayName: $this->displayName,
            namespace: $this->namespace,
            path: $this->path,
            meta: $this->meta,
            state: $this->state,
            features: $this->features,
            values: $values,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toManifestArray(): array
    {
        return [
            'meta' => $this->meta->toArray(),
            'state' => $this->state->toArray(),
            'settings' => [
                'schema' => $this->features->toArray(),
                'values' => $this->values->toArray(),
            ],
        ];
    }
}
