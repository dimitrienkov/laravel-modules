<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ModulesListCommand extends Command
{
    protected $signature = 'modules:list
        {--enabled : Show only enabled modules}
        {--disabled : Show only disabled modules}
        {--kind= : Filter by module kind (module, subsystem, integration)}
        {--group= : Filter by module group}';

    protected $description = 'List all registered modules';

    public function handle(ListModulesUseCase $useCase, ModuleGroupLabelResolver $groupLabels): int
    {
        if ((bool) $this->option('enabled') && (bool) $this->option('disabled')) {
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

        /** @var string|null $groupRaw */
        $groupRaw = $this->option('group');
        $groupFilter = null;

        if ($groupRaw !== null) {
            try {
                $groupFilter = new ModuleGroup($groupRaw);
            } catch (InvalidArgumentException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $enabledFilter = match (true) {
            (bool) $this->option('enabled') => true,
            (bool) $this->option('disabled') => false,
            default => null,
        };

        try {
            $result = $useCase->execute($enabledFilter, $kindFilter, $groupFilter);

            if ($result->modules === []) {
                $this->components->info($this->emptyMessage($groupFilter));

                return self::SUCCESS;
            }

            $rows = array_map(static fn(Module $m): array => [
                $m->name,
                $m->meta->kind->value,
                $groupLabels->displayLabel($m->meta->group),
                $m->displayName,
                $m->meta->version->value,
                $m->isEnabled() ? '<info>Yes</info>' : '<comment>No</comment>',
                $m->path,
            ], $result->modules);
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Name', 'Kind', 'Group', 'Display Name', 'Version', 'Enabled', 'Path'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function emptyMessage(?ModuleGroup $groupFilter): string
    {
        return $groupFilter instanceof ModuleGroup
            ? "No modules found in group [{$groupFilter->value}]."
            : 'No modules found.';
    }
}
