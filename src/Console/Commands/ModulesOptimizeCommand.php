<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands;

use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModulesOptimizeCommand extends Command
{
    protected $signature = 'modules:optimize';
    protected $description = 'Cache module registry for production';

    public function handle(
        ModuleRegistry $registry,
        Filesystem $filesystem,
    ): int {
        $this->components->info('Caching module registry...');

        $payload = $registry->buildCachePayload();
        $cachePath = $registry->cachePath();

        $filesystem->ensureDirectoryExists(\dirname($cachePath));
        $filesystem->put($cachePath, '<?php return ' . var_export($payload, true) . ';' . PHP_EOL);

        $this->components->twoColumnDetail('Cache path', $cachePath);
        $this->components->twoColumnDetail('Modules cached', (string) \count($payload['modules']));
        $this->components->info('Module registry cached successfully.');

        return self::SUCCESS;
    }
}
