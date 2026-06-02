<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
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

    public function load(Module $module): LoadReport
    {
        $configDir = $this->layout->configDir($module);

        if (! $this->filesystem->isDirectory($configDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $files = $this->filesystem->glob($configDir . '/*.php') ?: [];
        sort($files);

        $merged = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $this->mergeConfig($module, $file);
            $merged[] = basename($file);
        }

        if ($merged === []) {
            return LoadReport::skipped(SkipReason::EmptyDirectory);
        }

        return LoadReport::applied(['config' => $merged]);
    }

    public function priority(): int
    {
        return 10;
    }

    private function mergeConfig(Module $module, string $file): void
    {
        $configKey = basename($file, '.php');
        $scopedKey = $module->name . '.' . $configKey;

        $data = require $file;
        $existing = $this->config->get($scopedKey);

        if (\is_array($existing) && \is_array($data)) {
            $this->config->set($scopedKey, array_replace_recursive($existing, $data));

            return;
        }

        $this->config->set($scopedKey, $data);
    }
}
