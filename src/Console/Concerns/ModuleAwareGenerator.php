<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Concerns;

use DimitrienkoV\LaravelModules\Console\Support\ModuleOption;
use DimitrienkoV\LaravelModules\Console\Support\ModuleResolver;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

/**
 * Turns a native Laravel `GeneratorCommand` subclass into a module-aware one.
 *
 * Without `--module` every hook delegates to the parent command, so the host
 * behaviour stays byte-for-byte identical. With `--module=<name>` the generated
 * class is redirected into the module's sub-namespace (and, because modules live
 * under `app/`, the inherited `getPath()` already writes the file inside the
 * module without any path remap for namespace-only generators).
 *
 * The module is resolved exclusively through the
 * {@see \DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface} contract
 * and cached per invocation. Matching-test options are rejected up front in
 * module mode so module artifacts never spill host test files.
 *
 * Each consuming command declares its home through {@see moduleSubNamespace()}.
 */
trait ModuleAwareGenerator
{
    /**
     * The matching-test options a module artifact must never spill: rejected up
     * front in module mode (see handle()) and stripped from every nested
     * sub-generator (see call()).
     *
     * @var array<int, string>
     */
    private const array MATCHING_TEST_OPTIONS = ['test', 'pest', 'phpunit'];

    private bool $isModuleResolved = false;

    private ?Module $resolvedModule = null;

    /**
     * @return int|bool|null
     */
    public function handle()
    {
        $this->isModuleResolved = false;
        $this->resolvedModule = null;

        try {
            $module = $this->module();
        } catch (ModuleExceptionInterface $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($module instanceof Module && $this->shouldGenerateMatchingTest()) {
            $this->components->error(
                'Module-aware generators do not create matching tests; remove the --test, --pest, or --phpunit option.',
            );

            return self::FAILURE;
        }

        // GeneratorCommand::handle() is `@return bool|null`: it prints and
        // returns `false` for a soft failure (reserved name, class already
        // exists) that the kernel casts to exit 0. Mapping that falsy parent
        // result to a fixed SUCCESS is the deliberate host-parity contract —
        // native generators exit 0 in those cases too — and keeps the
        // unknown-module path the only non-zero outcome. The parent contract
        // never yields an int, so there is nothing else to forward here.
        parent::handle();

        return self::SUCCESS;
    }

    /**
     * Route every internally dispatched `make:*` generator into the same module
     * and strip matching-test options, so a module artifact (e.g. `make:model`
     * with `-mfs`, or `make:controller --requests`) never spawns host factories,
     * migrations, seeders, form requests, or test files. Host mode is untouched.
     *
     * @param Command|string       $command
     * @param array<string, mixed> $arguments
     */
    public function call($command, array $arguments = [])
    {
        $isNestedModuleMake = \is_string($command)
            && str_starts_with($command, 'make:')
            && $this->module() instanceof Module;

        if (! $isNestedModuleMake) {
            return parent::call($command, $arguments);
        }

        $arguments = $this->withModuleOption($arguments);

        foreach (self::MATCHING_TEST_OPTIONS as $option) {
            unset($arguments['--' . $option]);
        }

        return parent::call($command, $arguments);
    }

    /**
     * The module-relative sub-namespace the generated class belongs to,
     * e.g. `Http\Controllers` or `Domain\Models`.
     */
    abstract protected function moduleSubNamespace(): string;

    /**
     * Resolve the target module from `--module`, or `null` for host mode.
     *
     * Normalisation and the registry lookup live in the shared
     * {@see ModuleResolver}, so `--module=blog` and `--module=Blog` resolve to
     * the same module and an unknown name surfaces as a {@see ModuleExceptionInterface}.
     */
    protected function module(): ?Module
    {
        if ($this->isModuleResolved) {
            return $this->resolvedModule;
        }

        $this->isModuleResolved = true;

        $option = $this->hasOption('module') ? $this->option('module') : null;

        return $this->resolvedModule = $this->moduleResolver()->resolve($option);
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::getDefaultNamespace($rootNamespace);
        }

        return $this->moduleNamespace($module);
    }

    /**
     * The fully-qualified namespace the generated class lands in for this module.
     */
    protected function moduleNamespace(Module $module): string
    {
        return $module->namespace . '\\' . $this->moduleSubNamespace();
    }

    /**
     * Redirect generated views (make:component, make:mail markdown/view) into the
     * module's `Resources/views` instead of the host `resources/views`. The native
     * relative path (`components/alert.blade.php`, `mail/digest.blade.php`) is kept
     * intact, so only the base directory changes.
     *
     * @param string $path
     *
     * @return string
     */
    protected function viewPath($path = '')
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::viewPath($path);
        }

        $base = $this->moduleLayout()->viewsDir($module);

        return $base . ($path !== '' ? \DIRECTORY_SEPARATOR . $path : '');
    }

    protected function qualifyModel(string $model)
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::qualifyModel($model);
        }

        $model = str_replace('/', '\\', ltrim($model, '\\/'));

        $qualified = Str::startsWith($model, $this->rootNamespace())
            ? $model
            : $this->moduleLayout()->modelNamespace($module) . '\\' . $model;

        /** @var class-string $qualified */
        return $qualified;
    }

    /**
     * Add `--module` to the parent command's option set. We only ever append, so
     * the native signature stays intact.
     *
     * @return array<int|string, mixed>
     */
    protected function getOptions()
    {
        $options = parent::getOptions();
        $options[] = ModuleOption::make('Generate the class inside the given module');

        return $options;
    }

    /**
     * Merge `--module` into the arguments of an internally dispatched generator,
     * normalised through the same resolver as {@see module()} so the sub-generator
     * receives the canonical module name.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    protected function withModuleOption(array $arguments): array
    {
        $option = $this->hasOption('module') ? $this->option('module') : null;
        $name = $this->moduleResolver()->normalize($option);

        if ($name !== null) {
            $arguments['--module'] = $name;
        }

        return $arguments;
    }

    private function moduleResolver(): ModuleResolver
    {
        return $this->laravel->make(ModuleResolver::class);
    }

    private function moduleLayout(): ModuleLayout
    {
        return $this->laravel->make(ModuleLayout::class);
    }

    private function shouldGenerateMatchingTest(): bool
    {
        foreach (self::MATCHING_TEST_OPTIONS as $option) {
            if ($this->hasOption($option) && $this->option($option) === true) {
                return true;
            }
        }

        return false;
    }
}
