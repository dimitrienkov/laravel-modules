<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Module-aware `make:migration`.
 *
 * Migrations are anonymous classes with no namespace and a special constructor
 * (MigrationCreator + Composer), so instead of subclassing the GeneratorCommand
 * pipeline we translate `--module` into the native `--path`/`--realpath` options
 * (Variant B), pointing the creator at the module's `Database/Migrations`. This
 * is resilient to signature differences between Laravel 12 and 13.
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
        $name = $this->option('module');

        if (\is_string($name) && trim($name) !== '') {
            try {
                $module = $this->laravel->make(ModuleRegistryInterface::class)->find(Str::snake(trim($name)));
            } catch (ModuleExceptionInterface $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            $this->input->setOption('path', $this->laravel->make(ModuleLayout::class)->migrationsDir($module));
            $this->input->setOption('realpath', true);
        }

        parent::handle();

        return self::SUCCESS;
    }
}
