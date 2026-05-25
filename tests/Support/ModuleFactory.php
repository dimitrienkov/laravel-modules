<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependency;

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
                dependencies: new ModuleDependencies(
                    array_combine(
                        array_keys($dependencies),
                        array_map(
                            static fn (string $constraint, string $name): ModuleDependency => new ModuleDependency($name, $constraint),
                            $dependencies,
                            array_keys($dependencies),
                        ),
                    ),
                ),
            ),
            state: new ManifestState($enabled, null),
            features: new FeatureSchema([]),
        );
    }
}
