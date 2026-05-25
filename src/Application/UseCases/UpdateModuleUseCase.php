<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\UpdateModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class UpdateModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
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
            $sourceName = $prepared->manifest['meta']['name'];
            if ($sourceName !== $moduleName) {
                throw ModuleUpdateException::nameMismatch($moduleName, $sourceName);
            }

            $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $preservedState = new ManifestState(
                enabled: $existingModule->state->enabled,
                installedAt: $existingModule->state->installedAt,
                updatedAt: $now,
            );

            $candidate = Module::fromManifest(
                path: $existingModule->path,
                namespace: $existingModule->namespace,
                manifest: $prepared->manifest,
                manifestPath: $prepared->manifestPath,
            )->withState($preservedState);

            $allModules = $this->registry->all();
            $candidateGraph = array_map(
                static fn (Module $m): Module => $m->name === $moduleName ? $candidate : $m,
                $allModules,
            );
            $this->dependencyGuard->assertGraphValid($candidateGraph);

            $existingValues = $this->manifestRepository->readValues($existingModule);
            $this->directoryOps->replaceDirectoryWithBackup(
                $existingModule->path,
                $prepared->path,
                $moduleName,
            );

            [$mergedValues, $skippedKeys] = $this->mergeValues(
                $existingValues,
                $candidate->features,
                $candidate->name,
                $candidate->manifestPath(),
            );

            $this->manifestRepository->saveValues($candidate, $mergedValues);
            $this->manifestRepository->updateState($candidate, $preservedState);
            $this->invalidator->invalidate();

            return new UpdateModuleResult(
                name: $moduleName,
                oldVersion: $existingModule->meta->version,
                newVersion: $candidate->meta->version,
                skippedValues: $skippedKeys,
                path: $existingModule->path,
            );
        } finally {
            $prepared->cleanup();
        }
    }

    /**
     * @return array{0: FeatureValues, 1: list<string>}
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
                $skipped[] = $key;

                continue;
            }

            $definition = $newSchemaDefinitions[$key];

            try {
                $merged[$key] = $definition->normalize($value, $manifestPath);
            } catch (\Throwable) {
                $skipped[] = $key;
            }
        }

        return [
            FeatureValues::fromArray($merged, $newSchema, $moduleName, $manifestPath),
            $skipped,
        ];
    }
}
