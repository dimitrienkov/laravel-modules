<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\PathNormalizer;
use Illuminate\Contracts\Config\Repository;

final readonly class ModuleDirectoryScanner
{
    public function __construct(
        private Repository $config,
        private LocalFilesystem $filesystem,
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

        $normalizedAppPath = PathNormalizer::normalize($this->appPath);
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

            $normalizedRoot = PathNormalizer::normalize($realRoot);

            if (! str_starts_with($normalizedRoot, $normalizedAppPath)) {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.directories',
                    "directory [{$directory}] resolves outside app_path().",
                );
            }

            foreach ($this->filesystem->directories($realRoot) as $modulePath) {
                if ($this->filesystem->isFile($this->layout->manifestFilePath($modulePath))) {
                    $moduleDirectories[] = $modulePath;
                }
            }
        }

        sort($moduleDirectories);

        return $moduleDirectories;
    }

}
