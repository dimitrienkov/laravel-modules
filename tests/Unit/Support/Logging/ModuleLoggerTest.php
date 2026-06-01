<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
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
use DimitrienkoV\LaravelModules\Support\Logging\ModuleLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

#[CoversClass(ModuleLogger::class)]
#[Group('unit')]
final class ModuleLoggerTest extends TestCase
{
    private const string FEATURE_SECRET = 'TOP_SECRET_FEATURE_DEFAULT';

    #[Test]
    public function appliedLoaderOutcomeLogsOnlyWhitelistedScalars(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderOutcome(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(['config' => ['settings.php']]),
        );

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level']);
        self::assertSame('pipeline.loader.applied', $logger->records[0]['message']);
        self::assertSame([
            'module' => 'blog',
            'loader' => 'WhitelistFakeLoader',
            'artifacts' => ['config' => ['settings.php']],
        ], $logger->records[0]['context']);

        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function skippedLoaderOutcomeLogsReasonValueOnly(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderOutcome(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::skipped(SkipReason::NoDirectory),
        );

        self::assertSame('pipeline.loader.skipped', $logger->records[0]['message']);
        self::assertSame([
            'module' => 'blog',
            'loader' => 'WhitelistFakeLoader',
            'reason' => 'no_directory',
        ], $logger->records[0]['context']);
        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function loaderFailureLogsExceptionClassAndMessageWithoutModuleInternals(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new \RuntimeException('boom'),
        );

        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertSame('pipeline.loader.failed', $logger->records[0]['message']);
        self::assertSame([
            'module' => 'blog',
            'loader' => 'WhitelistFakeLoader',
            'exception' => \RuntimeException::class,
            'message' => 'boom',
        ], $logger->records[0]['context']);
        self::assertStringNotContainsString(self::FEATURE_SECRET, $this->encode($logger));
    }

    #[Test]
    public function lifecycleStartedLogsModuleAndSourceKindOnly(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::DEBUG, $this->allEventsEnabled());

        $moduleLogger->lifecycleStarted(LifecycleOperation::Install, 'blog', 'zip');

        self::assertSame('lifecycle.install.started', $logger->records[0]['message']);
        self::assertSame(['module' => 'blog', 'source' => 'zip'], $logger->records[0]['context']);
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

        $moduleLogger->loaderOutcome(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(),
        );

        self::assertSame([], $logger->records);

        // A different category still logs — the gate is per-category, not global.
        $moduleLogger->discoveryCompleted(1, 1, 0);

        self::assertCount(1, $logger->records);
        self::assertSame('discovery.completed', $logger->records[0]['message']);
    }

    #[Test]
    public function isSilentWhenTheEventLevelIsBelowTheThreshold(): void
    {
        $logger = new RecordingPsrLogger();
        $moduleLogger = new ModuleLogger($logger, LogLevel::ERROR, $this->allEventsEnabled());

        // debug-level event filtered out by the error threshold.
        $moduleLogger->loaderOutcome(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            LoadReport::applied(),
        );

        self::assertSame([], $logger->records);

        // error-level event meets the threshold and is written.
        $moduleLogger->loaderFailed(
            $this->moduleCarryingSecrets(),
            new WhitelistFakeLoader(),
            new \RuntimeException('boom'),
        );

        self::assertCount(1, $logger->records);
        self::assertSame('pipeline.loader.failed', $logger->records[0]['message']);
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
