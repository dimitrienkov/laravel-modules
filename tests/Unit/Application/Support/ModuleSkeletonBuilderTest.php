<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleSkeletonBuilder::class)]
#[Group('lifecycle')]
final class ModuleSkeletonBuilderTest extends TestCase
{
    use UsesTempDirectory;

    private ModuleSkeletonBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDirectory('skeleton_builder');

        $this->builder = new ModuleSkeletonBuilder(
            new LocalFilesystem(new Filesystem()),
            new AtomicFileWriter(),
            \dirname(__DIR__, 4) . '/stubs',
        );
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();
        parent::tearDown();
    }

    #[Test]
    public function buildCreatesExpectedDirectories(): void
    {
        $targetPath = $this->tempDir . '/Blog';

        $this->builder->build($targetPath, 'App\\Modules\\Blog', 'Blog', 'blog');

        $this->assertDirectoryExists($targetPath . '/Config');
        $this->assertDirectoryExists($targetPath . '/Console/Commands');
        $this->assertDirectoryExists($targetPath . '/Database/Factories');
        $this->assertDirectoryExists($targetPath . '/Database/Migrations');
        $this->assertDirectoryExists($targetPath . '/Domain/Models');
        $this->assertDirectoryExists($targetPath . '/Http/Middleware');
        $this->assertDirectoryExists($targetPath . '/Providers');
        $this->assertDirectoryExists($targetPath . '/Routes');
    }

    #[Test]
    public function buildWritesProviderFromStub(): void
    {
        $targetPath = $this->tempDir . '/Blog';

        $this->builder->build($targetPath, 'App\\Modules\\Blog', 'Blog', 'blog');

        $providerPath = $targetPath . '/Providers/BlogServiceProvider.php';
        $this->assertFileExists($providerPath);

        $content = file_get_contents($providerPath);
        $this->assertStringContainsString('App\\Modules\\Blog', $content);
        $this->assertStringContainsString('Blog', $content);
    }

    #[Test]
    public function buildThrowsWhenStubMissing(): void
    {
        $builder = new ModuleSkeletonBuilder(
            new LocalFilesystem(new Filesystem()),
            new AtomicFileWriter(),
            $this->tempDir . '/nonexistent-stubs',
        );

        $this->expectException(ModuleScaffoldException::class);
        $this->expectExceptionMessageMatches('/provider stub not found/');

        $builder->build($this->tempDir . '/Blog', 'App\\Modules\\Blog', 'Blog', 'blog');
    }
}
