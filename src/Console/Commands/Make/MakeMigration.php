<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Support\ModuleOption;
use DimitrienkoV\LaravelModules\Console\Support\ModuleResolver;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;

/**
 * Module-aware `make:migration`.
 *
 * Migrations are anonymous classes with no namespace and a special constructor
 * (MigrationCreator + Composer), so instead of subclassing the GeneratorCommand
 * pipeline we translate `--module` into the native `--path`/`--realpath` options
 * (Variant B), pointing the creator at the module's `Database/Migrations`. This
 * is resilient to signature differences between Laravel 12 and 13. Module
 * normalisation/resolution is shared with the trait via {@see ModuleResolver}.
 */
final class MakeMigration extends MigrateMakeCommand
{
    public function __construct(MigrationCreator $creator, Composer $composer)
    {
        parent::__construct($creator, $composer);

        $this->getDefinition()->addOption(
            ModuleOption::make('Generate the migration inside the given module'),
        );
    }

    /**
     * @return int
     */
    public function handle()
    {
        try {
            $module = $this->laravel->make(ModuleResolver::class)->resolve($this->option('module'));
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($module instanceof Module) {
            if ($this->userProvidedPath()) {
                $this->components->error(
                    'The --path option cannot be combined with --module; a module migration is always '
                    . "written to the module's Database/Migrations directory.",
                );

                return self::FAILURE;
            }

            $this->input->setOption('path', $this->laravel->make(ModuleLayout::class)->migrationsDir($module));
            $this->input->setOption('realpath', true);
        }

        // MigrateMakeCommand::handle() returns void (the kernel casts it to exit
        // 0), so a fixed SUCCESS preserves host parity. A duplicate-name clash
        // surfaces as MigrationCreator's uncaught InvalidArgumentException —
        // intentionally left unhandled, exactly as the native command leaves it.
        parent::handle();

        return self::SUCCESS;
    }

    /**
     * Whether the developer passed an explicit `--path`. In module mode that
     * collides with the module's own migration directory, so we refuse rather
     * than silently overwrite the option.
     */
    private function userProvidedPath(): bool
    {
        $path = $this->option('path');

        return \is_string($path) && trim($path) !== '';
    }
}
