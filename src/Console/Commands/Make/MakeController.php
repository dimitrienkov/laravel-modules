<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Routing\Console\ControllerMakeCommand;

/**
 * Module-aware `make:controller`.
 *
 * The optional `--model`/`--parent` model and the `--requests` form requests are
 * generated inside the module by the trait's `call()` interception, and the
 * model reference is qualified into the module's `Domain\Models` by the trait's
 * `qualifyModel()`. The parent owns the full form-request token contract; in
 * module mode we only repoint the host `Http\Requests` namespace those tokens
 * carry at the module, so a token rename in a future Laravel release can never
 * silently strand a request import in the host.
 */
final class MakeController extends ControllerMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Http\\Controllers';
    }

    /**
     * @param array<array-key, mixed> $replace
     * @param string                  $modelClass
     *
     * @return array<array-key, mixed>
     */
    protected function buildFormRequestReplacements(array $replace, $modelClass)
    {
        $replace = parent::buildFormRequestReplacements($replace, $modelClass);

        $module = $this->module();

        if (! $module instanceof Module || ! $this->option('requests')) {
            return $replace;
        }

        // The parent stamps the host `App\Http\Requests` namespace into every
        // request token; the request files themselves already land in the module
        // via the trait's call() override, so the only fix is to repoint that one
        // namespace at the module's request namespace (single source of truth).
        $hostRequestsNamespace = $this->rootNamespace() . 'Http\\Requests';
        $moduleRequestsNamespace = $this->moduleLayout()->requestsNamespace($module);

        return array_map(
            static fn (mixed $value): mixed => \is_string($value)
                ? str_replace($hostRequestsNamespace, $moduleRequestsNamespace, $value)
                : $value,
            $replace,
        );
    }
}
