<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\ModuleConfigKeys;
use DimitrienkoV\LaravelModules\Support\PathNormalizer;
use Illuminate\Support\Str;

final readonly class ModuleDirectoryPaths
{
    /**
     * @param list<string> $directories Module discovery roots, structurally
     *                                  validated by {@see \DimitrienkoV\LaravelModules\Support\ModulePathsConfig}.
     */
    public function __construct(
        private array $directories,
        private string $basePath,
        private string $appPath,
        private string $configuredBackupRoot,
    ) {}

    public function defaultTargetRoot(): string
    {
        $roots = $this->configuredRoots();

        return $roots[0];
    }

    public function resolveTargetRoot(string $directory): string
    {
        $roots = $this->configuredRoots();
        $normalizedDirectory = PathNormalizer::resolveAbsolute($directory, $this->basePath);

        foreach ($roots as $root) {
            if (PathNormalizer::normalize($root) === PathNormalizer::normalize($normalizedDirectory)) {
                return $root;
            }
        }

        $rootList = implode(', ', array_map(
            fn(string $root): string => '[' . $root . ']',
            $roots,
        ));

        throw InvalidConfigurationException::forKey(
            ModuleConfigKeys::DIRECTORIES,
            "directory [{$directory}] is not a configured module root. Configured roots: {$rootList}",
        );
    }

    public function targetModulePath(string $targetRoot, string $moduleName): string
    {
        return $targetRoot . '/' . Str::studly($moduleName);
    }

    public function backupRoot(): string
    {
        return PathNormalizer::resolveAbsolute($this->configuredBackupRoot, $this->basePath);
    }

    public function backupPath(string $moduleName): string
    {
        return $this->backupRoot() . '/' . $moduleName . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Resolve configured directories to absolute roots, enforcing the
     * consumer-specific `app_path()` containment guard. Structural validation
     * (non-empty list of non-empty strings) is already done by ModulePathsConfig;
     * this class owns only the containment rule, resolving roots as pure strings
     * — unlike {@see \DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner},
     * which resolves via `realpath` because it touches the filesystem.
     *
     * @return list<string>
     */
    public function configuredRoots(): array
    {
        $normalizedAppPath = PathNormalizer::normalize($this->appPath);
        $roots = [];

        foreach ($this->directories as $directory) {
            $root = PathNormalizer::resolveAbsolute($directory, $this->basePath);

            $normalizedRoot = PathNormalizer::normalize($root);
            if (! str_starts_with($normalizedRoot, $normalizedAppPath)) {
                throw InvalidConfigurationException::forKey(
                    ModuleConfigKeys::DIRECTORIES,
                    "directory [{$directory}] resolves outside app_path().",
                );
            }

            $roots[] = $root;
        }

        return $roots;
    }
}
