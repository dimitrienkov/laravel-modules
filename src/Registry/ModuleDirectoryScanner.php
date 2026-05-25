<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

final readonly class ModuleDirectoryScanner
{
    public function __construct(
        private Repository $config,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
        private string $basePath,
        private string $appPath,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function scan(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);

        if (! \is_array($directories)) {
            throw InvalidConfigurationException::forKey(
                'modules.paths.directories',
                'must be a list of directory paths.',
            );
        }

        $normalizedAppPath = $this->normalizePath($this->appPath);
        $moduleDirectories = [];

        foreach ($directories as $directory) {
            if (! \is_string($directory) || trim($directory) === '') {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.directories',
                    'each entry must be a non-empty string.',
                );
            }

            $root = $this->basePath . '/' . trim($directory, '/\\');
            $realRoot = realpath($root);

            if ($realRoot === false) {
                continue;
            }

            $normalizedRoot = $this->normalizePath($realRoot);

            if (! str_starts_with($normalizedRoot, $normalizedAppPath)) {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.directories',
                    "directory [{$directory}] resolves outside app_path().",
                );
            }

            foreach ($this->filesystem->directories($realRoot) as $modulePath) {
                if (is_file($this->layout->manifestFilePath($modulePath))) {
                    $moduleDirectories[] = $modulePath;
                }
            }
        }

        sort($moduleDirectories);

        return $moduleDirectories;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}
