<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

final readonly class ModuleStatePaths
{
    public function __construct(
        private Repository $config,
        private string $basePath,
    ) {
    }

    public function stateRoot(): string
    {
        $stateRoot = $this->config->get('modules.paths.state');

        if ($stateRoot !== null && \is_string($stateRoot) && trim($stateRoot) !== '') {
            return PathNormalizer::resolveAbsolute($stateRoot, $this->basePath);
        }

        return $this->basePath . '/storage/app/private/modules';
    }

    public function stateDirectory(string $moduleName): string
    {
        return $this->stateRoot() . '/' . $moduleName;
    }

    public function stateFile(string $moduleName): string
    {
        return $this->stateDirectory($moduleName) . '/' . ModuleFileNames::STATE;
    }

    public function validate(): void
    {
        $stateRoot = $this->stateRoot();

        $directories = $this->config->get('modules.paths.directories', []);
        if (! \is_array($directories)) {
            return;
        }

        $normalizedStateRoot = PathNormalizer::normalize($stateRoot);

        foreach ($directories as $directory) {
            if (! \is_string($directory) || trim($directory) === '') {
                continue;
            }

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
