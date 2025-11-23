<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

final readonly class ConfigLoaderService
{
    public function __construct(
        private Application $app,
        private Repository  $config,
        private Filesystem  $filesystem,
    ) {
    }

    public function autoload(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $this->discoverConfigFiles()
            ->each(fn (mixed $file) => $this->mergeConfig($file));
    }

    /**
     * @return Collection<int, string>
     */
    private function discoverConfigFiles(): Collection
    {
        /** @var string[] $directories */
        $directories = $this->config->get('modules.paths.directories', []);

        $configFolderRaw = $this->config->get('modules.paths.config', 'Config');
        $configFolder = \is_string($configFolderRaw) ? $configFolderRaw : 'Config';

        return Collection::make($directories)
            ->map(fn (mixed $path): string => $this->app->basePath("{$path}/*/{$configFolder}/*.php"))
            ->flatMap(fn (mixed $pattern): array => $this->filesystem->glob($pattern) ?: [])
            ->filter()
            ->values();
    }

    private function mergeConfig(string $file): void
    {
        $name = basename($file, '.php');
        $data = require $file;

        $existing = $this->config->get($name);

        if (\is_array($existing) && \is_array($data)) {
            $this->config->set($name, array_merge($existing, $data));

            return;
        }

        $this->config->set($name, $data);
    }
}
