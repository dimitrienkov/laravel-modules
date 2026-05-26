<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;

interface ModuleStateRepositoryInterface
{
    public function read(string $moduleName, Module $module): ModuleStateDocument;

    public function readState(string $moduleName, Module $module): ModuleState;

    public function readValues(Module $module): FeatureValues;

    public function writeDocument(string $moduleName, ModuleStateDocument $document): void;

    public function writeState(Module $module, ModuleState $state): Module;

    public function writeValues(Module $module, FeatureValues $values): void;

    public function delete(string $moduleName): void;

    public function moveToBackup(string $moduleName, string $backupPath): ?string;

    public function exists(string $moduleName): bool;
}
