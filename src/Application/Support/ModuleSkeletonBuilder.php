<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModulePermissions;
use Throwable;

final readonly class ModuleSkeletonBuilder
{
    public function __construct(
        private LocalFilesystem $filesystem,
        private AtomicFileWriter $fileWriter,
    ) {
    }

    public function build(string $targetPath, string $namespace, string $studlyName, string $moduleName): void
    {
        $this->createDirectories($targetPath, $moduleName);
        $this->renderProviderStub($targetPath, $namespace, $studlyName, $moduleName);
    }

    private function createDirectories(string $targetPath, string $moduleName): void
    {
        $directories = [
            $targetPath,
            $targetPath . '/Config',
            $targetPath . '/Console/Commands',
            $targetPath . '/Database/Factories',
            $targetPath . '/Database/Migrations',
            $targetPath . '/Domain/Models',
            $targetPath . '/Http/Middleware',
            $targetPath . '/Providers',
            $targetPath . '/Routes',
        ];

        foreach ($directories as $dir) {
            if (! $this->filesystem->isDirectory($dir) && ! $this->filesystem->makeDirectory($dir, ModulePermissions::DIRECTORY, true)) {
                throw ModuleScaffoldException::forModule(
                    $moduleName,
                    "failed to create directory [{$dir}].",
                );
            }
        }
    }

    private function renderProviderStub(string $targetPath, string $namespace, string $studlyName, string $moduleName): void
    {
        $stubPath = \dirname(__DIR__, 3) . '/stubs/module-service-provider.stub';

        if (! $this->filesystem->isFile($stubPath)) {
            throw ModuleScaffoldException::forModule(
                $moduleName,
                "provider stub not found at [{$stubPath}].",
            );
        }

        try {
            $content = $this->filesystem->get($stubPath);
        } catch (\Throwable $e) {
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
