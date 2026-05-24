<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface FeatureRepositoryInterface
{
    public function get(string $moduleName, string $key): bool|int|string;

    public function bool(string $moduleName, string $key): bool;

    public function int(string $moduleName, string $key): int;

    public function string(string $moduleName, string $key): string;
}
