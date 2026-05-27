<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Console\Command;

final class ModulesListCommand extends Command
{
    protected $signature = 'modules:list
        {--enabled : Show only enabled modules}
        {--disabled : Show only disabled modules}
        {--kind= : Filter by module kind (module, subsystem, integration)}';

    protected $description = 'List all registered modules';

    public function handle(ListModulesUseCase $useCase): int
    {
        if ($this->option('enabled') && $this->option('disabled')) {
            $this->components->error('Options --enabled and --disabled cannot be used together.');

            return self::FAILURE;
        }

        /** @var string|null $kindRaw */
        $kindRaw = $this->option('kind');
        $kindFilter = null;

        if ($kindRaw !== null) {
            $kindFilter = ModuleKind::tryFrom($kindRaw);

            if ($kindFilter === null) {
                $allowed = implode(', ', array_column(ModuleKind::cases(), 'value'));
                $this->components->error("Invalid kind [{$kindRaw}]; allowed values: {$allowed}.");

                return self::FAILURE;
            }
        }

        try {
            $enabledFilter = match (true) {
                (bool) $this->option('enabled') => true,
                (bool) $this->option('disabled') => false,
                default => null,
            };

            $result = $useCase->execute($enabledFilter, $kindFilter);
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->modules === []) {
            $this->components->info('No modules found.');

            return self::SUCCESS;
        }

        $rows = array_map(static fn (Module $m): array => [
            $m->name,
            $m->meta->kind->value,
            $m->displayName,
            $m->meta->version,
            $m->isEnabled() ? '<info>Yes</info>' : '<comment>No</comment>',
            $m->path,
        ], $result->modules);

        $this->table(
            ['Name', 'Kind', 'Display Name', 'Version', 'Enabled', 'Path'],
            $rows,
        );

        return self::SUCCESS;
    }
}
