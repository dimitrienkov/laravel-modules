<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\UseCases\ScaffoldModuleUseCase;
use Illuminate\Console\Command;

final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : The module name (lowercase snake_case)}
        {--directory= : Target module root directory}
        {--disabled : Create the module in disabled state}
        {--force : Overwrite if module already exists}';

    protected $description = 'Scaffold a new module';

    public function handle(ScaffoldModuleUseCase $useCase): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $directory */
        $directory = $this->option('directory');

        $config = new ScaffoldModuleConfig(
            name: $name,
            directory: $directory,
            enabled: ! $this->option('disabled'),
            force: (bool) $this->option('force'),
        );

        try {
            $result = $useCase->execute($config);

            $this->components->info("Module [{$result->name}] scaffolded.");
            $this->components->twoColumnDetail('Path', $result->path);
            $this->components->twoColumnDetail('Provider', $result->providerClass);
            $this->components->twoColumnDetail('Enabled', $result->enabled ? 'Yes' : 'No');

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
