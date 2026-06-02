<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use Override;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Support\Str;

/**
 * Module-aware `make:listener`.
 *
 * The listener class lands in the module's `Domain\Listeners` (trait). When an
 * `--event` is supplied the parent auto-qualifies a bare event name against the
 * host `App\Events\…` namespace; in module mode we repoint that single import at
 * the module's own `Domain\Events`, so it lines up with where `make:event` stores
 * a module event. A fully-qualified `--event` (already rooted in the app
 * namespace, `Illuminate`, or an absolute `\`) is left exactly as the parent
 * leaves it — mirroring the parent's own auto-qualification condition.
 */
final class MakeListener extends ListenerMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return ModuleSegment::Listeners->namespaceSegment();
    }

    /**
     * @param string $name
     */
    #[Override]
    protected function buildClass($name): string
    {
        $class = parent::buildClass($name);
        $module = $this->module();

        if (! $module instanceof Module) {
            return $class;
        }

        $event = $this->option('event');

        if (! \is_string($event) || $event === '' || ! $this->eventWasHostQualified($event)) {
            return $class;
        }

        $hostPrefix = $this->laravel->getNamespace() . 'Events\\';
        $modulePrefix = $this->moduleLayout()->eventsNamespace($module) . '\\';

        return str_replace($hostPrefix, $modulePrefix, $class);
    }

    /**
     * Mirror the parent's auto-qualification test: a bare event name — one not
     * already rooted in the app namespace, `Illuminate`, or an absolute `\` — is
     * the only case the parent rewrites to `App\Events\…`, and so the only case
     * we then repoint at the module's events namespace.
     */
    private function eventWasHostQualified(string $event): bool
    {
        return ! Str::startsWith($event, [
            $this->laravel->getNamespace(),
            'Illuminate',
            '\\',
        ]);
    }
}
