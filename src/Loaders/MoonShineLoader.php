<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\Module;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;

final readonly class MoonShineLoader implements LoaderInterface
{
    public function __construct(
        private CoreContract $core,
    ) {
    }

    public function load(Module $module): void
    {
        $this->core->autoload($module->namespace);
    }

    public function priority(): int
    {
        return 90;
    }
}
