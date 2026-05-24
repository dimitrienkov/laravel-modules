<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\Module;

interface ModuleManifestRepositoryInterface
{
    public function load(string $modulePath): Module;

    public function save(Module $module): void;

    public function updateState(Module $module, ManifestState $state): Module;

    public function updateFeatureValues(Module $module, FeatureValues $values): Module;
}
