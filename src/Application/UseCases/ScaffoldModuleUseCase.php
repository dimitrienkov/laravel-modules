<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;

final readonly class ScaffoldModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ModuleStateRepositoryInterface $stateRepository,
        private NamespaceResolverInterface $namespaceResolver,
        private ModuleLifecyclePaths $paths,
        private LifecycleRegistryInvalidator $invalidator,
        private ModuleSkeletonBuilder $skeletonBuilder,
        private ModuleDirectoryOperations $directoryOps,
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
            $this->directoryOps->cleanupDirectory($targetPath);
        }

        $namespace = $this->namespaceResolver->resolve($targetPath);
        $studlyName = $this->studlyCase($config->name);

        try {
            $this->skeletonBuilder->build($targetPath, $namespace, $studlyName, $config->name);

            $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $state = new ModuleState(
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

            $this->manifestRepository->writeManifest($module);

            $values = new FeatureValues($module->features, []);
            $this->stateRepository->write($config->name, new ModuleStateDocument($state, $values));
        } catch (\Throwable $e) {
            $this->directoryOps->cleanupDirectory($targetPath);
            $this->stateRepository->delete($config->name);

            if ($e instanceof ModuleScaffoldException) {
                throw $e;
            }

            throw ModuleScaffoldException::forModule(
                $config->name,
                'scaffold failed, cleaned up partial artifacts.',
                $e,
            );
        }

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
        try {
            $existing = $this->registry->find($name);

            if (! $force) {
                throw ModuleAlreadyExistsException::forName($name);
            }

            $normalizedExisting = rtrim(str_replace('\\', '/', $existing->path), '/');
            $normalizedTarget = rtrim(str_replace('\\', '/', $targetPath), '/');

            if ($normalizedExisting !== $normalizedTarget) {
                throw ModuleAlreadyExistsException::forName($name);
            }
        } catch (ModuleNotFoundException) {
        }

        if (! $force && is_dir($targetPath)) {
            throw ModuleAlreadyExistsException::forPath($name, $targetPath);
        }
    }

    private function studlyCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}
