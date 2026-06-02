<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleResult;
use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\Manifest\VO\Version;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Support\PathNormalizer;
use Illuminate\Support\Str;
use Throwable;

final readonly class ScaffoldModuleUseCase
{
    private const string DEFAULT_VERSION = '1.0.0';

    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ModuleStateRepositoryInterface $stateRepository,
        private NamespaceResolverInterface $namespaceResolver,
        private ModuleDirectoryPaths $paths,
        private LifecycleRegistryInvalidator $invalidator,
        private ModuleSkeletonBuilder $skeletonBuilder,
        private ModuleDirectoryOperations $directoryOps,
        private PartialModuleRollback $rollback,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
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

        if ($config->force && $this->directoryOps->exists($targetPath)) {
            try {
                $this->directoryOps->deleteDirectory($targetPath);
            } catch (DirectoryOperationException $e) {
                throw ModuleScaffoldException::forModule($config->name, "failed to remove existing directory [{$targetPath}].", $e);
            }
        }

        $namespace = $this->namespaceResolver->resolve($targetPath);
        $studlyName = Str::studly($config->name);
        $resolvedKind = $config->kind ?? $this->inferKind($targetRoot);

        $this->diagnostics->lifecycleStarted(LifecycleOperation::Scaffold, $config->name);

        try {
            try {
                $this->skeletonBuilder->build($targetPath, $namespace, $studlyName, $config->name, $config->components);

                $state = ModuleState::initialState(enabled: $config->enabled);

                $module = new Module(
                    name: $config->name,
                    displayName: $studlyName,
                    namespace: $namespace,
                    path: $targetPath,
                    schemaVersion: ManifestValidator::CURRENT_SCHEMA_VERSION,
                    meta: new ManifestMeta(
                        name: $config->name,
                        displayName: $studlyName,
                        kind: $resolvedKind,
                        version: new Version(self::DEFAULT_VERSION),
                        author: null,
                        description: null,
                        license: null,
                        dependencies: new ModuleDependencies([]),
                        group: $config->group,
                    ),
                    state: $state,
                    features: new FeatureSchema([]),
                );

                $this->manifestRepository->writeManifest($module);

                $values = new FeatureValues($module->features, []);
                $origin = ModuleOrigin::forLocal($module->meta->version);
                $this->stateRepository->writeDocument($config->name, new ModuleStateDocument($state, $values, $origin));
            } catch (Throwable $e) {
                $cleanupNote = $this->rollback->rollback($config->name, $targetPath);

                $this->diagnostics->lifecycleRolledBack(LifecycleOperation::Scaffold, $config->name, 'scaffold');

                if ($e instanceof ModuleScaffoldException) {
                    throw $e;
                }

                throw ModuleScaffoldException::forModule(
                    $config->name,
                    'scaffold failed, cleaned up partial artifacts.' . $cleanupNote,
                    $e,
                );
            }

            $this->invalidator->flushAndReset();

            $this->diagnostics->lifecycleSucceeded(LifecycleOperation::Scaffold, $config->name);

            $providerClass = $namespace . '\\Providers\\' . $studlyName . 'ServiceProvider';

            return new ScaffoldModuleResult(
                name: $config->name,
                path: $targetPath,
                enabled: $config->enabled,
                providerClass: $providerClass,
            );
        } catch (Throwable $e) {
            $this->diagnostics->lifecycleFailed(LifecycleOperation::Scaffold, $config->name, $e);

            throw $e;
        }
    }

    private function validateName(string $name): void
    {
        try {
            ManifestFieldReader::assertModuleName($name, 'meta.name', 'scaffold');
        } catch (Throwable $e) {
            throw ModuleScaffoldException::forModule($name, 'invalid module name — must be lowercase snake_case.', $e);
        }
    }

    private function inferKind(string $targetRoot): ModuleKind
    {
        $lastSegment = basename($targetRoot);

        return match ($lastSegment) {
            'Integrations' => ModuleKind::Integration,
            'Subsystems' => ModuleKind::Subsystem,
            default => ModuleKind::Module,
        };
    }

    private function assertNotExists(string $name, string $targetPath, bool $force): void
    {
        if ($this->registry->has($name)) {
            if (! $force) {
                throw ModuleAlreadyExistsException::forName($name);
            }

            $existing = $this->registry->find($name);

            if (PathNormalizer::normalize($existing->path) !== PathNormalizer::normalize($targetPath)) {
                throw ModuleAlreadyExistsException::forName($name);
            }
        }

        if (! $force && $this->directoryOps->exists($targetPath)) {
            throw ModuleAlreadyExistsException::forPath($name, $targetPath);
        }
    }
}
