<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class ManifestValidator implements ManifestValidatorInterface
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'meta' => true,
        'state' => true,
        'settings' => true,
    ];

    private const array ALLOWED_SETTINGS_KEYS = [
        'schema' => true,
        'values' => true,
    ];

    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest, string $manifestPath): void
    {
        $this->assertTopLevelKeys($manifest, $manifestPath);

        $meta = $this->requiredObject($manifest, 'meta', $manifestPath);
        ManifestMeta::fromArray($meta, $manifestPath);

        $state = $this->requiredObject($manifest, 'state', $manifestPath);
        ManifestState::fromArray($state, $manifestPath);

        $settings = $this->requiredObject($manifest, 'settings', $manifestPath);
        $this->assertSettingsKeys($settings, $manifestPath);

        $schemaRaw = $settings['schema'] ?? [];
        if (! \is_array($schemaRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings.schema must be an object.');
        }

        $valuesRaw = $settings['values'] ?? [];
        if (! \is_array($valuesRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'settings.values must be an object.');
        }

        /** @var array<string, mixed> $schemaRaw */
        $schema = FeatureSchema::fromArray($schemaRaw, $manifestPath);
        /** @var array<string, mixed> $valuesRaw */
        FeatureValues::fromArray($valuesRaw, $schema, $manifestPath);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assertTopLevelKeys(array $manifest, string $manifestPath): void
    {
        foreach (array_keys($manifest) as $key) {
            if ($key === 'autoload') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    'autoload section is not supported; loaders use ModuleLayout conventions.'
                );
            }

            if (! isset(self::ALLOWED_TOP_LEVEL_KEYS[$key])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "manifest contains unknown top-level key [{$key}]."
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function requiredObject(array $data, string $key, string $manifestPath): array
    {
        $value = $data[$key] ?? null;

        if (! \is_array($value) || ($value !== [] && array_is_list($value))) {
            throw InvalidManifestException::forPath($manifestPath, "{$key} must be an object.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function assertSettingsKeys(array $settings, string $manifestPath): void
    {
        foreach (array_keys($settings) as $key) {
            if (! isset(self::ALLOWED_SETTINGS_KEYS[$key])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings contains unknown key [{$key}]."
                );
            }
        }
    }
}
