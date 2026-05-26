<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\Enums\RemoveStrategy;
use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class ModulesRemoveCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'modules:remove
        {name : The module name to remove}
        {--force : Force the operation to run in production}
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

        try {
            $strategy = (bool) $this->option('delete-permanently')
                ? RemoveStrategy::Permanent
                : RemoveStrategy::Backup;

            $result = $useCase->execute($name, strategy: $strategy);

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
