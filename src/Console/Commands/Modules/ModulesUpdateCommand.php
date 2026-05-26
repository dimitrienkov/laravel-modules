<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\UpdateModuleUseCase;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class ModulesUpdateCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'modules:update
        {name : The module name to update}
        {source : Path to updated module directory or .zip archive}';

    protected $description = 'Update a module from a directory or archive';

    public function handle(UpdateModuleUseCase $useCase): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        /** @var string $name */
        $name = $this->argument('name');
        /** @var string $source */
        $source = $this->argument('source');

        try {
            $result = $useCase->execute($name, $source);

            $this->components->info("Module [{$result->name}] updated.");
            $this->components->twoColumnDetail('Version', "{$result->oldVersion} → {$result->newVersion}");
            $this->components->twoColumnDetail('Path', $result->path);

            if ($result->skippedValues !== []) {
                $this->newLine();
                $this->components->warn('Skipped settings values (removed or invalid in new schema):');
                foreach ($result->skippedValues as $skipped) {
                    $this->components->twoColumnDetail($skipped->key, "<comment>{$skipped->reason}</comment>");
                }
            }

            $this->newLine();
            $this->components->warn('Run `php artisan migrate` to apply module migrations.');

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
