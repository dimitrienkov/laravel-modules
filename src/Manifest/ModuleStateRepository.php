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
use DimitrienkoV\LaravelModules\Support\ModuleFileNames;
use DimitrienkoV\LaravelModules\Support\ModulePermissions;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use Illuminate\Filesystem\Filesystem;

final readonly class ModuleStateRepository implements ModuleStateRepositoryInterface
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'enabled' => true,
        'installed_at' => true,
        'updated_at' => true,
        'settings' => true,
    ];

    private const array STATE_FIELD_KEYS = [
        'enabled' => true,
        'installed_at' => true,
        'updated_at' => true,
    ];

    public function __construct(
        private ModuleStatePaths $paths,
        private AtomicJsonWriter $writer,
        private Filesystem $filesystem,
    ) {
    }

    public function read(string $moduleName, Module $module): ModuleStateDocument
    {
        $statePath = $this->paths->stateFile($moduleName);

        if (! is_file($statePath)) {
            return new ModuleStateDocument(
                ModuleState::defaultDisabled(),
                new FeatureValues($module->features, []),
            );
        }

        $raw = $this->readStateFile($statePath, $moduleName);
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
        $statePath = $this->paths->stateFile($moduleName);

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
        $statePath = $this->paths->stateFile($module->name);

        $state = is_file($statePath)
            ? $this->readState($module->name, $module)
            : $module->state;

        $this->writeDocument($module->name, new ModuleStateDocument($state, $values));
    }

    public function delete(string $moduleName): void
    {
        $stateDir = $this->paths->stateDirectory($moduleName);

        if (! is_dir($stateDir)) {
            return;
        }

        $stateFile = $this->paths->stateFile($moduleName);

        if (is_file($stateFile) && ! $this->filesystem->delete($stateFile)) {
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
        $statePath = $this->paths->stateFile($moduleName);

        if (! is_file($statePath)) {
            return null;
        }

        $backupStatePath = $backupPath . '/' . ModuleFileNames::STATE;
        $backupDir = \dirname($backupStatePath);

        if (! is_dir($backupDir) && ! $this->filesystem->makeDirectory($backupDir, ModulePermissions::DIRECTORY, true)) {
            throw ModuleStateWriteException::forPath(
                $backupStatePath,
                "backup directory [{$backupDir}] could not be created.",
            );
        }

        if (! $this->filesystem->copy($statePath, $backupStatePath)) {
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
        return is_file($this->paths->stateFile($moduleName));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStateFile(string $statePath, string $moduleName): array
    {
        try {
            $content = $this->filesystem->get($statePath);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            throw InvalidModuleStateException::forPath($statePath, 'state file could not be read.', $e);
        }

        try {
            $raw = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
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
        return array_intersect_key($raw, self::STATE_FIELD_KEYS);
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

        $allowedSettingsKeys = ['values' => true];
        foreach (array_keys($settings) as $key) {
            if (! isset($allowedSettingsKeys[$key])) {
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
