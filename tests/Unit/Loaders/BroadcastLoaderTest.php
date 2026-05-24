<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\BroadcastLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BroadcastLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-broadcast-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_requires_channels_file_when_it_exists(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $channelsFile = $modulePath . '/Routes/channels.php';
        mkdir(\dirname($channelsFile), 0755, true);

        $marker = 'BROADCAST_LOADED_' . bin2hex(random_bytes(4));
        file_put_contents($channelsFile, "<?php define('{$marker}', true);");

        (new BroadcastLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertTrue(\defined($marker));
    }

    #[Test]
    public function it_returns_early_when_channels_file_is_missing(): void
    {
        $marker = 'BROADCAST_NOT_LOADED_' . bin2hex(random_bytes(4));

        (new BroadcastLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertFalse(\defined($marker));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
