<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;

final readonly class ManifestSettingsValidator
{
    private const array ALLOWED_SETTINGS_KEYS = [
        'schema' => true,
        'values' => true,
    ];

    /**
     * @param array<string, mixed> $settings
     */
    public function validate(array $settings, string $moduleName, string $manifestPath): void
    {
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
        FeatureValues::fromArray($valuesRaw, $schema, $moduleName, $manifestPath);
    }
}
