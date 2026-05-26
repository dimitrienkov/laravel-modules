<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface ModuleRegistryCacheInterface
{
    public function exists(): bool;

    /**
     * @return array{modules: array<string, \DimitrienkoV\LaravelModules\Manifest\VO\Module>, loadOrder: array<int, \DimitrienkoV\LaravelModules\Manifest\VO\Module>}
     */
    public function load(): array;

    /**
     * @param array<int, \DimitrienkoV\LaravelModules\Manifest\VO\Module> $loadOrder
     */
    public function write(array $loadOrder): string;

    public function forget(): void;
}
