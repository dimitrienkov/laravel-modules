<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;

final readonly class MoonShineModuleAutoloader
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {
    }

    public function autoload(CoreContract $core): void
    {
        foreach ($this->registry->loadOrder() as $module) {
            if ($module->isEnabled()) {
                $core->autoload($module->namespace);
            }
        }
    }
}
