<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\EventLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_adds_listeners_directory_to_event_discovery_paths(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $listenersDir = $modulePath . '/Domain/Listeners';
        mkdir($listenersDir, 0755, true);

        (new EventLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        $reflection = new \ReflectionProperty(EventServiceProvider::class, 'eventDiscoveryPaths');
        /** @var iterable<int, string> $paths */
        $paths = $reflection->getValue(null);
        $pathsArray = $paths instanceof \Traversable ? iterator_to_array($paths) : (array) $paths;

        self::assertContains($listenersDir, $pathsArray);
    }

    #[Test]
    public function it_returns_early_when_listeners_directory_is_missing(): void
    {
        (new EventLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $reflection = new \ReflectionProperty(EventServiceProvider::class, 'eventDiscoveryPaths');
        $paths = $reflection->getValue(null);

        self::assertNull($paths);
    }
}
