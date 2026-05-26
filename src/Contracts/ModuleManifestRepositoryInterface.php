<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface ModuleManifestRepositoryInterface
{
    public function load(string $modulePath): Module;

    public function writeManifest(Module $module): void;
}
