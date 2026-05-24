<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

interface LoaderInterface
{
    public function load(Module $module): void;

    public function priority(): int;
}
