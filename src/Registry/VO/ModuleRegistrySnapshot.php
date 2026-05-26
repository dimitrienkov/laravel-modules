<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ModuleRegistrySnapshot
{
    /** @var array<string, Module> */
    private array $moduleMap;

    /**
     * @param array<int, Module> $modules
     */
    public function __construct(
        private array $modules,
    ) {
        $map = [];

        foreach ($modules as $module) {
            if (isset($map[$module->name])) {
                throw InvalidManifestException::forPath(
                    $module->manifestPath(),
                    "duplicate module name [{$module->name}].",
                );
            }

            $map[$module->name] = $module;
        }

        $this->moduleMap = $map;
    }

    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $name): Module
    {
        return $this->moduleMap[$name] ?? throw ModuleNotFoundException::forName($name);
    }

    public function has(string $name): bool
    {
        return isset($this->moduleMap[$name]);
    }

    public function count(): int
    {
        return \count($this->modules);
    }
}
