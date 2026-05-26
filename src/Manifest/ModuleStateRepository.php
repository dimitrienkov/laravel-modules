<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleStateWriteException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleFileNames;
use DimitrienkoV\LaravelModules\Support\ModulePermissions;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;

final readonly class ModuleStateRepository implements ModuleStateRepositoryInterface
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'enabled' => true,
        'installed_at' => true,
        'updated_at' => true,
        'settings' => true,
    ];

    private const array ALLOWED_SETTINGS_KEYS = ['values' => true];

    public function __construct(
        private ModuleStatePaths $paths,
        private AtomicJsonWriter $writer,
        private LocalFilesystem $filesystem,
    ) {
    }

    public function read(string $moduleName, Module $module): ModuleStateDocument
    {
        $statePath = $this->paths->file($moduleName);

        if (! $this->filesystem->isFile($statePath)) {
            return new ModuleStateDocument(
                ModuleState::defaultDisabled(),
                new FeatureValues($module->features, []),
            );
        }

        $raw = $this->readStateFile($statePath);
        $this->validateTopLevelKeys($raw, $statePath);

        $stateArray = $this->extractStateFields($raw);
        $valuesArray = $this->extractValues($raw, $statePath);

        try {
            $state = ModuleState::fromArray($stateArray, $statePath);
            $values = FeatureValues::fromArray($valuesArray, $module->features, $moduleName, $statePath);
        } catch (InvalidManifestException $e) {
            throw InvalidModuleStateException::forPath($statePath, $e->getMessage(), $e);
        }

        return new ModuleStateDocument($state, $values);
    }

    public function readState(string $moduleName, Module $module): ModuleState
    {
        return $this->read($moduleName, $module)->state;
    }

    public function readValues(Module $module): FeatureValues
    {
        return $this->read($module->name, $module)->values;
    }

    public function writeDocument(string $moduleName, ModuleStateDocument $document): void
    {
        $statePath = $this->paths->file($moduleName);

        try {
            $this->writer->write($statePath, $document->toArray());
        } catch (ManifestWriteException $e) {
            throw ModuleStateWriteException::forPath($statePath, $e->getMessage(), $e);
        }
    }

    public function writeState(Module $module, ModuleState $state): Module
    {
        $currentValues = $this->readValues($module);
        $updated = $module->withState($state);
        $this->writeDocument($module->name, new ModuleStateDocument($state, $currentValues));

        return $updated;
    }

    public function writeValues(Module $module, FeatureValues $values): void
    {
        $statePath = $this->paths->file($module->name);

        $state = $this->filesystem->isFile($statePath)
            ? $this->readState($module->name, $module)
            : $module->state;

        $this->writeDocument($module->name, new ModuleStateDocument($state, $values));
    }

    public function delete(string $moduleName): void
    {
        $stateDir = $this->paths->directory($moduleName);

        if (! $this->filesystem->isDirectory($stateDir)) {
            return;
        }

        $stateFile = $this->paths->file($moduleName);

        if (! $this->filesystem->deleteFileIfExists($stateFile)) {
            throw ModuleStateWriteException::forPath(
                $stateFile,
                'state file could not be deleted.',
            );
        }

        if (! $this->filesystem->deleteDirectory($stateDir)) {
            throw ModuleStateWriteException::forPath(
                $stateDir,
                'state directory could not be removed.',
            );
        }
    }

    public function moveToBackup(string $moduleName, string $backupPath): ?string
    {
        $statePath = $this->paths->file($moduleName);

        if (! $this->filesystem->isFile($statePath)) {
            return null;
        }

        $backupStatePath = $backupPath . '/' . ModuleFileNames::STATE;
        $backupDir = \dirname($backupStatePath);

        if (! $this->filesystem->isDirectory($backupDir) && ! $this->filesystem->makeDirectory($backupDir, ModulePermissions::DIRECTORY, true)) {
            throw ModuleStateWriteException::forPath(
                $backupStatePath,
                "backup directory [{$backupDir}] could not be created.",
            );
        }

        if (! $this->filesystem->copyFile($statePath, $backupStatePath)) {
            throw ModuleStateWriteException::forPath(
                $backupStatePath,
                'state file could not be copied to backup.',
            );
        }

        $this->delete($moduleName);

        return $backupStatePath;
    }

    public function exists(string $moduleName): bool
    {
        return $this->filesystem->isFile($this->paths->file($moduleName));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStateFile(string $statePath): array
    {
        try {
            $content = $this->filesystem->get($statePath);
        } catch (FileNotFoundException $e) {
            throw InvalidModuleStateException::forPath($statePath, 'state file could not be read.', $e);
        }

        try {
            $raw = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidModuleStateException::forPath($statePath, 'state file contains invalid JSON: ' . $e->getMessage(), $e);
        }

        if (! \is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw InvalidModuleStateException::forPath($statePath, 'state file must contain a JSON object.');
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function validateTopLevelKeys(array $raw, string $statePath): void
    {
        foreach (array_keys($raw) as $key) {
            if (! isset(self::ALLOWED_TOP_LEVEL_KEYS[$key])) {
                throw InvalidModuleStateException::forPath(
                    $statePath,
                    "state file contains unknown top-level key [{$key}].",
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function extractStateFields(array $raw): array
    {
        return array_intersect_key($raw, ModuleState::ALLOWED_KEYS);
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function extractValues(array $raw, string $statePath): array
    {
        if (! \array_key_exists('settings', $raw)) {
            return [];
        }

        $settings = $this->assertJsonObject($raw['settings'], 'settings', $statePath);
        if ($settings === null) {
            return [];
        }

        foreach (array_keys($settings) as $key) {
            if (! isset(self::ALLOWED_SETTINGS_KEYS[$key])) {
                throw InvalidModuleStateException::forPath(
                    $statePath,
                    "settings contains unknown key [{$key}], only 'values' is allowed.",
                );
            }
        }

        if (! \array_key_exists('values', $settings)) {
            return [];
        }

        return $this->assertJsonObject($settings['values'], 'settings.values', $statePath) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assertJsonObject(mixed $value, string $fieldName, string $statePath): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! \is_array($value)) {
            throw InvalidModuleStateException::forPath($statePath, "{$fieldName} must be a JSON object.");
        }

        if ($value === []) {
            return null;
        }

        if (array_is_list($value)) {
            throw InvalidModuleStateException::forPath($statePath, "{$fieldName} must be a JSON object.");
        }

        return $value;
    }
}
