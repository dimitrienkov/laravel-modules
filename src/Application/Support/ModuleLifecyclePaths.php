<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

final readonly class ModuleLifecyclePaths
{
    public function __construct(
        private Repository $config,
        private string $basePath,
        private string $appPath,
    ) {
    }

    public function defaultTargetRoot(): string
    {
        $roots = $this->configuredRoots();

        return $roots[0];
    }

    public function resolveTargetRoot(string $directory): string
    {
        $roots = $this->configuredRoots();
        $normalizedDirectory = $this->resolveAbsolute($directory);

        foreach ($roots as $root) {
            if ($this->normalizePath($root) === $this->normalizePath($normalizedDirectory)) {
                return $root;
            }
        }

        $rootList = implode(', ', array_map(
            fn (string $root): string => '[' . $root . ']',
            $roots,
        ));

        throw InvalidConfigurationException::forKey(
            'modules.paths.directories',
            "directory [{$directory}] is not a configured module root. Configured roots: {$rootList}",
        );
    }

    public function targetModulePath(string $targetRoot, string $moduleName): string
    {
        return $targetRoot . '/' . $this->studlyCase($moduleName);
    }

    public function backupRoot(): string
    {
        $backup = $this->config->get('modules.paths.backup');

        if ($backup !== null && \is_string($backup) && trim($backup) !== '') {
            return $backup;
        }

        return $this->basePath . '/storage/app/module-backups';
    }

    public function backupPath(string $moduleName): string
    {
        return $this->backupRoot() . '/' . $moduleName . '-' . date('Ymd-His');
    }

    public function collisionSafeBackupPath(string $moduleName): string
    {
        $base = $this->backupPath($moduleName);

        if (! is_dir($base)) {
            return $base;
        }

        $suffix = 1;
        while (is_dir($base . '-' . $suffix)) {
            $suffix++;
        }

        return $base . '-' . $suffix;
    }

    /**
     * @return list<string>
     */
    public function configuredRoots(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);

        if (! \is_array($directories)) {
            throw InvalidConfigurationException::forKey(
                'modules.paths.directories',
                'must be a list of directory paths.',
            );
        }

        $normalizedAppPath = $this->normalizePath($this->appPath);
        $roots = [];

        foreach ($directories as $directory) {
            if (! \is_string($directory) || trim($directory) === '') {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.directories',
                    'each entry must be a non-empty string.',
                );
            }

            $root = $this->resolveAbsolute($directory);

            $normalizedRoot = $this->normalizePath($root);
            if (! str_starts_with($normalizedRoot, $normalizedAppPath)) {
                throw InvalidConfigurationException::forKey(
                    'modules.paths.directories',
                    "directory [{$directory}] resolves outside app_path().",
                );
            }

            $roots[] = $root;
        }

        if ($roots === []) {
            throw InvalidConfigurationException::forKey(
                'modules.paths.directories',
                'at least one module directory must be configured.',
            );
        }

        return $roots;
    }

    private function resolveAbsolute(string $directory): string
    {
        if (str_starts_with($directory, '/')) {
            return $directory;
        }

        return $this->basePath . '/' . trim($directory, '/\\');
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    private function studlyCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}
