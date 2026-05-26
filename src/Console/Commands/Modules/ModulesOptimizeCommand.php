<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\OptimizeModulesUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleCacheWriteException;
use Illuminate\Console\Command;

final class ModulesOptimizeCommand extends Command
{
    protected $signature = 'modules:optimize';
    protected $description = 'Cache module registry for production';

    public function handle(OptimizeModulesUseCase $useCase): int
    {
        $this->components->info('Caching module registry...');

        try {
            $result = $useCase->execute();

            $this->components->twoColumnDetail('Cache path', $result->path);
            $this->components->twoColumnDetail('Modules cached', (string) $result->count);
            $this->components->info('Module registry cached successfully.');

            return self::SUCCESS;
        } catch (ModuleCacheWriteException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
