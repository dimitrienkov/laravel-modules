<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface FeatureRepositoryInterface
{
    public function get(string $moduleName, string $key): bool|int|string;

    public function getBool(string $moduleName, string $key): bool;

    public function getInt(string $moduleName, string $key): int;

    public function getString(string $moduleName, string $key): string;
}
