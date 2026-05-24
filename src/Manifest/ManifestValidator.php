<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;

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

        $metaRaw = ManifestFieldReader::requiredObject($manifest, 'meta', $manifestPath);
        $meta = ManifestMeta::fromArray($metaRaw, $manifestPath);

        $state = ManifestFieldReader::requiredObject($manifest, 'state', $manifestPath);
        ManifestState::fromArray($state, $manifestPath);

        $settings = ManifestFieldReader::requiredObject($manifest, 'settings', $manifestPath);
        ManifestFieldReader::assertAllowedKeys($settings, self::ALLOWED_SETTINGS_KEYS, 'settings', $manifestPath);

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
        FeatureValues::fromArray($valuesRaw, $schema, $meta->name, $manifestPath);
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
                    'autoload section is not supported; loaders use ModuleLayout conventions.',
                );
            }

            if (! isset(self::ALLOWED_TOP_LEVEL_KEYS[$key])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "manifest contains unknown top-level key [{$key}].",
                );
            }
        }
    }
}
