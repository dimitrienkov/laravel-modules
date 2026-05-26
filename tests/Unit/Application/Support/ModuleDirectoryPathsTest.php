<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

    #[Test]
    public function configuredRootsRejectsEmptyStringEntry(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/non-empty string/');

        $paths = $this->makePaths(['']);
        $paths->configuredRoots();
    }

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
    public function backupRootReturnsDefaultWhenNotConfigured(): void
    {
        $paths = $this->makePaths(['app/Modules'], null);

        $this->assertSame('/project/storage/app/module-backups', $paths->backupRoot());
    }

    #[Test]
    public function backupRootReturnsCustomPath(): void
    {
        $paths = $this->makePaths(['app/Modules'], '/custom/backups');

        $this->assertSame('/custom/backups', $paths->backupRoot());
    }

    #[Test]
    public function backupPathIncludesModuleNameAndTimestamp(): void
    {
        $paths = $this->makePaths(['app/Modules'], '/backups');

        $result = $paths->backupPath('blog');

        $this->assertMatchesRegularExpression('#^/backups/blog-\d{8}-\d{6}$#', $result);
    }

    /**
     * @param list<string> $directories
     */
    private function makePaths(array $directories, string|null $backup = null): ModuleDirectoryPaths
    {
        $config = [
            'modules' => [
                'paths' => [
                    'directories' => $directories,
                ],
            ],
        ];

        if ($backup !== null) {
            $config['modules']['paths']['backup'] = $backup;
        }

        return new ModuleDirectoryPaths(
            config: new Repository($config),
            basePath: $this->basePath,
            appPath: $this->appPath,
        );
    }
}
