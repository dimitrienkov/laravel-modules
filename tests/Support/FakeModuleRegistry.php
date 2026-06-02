<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

/**
 * In-memory {@see ModuleRegistryInterface} for command/generator feature tests:
 * register a handful of {@see Module} VOs and resolve them by canonical name
 * without touching the filesystem-backed snapshot pipeline.
 */
final class FakeModuleRegistry implements ModuleRegistryInterface
{
    /**
     * @var array<string, Module>
     */
    private array $modules = [];

    public function add(Module $module): void
    {
        $this->modules[$module->name] = $module;
    }

    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function find(string $name): Module
    {
        return $this->modules[$name] ?? throw ModuleNotFoundException::forName($name);
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function reset(): void
    {
        // No cached state to clear in the in-memory fake.
    }
}
