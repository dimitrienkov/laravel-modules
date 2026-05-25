<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\BroadcastLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_defers_channels_file_until_app_boot(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $channelsFile = $modulePath . '/Routes/channels.php';
        mkdir(\dirname($channelsFile), 0755, true);

        $marker = 'BROADCAST_LOADED_' . bin2hex(random_bytes(4));
        file_put_contents($channelsFile, "<?php define('{$marker}', true);");

        $app = new Application($this->tempDir);

        (new BroadcastLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertFalse(\defined($marker));

        $app->boot();

        self::assertTrue(\defined($marker));
    }

    #[Test]
    public function it_returns_early_when_channels_file_is_missing(): void
    {
        $marker = 'BROADCAST_NOT_LOADED_' . bin2hex(random_bytes(4));

        $app = new Application($this->tempDir);

        (new BroadcastLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->boot();

        self::assertFalse(\defined($marker));
    }
}
