<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

final readonly class ModuleStatePaths
{
    public function __construct(
        private string $configuredStateRoot,
        private string $basePath,
    ) {}

    public function root(): string
    {
        return PathNormalizer::resolveAbsolute($this->configuredStateRoot, $this->basePath);
    }

    public function directory(string $moduleName): string
    {
        return $this->root() . '/' . $moduleName;
    }

    public function file(string $moduleName): string
    {
        return $this->directory($moduleName) . '/' . ModuleFileNames::STATE;
    }
}
