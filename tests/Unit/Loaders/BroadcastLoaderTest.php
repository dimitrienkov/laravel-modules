<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\BroadcastLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BroadcastLoader::class)]
#[Group('loaders')]
final class BroadcastLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('broadcast-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function defersChannelsFileUntilBroadcastManagerResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $channelsFile = $modulePath . '/Routes/channels.php';
        mkdir(\dirname($channelsFile), 0755, true);

        $marker = 'BROADCAST_LOADED_' . bin2hex(random_bytes(4));
        file_put_contents($channelsFile, "<?php define('{$marker}', true);");

        $app = new Application($this->tempDir);
        $app->singleton(BroadcastManager::class, static fn(Application $a): BroadcastManager => new BroadcastManager($a));

        $report = (new BroadcastLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertFalse(\defined($marker));

        $app->make(BroadcastManager::class);

        self::assertTrue(\defined($marker));
        self::assertTrue($report->wasApplied());
        self::assertSame(['channels' => ['channels.php']], $report->artifacts);
    }

    #[Test]
    public function returnsEarlyWhenChannelsFileIsMissing(): void
    {
        $marker = 'BROADCAST_NOT_LOADED_' . bin2hex(random_bytes(4));

        $app = new Application($this->tempDir);
        $app->singleton(BroadcastManager::class, static fn(Application $a): BroadcastManager => new BroadcastManager($a));

        $report = (new BroadcastLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->make(BroadcastManager::class);

        self::assertFalse(\defined($marker));
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::FileNotFound, $report->reason);
    }
}
