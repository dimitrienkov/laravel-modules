<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\text;

class MakeModule extends Command
{
    protected $signature = 'make:module {moduleName?} {--m|model=} {--mi|migration}';

    protected $description = 'Create a new module with optional components (Model, Migration)';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): void
    {
        $moduleName = $this->argument('moduleName') ?? $this->askModuleName();

        $moduleName = $this->formatModuleName($moduleName);

        $modulePath = app_path("Modules/{$moduleName}");

        $this->createModuleFolder($modulePath);

        if ($this->confirm('Do you want to create a model for this module?', true)) {
            $modelName = $this->option('model') ?: $this->askModelName();

            $this->createModelWithMigration($modulePath, $moduleName, $modelName);
        }
    }

    private function askModuleName(): string
    {
        return text('Module name', 'UserModule', required: true);
    }

    private function formatModuleName(string $name): string
    {
        return str($name)->ucfirst()->remove('module', false)->value() . 'Module';
    }

    private function createModuleFolder(string $path): void
    {
        $this->filesystem->makeDirectory($path, 0755, true);
        $this->info("Module folder created at {$path}");
    }

    private function askModelName(): string
    {
        return $this->ask('What is the name of the model?', str($this->argument('moduleName'))->remove('Module')->value());
    }

    private function createModelWithMigration(string $modulePath, string $moduleName, string $modelName): void
    {
        $modelPath = "{$modulePath}/Models/{$modelName}.php";

        $this->filesystem->makeDirectory(dirname($modelPath), 0755, true);

        $this->call('make:model', [
            'name' => "Modules/$moduleName/Models/$modelName",
        ]);

        $this->info("Model created for {$modelName} at {$modelPath}");

        if ($this->option('migration') || $this->confirm('Do you want to create a migration for this model?', true)) {
            $migrationPath = app_path("Modules/$moduleName/Database/Migrations");

            $this->filesystem->makeDirectory($migrationPath, 0755, true);

            $this->call('make:migration', [
                'name' => 'create_' . str($modelName)->snake() . '_table',
                '--path' => "app/Modules/{$moduleName}/Database/Migrations",
                '--create' => true,
            ]);

            $this->info("Migration created for {$modelName} at {$migrationPath}");
        }
    }
}
