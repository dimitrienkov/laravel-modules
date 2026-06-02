<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModulePermissions;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
use Throwable;

final readonly class ModuleSkeletonBuilder
{
    public function __construct(
        private LocalFilesystem $filesystem,
        private AtomicFileWriter $fileWriter,
        private string $stubsPath,
    ) {
    }

    /**
     * @param array<int, ScaffoldComponent>|null $components Explicit component
     *                                                       selection; `null` keeps the default minimal skeleton.
     */
    public function build(string $targetPath, string $namespace, string $studlyName, string $moduleName, ?array $components = null): void
    {
        $this->createDirectories($targetPath, $moduleName, $components);
        $this->renderProviderStub($targetPath, $namespace, $studlyName, $moduleName);
    }

    /**
     * @param array<int, ScaffoldComponent>|null $components
     */
    private function createDirectories(string $targetPath, string $moduleName, ?array $components): void
    {
        $directories = $components === null
            ? $this->defaultDirectories($targetPath)
            : $this->componentDirectories($targetPath, $components);

        foreach ($directories as $dir) {
            if (! $this->filesystem->isDirectory($dir) && ! $this->filesystem->makeDirectory($dir, ModulePermissions::DIRECTORY, true)) {
                throw ModuleScaffoldException::forModule(
                    $moduleName,
                    "failed to create directory [{$dir}].",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultDirectories(string $targetPath): array
    {
        return [
            $targetPath,
            $targetPath . '/Config',
            $targetPath . '/' . ModuleSegment::Commands->relativeDirectory(),
            $targetPath . '/' . ModuleSegment::Factories->relativeDirectory(),
            $targetPath . '/' . ModuleSegment::Migrations->relativeDirectory(),
            $targetPath . '/' . ModuleSegment::Models->relativeDirectory(),
            $targetPath . '/' . ModuleSegment::Middleware->relativeDirectory(),
            $targetPath . '/Providers',
            $targetPath . '/Routes',
        ];
    }

    /**
     * The mandatory module root and Providers directory, plus whatever the
     * selected components map to. The manifest and state are written by their
     * repositories, never as directories here.
     *
     * @param array<int, ScaffoldComponent> $components
     *
     * @return array<int, string>
     */
    private function componentDirectories(string $targetPath, array $components): array
    {
        $directories = [
            $targetPath,
            $targetPath . '/Providers',
        ];

        foreach ($components as $component) {
            foreach ($component->relativeDirectories() as $relative) {
                $directories[] = $targetPath . '/' . $relative;
            }
        }

        return array_values(array_unique($directories));
    }

    private function renderProviderStub(string $targetPath, string $namespace, string $studlyName, string $moduleName): void
    {
        $stubPath = $this->stubsPath . '/module-service-provider.stub';

        if (! $this->filesystem->isFile($stubPath)) {
            throw ModuleScaffoldException::forModule(
                $moduleName,
                "provider stub not found at [{$stubPath}].",
            );
        }

        try {
            $content = $this->filesystem->get($stubPath);
        } catch (Throwable $e) {
            throw ModuleScaffoldException::forModule(
                $moduleName,
                "failed to read provider stub [{$stubPath}].",
                $e,
            );
        }

        $content = str_replace(
            ['{{ namespace }}', '{{ studlyName }}'],
            [$namespace, $studlyName],
            $content,
        );

        $providerPath = $targetPath . '/Providers/' . $studlyName . 'ServiceProvider.php';

        try {
            $this->fileWriter->write($providerPath, $content);
        } catch (Throwable $e) {
            throw ModuleScaffoldException::forModule(
                $moduleName,
                "failed to write provider [{$providerPath}].",
                $e,
            );
        }
    }
}
