<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class ModulesRemoveCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'modules:remove
        {name : The module name to remove}
        {--yes : Skip confirmation prompt}
        {--delete-permanently : Delete permanently without backup}';

    protected $description = 'Remove a module';

    public function handle(RemoveModuleUseCase $useCase): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        /** @var string $name */
        $name = $this->argument('name');

        $this->components->warn('If this module has migrations, run `php artisan migrate:rollback` before removing.');

        if (! $this->option('yes') && ! $this->components->confirm("Remove module [{$name}]?")) {
            $this->components->info('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $useCase->execute($name, deletePermanently: (bool) $this->option('delete-permanently'));

            $this->components->info("Module [{$result->name}] removed.");
            $this->components->twoColumnDetail('Removed from', $result->removedPath);

            if ($result->backupPath !== null) {
                $this->components->twoColumnDetail('Backup', $result->backupPath);
            } else {
                $this->components->twoColumnDetail('Backup', 'No backup (permanently deleted)');
            }

            return self::SUCCESS;
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
