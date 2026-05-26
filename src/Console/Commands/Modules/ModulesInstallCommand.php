<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\InstallModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use Illuminate\Console\Command;

final class ModulesInstallCommand extends Command
{
    protected $signature = 'modules:install
        {source : Path to module directory or .zip archive}
        {--directory= : Target module root directory}
        {--disabled : Install in disabled state}';

    protected $description = 'Install a module from a directory or archive';

    public function handle(InstallModuleUseCase $useCase): int
    {
        /** @var string $source */
        $source = $this->argument('source');

        try {
            /** @var string|null $directory */
            $directory = $this->option('directory');

            $result = $useCase->execute(
                sourcePath: $source,
                directory: $directory,
                enabled: ! $this->option('disabled'),
            );

            $this->components->info("Module [{$result->name}] installed.");
            $this->components->twoColumnDetail('Path', $result->path);
            $this->components->twoColumnDetail('Source', $result->sourceKind->value);
            $this->components->twoColumnDetail('Enabled', $result->enabled ? 'Yes' : 'No');
            $this->newLine();
            $this->components->warn('Run `php artisan migrate` to apply module migrations.');

            return self::SUCCESS;
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
