<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\Version;
use DimitrienkoV\LaravelModules\Support\Logging\LifecyclePhase;
use DimitrienkoV\LaravelModules\Support\Logging\LogEvent;
use DimitrienkoV\LaravelModules\Support\Logging\ModuleLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use RuntimeException;

#[CoversClass(ModuleLogger::class)]
#[Group('unit')]
final class ModuleLoggerTest extends TestCase
{
    private const string FEATURE_SECRET = 'TOP_SECRET_FEATURE_DEFAULT';

    #[Test]
    public function appliedLoaderCompletedLogsOnlyWhitelistedScalars(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderCompleted(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(['config' => ['settings.php']]),
        );

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level']);
        self::assertSame(LogEvent::PipelineLoaderApplied->value, $logger->records[0]['message']);
        self::assertSame([
            'module' => 'blog',
            'loader' => 'WhitelistFakeLoader',
            'artifacts' => ['config' => ['settings.php']],
        ], $logger->records[0]['context']);

        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function skippedLoaderCompletedLogsReasonValueOnly(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderCompleted(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::skipped(SkipReason::NoDirectory),
        );

        self::assertSame(LogEvent::PipelineLoaderSkipped->value, $logger->records[0]['message']);
        self::assertSame([
            'module' => 'blog',
            'loader' => 'WhitelistFakeLoader',
            'reason' => 'no_directory',
        ], $logger->records[0]['context']);
        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function loaderFailureLogsTheThrowableObjectAndMessageWithoutModuleInternals(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $exception = new RuntimeException('boom');

        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            $exception,
        );

        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertSame(LogEvent::PipelineLoaderFailed->value, $logger->records[0]['message']);

        $context = $logger->records[0]['context'];
        self::assertSame('blog', $context['module']);
        self::assertSame('WhitelistFakeLoader', $context['loader']);
        // The object itself is logged so the channel can render trace + $previous.
        self::assertSame($exception, $context['exception']);
        self::assertSame('boom', $context['message']);
        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function lifecycleStartedLogsModuleAndSourceKindOnly(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleStarted(LifecycleOperation::Install, 'blog', 'zip');

        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Install, LifecyclePhase::Started),
            $logger->records[0]['message'],
        );
        self::assertSame(['module' => 'blog', 'source' => 'zip'], $logger->records[0]['context']);
    }

    #[Test]
    public function lifecycleStartedForAGlobalOperationLogsAnEmptyContext(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleStarted(LifecycleOperation::Optimize);

        self::assertSame(LogLevel::INFO, $logger->records[0]['level']);
        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Optimize, LifecyclePhase::Started),
            $logger->records[0]['message'],
        );
        self::assertSame([], $logger->records[0]['context']);
    }

    #[Test]
    public function lifecycleSucceededLogsModuleAtInfo(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleSucceeded(LifecycleOperation::Install, 'blog');

        self::assertSame(LogLevel::INFO, $logger->records[0]['level']);
        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Install, LifecyclePhase::Succeeded),
            $logger->records[0]['message'],
        );
        self::assertSame(['module' => 'blog'], $logger->records[0]['context']);
    }

    #[Test]
    public function lifecycleFailedLogsTheThrowableObjectAndMessageAtError(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $exception = new RuntimeException('kaboom');

        $moduleLogger->lifecycleFailed(LifecycleOperation::Remove, 'blog', $exception);

        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Remove, LifecyclePhase::Failed),
            $logger->records[0]['message'],
        );

        $context = $logger->records[0]['context'];
        self::assertSame('blog', $context['module']);
        self::assertSame($exception, $context['exception']);
        self::assertSame('kaboom', $context['message']);
    }

    #[Test]
    public function lifecycleRolledBackLogsModuleAndStageAtWarning(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleRolledBack(LifecycleOperation::Update, 'blog', 'persistence');

        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Update, LifecyclePhase::RolledBack),
            $logger->records[0]['message'],
        );
        self::assertSame(['module' => 'blog', 'stage' => 'persistence'], $logger->records[0]['context']);
    }

    #[Test]
    public function lifecycleBackupCreatedLogsModuleAndBackupAtInfoWithoutOperationKey(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleBackupCreated(LifecycleOperation::Update, 'blog', '/backups/blog-123');

        self::assertSame(LogLevel::INFO, $logger->records[0]['level']);
        self::assertSame(
            LogEvent::lifecycle(LifecycleOperation::Update, LifecyclePhase::BackupCreated),
            $logger->records[0]['message'],
        );
        self::assertSame(['module' => 'blog', 'backup' => '/backups/blog-123'], $logger->records[0]['context']);
    }

    #[Test]
    public function pipelineFinishedStaysAtDebugForACleanRun(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->pipelineFinished(new PipelineRunSummary(
            modulesEnabled: 1,
            loaders: 2,
            applied: 2,
            skipped: 0,
            failed: 0,
            durationMs: 1.5,
        ));

        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level']);
        self::assertSame(LogEvent::PipelineFinished->value, $logger->records[0]['message']);
    }

    #[Test]
    public function pipelineFinishedEscalatesToWarningWhenAnyLoaderFailed(): void
    {
        $logger = new RecordingPsrLogger();
        // A warning threshold would hide a clean (debug) run, but the failed run escalates.
        $moduleLogger = new ModuleLogger($logger, LogLevel::WARNING, $this->allEventsEnabled());

        $moduleLogger->pipelineFinished(new PipelineRunSummary(
            modulesEnabled: 1,
            loaders: 2,
            applied: 1,
            skipped: 0,
            failed: 1,
            durationMs: 2.0,
        ));

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertSame(LogEvent::PipelineFinished->value, $logger->records[0]['message']);
    }

    #[Test]
    public function unrecognisedThresholdFailsOpenToDebug(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, 'bogus', $this->allEventsEnabled());

        $moduleLogger->discoveryCompleted(1, 1, 0);

        self::assertCount(1, $logger->records);
        self::assertSame(LogEvent::DiscoveryCompleted->value, $logger->records[0]['message']);
    }

    #[Test]
    public function thresholdComparisonIsCaseInsensitive(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, 'INFO', $this->allEventsEnabled());

        // debug — below the (mixed-case) INFO threshold, filtered out.
        $moduleLogger->discoveryCompleted(1, 1, 0);
        // info — meets the threshold, written.
        $moduleLogger->cacheWritten(2, 'bootstrap/cache/modules.php');

        self::assertCount(1, $logger->records);
        self::assertSame(LogEvent::CacheWritten->value, $logger->records[0]['message']);
    }

    #[Test]
    public function errorEventsBypassADisabledCategoryWhileNonErrorEventsStayGated(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, [
            'discovery' => true,
            'cache' => true,
            'pipeline' => false,
            'lifecycle' => false,
        ]);

        // ERROR bypasses the disabled pipeline category.
        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new RuntimeException('boom'),
        );
        // DEBUG stays gated by the disabled pipeline category.
        $moduleLogger->loaderCompleted(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(),
        );
        // ERROR bypasses the disabled lifecycle category.
        $moduleLogger->lifecycleFailed(LifecycleOperation::Remove, 'blog', new RuntimeException('x'));
        // INFO stays gated by the disabled lifecycle category.
        $moduleLogger->lifecycleStarted(LifecycleOperation::Remove, 'blog');

        $messages = array_map(static fn(array $record): string => $record['message'], $logger->records);

        self::assertSame([
            LogEvent::PipelineLoaderFailed->value,
            LogEvent::lifecycle(LifecycleOperation::Remove, LifecyclePhase::Failed),
        ], $messages);
    }

    #[Test]
    public function errorEventsAreRecordedEvenWhenTheThresholdIsRaisedAboveError(): void
    {
        $logger = new RecordingPsrLogger();
        // The highest threshold AND the two failure categories are off: the floor
        // on ERROR must still let a loader/lifecycle failure through.
        $moduleLogger = new ModuleLogger($logger, LogLevel::EMERGENCY, [
            'discovery' => false,
            'cache' => false,
            'pipeline' => false,
            'lifecycle' => false,
        ]);

        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new RuntimeException('boom'),
        );
        $moduleLogger->lifecycleFailed(LifecycleOperation::Install, 'blog', new RuntimeException('x'));
        // A WARNING-level event at the same raised threshold stays suppressed.
        $moduleLogger->lifecycleRolledBack(LifecycleOperation::Install, 'blog', 'persistence');

        $messages = array_map(static fn(array $record): string => $record['message'], $logger->records);

        self::assertSame([
            LogEvent::PipelineLoaderFailed->value,
            LogEvent::lifecycle(LifecycleOperation::Install, LifecyclePhase::Failed),
        ], $messages);
    }

    #[Test]
    public function discoveryRootRejectedIsRecordedAtErrorEvenWhenItsCategoryIsOffAndThresholdRaised(): void
    {
        $logger = new RecordingPsrLogger();
        // discovery category off + threshold above error: a rejected root aborts
        // discovery (it precedes a thrown exception), so it must not be hideable.
        $moduleLogger = new ModuleLogger($logger, LogLevel::EMERGENCY, [
            'discovery' => false,
            'cache' => true,
            'pipeline' => true,
            'lifecycle' => true,
        ]);

        $moduleLogger->discoveryRootRejected('outside/Modules', 'resolves outside app_path()');
        // The non-fatal sibling stays at WARNING and is suppressed by the threshold.
        $moduleLogger->discoveryRootMissing('app/Modules');

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertSame(LogEvent::DiscoveryRootRejected->value, $logger->records[0]['message']);
        self::assertSame([
            'directory' => 'outside/Modules',
            'reason' => 'resolves outside app_path()',
        ], $logger->records[0]['context']);
    }

    #[Test]
    public function disablingTheCacheCategoryStillSuppressesTheWarningLevelCacheInvalid(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, [
            'discovery' => true,
            'cache' => false,
            'pipeline' => true,
            'lifecycle' => true,
        ]);

        // WARNING < ERROR, so the bypass does not apply — the disabled category wins.
        $moduleLogger->cacheInvalid('corrupt', new RuntimeException('parse'));

        self::assertSame([], $logger->records);
    }

    #[Test]
    public function anEnabledCategoryStillObeysTheThresholdForBelowErrorEvents(): void
    {
        $logger = new RecordingPsrLogger();
        // Cache category ON, threshold at error: a WARNING-level cache event is
        // still suppressed because only ERROR+ ignores the threshold.
        $moduleLogger = new ModuleLogger($logger, LogLevel::ERROR, $this->allEventsEnabled());

        $moduleLogger->cacheInvalid('corrupt', new RuntimeException('parse'));

        self::assertSame([], $logger->records);
    }

    #[Test]
    public function isSilentWhenTheCategoryToggleIsOff(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, [
            'discovery' => true,
            'cache' => true,
            'pipeline' => false,
            'lifecycle' => true,
        ]);

        $moduleLogger->loaderCompleted(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(),
        );

        self::assertSame([], $logger->records);

        // A different category still logs — the gate is per-category, not global.
        $moduleLogger->discoveryCompleted(1, 1, 0);

        self::assertCount(1, $logger->records);
        self::assertSame(LogEvent::DiscoveryCompleted->value, $logger->records[0]['message']);
    }

    #[Test]
    public function isSilentWhenTheEventLevelIsBelowTheThreshold(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::ERROR, $this->allEventsEnabled());

        // debug-level event filtered out by the error threshold.
        $moduleLogger->loaderCompleted(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(),
        );

        self::assertSame([], $logger->records);

        // error-level event meets the threshold and is written.
        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new RuntimeException('boom'),
        );

        self::assertCount(1, $logger->records);
        self::assertSame(LogEvent::PipelineLoaderFailed->value, $logger->records[0]['message']);
    }

    #[Test]
    public function aThrowingChannelOnTheDebugPathIsSwallowed(): void
    {
        $logger = new ThrowingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        // Must not propagate — diagnostics are additive and never break boot.
        $moduleLogger->discoveryCompleted(1, 1, 0);

        // The write was attempted (gating passed) and the channel failure swallowed.
        self::assertSame(1, $logger->attempts);
    }

    #[Test]
    public function aThrowingChannelOnTheErrorPathIsSwallowed(): void
    {
        $logger = new ThrowingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new RuntimeException('boom'),
        );

        self::assertSame(1, $logger->attempts);
    }

    /**
     * @return array<string, bool>
     */
    private function allEventsEnabled(): array
    {
        return [
            'discovery' => true,
            'cache' => true,
            'pipeline' => true,
            'lifecycle' => true,
        ];
    }

    private function encode(RecordingPsrLogger $logger): string
    {
        return (string) json_encode($logger->records);
    }

    private function moduleCarryingSecrets(): Module
    {
        $features = new FeatureSchema([
            'api_token' => new FeatureDefinition(
                key: 'api_token',
                type: FeatureType::String,
                hasDefault: true,
                default: self::FEATURE_SECRET,
                min: null,
                max: null,
                options: [],
            ),
        ]);

        return new Module(
            name: 'blog',
            displayName: 'Blog',
            namespace: 'App\\Modules\\Blog',
            path: '/var/www/app/Modules/Blog',
            schemaVersion: 1,
            meta: new ManifestMeta(
                name: 'blog',
                displayName: 'Blog',
                kind: ModuleKind::Module,
                version: new Version('1.0.0'),
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
            ),
            state: new ModuleState(true, null),
            features: $features,
        );
    }
}

final class WhitelistFakeLoader implements LoaderInterface
{
    public function load(Module $module): LoadReport
    {
        return LoadReport::applied();
    }

    public function priority(): int
    {
        return 0;
    }
}

final class RecordingPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class ThrowingPsrLogger extends AbstractLogger
{
    public int $attempts = 0;

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->attempts++;

        throw new RuntimeException('log channel is broken');
    }
}
