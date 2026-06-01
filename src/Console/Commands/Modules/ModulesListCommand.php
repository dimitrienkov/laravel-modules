<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Console\Command;

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

        /** @var string|null $groupFilter */
        $groupFilter = $this->option('group');

        if ($groupFilter !== null && ! $this->isValidGroup($groupFilter)) {
            $this->components->error(
                "Invalid group [{$groupFilter}]; expected kebab-case: lowercase letters and digits in hyphen-separated segments.",
            );

            return self::FAILURE;
        }

        try {
            $enabledFilter = match (true) {
                (bool) $this->option('enabled') => true,
                (bool) $this->option('disabled') => false,
                default => null,
            };

            $result = $useCase->execute($enabledFilter, $kindFilter, $groupFilter);

            if ($result->modules === []) {
                $this->components->info($this->emptyMessage($groupFilter));

                return self::SUCCESS;
            }

            $rows = array_map(static fn (Module $m): array => [
                $m->name,
                $m->meta->kind->value,
                $groupLabels->displayLabel($m->meta->group),
                $m->displayName,
                $m->meta->version,
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

    private function isValidGroup(string $group): bool
    {
        try {
            ManifestFieldReader::assertModuleGroup($group, '--group', 'modules:list');

            return true;
        } catch (ModuleExceptionInterface) {
            return false;
        }
    }

    private function emptyMessage(?string $groupFilter): string
    {
        return $groupFilter !== null
            ? "No modules found in group [{$groupFilter}]."
            : 'No modules found.';
    }
}
