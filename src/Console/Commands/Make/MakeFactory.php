<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ClassName;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Support\Str;

/**
 * Module-aware `make:factory`.
 *
 * The parent always writes factories to `database/factories` and stamps a
 * `Database\Factories\…` namespace into the stub. Both are repointed at the
 * module (`Database/Factories` dir, module factory namespace) while the related
 * model is qualified into the module's `Domain\Models` by the trait.
 */
final class MakeFactory extends FactoryMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Database\\Factories';
    }

    protected function getPath($name)
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::getPath($name);
        }

        $relative = Str::finish(
            Str::replaceFirst($this->moduleLayout()->factoriesNamespace($module) . '\\', '', $name),
            'Factory',
        );

        return $this->moduleLayout()->factoriesDir($module) . '/' . str_replace('\\', '/', $relative) . '.php';
    }

    protected function buildClass($name)
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::buildClass($name);
        }

        $modelOption = $this->option('model');
        $rawModel = \is_string($modelOption) && $modelOption !== ''
            ? $modelOption
            : $this->guessModelName($name);

        $namespaceModel = $this->qualifyModel($rawModel);
        $model = ClassName::short($namespaceModel);
        $factory = ClassName::short(Str::ucfirst(str_replace('Factory', '', $name)));

        // Each model/factory value is emitted in both the spaced (`{{ x }}`) and
        // unspaced (`{{x}}`) Blade-token notations, plus the legacy `Dummy*`
        // placeholders, so the substitution covers every published stub variant.
        $replace = [
            '{{ factoryNamespace }}' => $this->moduleLayout()->factoriesNamespace($module),

            'NamespacedDummyModel' => $namespaceModel,
            '{{ namespacedModel }}' => $namespaceModel,
            '{{namespacedModel}}' => $namespaceModel,

            'DummyModel' => $model,
            '{{ model }}' => $model,
            '{{model}}' => $model,

            '{{ factory }}' => $factory,
            '{{factory}}' => $factory,
        ];

        $stub = $this->files->get($this->getStub());
        $this->replaceNamespace($stub, $name);
        $stub = $this->replaceClass($stub, $name);

        return str_replace(array_keys($replace), array_values($replace), $stub);
    }
}
