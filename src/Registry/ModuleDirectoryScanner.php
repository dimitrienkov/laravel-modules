<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Support\ModuleConfigKeys;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\PathNormalizer;

final readonly class ModuleDirectoryScanner
{
    /**
     * @param list<string> $directories Configured module discovery roots,
     *                                  produced and validated by
     *                                  {@see \DimitrienkoV\LaravelModules\Support\ModulePathsConfig},
     *                                  the single owner of `modules.paths.*`.
     */
    public function __construct(
        private array $directories,
        private LocalFilesystem $filesystem,
        private ModuleLayout $layout,
        private string $basePath,
        private string $appPath,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {}

    /**
     * @return array<int, string>
     */
    public function scan(): array
    {
        $normalizedAppPath = PathNormalizer::normalize($this->appPath);
        $moduleDirectories = [];

        foreach ($this->directories as $directory) {
            $relativeDirectory = trim($directory, '/\\');
            $root = $this->basePath . '/' . $relativeDirectory;
            $realRoot = realpath($root);

            if ($realRoot === false) {
                $this->diagnostics->discoveryRootMissing($relativeDirectory);

                continue;
            }

            $normalizedRoot = PathNormalizer::normalize($realRoot);

            if (! str_starts_with($normalizedRoot, $normalizedAppPath)) {
                $this->diagnostics->discoveryRootRejected($relativeDirectory, 'resolves outside app_path()');

                throw InvalidConfigurationException::forKey(
                    ModuleConfigKeys::DIRECTORIES,
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
