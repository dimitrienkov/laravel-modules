<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Throwable;

/**
 * No-op diagnostics sink bound when `modules.logging.enabled` is false (the
 * default). Lets every call site depend on {@see ModuleDiagnosticsInterface}
 * unconditionally — no `if ($logging)` branches in discovery, cache, pipeline or
 * lifecycle code. Stateless, so it is Octane-safe as a singleton.
 */
final class NullModuleDiagnostics implements ModuleDiagnosticsInterface
{
    public function discoveryRootMissing(string $root): void
    {
    }

    public function discoveryRootRejected(string $root, string $reason): void
    {
    }

    public function discoveryModuleFound(string $module, string $path): void
    {
    }

    public function discoveryCompleted(int $total, int $enabled, int $disabled): void
    {
    }

    public function cacheHit(int $count): void
    {
    }

    public function cacheMiss(): void
    {
    }

    public function cacheWritten(int $count, string $path): void
    {
    }

    public function cacheCleared(): void
    {
    }

    public function cacheInvalid(string $reason): void
    {
    }

    public function pipelineStarted(int $modulesEnabled, int $loaders): void
    {
    }

    public function loaderOutcome(Module $module, LoaderInterface $loader, LoadReport $report): void
    {
    }

    public function loaderFailed(Module $module, LoaderInterface $loader, Throwable $exception): void
    {
    }

    public function pipelineFinished(PipelineRunSummary $summary): void
    {
    }

    public function lifecycleStarted(LifecycleOperation $operation, string $module, ?string $sourceKind = null): void
    {
    }

    public function lifecycleSucceeded(LifecycleOperation $operation, string $module): void
    {
    }

    public function lifecycleRolledBack(LifecycleOperation $operation, string $module, string $stage): void
    {
    }

    public function lifecycleBackupCreated(LifecycleOperation $operation, string $module, string $backupPath): void
    {
    }
}
