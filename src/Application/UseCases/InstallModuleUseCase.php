<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\InstallModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;

final readonly class InstallModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ModuleStateRepositoryInterface $stateRepository,
        private ModuleSourcePreparer $sourcePreparer,
        private ModuleDirectoryPaths $paths,
        private ModuleDependencyGuard $dependencyGuard,
        private ModuleDirectoryOperations $directoryOps,
        private LifecycleRegistryInvalidator $invalidator,
        private NamespaceResolverInterface $namespaceResolver,
    ) {
    }

    public function execute(string $sourcePath, ?string $directory = null, bool $enabled = true): InstallModuleResult
    {
        $prepared = $this->sourcePreparer->prepare($sourcePath);

        try {
            $moduleName = $prepared->moduleName();

            $this->assertNotInRegistry($moduleName);

            $targetRoot = $directory !== null
                ? $this->paths->resolveTargetRoot($directory)
                : $this->paths->defaultTargetRoot();

            $targetPath = $this->paths->targetModulePath($targetRoot, $moduleName);

            if (is_dir($targetPath)) {
                throw ModuleAlreadyExistsException::forPath($moduleName, $targetPath);
            }

            $namespace = $this->namespaceResolver->resolve($targetPath);

            $candidateState = ModuleState::initialState(enabled: $enabled);

            $candidate = Module::fromManifest(
                path: $targetPath,
                namespace: $namespace,
                manifest: $prepared->manifest,
                manifestPath: $prepared->manifestPath,
                state: $candidateState,
            );

            $allModules = $this->registry->all();
            $allModules[] = $candidate;
            $this->dependencyGuard->assertGraphValid($allModules);

            try {
                $this->directoryOps->copyDirectory($prepared->path, $targetPath);
            } catch (DirectoryOperationException $e) {
                throw ModuleInstallException::forSource($prepared->path, $e->getMessage(), $e);
            }

            try {
                $this->manifestRepository->writeManifest($candidate);

                $values = new FeatureValues($candidate->features, []);
                $this->stateRepository->writeDocument(
                    $candidate->name,
                    new ModuleStateDocument($candidateState, $values),
                );
            } catch (\Throwable $e) {
                $this->directoryOps->deleteDirectoryQuietly($targetPath);
                $this->stateRepository->delete($candidate->name);

                throw ModuleInstallException::forModule(
                    $moduleName,
                    'persistence failed after copy, rolled back target directory and state.',
                    $e,
                );
            }

            $this->invalidator->invalidate();

            return new InstallModuleResult(
                name: $candidate->name,
                path: $targetPath,
                enabled: $enabled,
                sourceKind: $prepared->sourceKind,
            );
        } finally {
            $prepared->cleanup();
        }
    }

    private function assertNotInRegistry(string $name): void
    {
        try {
            $this->registry->find($name);

            throw ModuleAlreadyExistsException::forName($name);
        } catch (ModuleNotFoundException) {
        }
    }
}
