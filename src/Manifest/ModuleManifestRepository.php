<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;

final readonly class ModuleManifestRepository implements ModuleManifestRepositoryInterface
{
    public function __construct(
        private ModuleLayout $layout,
        private AtomicJsonWriter $writer,
        private ManifestValidatorInterface $validator,
        private NamespaceResolverInterface $namespaceResolver,
        private ManifestDocumentReader $documentReader,
        private ModuleStateRepositoryInterface $stateRepository,
    ) {
    }

    public function load(string $modulePath): Module
    {
        $normalizedModulePath = rtrim($modulePath, '/\\');
        $manifestPath = $this->layout->manifestFilePath($normalizedModulePath);

        if (! is_file($manifestPath)) {
            throw ModuleNotFoundException::forPath($normalizedModulePath);
        }

        $manifest = $this->documentReader->read($manifestPath);
        $this->validator->validate($manifest, $manifestPath);

        $namespace = $this->namespaceResolver->resolve($normalizedModulePath);
        $metaRaw = $manifest['meta'];
        $moduleName = $metaRaw['name'];

        $manifestModule = Module::fromManifest(
            path: $normalizedModulePath,
            namespace: $namespace,
            manifest: $manifest,
            manifestPath: $manifestPath,
            state: \DimitrienkoV\LaravelModules\Manifest\VO\ModuleState::disabledDefault(),
        );

        $state = $this->stateRepository->readState($moduleName, $manifestModule);

        return $manifestModule->withState($state);
    }

    public function writeManifest(Module $module): void
    {
        $manifestPath = $this->layout->manifestFile($module);
        $manifest = $module->toManifestArray();

        $this->validator->validate($manifest, $manifestPath);
        $this->writer->write($manifestPath, $manifest);
    }
}
