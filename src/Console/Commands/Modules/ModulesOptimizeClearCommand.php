<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\ClearModulesOptimizeCacheUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleCacheWriteException;
use Illuminate\Console\Command;

final class ModulesOptimizeClearCommand extends Command
{
    protected $signature = 'modules:optimize-clear';
    protected $description = 'Remove the cached module registry';

    public function handle(ClearModulesOptimizeCacheUseCase $useCase): int
    {
        $this->components->info('Clearing cached module registry...');

        try {
            $result = $useCase->execute();

            if (! $result->cleared) {
                $this->components->info('No cache to clear.');

                return self::SUCCESS;
            }

            $this->components->info('Module registry cache cleared.');

            return self::SUCCESS;
        } catch (ModuleCacheWriteException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
