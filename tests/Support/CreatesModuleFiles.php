<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

trait CreatesModuleFiles
{
    /**
     * @param array<string, string>       $dependencies
     * @param array<string, mixed>|object $schema
     */
    protected function writeModuleManifest(
        string $modulesDir,
        string $name,
        string $version = '1.0.0',
        array $dependencies = [],
        array|object $schema = new \stdClass(),
    ): string {
        $studlyName = ucfirst($name);
        $modulePath = $modulesDir . '/' . $studlyName;

        if (! is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
        }

        $manifest = [
            'meta' => [
                'name' => $name,
                'display_name' => $studlyName,
                'version' => $version,
            ],
            'settings' => [
                'schema' => $schema,
            ],
        ];

        if ($dependencies !== []) {
            $manifest['meta']['dependencies'] = $dependencies;
        }

        file_put_contents(
            $modulePath . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return $modulePath;
    }

    /**
     * @param array<string, mixed>|object|null $values
     */
    protected function writeModuleState(
        string $stateRoot,
        string $name,
        bool $enabled = true,
        ?string $installedAt = null,
        array|object|null $values = null,
    ): void {
        $stateDir = $stateRoot . '/' . $name;
        if (! is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }

        $state = ['enabled' => $enabled];

        if ($installedAt !== null) {
            $state['installed_at'] = $installedAt;
        }

        if ($values !== null) {
            $state['settings'] = ['values' => $values];
        }

        file_put_contents(
            $stateDir . '/state.json',
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function readStateFile(string $stateRoot, string $name): array
    {
        return json_decode(
            (string) file_get_contents($stateRoot . '/' . $name . '/state.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
