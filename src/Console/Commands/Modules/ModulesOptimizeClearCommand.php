<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use Illuminate\Console\Command;

final class ModulesOptimizeClearCommand extends Command
{
    protected $signature = 'modules:optimize-clear';
    protected $description = 'Remove the cached module registry';

    public function handle(
        ModuleRegistryCache $cache,
        ModuleRegistry $registry,
    ): int {
        $this->components->info('Clearing cached module registry...');

        if ($cache->exists()) {
            $cache->forget();
            $registry->reset();
            $this->components->info('Module registry cache cleared.');
        } else {
            $this->components->info('No cache to clear.');
        }

        return self::SUCCESS;
    }
}
