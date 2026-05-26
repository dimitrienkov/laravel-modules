<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\DisableModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use Illuminate\Console\Command;

final class ModulesDisableCommand extends Command
{
    protected $signature = 'modules:disable {name : The module name to disable}';

    protected $description = 'Disable a module';

    public function handle(DisableModuleUseCase $useCase): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $module = $useCase->execute($name);
            $this->components->info("Module [{$module->name}] disabled.");

            return self::SUCCESS;
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
