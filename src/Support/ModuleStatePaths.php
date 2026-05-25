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
            return $this->resolveAbsolute($stateRoot);
        }

        return $this->basePath . '/storage/app/private/modules';
    }

    public function stateDirectory(string $moduleName): string
    {
        return $this->stateRoot() . '/' . $moduleName;
    }

    public function stateFile(string $moduleName): string
    {
        return $this->stateDirectory($moduleName) . '/state.json';
    }

    public function validate(): void
    {
        $stateRoot = $this->stateRoot();

        $directories = $this->config->get('modules.paths.directories', []);
        if (! \is_array($directories)) {
            return;
        }

        $normalizedStateRoot = $this->normalizePath($stateRoot);

        foreach ($directories as $directory) {
            if (! \is_string($directory) || trim($directory) === '') {
                continue;
            }

            $resolved = $this->resolveAbsolute($directory);
            $normalizedDir = $this->normalizePath($resolved);

            if (str_starts_with($normalizedStateRoot, $normalizedDir)) {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.state',
                    "state root [{$stateRoot}] must not be inside module directory [{$directory}].",
                );
            }
        }
    }

    private function resolveAbsolute(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->basePath . '/' . trim($path, '/\\');
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}
