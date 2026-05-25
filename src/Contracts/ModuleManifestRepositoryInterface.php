<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface ModuleManifestRepositoryInterface
{
    public function load(string $modulePath): Module;

    public function readValues(Module $module): FeatureValues;

    public function saveValues(Module $module, FeatureValues $values): void;

    public function updateState(Module $module, ManifestState $state): Module;
}
