<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;

final readonly class ManifestSettingsValidator
{
    private const array ALLOWED_SETTINGS_KEYS = [
        'schema' => true,
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

        FeatureSchema::fromArray($schemaRaw, $manifestPath);
    }
}
