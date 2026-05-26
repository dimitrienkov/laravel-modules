<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;

final readonly class ManifestValidator implements ManifestValidatorInterface
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'meta' => true,
        'settings' => true,
    ];

    public function __construct(
        private ManifestSettingsValidator $settingsValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest, string $manifestPath): void
    {
        $this->assertTopLevelKeys($manifest, $manifestPath);

        $metaRaw = ManifestFieldReader::requiredObject($manifest, 'meta', $manifestPath);
        /** @var array<string, mixed> $metaRaw */
        $meta = ManifestMeta::fromArray($metaRaw, $manifestPath);

        $settings = ManifestFieldReader::requiredObject($manifest, 'settings', $manifestPath);
        /** @var array<string, mixed> $settings */
        $this->settingsValidator->validate($settings, $meta->name, $manifestPath);
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
