<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;

final readonly class ModuleStatePaths
{
    /**
     * @param list<string> $directories Configured module discovery roots,
     *                                  already structurally validated by the
     *                                  composition root.
     */
    public function __construct(
        private ?string $stateRoot,
        private array $directories,
        private string $basePath,
    ) {}

    public function root(): string
    {
        if ($this->stateRoot !== null) {
            return PathNormalizer::resolveAbsolute($this->stateRoot, $this->basePath);
        }

        return $this->basePath . '/storage/app/private/modules';
    }

    public function directory(string $moduleName): string
    {
        return $this->root() . '/' . $moduleName;
    }

    public function file(string $moduleName): string
    {
        return $this->directory($moduleName) . '/' . ModuleFileNames::STATE;
    }

    public function validate(): void
    {
        $stateRoot = $this->root();
        $normalizedStateRoot = PathNormalizer::normalize($stateRoot);

        foreach ($this->directories as $directory) {
            $resolved = PathNormalizer::resolveAbsolute($directory, $this->basePath);
            $normalizedDir = PathNormalizer::normalize($resolved);

            if (str_starts_with($normalizedStateRoot, $normalizedDir)) {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.state',
                    "state root [{$stateRoot}] must not be inside module directory [{$directory}].",
                );
            }
        }
    }
}
