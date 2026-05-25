<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\EnableModuleUseCase;
use Illuminate\Console\Command;

final class ModulesEnableCommand extends Command
{
    protected $signature = 'modules:enable {name : The module name to enable}';

    protected $description = 'Enable a module';

    public function handle(EnableModuleUseCase $useCase): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $module = $useCase->execute($name);
            $this->components->info("Module [{$module->name}] enabled.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
