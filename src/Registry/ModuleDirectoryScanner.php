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
     * @param list<string> $directories Module discovery roots, structurally
     *                                  validated by {@see \DimitrienkoV\LaravelModules\Support\ModulePathsConfig}.
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
     * Discover module directories under the configured roots. This class owns the
     * scan-time `app_path()` containment guard, resolving roots via `realpath`
     * (it reads the filesystem and skips missing roots) — distinct from
     * {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths},
     * which resolves the same roots as pure strings for target computation.
     *
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
