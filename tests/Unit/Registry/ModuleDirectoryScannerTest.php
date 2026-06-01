<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDirectoryScanner::class)]
#[Group('registry')]
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
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function scansConfiguredDirectories(): void
    {
        $this->createModule('Blog');
        $this->createModule('Users');

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertCount(2, $paths);
        self::assertStringContainsString('Blog', $paths[0]);
        self::assertStringContainsString('Users', $paths[1]);
    }

    #[Test]
    public function ignoresDirectoriesWithoutManifest(): void
    {
        $this->createModule('Blog');
        mkdir($this->tempDir . '/app/Modules/Empty', 0755, true);

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertCount(1, $paths);
    }

    #[Test]
    public function throwsForNonStringConfigEntries(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('each entry must be a non-empty string');

        $this->scanner(['app/Modules', 42])->scan();
    }

    #[Test]
    public function throwsForEmptyStringConfigEntry(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('each entry must be a non-empty string');

        $this->scanner([''])->scan();
    }

    #[Test]
    public function throwsForNonArrayDirectoriesConfig(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a list of directory paths');

        $scanner = new ModuleDirectoryScanner(
            config: new Repository([
                'modules' => ['paths' => ['directories' => 'not-an-array']],
            ]),
            filesystem: new LocalFilesystem(new Filesystem()),
            layout: new ModuleLayout(),
            basePath: $this->tempDir,
            appPath: $this->tempDir . '/app',
        );

        $scanner->scan();
    }

    #[Test]
    public function returnsSortedPaths(): void
    {
        $this->createModule('Zzz');
        $this->createModule('Aaa');

        $paths = $this->scanner(['app/Modules'])->scan();

        self::assertStringContainsString('Aaa', $paths[0]);
        self::assertStringContainsString('Zzz', $paths[1]);
    }

    #[Test]
    public function returnsEmptyForMissingDirectory(): void
    {
        $paths = $this->scanner(['app/NonexistentPath'])->scan();

        self::assertSame([], $paths);
    }

    #[Test]
    public function throwsForDirectoryOutsideAppPath(): void
    {
        $outsideDir = $this->tempDir . '/outside';
        mkdir($outsideDir . '/Modules/Blog', 0755, true);
        file_put_contents($outsideDir . '/Modules/Blog/module.json', '{}');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('resolves outside app_path()');

        $this->scanner(['outside/Modules'])->scan();
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
            filesystem: new LocalFilesystem(new Filesystem()),
            layout: new ModuleLayout(),
            basePath: $this->tempDir,
            appPath: $this->tempDir . '/app',
        );
    }

    private function createModule(string $name): void
    {
        $path = $this->tempDir . '/app/Modules/' . $name;
        mkdir($path, 0755, true);
        file_put_contents($path . '/module.json', '{}');
    }

}
