<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Throwable;

/**
 * Opt-in diagnostic sink for the module runtime. A single segmented contract —
 * discovery, cache, pipeline, lifecycle — whose one responsibility is to turn a
 * domain event into a structured, channel-gated log record.
 *
 * Implementations either write (when enabled) via an injected
 * `Psr\Log\LoggerInterface` or do nothing (the null object). Category gating
 * (`modules.logging.events.*`) and the severity threshold are applied inside the
 * implementation, so call sites never branch on whether logging is on.
 *
 * Parameters are domain types ({@see Module}, {@see LoaderInterface},
 * {@see LoadReport}, {@see PipelineRunSummary}, {@see LifecycleOperation},
 * {@see Throwable}) and whitelisted scalars only. No method ever receives a raw
 * manifest, feature values, or secrets — the recorded context is built lazily
 * from these typed inputs and is guaranteed to stay free of customer data.
 */
interface ModuleDiagnosticsInterface
{
    public function discoveryRootMissing(string $root): void;

    public function discoveryRootRejected(string $root, string $reason): void;

    public function discoveryModuleFound(string $module, string $path): void;

    public function discoveryCompleted(int $total, int $enabled, int $disabled): void;

    public function cacheHit(int $count): void;

    public function cacheMiss(): void;

    public function cacheWritten(int $count, string $path): void;

    public function cacheCleared(): void;

    public function cacheInvalid(string $reason): void;

    public function pipelineStarted(int $modulesEnabled, int $loaders): void;

    public function loaderOutcome(Module $module, LoaderInterface $loader, LoadReport $report): void;

    public function loaderFailed(Module $module, LoaderInterface $loader, Throwable $exception): void;

    public function pipelineFinished(PipelineRunSummary $summary): void;

    public function lifecycleStarted(LifecycleOperation $operation, ?string $module = null, ?string $sourceKind = null): void;

    public function lifecycleSucceeded(LifecycleOperation $operation, ?string $module = null): void;

    public function lifecycleRolledBack(LifecycleOperation $operation, string $module, string $stage): void;

    public function lifecycleBackupCreated(LifecycleOperation $operation, string $module, string $backupPath): void;
}
