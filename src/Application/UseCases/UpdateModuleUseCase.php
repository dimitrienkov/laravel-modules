<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\SkippedFeatureValue;
use DimitrienkoV\LaravelModules\Application\DTOs\UpdateModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use Throwable;

final readonly class UpdateModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ModuleStateRepositoryInterface $stateRepository,
        private ModuleSourcePreparer $sourcePreparer,
        private ModuleDependencyGuard $dependencyGuard,
        private ModuleDirectoryOperations $directoryOps,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(string $moduleName, string $sourcePath): UpdateModuleResult
    {
        $existingModule = $this->registry->find($moduleName);
        $prepared = $this->sourcePreparer->prepare($sourcePath);

        try {
            $sourceName = $prepared->moduleName();
            if ($sourceName !== $moduleName) {
                throw ModuleUpdateException::nameMismatch($moduleName, $sourceName);
            }

            $preservedState = ModuleState::updatedFrom($existingModule->state);

            $candidate = Module::fromManifest(
                path: $existingModule->path,
                namespace: $existingModule->namespace,
                manifest: $prepared->manifest,
                manifestPath: $prepared->manifestPath,
                state: $preservedState,
            );

            $allModules = $this->registry->all();
            $candidateGraph = array_map(
                static fn (Module $m): Module => $m->name === $moduleName ? $candidate : $m,
                $allModules,
            );
            $this->dependencyGuard->assertGraphValid($candidateGraph);

            $existingValues = $this->stateRepository->readValues($existingModule);
            $existingStateDocument = $this->stateRepository->read($existingModule->name, $existingModule);

            try {
                $backupPath = $this->directoryOps->replaceDirectoryWithBackup(
                    $existingModule->path,
                    $prepared->path,
                    $moduleName,
                );
            } catch (DirectoryOperationException $e) {
                throw ModuleUpdateException::forModule($moduleName, $e->getMessage(), $e);
            }

            try {
                $this->manifestRepository->writeManifest($candidate);

                [$mergedValues, $skippedDetails] = $this->mergeValues(
                    $existingValues,
                    $candidate->features,
                    $candidate->name,
                    $candidate->manifestPath(),
                );

                $this->stateRepository->writeDocument(
                    $moduleName,
                    new ModuleStateDocument($preservedState, $mergedValues),
                );
            } catch (Throwable $e) {
                try {
                    $this->directoryOps->restoreBackup($backupPath, $existingModule->path);
                    $this->stateRepository->writeDocument($existingModule->name, $existingStateDocument);
                } catch (Throwable $restoreError) {
                    throw ModuleUpdateException::forModule(
                        $moduleName,
                        "persistence failed and restore also failed. Backup remains at [{$backupPath}]. Restore error: {$restoreError->getMessage()}",
                        $e,
                    );
                }

                throw ModuleUpdateException::forModule(
                    $moduleName,
                    'persistence failed after directory replacement, restored from backup.',
                    $e,
                );
            }

            $this->invalidator->flushAndReset();

            return new UpdateModuleResult(
                name: $moduleName,
                oldVersion: $existingModule->meta->version,
                newVersion: $candidate->meta->version,
                skippedValues: $skippedDetails,
                path: $existingModule->path,
            );
        } finally {
            $prepared->cleanup();
        }
    }

    /**
     * @return array{0: FeatureValues, 1: list<SkippedFeatureValue>}
     */
    private function mergeValues(
        FeatureValues $existingValues,
        FeatureSchema $newSchema,
        string $moduleName,
        string $manifestPath,
    ): array {
        $existingArray = $existingValues->toArray();
        $newSchemaDefinitions = $newSchema->all();
        $merged = [];
        $skipped = [];

        foreach ($existingArray as $key => $value) {
            if (! isset($newSchemaDefinitions[$key])) {
                $skipped[] = new SkippedFeatureValue($key, 'removed from schema');

                continue;
            }

            $definition = $newSchemaDefinitions[$key];

            try {
                $merged[$key] = $definition->normalize($value, $manifestPath);
            } catch (Throwable $e) {
                $skipped[] = new SkippedFeatureValue($key, 'invalid value: ' . $e->getMessage());
            }
        }

        return [
            FeatureValues::fromArray($merged, $newSchema, $moduleName, $manifestPath),
            $skipped,
        ];
    }
}
