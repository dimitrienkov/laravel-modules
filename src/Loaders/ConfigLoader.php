<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

final readonly class ConfigLoader implements LoaderInterface
{
    public function __construct(
        private Repository $config,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $configDir = $this->layout->configDir($module);

        if (! $this->filesystem->isDirectory($configDir)) {
            return;
        }

        $files = $this->filesystem->glob($configDir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $this->mergeConfig($file);
        }
    }

    public function priority(): int
    {
        return 10;
    }

    private function mergeConfig(string $file): void
    {
        $key = basename($file, '.php');
        $data = require $file;
        $existing = $this->config->get($key);

        if (\is_array($existing) && \is_array($data)) {
            $this->config->set($key, array_replace_recursive($existing, $data));

            return;
        }

        $this->config->set($key, $data);
    }
}
