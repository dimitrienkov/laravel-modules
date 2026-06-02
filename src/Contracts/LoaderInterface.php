<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface LoaderInterface
{
    public function load(Module $module): LoadReport;

    public function priority(): int;
}
