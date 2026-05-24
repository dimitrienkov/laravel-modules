<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleDirectoryScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-scanner-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_scans_configured_directories(): void
    {
        $this->createModule('Blog');
        $this->createModule('Users');

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertCount(2, $paths);
        self::assertStringContainsString('Blog', $paths[0]);
        self::assertStringContainsString('Users', $paths[1]);
    }

    #[Test]
    public function it_ignores_directories_without_manifest(): void
    {
        $this->createModule('Blog');
        mkdir($this->tempDir . '/app/Modules/Empty', 0755, true);

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertCount(1, $paths);
    }

    #[Test]
    public function it_ignores_non_string_config_entries(): void
    {
        $this->createModule('Blog');

        $paths = $this->scanner(['app/Modules', 42, null])->scan();

        self::assertCount(1, $paths);
    }

    #[Test]
    public function it_returns_sorted_paths(): void
    {
        $this->createModule('Zzz');
        $this->createModule('Aaa');

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertStringContainsString('Aaa', $paths[0]);
        self::assertStringContainsString('Zzz', $paths[1]);
    }

    #[Test]
    public function it_returns_empty_for_missing_directory(): void
    {
        $paths = $this->scanner(['nonexistent/path'])->scan();

        self::assertSame([], $paths);
    }

    /**
     * @param array<mixed> $directories
     */
    private function scanner(array $directories): ModuleDirectoryScanner
    {
        return new ModuleDirectoryScanner(
            config: new Repository([
                'modules' => ['paths' => ['directories' => $directories]],
            ]),
            filesystem: new Filesystem(),
            layout: new ModuleLayout(),
            basePath: $this->tempDir,
        );
    }

    private function createModule(string $name): void
    {
        $path = $this->tempDir . '/app/Modules/' . $name;
        mkdir($path, 0755, true);
        file_put_contents($path . '/module.json', '{}');
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
