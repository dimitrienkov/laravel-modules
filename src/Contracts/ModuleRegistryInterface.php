<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\Module;

interface ModuleRegistryInterface
{
    /**
     * @return array<int, Module>
     */
    public function all(): array;

    /**
     * @return array<int, Module>
     */
    public function loadOrder(): array;

    public function find(string $name): Module;
}
