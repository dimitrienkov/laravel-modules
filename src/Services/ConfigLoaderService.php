<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class ConfigLoaderService
{
    public function __construct(
        protected Repository  $configRepository,
        protected Filesystem  $filesystem,
        protected Application $application,
    )
    {
    }

    public function loadConfigs(): void
    {
        $configFiles = $this->getConfigFiles();

        if ($configFiles->isEmpty()) {
            return;
        }

        $configFiles->each(fn(string $configFile) => $this->registerConfig($configFile));
    }

    private function getConfigFiles(): Collection
    {
        return (new Collection($this->filesystem->glob($this->getBasePath())))->filter();
    }

    private function getBasePath(): string
    {
        $modulesPath = $this->configRepository->get('modules.paths.modules', 'app/Modules');
        $configPath = $this->configRepository->get('modules.paths.config', 'Config');

        return $this->application->basePath("$modulesPath/*/$configPath/*.php");
    }

    public function registerConfig(string $configFile): void
    {
        $this->configRepository->set(
            basename($configFile, '.php'),
            $this->filesystem->requireOnce($configFile)
        );
    }
}
