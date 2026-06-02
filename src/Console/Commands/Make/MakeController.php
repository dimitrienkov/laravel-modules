<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ClassName;
use Illuminate\Routing\Console\ControllerMakeCommand;

/**
 * Module-aware `make:controller`.
 *
 * Form requests and the optional `--model`/`--parent` model are generated inside
 * the module by the trait's `call()` interception, and the model reference is
 * qualified into the module's `Domain\Models` by the trait's `qualifyModel()`.
 * The one host-bound literal is the `App\Http\Requests` form-request namespace the
 * parent stitches into the controller's imports, repointed at the module below.
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
        $module = $this->module();

        if (! $module instanceof Module || ! $this->option('requests')) {
            return parent::buildFormRequestReplacements($replace, $modelClass);
        }

        // Generate the store/update requests (the trait's call() override routes
        // them into the module), then name them exactly as the parent would.
        $this->generateFormRequests($modelClass, 'Request', 'Request');

        $model = ClassName::short($modelClass);
        $storeRequestClass = 'Store' . $model . 'Request';
        $updateRequestClass = 'Update' . $model . 'Request';

        $namespace = $module->namespace . '\\Http\\Requests';
        $namespacedRequests = $namespace . '\\' . $storeRequestClass . ';';

        if ($storeRequestClass !== $updateRequestClass) {
            $namespacedRequests .= PHP_EOL . 'use ' . $namespace . '\\' . $updateRequestClass . ';';
        }

        return array_merge($replace, [
            '{{ storeRequest }}' => $storeRequestClass,
            '{{storeRequest}}' => $storeRequestClass,
            '{{ updateRequest }}' => $updateRequestClass,
            '{{updateRequest}}' => $updateRequestClass,
            '{{ namespacedStoreRequest }}' => $namespace . '\\' . $storeRequestClass,
            '{{namespacedStoreRequest}}' => $namespace . '\\' . $storeRequestClass,
            '{{ namespacedUpdateRequest }}' => $namespace . '\\' . $updateRequestClass,
            '{{namespacedUpdateRequest}}' => $namespace . '\\' . $updateRequestClass,
            '{{ namespacedRequests }}' => $namespacedRequests,
            '{{namespacedRequests}}' => $namespacedRequests,
        ]);
    }
}
