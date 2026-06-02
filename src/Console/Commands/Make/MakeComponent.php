<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
use Illuminate\Foundation\Console\ComponentMakeCommand;

/**
 * Module-aware `make:component`.
 *
 * The component class lands in the module's `View\Components` (trait), and its
 * Blade view (or anonymous `--view`) is redirected into the module's
 * `Resources/views/components` by the trait's `viewPath()` override.
 */
final class MakeComponent extends ComponentMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return ModuleSegment::Components->namespaceSegment();
    }

    /**
     * In module mode the component's `render()` must reference the module's view
     * namespace (`view('blog::components.alert')`), or it would resolve against
     * the host views the loader never registered. The parent already stamped the
     * bare `view('components.alert')` call, so we only repoint that reference;
     * the Blade path stays on the clean relative view. Inline components carry an
     * embedded template instead of a view reference, so they are left untouched.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $class = parent::buildClass($name);
        $module = $this->module();

        if (! $module instanceof Module || $this->option('inline')) {
            return $class;
        }

        $view = $this->getView();

        return str_replace("view('{$view}')", "view('{$module->name}::{$view}')", $class);
    }
}
