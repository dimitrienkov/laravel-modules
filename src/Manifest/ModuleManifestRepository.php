<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use JsonException;

final readonly class ModuleManifestRepository implements ModuleManifestRepositoryInterface
{
    public function __construct(
        private ModuleLayout $layout,
        private AtomicJsonWriter $writer,
        private ManifestValidatorInterface $validator,
        private NamespaceResolverInterface $namespaceResolver,
    ) {
    }

    public function load(string $modulePath): Module
    {
        $normalizedModulePath = rtrim($modulePath, '/\\');
        $manifestPath = $this->layout->manifestFilePath($normalizedModulePath);

        if (! is_file($manifestPath)) {
            throw ModuleNotFoundException::forPath($normalizedModulePath);
        }

        $manifest = $this->readManifest($manifestPath);
        $this->validator->validate($manifest, $manifestPath);

        return Module::fromManifest(
            path: $normalizedModulePath,
            namespace: $this->namespaceResolver->resolve($normalizedModulePath),
            manifest: $manifest,
            manifestPath: $manifestPath,
        );
    }

    public function save(Module $module): void
    {
        $manifestPath = $this->layout->manifestFile($module);
        $manifest = $module->toManifestArray();

        $this->validator->validate($manifest, $manifestPath);
        $this->writer->write($manifestPath, $manifest);
    }

    public function updateFeatureValues(Module $module, FeatureValues $values): Module
    {
        $updated = $module->withFeatureValues($values);
        $this->save($updated);

        return $updated;
    }

    public function updateState(Module $module, ManifestState $state): Module
    {
        $updated = $module->withState($state);
        $this->save($updated);

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $manifestPath): array
    {
        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidManifestException::forPath($manifestPath, 'manifest could not be read.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidManifestException::forPath($manifestPath, $exception->getMessage());
        }

        if (! \is_array($manifest) || array_is_list($manifest)) {
            throw InvalidManifestException::forPath($manifestPath, 'manifest root must be a JSON object.');
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
