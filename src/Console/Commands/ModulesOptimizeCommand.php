<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands;

use DimitrienkoV\LaravelModules\Services\ServiceProviderLoaderService;
use Illuminate\Console\Command;

class ModulesOptimizeCommand extends Command
{
    protected $signature = 'modules:optimize';
    protected $description = 'Cache module service providers for production';

    public function handle(
        ServiceProviderLoaderService $providerLoader,
    ): int {
        $this->components->info('Caching module service providers...');

        $providers = $providerLoader->discover();
        $providerLoader->cache($providers);

        $this->components->twoColumnDetail('Providers cached', \count($providers));

        foreach ($providers as $provider) {
            $this->line("  ✓ {$provider}");
        }

        $this->newLine();
        $this->components->info('Module providers cached successfully.');

        return self::SUCCESS;
    }
}
