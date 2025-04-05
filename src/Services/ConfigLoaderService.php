<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use LogicException;

final readonly class ConfigLoaderService
{
    public function __construct(
        protected Repository  $configRepository,
        protected Filesystem  $filesystem,
        protected Application $application,
    ) {
    }

    public function autoload(): void
    {
        $configFiles = $this->getConfigFiles();

        if ($configFiles->isEmpty()) {
            return;
        }

        $configFiles->each(function (mixed $configFile): void {
            $this->registerConfig($configFile);
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function getConfigFiles(): Collection
    {
        return (new Collection($this->filesystem->glob($this->getBasePath())))->filter();
    }

    private function getBasePath(): string
    {
        $modulesPath = $this->configRepository->get('modules.paths.modules', 'app/Modules');
        $configPath = $this->configRepository->get('modules.paths.config', 'Config');

        if (! \is_string($modulesPath) || ! \is_string($configPath)) {
            throw new LogicException('Invalid config paths for modules or config directory.');
        }

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
