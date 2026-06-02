<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use stdClass;

trait CreatesModuleFiles
{
    /**
     * Resolve the on-disk directory for a module inside a modules root.
     *
     * The directory name uses the studly form of the module name, matching the
     * scanner's expectation for `app/Modules/<Studly>`.
     */
    protected function moduleDirectory(string $modulesDir, string $name): string
    {
        return $modulesDir . '/' . ucfirst($name);
    }

    /**
     * Build the immutable manifest array for a module.
     *
     * Mirrors the shape produced by lifecycle scaffolding: top-level
     * `schema_version`, `meta` and `settings.schema` only.
     *
     * @param array<string, string>       $dependencies
     * @param array<string, mixed>|object $schema
     *
     * @return array<string, mixed>
     */
    protected function moduleManifestArray(
        string $name,
        string $version = '1.0.0',
        array $dependencies = [],
        array|object $schema = new stdClass(),
        string $kind = 'module',
        ?string $group = null,
    ): array {
        $manifest = [
            'schema_version' => 1,
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'kind' => $kind,
                'version' => $version,
            ],
            'settings' => [
                'schema' => $schema,
            ],
        ];

        if ($dependencies !== []) {
            $manifest['meta']['dependencies'] = $dependencies;
        }

        if ($group !== null) {
            $manifest['meta']['group'] = $group;
        }

        return $manifest;
    }

    /**
     * @param array<string, string>       $dependencies
     * @param array<string, mixed>|object $schema
     */
    protected function writeModuleManifest(
        string $modulesDir,
        string $name,
        string $version = '1.0.0',
        array $dependencies = [],
        array|object $schema = new stdClass(),
        string $kind = 'module',
        ?string $group = null,
    ): string {
        $modulePath = $this->moduleDirectory($modulesDir, $name);

        if (! is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
        }

        file_put_contents(
            $modulePath . '/module.json',
            json_encode(
                $this->moduleManifestArray($name, $version, $dependencies, $schema, $kind, $group),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );

        return $modulePath;
    }

    /**
     * @param array<string, mixed>|object|null $values
     * @param array<string, mixed>|null        $source
     */
    protected function writeModuleState(
        string $stateRoot,
        string $name,
        bool $enabled = true,
        ?string $installedAt = null,
        array|object|null $values = null,
        ?array $source = null,
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

        if ($source !== null) {
            $state['source'] = $source;
        }

        file_put_contents(
            $stateDir . '/state.json',
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Write a verbatim `state.json` payload, bypassing the structured builder.
     *
     * Use this for malformed/edge-case state fixtures where the raw bytes are
     * the subject of the test.
     */
    protected function writeRawState(string $stateRoot, string $name, string $json): void
    {
        $stateDir = $stateRoot . '/' . $name;
        if (! is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }

        file_put_contents($stateDir . '/state.json', $json);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readManifestFile(string $modulePath): array
    {
        return json_decode(
            (string) file_get_contents($modulePath . '/module.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
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
