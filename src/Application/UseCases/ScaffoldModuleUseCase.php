<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use Illuminate\Filesystem\Filesystem;

final readonly class ScaffoldModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ManifestValidatorInterface $validator,
        private NamespaceResolverInterface $namespaceResolver,
        private ModuleLifecyclePaths $paths,
        private LifecycleRegistryInvalidator $invalidator,
        private Filesystem $filesystem,
    ) {
    }

    public function execute(ScaffoldModuleConfig $config): ScaffoldModuleResult
    {
        $this->validateName($config->name);

        $targetRoot = $config->directory !== null
            ? $this->paths->resolveTargetRoot($config->directory)
            : $this->paths->defaultTargetRoot();

        $targetPath = $this->paths->targetModulePath($targetRoot, $config->name);

        $this->assertNotExists($config->name, $targetPath, $config->force);

        if ($config->force && is_dir($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }

        $namespace = $this->namespaceResolver->resolve($targetPath);
        $studlyName = $this->studlyCase($config->name);

        $this->createDirectoryStructure($targetPath, $namespace, $studlyName);

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $state = new ManifestState(
            enabled: ! $config->disabled,
            installedAt: $now,
            updatedAt: $now,
        );

        $module = new Module(
            name: $config->name,
            displayName: $studlyName,
            namespace: $namespace,
            path: $targetPath,
            meta: new ManifestMeta(
                name: $config->name,
                displayName: $studlyName,
                version: '1.0.0',
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
            ),
            state: $state,
            features: new FeatureSchema([]),
        );

        $manifest = $module->toManifestArray(new FeatureValues($module->features, []));
        $this->validator->validate($manifest, $targetPath . '/module.json');
        $this->manifestRepository->saveValues($module, new FeatureValues($module->features, []));

        $this->invalidator->invalidate();

        $providerClass = $namespace . '\\Providers\\' . $studlyName . 'ServiceProvider';

        return new ScaffoldModuleResult(
            name: $config->name,
            path: $targetPath,
            enabled: ! $config->disabled,
            providerClass: $providerClass,
        );
    }

    private function validateName(string $name): void
    {
        try {
            ManifestFieldReader::assertModuleName($name, 'name', 'scaffold');
        } catch (\Throwable $e) {
            throw ModuleScaffoldException::forModule($name, 'invalid module name — must be lowercase snake_case.', $e);
        }
    }

    private function assertNotExists(string $name, string $targetPath, bool $force): void
    {
        if (! $force) {
            try {
                $this->registry->find($name);
                throw ModuleAlreadyExistsException::forName($name);
            } catch (\DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException) {
            }

            if (is_dir($targetPath)) {
                throw ModuleAlreadyExistsException::forPath($name, $targetPath);
            }
        }
    }

    private function createDirectoryStructure(string $targetPath, string $namespace, string $studlyName): void
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
            if (! is_dir($dir)) {
                $this->filesystem->makeDirectory($dir, 0755, true);
            }
        }

        $stubPath = dirname(__DIR__, 3) . '/stubs/module-service-provider.stub';
        if (is_file($stubPath)) {
            $content = file_get_contents($stubPath);
            $content = str_replace(
                ['{{ namespace }}', '{{ studlyName }}'],
                [$namespace, $studlyName],
                $content,
            );
            file_put_contents($targetPath . '/Providers/' . $studlyName . 'ServiceProvider.php', $content);
        }
    }

    private function studlyCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}
