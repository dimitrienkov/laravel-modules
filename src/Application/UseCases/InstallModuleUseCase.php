<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\InstallModuleResult;
use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use Throwable;

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
        private PartialModuleRollback $rollback,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {
    }

    public function execute(string $sourcePath, ?string $directory = null, bool $enabled = true): InstallModuleResult
    {
        $prepared = $this->sourcePreparer->prepare($sourcePath);

        try {
            $moduleName = $prepared->moduleName();

            $this->assertNotInRegistry($moduleName);

            $this->diagnostics->lifecycleStarted(LifecycleOperation::Install, $moduleName, $prepared->sourceKind->value);

            $targetRoot = $directory !== null
                ? $this->paths->resolveTargetRoot($directory)
                : $this->paths->defaultTargetRoot();

            $targetPath = $this->paths->targetModulePath($targetRoot, $moduleName);

            if ($this->directoryOps->exists($targetPath)) {
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
                $origin = ModuleOrigin::forZip($candidate->meta->version, $prepared->checksum);
                $this->stateRepository->writeDocument(
                    $candidate->name,
                    new ModuleStateDocument($candidateState, $values, $origin),
                );
            } catch (Throwable $e) {
                $cleanupNote = $this->rollback->rollback($moduleName, $targetPath);

                $this->diagnostics->lifecycleRolledBack(LifecycleOperation::Install, $moduleName, 'persistence');

                throw ModuleInstallException::forModule(
                    $moduleName,
                    'persistence failed after copy, rolled back target directory.' . $cleanupNote,
                    $e,
                );
            }

            $this->invalidator->flushAndReset();

            $this->diagnostics->lifecycleSucceeded(LifecycleOperation::Install, $moduleName);

            return new InstallModuleResult(
                name: $candidate->name,
                path: $targetPath,
                enabled: $enabled,
                sourceKind: $prepared->sourceKind,
            );
        } finally {
            $this->sourcePreparer->cleanup($prepared);
        }
    }

    private function assertNotInRegistry(string $name): void
    {
        if ($this->registry->has($name)) {
            throw ModuleAlreadyExistsException::forName($name);
        }
    }
}
