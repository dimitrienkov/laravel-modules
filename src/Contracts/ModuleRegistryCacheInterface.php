<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface ModuleRegistryCacheInterface
{
    public function cachePath(): string;

    public function exists(): bool;

    /**
     * @return array{modules: array<string, Module>, loadOrder: array<int, Module>}
     */
    public function load(): array;

    /**
     * @param array<int, Module> $loadOrder
     */
    public function write(array $loadOrder): string;

    public function forget(): void;
}
