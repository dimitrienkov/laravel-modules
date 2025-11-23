<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands;

use DimitrienkoV\LaravelModules\Services\ServiceProviderLoaderService;
use Illuminate\Console\Command;

class ModulesOptimizeClearCommand extends Command
{
    protected $signature = 'modules:optimize-clear';
    protected $description = 'Remove the cached module service providers';

    public function handle(
        ServiceProviderLoaderService $providerLoader,
    ): int {
        $this->components->info('Clearing cached module providers...');

        if ($providerLoader->clearCache()) {
            $this->components->info('Module providers cache cleared.');
        } else {
            $this->components->info('No cache to clear.');
        }

        return self::SUCCESS;
    }
}
