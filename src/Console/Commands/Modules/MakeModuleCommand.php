<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Modules;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent;
use DimitrienkoV\LaravelModules\Application\UseCases\ScaffoldModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\multiselect;

final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : The module name (lowercase snake_case)}
        {--directory= : Target module root directory}
        {--kind= : Module kind (module, subsystem, integration)}
        {--group= : Module group for UI/CLI grouping (kebab-case)}
        {--with= : Comma-separated components to scaffold (application, config, console, database, domain, http, routes, views)}
        {--disabled : Create the module in disabled state}
        {--overwrite : Overwrite if module already exists}';

    protected $description = 'Scaffold a new module';

    public function handle(ScaffoldModuleUseCase $useCase): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $directory */
        $directory = $this->option('directory');

        /** @var string|null $kindRaw */
        $kindRaw = $this->option('kind');
        $kind = null;

        if ($kindRaw !== null) {
            $kind = ModuleKind::tryFrom($kindRaw);

            if ($kind === null) {
                $allowed = implode(', ', array_column(ModuleKind::cases(), 'value'));
                $this->components->error("Invalid kind [{$kindRaw}]; allowed values: {$allowed}.");

                return self::FAILURE;
            }
        }

        /** @var string|null $groupRaw */
        $groupRaw = $this->option('group');
        $group = null;

        if ($groupRaw !== null) {
            try {
                $group = new ModuleGroup($groupRaw);
            } catch (InvalidArgumentException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        try {
            $components = $this->resolveComponents();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $config = new ScaffoldModuleConfig(
            name: $name,
            directory: $directory,
            enabled: ! (bool) $this->option('disabled'),
            force: (bool) $this->option('overwrite'),
            kind: $kind,
            group: $group,
            components: $components,
        );

        try {
            $result = $useCase->execute($config);

            $this->components->info("Module [{$result->name}] scaffolded.");
            $this->components->twoColumnDetail('Path', $result->path);
            $this->components->twoColumnDetail('Provider', $result->providerClass);
            $this->components->twoColumnDetail('Enabled', $result->enabled ? 'Yes' : 'No');

            return self::SUCCESS;
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Resolve the component selection for the skeleton.
     *
     * `--with=` is parsed and validated fail-fast (an empty value is a valid,
     * mandatory-only selection). Without `--with`, an interactive run prompts via
     * a multiselect, while a non-interactive run returns `null` to keep the
     * default minimal skeleton. An invalid `--with` value raises an
     * {@see InvalidArgumentException}, which `handle()` turns into a clean failure.
     *
     * @return array<int, ScaffoldComponent>|null
     *
     * @throws InvalidArgumentException on an invalid `--with` value
     */
    private function resolveComponents(): ?array
    {
        $with = $this->option('with');

        if (\is_string($with)) {
            return ScaffoldComponent::fromOptionValue($with);
        }

        if ($this->input->isInteractive()) {
            return $this->promptForComponents();
        }

        return null;
    }

    /**
     * @return array<int, ScaffoldComponent>
     */
    private function promptForComponents(): array
    {
        $options = [];

        foreach (ScaffoldComponent::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        /** @var array<int, int|string> $selected */
        $selected = multiselect(
            label: 'Which components should the module include?',
            options: $options,
        );

        return ScaffoldComponent::fromValues(array_map(static fn(int|string $value): string => (string) $value, $selected));
    }
}
