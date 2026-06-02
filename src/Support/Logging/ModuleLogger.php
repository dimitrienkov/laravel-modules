<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Writes module diagnostics to an injected PSR-3 logger (a host log channel).
 *
 * Two gates run BEFORE any context array is allocated:
 *   1. the event's category toggle (`modules.logging.events.*`), and
 *   2. the global severity threshold (`modules.logging.level`).
 * Only if both pass is the whitelisted context built and the record written, so
 * a disabled or below-threshold event costs a method call plus two comparisons.
 * ERROR-level events bypass the category toggle (never the threshold) so a
 * silenced category cannot hide a loader or lifecycle failure.
 *
 * The recorded context is the whitelisted scalar projection guaranteed by
 * {@see ModuleDiagnosticsInterface} — never a raw {@see Module}, feature values,
 * or secrets.
 *
 * Stateless (config + logger are immutable), hence safe as an Octane singleton.
 */
final readonly class ModuleLogger implements ModuleDiagnosticsInterface
{
    /**
     * @var array<string, int>
     */
    private const array LEVEL_PRIORITY = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    private const string CATEGORY_DISCOVERY = 'discovery';

    private const string CATEGORY_CACHE = 'cache';

    private const string CATEGORY_PIPELINE = 'pipeline';

    private const string CATEGORY_LIFECYCLE = 'lifecycle';

    /**
     * @param array<string, bool> $events per-category toggles
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $level,
        private array $events,
    ) {
    }

    public function discoveryRootMissing(string $directory): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::WARNING)) {
            return;
        }

        $this->logger->log(LogLevel::WARNING, 'discovery.root.missing', ['directory' => $directory]);
    }

    public function discoveryRootRejected(string $directory, string $reason): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::WARNING)) {
            return;
        }

        $this->logger->log(LogLevel::WARNING, 'discovery.root.rejected', [
            'directory' => $directory,
            'reason' => $reason,
        ]);
    }

    public function discoveryModuleFound(string $module, string $path): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::DEBUG)) {
            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'discovery.module.found', [
            'module' => $module,
            'path' => $path,
        ]);
    }

    public function discoveryCompleted(int $total, int $enabled, int $disabled): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::DEBUG)) {
            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'discovery.completed', [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $disabled,
        ]);
    }

    public function cacheHit(int $count): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::DEBUG)) {
            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'cache.hit', ['count' => $count]);
    }

    public function cacheMiss(): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::DEBUG)) {
            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'cache.miss', []);
    }

    public function cacheWritten(int $count, string $path): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::INFO)) {
            return;
        }

        $this->logger->log(LogLevel::INFO, 'cache.written', [
            'count' => $count,
            'path' => $path,
        ]);
    }

    public function cacheCleared(): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::INFO)) {
            return;
        }

        $this->logger->log(LogLevel::INFO, 'cache.cleared', []);
    }

    public function cacheInvalid(string $reason, ?Throwable $exception = null): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::WARNING)) {
            return;
        }

        $context = ['reason' => $reason];

        if ($exception instanceof Throwable) {
            $context['exception'] = $exception;
        }

        $this->logger->log(LogLevel::WARNING, 'cache.invalid', $context);
    }

    public function pipelineStarted(int $modulesEnabled, int $loaders): void
    {
        if (! $this->shouldLog(self::CATEGORY_PIPELINE, LogLevel::DEBUG)) {
            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'pipeline.started', [
            'modules_enabled' => $modulesEnabled,
            'loaders' => $loaders,
        ]);
    }

    public function loaderOutcome(Module $module, LoaderInterface $loader, LoadReport $report): void
    {
        if (! $this->shouldLog(self::CATEGORY_PIPELINE, LogLevel::DEBUG)) {
            return;
        }

        if ($report->wasApplied()) {
            $this->logger->log(LogLevel::DEBUG, 'pipeline.loader.applied', [
                'module' => $module->name,
                'loader' => $this->shortName($loader::class),
                'artifacts' => $report->artifacts,
            ]);

            return;
        }

        $this->logger->log(LogLevel::DEBUG, 'pipeline.loader.skipped', [
            'module' => $module->name,
            'loader' => $this->shortName($loader::class),
            'reason' => $report->reason?->value,
        ]);
    }

    public function loaderFailed(Module $module, LoaderInterface $loader, Throwable $exception): void
    {
        if (! $this->shouldLog(self::CATEGORY_PIPELINE, LogLevel::ERROR)) {
            return;
        }

        // The Throwable object (not just its class) is passed so the PSR-3 channel
        // can render the stack trace and the wrapped `$previous` chain; `message`
        // stays as a readable extra for plain-text formatters.
        $this->logger->log(LogLevel::ERROR, 'pipeline.loader.failed', [
            'module' => $module->name,
            'loader' => $this->shortName($loader::class),
            'exception' => $exception,
            'message' => $exception->getMessage(),
        ]);
    }

    public function pipelineFinished(PipelineRunSummary $summary): void
    {
        // A run with failures is worth a WARNING; a clean run stays at DEBUG so it
        // does not surface on a warning-or-above channel.
        $level = $summary->failed > 0 ? LogLevel::WARNING : LogLevel::DEBUG;

        if (! $this->shouldLog(self::CATEGORY_PIPELINE, $level)) {
            return;
        }

        $this->logger->log($level, 'pipeline.finished', $summary->toArray());
    }

    public function lifecycleStarted(LifecycleOperation $operation, ?string $module = null, ?string $sourceKind = null): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::INFO)) {
            return;
        }

        $context = [];

        if ($module !== null) {
            $context['module'] = $module;
        }

        if ($sourceKind !== null) {
            $context['source'] = $sourceKind;
        }

        $this->logger->log(LogLevel::INFO, "lifecycle.{$operation->value}.started", $context);
    }

    public function lifecycleSucceeded(LifecycleOperation $operation, ?string $module = null): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::INFO)) {
            return;
        }

        $context = $module !== null ? ['module' => $module] : [];

        $this->logger->log(LogLevel::INFO, "lifecycle.{$operation->value}.succeeded", $context);
    }

    public function lifecycleFailed(LifecycleOperation $operation, ?string $module = null, ?Throwable $exception = null): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::ERROR)) {
            return;
        }

        $context = [];

        if ($module !== null) {
            $context['module'] = $module;
        }

        if ($exception instanceof Throwable) {
            // The object (not just its message) is logged so the channel can render
            // the stack trace and the wrapped `$previous` root cause.
            $context['exception'] = $exception;
            $context['message'] = $exception->getMessage();
        }

        $this->logger->log(LogLevel::ERROR, "lifecycle.{$operation->value}.failed", $context);
    }

    public function lifecycleRolledBack(LifecycleOperation $operation, string $module, string $stage): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::WARNING)) {
            return;
        }

        $this->logger->log(LogLevel::WARNING, "lifecycle.{$operation->value}.rolled_back", [
            'module' => $module,
            'stage' => $stage,
        ]);
    }

    public function lifecycleBackupCreated(LifecycleOperation $operation, string $module, string $backupPath): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::INFO)) {
            return;
        }

        $this->logger->log(LogLevel::INFO, "lifecycle.{$operation->value}.backup_created", [
            'module' => $module,
            'backup' => $backupPath,
        ]);
    }

    /**
     * Both gates, evaluated before any context is built: the category must be
     * toggled on and the event level must meet or exceed the configured
     * threshold. An unrecognised threshold falls back to "debug" so a typo
     * never silently hides diagnostics.
     *
     * Exception: events of ERROR or higher bypass the per-category toggle so that
     * silencing a noisy category (e.g. `events.pipeline=false`) cannot also
     * suppress a loader or lifecycle failure. The global threshold still applies —
     * an operator can mute everything below it by raising `level`.
     */
    private function shouldLog(string $category, string $level): bool
    {
        if ($this->priority($level) >= $this->priority(LogLevel::ERROR)) {
            return $this->priority($level) >= $this->priority($this->level);
        }

        if (($this->events[$category] ?? false) !== true) {
            return false;
        }

        return $this->priority($level) >= $this->priority($this->level);
    }

    /**
     * The configured threshold (`modules.logging.level`) may arrive in any case
     * from env; event levels are the lowercase PSR-3 constants. Normalising here
     * keeps a `MODULES_LOG_LEVEL=INFO` from falling through to the debug fallback.
     */
    private function priority(string $level): int
    {
        return self::LEVEL_PRIORITY[strtolower($level)] ?? 0;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
