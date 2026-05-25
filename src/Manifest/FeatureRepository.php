<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\FeatureTypeMismatchException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;

final class FeatureRepository implements FeatureRepositoryInterface
{
    /**
     * @var array<string, FeatureValues>
     */
    private array $values = [];

    public function __construct(
        private readonly ModuleRegistryInterface $registry,
        private readonly ModuleManifestRepositoryInterface $manifests,
    ) {
    }

    public function getBool(string $moduleName, string $key): bool
    {
        $value = $this->get($moduleName, $key);

        if (! \is_bool($value)) {
            throw FeatureTypeMismatchException::forType($moduleName, $key, 'bool', get_debug_type($value));
        }

        return $value;
    }

    public function get(string $moduleName, string $key): bool|int|string
    {
        return $this->values($moduleName)->get($moduleName, $key);
    }

    public function getInt(string $moduleName, string $key): int
    {
        $value = $this->get($moduleName, $key);

        if (! \is_int($value)) {
            throw FeatureTypeMismatchException::forType($moduleName, $key, 'int', get_debug_type($value));
        }

        return $value;
    }

    public function getString(string $moduleName, string $key): string
    {
        $value = $this->get($moduleName, $key);

        if (! \is_string($value)) {
            throw FeatureTypeMismatchException::forType($moduleName, $key, 'string', get_debug_type($value));
        }

        return $value;
    }

    private function values(string $moduleName): FeatureValues
    {
        if (isset($this->values[$moduleName])) {
            return $this->values[$moduleName];
        }

        $module = $this->registry->find($moduleName);
        $this->values[$moduleName] = $this->manifests->readValues($module);

        return $this->values[$moduleName];
    }
}
