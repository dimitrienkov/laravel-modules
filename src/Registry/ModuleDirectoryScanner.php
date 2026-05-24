<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

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
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function scan(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);
        if (! \is_array($directories)) {
            return [];
        }

        $moduleDirectories = [];
        foreach ($directories as $directory) {
            if (! \is_string($directory)) {
                continue;
            }

            $root = $this->basePath . '/' . trim($directory, '/\\');
            if (! $this->filesystem->isDirectory($root)) {
                continue;
            }

            foreach ($this->filesystem->directories($root) as $modulePath) {
                if (is_file($this->layout->manifestFilePath($modulePath))) {
                    $moduleDirectories[] = $modulePath;
                }
            }
        }

        sort($moduleDirectories);

        return $moduleDirectories;
    }
}
