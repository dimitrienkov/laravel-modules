<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
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
        return 'View\\Components';
    }
}
