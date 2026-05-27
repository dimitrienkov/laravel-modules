<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\UseCases\ScaffoldModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use Illuminate\Console\Command;

final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : The module name (lowercase snake_case)}
        {--directory= : Target module root directory}
        {--kind= : Module kind (module, subsystem, integration)}
        {--group= : Module group for UI/CLI grouping (kebab-case)}
        {--disabled : Create the module in disabled state}
        {--overwrite : Overwrite if module already exists}';

    protected $description = 'Scaffold a new module';

    public function handle(ScaffoldModuleUseCase $useCase): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $directory */
        $directory = $this->option('directory');

        /** @var string|null $kindRaw */
        $kindRaw = $this->option('kind');
        $kind = null;

        if ($kindRaw !== null) {
            $kind = ModuleKind::tryFrom($kindRaw);

            if ($kind === null) {
                $allowed = implode(', ', array_column(ModuleKind::cases(), 'value'));
                $this->components->error("Invalid kind [{$kindRaw}]; allowed values: {$allowed}.");

                return self::FAILURE;
            }
        }

        /** @var string|null $group */
        $group = $this->option('group');

        $config = new ScaffoldModuleConfig(
            name: $name,
            directory: $directory,
            enabled: ! $this->option('disabled'),
            force: (bool) $this->option('overwrite'),
            kind: $kind,
            group: $group,
        );

        try {
            $result = $useCase->execute($config);

            $this->components->info("Module [{$result->name}] scaffolded.");
            $this->components->twoColumnDetail('Path', $result->path);
            $this->components->twoColumnDetail('Provider', $result->providerClass);
            $this->components->twoColumnDetail('Enabled', $result->enabled ? 'Yes' : 'No');

            return self::SUCCESS;
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
