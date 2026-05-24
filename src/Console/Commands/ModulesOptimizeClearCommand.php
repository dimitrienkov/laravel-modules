<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands;

use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModulesOptimizeClearCommand extends Command
{
    protected $signature = 'modules:optimize-clear';
    protected $description = 'Remove the cached module registry';

    public function handle(
        ModuleRegistry $registry,
        Filesystem $filesystem,
    ): int {
        $this->components->info('Clearing cached module registry...');

        if ($filesystem->exists($registry->cachePath())) {
            $filesystem->delete($registry->cachePath());
            $this->components->info('Module registry cache cleared.');
        } else {
            $this->components->info('No cache to clear.');
        }

        return self::SUCCESS;
    }
}
