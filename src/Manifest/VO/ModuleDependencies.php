<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;

final readonly class ModuleDependencies
{
    /**
     * @param array<string, ModuleDependency> $dependencies
     */
    public function __construct(
        private array $dependencies,
    ) {
    }

    /**
     * @param array<mixed> $dependencies
     */
    public static function fromArray(array $dependencies, string $manifestPath): self
    {
        if (array_is_list($dependencies) && $dependencies !== []) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                'meta.dependencies must be an object mapping module names to Composer constraints; list form is not supported.',
            );
        }

        $normalized = [];

        foreach ($dependencies as $dependencyName => $constraint) {
            if (! \is_string($dependencyName) || trim($dependencyName) === '') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    'meta.dependencies object keys must be non-empty module names.',
                );
            }

            ManifestFieldReader::assertModuleName(
                $dependencyName,
                "meta.dependencies.{$dependencyName}",
                $manifestPath,
            );

            if (! \is_string($constraint) || trim($constraint) === '') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "meta.dependencies.{$dependencyName} must be a non-empty Composer constraint.",
                );
            }

            $normalized[$dependencyName] = new ModuleDependency(
                name: $dependencyName,
                constraint: $constraint,
            );
        }

        ksort($normalized);

        return new self($normalized);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->dependencies as $name => $dependency) {
            $result[$name] = $dependency->constraint;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->dependencies);
    }

    public function constraintFor(string $moduleName): ?string
    {
        return isset($this->dependencies[$moduleName])
            ? $this->dependencies[$moduleName]->constraint
            : null;
    }

    public function isEmpty(): bool
    {
        return $this->dependencies === [];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->all();
    }
}
