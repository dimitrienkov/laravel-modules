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
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;

final readonly class ModuleStateRepository implements ModuleStateRepositoryInterface
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'enabled' => true,
        'installed_at' => true,
        'updated_at' => true,
        'settings' => true,
    ];

    public function __construct(
        private ModuleStatePaths $paths,
        private AtomicJsonWriter $writer,
    ) {
    }

    public function read(string $moduleName, Module $module): ModuleStateDocument
    {
        $statePath = $this->paths->stateFile($moduleName);

        if (! is_file($statePath)) {
            return new ModuleStateDocument(
                ModuleState::disabledDefault(),
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

    public function write(string $moduleName, ModuleStateDocument $document): void
    {
        $statePath = $this->paths->stateFile($moduleName);

        try {
            $this->writer->write($statePath, $document->toArray());
        } catch (ManifestWriteException $e) {
            throw ModuleStateWriteException::forPath($statePath, $e->getMessage(), $e);
        }
    }

    public function updateState(Module $module, ModuleState $state): Module
    {
        $currentValues = $this->readValues($module);
        $updated = $module->withState($state);
        $this->write($module->name, new ModuleStateDocument($state, $currentValues));

        return $updated;
    }

    public function saveValues(Module $module, FeatureValues $values): void
    {
        $statePath = $this->paths->stateFile($module->name);

        $state = is_file($statePath)
            ? $this->readState($module->name, $module)
            : $module->state;

        $this->write($module->name, new ModuleStateDocument($state, $values));
    }

    public function delete(string $moduleName): void
    {
        $stateDir = $this->paths->stateDirectory($moduleName);

        if (! is_dir($stateDir)) {
            return;
        }

        $stateFile = $this->paths->stateFile($moduleName);

        if (is_file($stateFile)) {
            @unlink($stateFile);

            if (is_file($stateFile)) {
                throw ModuleStateWriteException::forPath(
                    $stateFile,
                    'state file could not be deleted.',
                );
            }
        }

        @rmdir($stateDir);

        if (is_dir($stateDir)) {
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

        $backupStatePath = $backupPath . '/state.json';
        $backupDir = \dirname($backupStatePath);

        if (! is_dir($backupDir) && ! mkdir($backupDir, 0755, true) && ! is_dir($backupDir)) {
            throw ModuleStateWriteException::forPath(
                $backupStatePath,
                "backup directory [{$backupDir}] could not be created.",
            );
        }

        if (! copy($statePath, $backupStatePath)) {
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
        $content = @file_get_contents($statePath);
        if ($content === false) {
            throw InvalidModuleStateException::forPath($statePath, 'state file could not be read.');
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
        $stateFields = [];

        if (\array_key_exists('enabled', $raw)) {
            $stateFields['enabled'] = $raw['enabled'];
        }
        if (\array_key_exists('installed_at', $raw)) {
            $stateFields['installed_at'] = $raw['installed_at'];
        }
        if (\array_key_exists('updated_at', $raw)) {
            $stateFields['updated_at'] = $raw['updated_at'];
        }

        return $stateFields;
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

        $settings = $raw['settings'];

        if (! \is_array($settings)) {
            if ($settings === null) {
                return [];
            }

            throw InvalidModuleStateException::forPath(
                $statePath,
                'settings must be a JSON object.',
            );
        }

        if ($settings === []) {
            return [];
        }

        if (array_is_list($settings)) {
            throw InvalidModuleStateException::forPath(
                $statePath,
                'settings must be a JSON object.',
            );
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

        $values = $settings['values'];

        if (! \is_array($values)) {
            if ($values === null) {
                return [];
            }

            throw InvalidModuleStateException::forPath(
                $statePath,
                'settings.values must be a JSON object.',
            );
        }

        if ($values === []) {
            return [];
        }

        if (array_is_list($values)) {
            throw InvalidModuleStateException::forPath(
                $statePath,
                'settings.values must be a JSON object.',
            );
        }

        return $values;
    }
}
