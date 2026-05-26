<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Console\Command;

final class ModulesListCommand extends Command
{
    protected $signature = 'modules:list
        {--enabled : Show only enabled modules}
        {--disabled : Show only disabled modules}';

    protected $description = 'List all registered modules';

    public function handle(ModuleRegistryInterface $registry): int
    {
        try {
            $modules = $registry->all();
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('enabled')) {
            $modules = array_filter($modules, static fn (Module $m): bool => $m->isEnabled());
        } elseif ($this->option('disabled')) {
            $modules = array_filter($modules, static fn (Module $m): bool => ! $m->isEnabled());
        }

        if ($modules === []) {
            $this->components->info('No modules found.');

            return self::SUCCESS;
        }

        $rows = array_map(static fn (Module $m): array => [
            $m->name,
            $m->displayName,
            $m->meta->version,
            $m->isEnabled() ? '<info>Yes</info>' : '<comment>No</comment>',
            $m->path,
        ], array_values($modules));

        $this->table(
            ['Name', 'Display Name', 'Version', 'Enabled', 'Path'],
            $rows,
        );

        return self::SUCCESS;
    }
}
