<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

final readonly class PolicyLoader implements LoaderInterface
{
    public function __construct(
        private Gate $gate,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $policiesDir = $this->layout->policiesDir($module);

        if (! $this->filesystem->isDirectory($policiesDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $files = $this->filesystem->glob($policiesDir . '/*Policy.php') ?: [];
        sort($files);

        $policies = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $this->registerPolicy($module, $file);
            $policies[] = basename($file);
        }

        if ($policies === []) {
            return LoadReport::skipped(SkipReason::EmptyDirectory);
        }

        return LoadReport::applied(['policies' => $policies]);
    }

    public function priority(): int
    {
        return 37;
    }

    private function registerPolicy(Module $module, string $file): void
    {
        $basename = basename($file, '.php');
        $policyFqcn = $this->layout->policiesNamespace($module) . '\\' . $basename;
        $modelName = substr($basename, 0, -\strlen('Policy'));
        $modelFqcn = $this->layout->modelNamespace($module) . '\\' . $modelName;

        if (! class_exists($policyFqcn) || ! class_exists($modelFqcn)) {
            return;
        }

        if ((new ReflectionClass($policyFqcn))->isAbstract()) {
            return;
        }

        $this->gate->policy($modelFqcn, $policyFqcn);
    }
}
