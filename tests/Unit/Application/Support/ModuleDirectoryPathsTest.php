<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDirectoryPaths::class)]
#[Group('lifecycle')]
final class ModuleDirectoryPathsTest extends TestCase
{
    private string $basePath;

    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = '/project';
        $this->appPath = '/project/app';
    }

    #[Test]
    public function defaultTargetRootReturnsFirstConfiguredRoot(): void
    {
        $paths = $this->makePaths(['app/Modules', 'app/Integrations']);

        $this->assertSame('/project/app/Modules', $paths->defaultTargetRoot());
    }

    #[Test]
    public function resolveTargetRootAcceptsConfiguredRoot(): void
    {
        $paths = $this->makePaths(['app/Modules', 'app/Integrations']);

        $this->assertSame('/project/app/Integrations', $paths->resolveTargetRoot('app/Integrations'));
    }

    #[Test]
    public function resolveTargetRootRejectsUnknownRoot(): void
    {
        $paths = $this->makePaths(['app/Modules']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/not a configured module root/');

        $paths->resolveTargetRoot('app/Unknown');
    }

    #[Test]
    public function configuredRootsRejectsRootOutsideAppPath(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/resolves outside app_path/');

        $paths = $this->makePaths(['vendor/some-package']);
        $paths->configuredRoots();
    }

    // Per-entry structural validation (non-empty string) moved to the
    // composition root and is locked in ModuleLoaderServiceProviderTest. The
    // service still owns the app_path guard and the empty-list check below.

    #[Test]
    public function configuredRootsRejectsEmptyList(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/at least one/');

        $paths = $this->makePaths([]);
        $paths->configuredRoots();
    }

    #[Test]
    public function targetModulePathBuildsStudlyCasePath(): void
    {
        $paths = $this->makePaths(['app/Modules']);

        $this->assertSame(
            '/project/app/Modules/UserAuth',
            $paths->targetModulePath('/project/app/Modules', 'user_auth'),
        );
    }

    #[Test]
    public function targetModulePathHandlesSimpleName(): void
    {
        $paths = $this->makePaths(['app/Modules']);

        $this->assertSame(
            '/project/app/Modules/Blog',
            $paths->targetModulePath('/project/app/Modules', 'blog'),
        );
    }

    #[Test]
    public function backupRootReturnsConfiguredPath(): void
    {
        $paths = $this->makePaths(['app/Modules'], '/custom/backups');

        $this->assertSame('/custom/backups', $paths->backupRoot());
    }

    #[Test]
    public function backupPathIncludesModuleNameAndTimestamp(): void
    {
        $paths = $this->makePaths(['app/Modules'], '/backups');

        $result = $paths->backupPath('blog');

        $this->assertMatchesRegularExpression('#^/backups/blog-\d{8}-\d{6}-[0-9a-f]{8}$#', $result);
    }

    /**
     * @param list<string> $directories
     */
    private function makePaths(
        array $directories,
        string $backup = '/project/storage/app/module-backups',
    ): ModuleDirectoryPaths {
        return new ModuleDirectoryPaths(
            directories: $directories,
            basePath: $this->basePath,
            appPath: $this->appPath,
            configuredBackupRoot: $backup,
        );
    }
}
