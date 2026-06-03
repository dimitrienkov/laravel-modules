<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

/**
 * Validating owner of how `modules.paths.*` is read and structurally checked.
 *
 * Resolved once at the composition root (bound as a shared singleton, forced
 * eagerly in the provider's boot()) and read through {@see fromRepository()},
 * which validates the structure — `directories` is a non-empty list of non-empty
 * strings, `state`/`backup` are non-empty string paths — and keeps only scalars,
 * never the framework config repository, so path services stay decoupled and
 * Octane-safe. Both the production object graph and the test harness build their
 * path services from this one resolver, so invalid config fails identically
 * everywhere.
 *
 * It does NOT own consumer-specific path semantics: the `app_path()` containment
 * guard lives in {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths}
 * and {@see \DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner}, which
 * resolve roots differently (pure-string vs `realpath`) on purpose.
 */
final readonly class ModulePathsConfig
{
    /**
     * @param list<string> $directories
     */
    private function __construct(
        private array $directories,
        private string $stateRoot,
        private string $backupRoot,
    ) {}

    public static function fromRepository(Repository $config): self
    {
        return new self(
            self::readDirectories($config),
            self::readRequiredPath($config, ModuleConfigKeys::STATE_ROOT),
            self::readRequiredPath($config, ModuleConfigKeys::BACKUP_ROOT),
        );
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->directories;
    }

    public function stateRoot(): string
    {
        return $this->stateRoot;
    }

    public function backupRoot(): string
    {
        return $this->backupRoot;
    }

    /**
     * @return list<string>
     */
    private static function readDirectories(Repository $config): array
    {
        $directories = $config->get(ModuleConfigKeys::DIRECTORIES, []);

        if (! \is_array($directories)) {
            throw InvalidConfigurationException::forKey(
                ModuleConfigKeys::DIRECTORIES,
                'must be a list of directory paths.',
            );
        }

        $resolved = [];

        foreach ($directories as $index => $directory) {
            if (! \is_string($directory) || trim($directory) === '') {
                throw InvalidConfigurationException::forKey(
                    ModuleConfigKeys::DIRECTORIES,
                    \sprintf(
                        'entry at index %s must be a non-empty string, got [%s].',
                        \is_int($index) ? (string) $index : "'{$index}'",
                        get_debug_type($directory),
                    ),
                );
            }

            $resolved[] = $directory;
        }

        if ($resolved === []) {
            throw InvalidConfigurationException::forKey(
                ModuleConfigKeys::DIRECTORIES,
                'at least one module directory must be configured.',
            );
        }

        return $resolved;
    }

    private static function readRequiredPath(Repository $config, string $key): string
    {
        $value = $config->get($key);

        if (! \is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::forKey(
                $key,
                \sprintf('must be a non-empty string path, got [%s].', get_debug_type($value)),
            );
        }

        return $value;
    }
}
