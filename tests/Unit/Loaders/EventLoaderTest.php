<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\EventLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventLoader::class)]
#[Group('loaders')]
final class EventLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('event-loader');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionProperty(EventServiceProvider::class, 'eventDiscoveryPaths');
        $reflection->setValue(null, null);

        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function addsListenersDirectoryToEventDiscoveryPaths(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $listenersDir = $modulePath . '/Domain/Listeners';
        mkdir($listenersDir, 0755, true);

        $report = (new EventLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        $reflection = new \ReflectionProperty(EventServiceProvider::class, 'eventDiscoveryPaths');
        /** @var iterable<int, string> $paths */
        $paths = $reflection->getValue(null);
        $pathsArray = $paths instanceof \Traversable ? iterator_to_array($paths) : (array) $paths;

        self::assertContains($listenersDir, $pathsArray);
        self::assertTrue($report->wasApplied());
        self::assertSame(['listeners' => ['Domain/Listeners']], $report->artifacts);
    }

    #[Test]
    public function returnsEarlyWhenListenersDirectoryIsMissing(): void
    {
        $report = (new EventLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $reflection = new \ReflectionProperty(EventServiceProvider::class, 'eventDiscoveryPaths');
        $paths = $reflection->getValue(null);

        self::assertNull($paths);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
    }
}
