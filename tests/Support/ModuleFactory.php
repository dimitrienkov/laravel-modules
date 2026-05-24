<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Manifest\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\Module;
use DimitrienkoV\LaravelModules\Manifest\ModuleDependencies;

final readonly class ModuleFactory
{
    /**
     * @param array<string, string> $dependencies
     */
    public static function make(
        string $name = 'blog',
        string $version = '1.0.0',
        bool $enabled = true,
        array $dependencies = [],
        ?string $path = null,
        ?string $namespace = null,
    ): Module {
        $path ??= sys_get_temp_dir() . '/laravel-modules/' . $name;
        $namespace ??= 'App\\Modules\\' . ucfirst($name);
        $features = new FeatureSchema([]);

        return new Module(
            name: $name,
            displayName: ucfirst($name),
            namespace: $namespace,
            path: $path,
            meta: new ManifestMeta(
                name: $name,
                displayName: ucfirst($name),
                version: $version,
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies($dependencies),
            ),
            state: new ManifestState($enabled, null),
            features: $features,
            values: new FeatureValues($features, []),
        );
    }
}
