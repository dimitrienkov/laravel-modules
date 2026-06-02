<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ClassName;
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
 *
 * Events of ERROR or higher bypass BOTH gates: while logging is enabled, neither
 * a silenced category nor a raised threshold can hide a loader or lifecycle
 * failure — that is the "a failure cannot be hidden" contract.
 *
 * The recorded context is the whitelisted scalar projection guaranteed by
 * {@see ModuleDiagnosticsInterface} — never a raw {@see Module}, feature values,
 * or secrets. Event names come from {@see LogEvent} so the producer and its
 * tests share one taxonomy.
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
     * The configured threshold (`modules.logging.level`) may arrive in any case
     * from env; resolving it to a priority once in the constructor keeps the
     * lowercase normalisation out of the per-event hot path and lets an
     * unrecognised threshold fail open to "debug" (priority 0).
     */
    private int $thresholdPriority;

    /**
     * @param array<string, bool> $events per-category toggles
     */
    public function __construct(
        private LoggerInterface $logger,
        string $level,
        private array $events,
    ) {
        $this->thresholdPriority = self::LEVEL_PRIORITY[strtolower($level)] ?? 0;
    }

    public function discoveryRootMissing(string $directory): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::WARNING)) {
            return;
        }

        $this->write(LogLevel::WARNING, LogEvent::DiscoveryRootMissing->value, ['directory' => $directory]);
    }

    public function discoveryRootRejected(string $directory, string $reason): void
    {
        // A rejected root is the aborting condition behind a thrown
        // InvalidConfigurationException, so it is logged at ERROR — it must reach
        // the channel even when an operator has raised the threshold above warning.
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::ERROR)) {
            return;
        }

        $this->write(LogLevel::ERROR, LogEvent::DiscoveryRootRejected->value, [
            'directory' => $directory,
            'reason' => $reason,
        ]);
    }

    public function discoveryModuleFound(string $module, string $path): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::DEBUG)) {
            return;
        }

        $this->write(LogLevel::DEBUG, LogEvent::DiscoveryModuleFound->value, [
            'module' => $module,
            'path' => $path,
        ]);
    }

    public function discoveryCompleted(int $total, int $enabled, int $disabled): void
    {
        if (! $this->shouldLog(self::CATEGORY_DISCOVERY, LogLevel::DEBUG)) {
            return;
        }

        $this->write(LogLevel::DEBUG, LogEvent::DiscoveryCompleted->value, [
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

        $this->write(LogLevel::DEBUG, LogEvent::CacheHit->value, ['count' => $count]);
    }

    public function cacheMiss(): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::DEBUG)) {
            return;
        }

        $this->write(LogLevel::DEBUG, LogEvent::CacheMiss->value, []);
    }

    public function cacheWritten(int $count, string $path): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::INFO)) {
            return;
        }

        $this->write(LogLevel::INFO, LogEvent::CacheWritten->value, [
            'count' => $count,
            'path' => $path,
        ]);
    }

    public function cacheCleared(): void
    {
        if (! $this->shouldLog(self::CATEGORY_CACHE, LogLevel::INFO)) {
            return;
        }

        $this->write(LogLevel::INFO, LogEvent::CacheCleared->value, []);
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

        $this->write(LogLevel::WARNING, LogEvent::CacheInvalid->value, $context);
    }

    public function pipelineStarted(int $modulesEnabled, int $loaders): void
    {
        if (! $this->shouldLog(self::CATEGORY_PIPELINE, LogLevel::DEBUG)) {
            return;
        }

        $this->write(LogLevel::DEBUG, LogEvent::PipelineStarted->value, [
            'modules_enabled' => $modulesEnabled,
            'loaders' => $loaders,
        ]);
    }

    public function loaderCompleted(Module $module, LoaderInterface $loader, LoadReport $report): void
    {
        if (! $this->shouldLog(self::CATEGORY_PIPELINE, LogLevel::DEBUG)) {
            return;
        }

        if ($report->wasApplied()) {
            $this->write(LogLevel::DEBUG, LogEvent::PipelineLoaderApplied->value, [
                'module' => $module->name,
                'loader' => ClassName::short($loader::class),
                'artifacts' => $report->artifacts,
            ]);

            return;
        }

        $this->write(LogLevel::DEBUG, LogEvent::PipelineLoaderSkipped->value, [
            'module' => $module->name,
            'loader' => ClassName::short($loader::class),
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
        $this->write(LogLevel::ERROR, LogEvent::PipelineLoaderFailed->value, [
            'module' => $module->name,
            'loader' => ClassName::short($loader::class),
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

        $this->write($level, LogEvent::PipelineFinished->value, $summary->toArray());
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

        $this->write(LogLevel::INFO, LogEvent::lifecycle($operation, LifecyclePhase::Started), $context);
    }

    public function lifecycleSucceeded(LifecycleOperation $operation, ?string $module = null): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::INFO)) {
            return;
        }

        $context = $module !== null ? ['module' => $module] : [];

        $this->write(LogLevel::INFO, LogEvent::lifecycle($operation, LifecyclePhase::Succeeded), $context);
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

        $this->write(LogLevel::ERROR, LogEvent::lifecycle($operation, LifecyclePhase::Failed), $context);
    }

    public function lifecycleRolledBack(LifecycleOperation $operation, string $module, string $stage): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::WARNING)) {
            return;
        }

        $this->write(LogLevel::WARNING, LogEvent::lifecycle($operation, LifecyclePhase::RolledBack), [
            'module' => $module,
            'stage' => $stage,
        ]);
    }

    public function lifecycleBackupCreated(LifecycleOperation $operation, string $module, string $backupPath): void
    {
        if (! $this->shouldLog(self::CATEGORY_LIFECYCLE, LogLevel::INFO)) {
            return;
        }

        $this->write(LogLevel::INFO, LogEvent::lifecycle($operation, LifecyclePhase::BackupCreated), [
            'module' => $module,
            'backup' => $backupPath,
        ]);
    }

    /**
     * Decide whether an event is recorded. Events of ERROR or higher always pass
     * (the failure-visibility floor): neither a silenced category nor a raised
     * threshold can hide them. Everything below ERROR must clear both its
     * per-category toggle and the configured severity threshold.
     */
    private function shouldLog(string $category, string $level): bool
    {
        $priority = $this->priority($level);

        if ($priority >= self::LEVEL_PRIORITY[LogLevel::ERROR]) {
            return true;
        }

        if (! $this->isCategoryEnabled($category)) {
            return false;
        }

        return $priority >= $this->thresholdPriority;
    }

    private function isCategoryEnabled(string $category): bool
    {
        return ($this->events[$category] ?? false) === true;
    }

    /**
     * Event levels are the lowercase PSR-3 constants, so no normalisation is
     * needed here; the configured threshold is normalised once in the constructor.
     */
    private function priority(string $level): int
    {
        return self::LEVEL_PRIORITY[$level] ?? 0;
    }

    /**
     * The single point that touches the channel. A throwing log channel must
     * never escape into boot or a lifecycle operation, so the write is isolated:
     * diagnostics are additive and the host exception handler remains the source
     * of truth for the real failure. A broken channel is deliberately swallowed.
     *
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            // Intentionally silent — see the method docblock.
        }
    }
}
