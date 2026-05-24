<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;

final readonly class ModuleDependencies
{
    /**
     * @param array<string, string> $constraints
     */
    public function __construct(
        private array $constraints,
    ) {
    }

    /**
     * @param array<mixed> $dependencies
     */
    public static function fromArray(array $dependencies, string $manifestPath): self
    {
        $normalized = [];

        if (array_is_list($dependencies)) {
            foreach ($dependencies as $dependencyName) {
                if (! \is_string($dependencyName) || trim($dependencyName) === '') {
                    throw InvalidManifestException::forPath(
                        $manifestPath,
                        'meta.dependencies list entries must be non-empty module names.',
                    );
                }

                ManifestFieldReader::assertModuleName(
                    $dependencyName,
                    "meta.dependencies.{$dependencyName}",
                    $manifestPath,
                );

                $normalized[$dependencyName] = '*';
            }
        } else {
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

                $normalized[$dependencyName] = $constraint;
            }
        }

        ksort($normalized);

        return new self($normalized);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->constraints;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->constraints);
    }

    public function constraintFor(string $moduleName): ?string
    {
        return $this->constraints[$moduleName] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->constraints === [];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->constraints;
    }
}
