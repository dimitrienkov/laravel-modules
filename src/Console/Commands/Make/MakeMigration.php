<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Support\ModuleResolver;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Symfony\Component\Console\Input\InputOption;

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
            new InputOption('module', null, InputOption::VALUE_REQUIRED, 'Generate the migration inside the given module'),
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
            $this->input->setOption('path', $this->laravel->make(ModuleLayout::class)->migrationsDir($module));
            $this->input->setOption('realpath', true);
        }

        parent::handle();

        return self::SUCCESS;
    }
}
