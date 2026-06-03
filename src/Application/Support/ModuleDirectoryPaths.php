<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\PathNormalizer;
use Illuminate\Support\Str;

final readonly class ModuleDirectoryPaths
{
    /**
     * @param list<string> $directories Configured module discovery roots,
     *                                  already structurally validated by the
     *                                  composition root.
     */
    public function __construct(
        private array $directories,
        private string $basePath,
        private string $appPath,
        private ?string $backupRoot = null,
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
            'modules.paths.directories',
            "directory [{$directory}] is not a configured module root. Configured roots: {$rootList}",
        );
    }

    public function targetModulePath(string $targetRoot, string $moduleName): string
    {
        return $targetRoot . '/' . Str::studly($moduleName);
    }

    public function backupRoot(): string
    {
        if ($this->backupRoot !== null) {
            return $this->backupRoot;
        }

        return $this->basePath . '/storage/app/module-backups';
    }

    public function backupPath(string $moduleName): string
    {
        return $this->backupRoot() . '/' . $moduleName . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    /**
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
}
