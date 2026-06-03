<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Support\ModulePathsConfig;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModulePathsConfig::class)]
#[Group('support')]
final class ModulePathsConfigTest extends TestCase
{
    #[Test]
    public function exposesValidatedScalarsForValidConfig(): void
    {
        $paths = ModulePathsConfig::fromRepository($this->config([
            'directories' => ['app/Modules', 'app/Integrations'],
            'state' => 'storage/app/private/modules',
            'backup' => 'storage/app/module-backups',
        ]));

        $this->assertSame(['app/Modules', 'app/Integrations'], $paths->directories());
        $this->assertSame('storage/app/private/modules', $paths->stateRoot());
        $this->assertSame('storage/app/module-backups', $paths->backupRoot());
    }

    #[Test]
    public function rejectsNonArrayDirectories(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a list of directory paths');

        ModulePathsConfig::fromRepository($this->config(['directories' => 'app/Modules']));
    }

    #[Test]
    public function rejectsNonStringDirectoryEntryWithIndexAndType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/entry at index 1 must be a non-empty string, got \[int\]/');

        ModulePathsConfig::fromRepository($this->config(['directories' => ['app/Modules', 42]]));
    }

    #[Test]
    public function rejectsEmptyStringDirectoryEntryWithIndex(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/entry at index 0 must be a non-empty string/');

        ModulePathsConfig::fromRepository($this->config(['directories' => ['']]));
    }

    #[Test]
    public function rejectsEmptyDirectoryList(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('at least one module directory must be configured');

        ModulePathsConfig::fromRepository($this->config(['directories' => []]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidRequiredStringProvider(): iterable
    {
        yield 'null' => [null];
        yield 'blank' => ['   '];
        yield 'integer' => [123];
        yield 'array' => [['nested']];
    }

    #[Test]
    #[DataProvider('invalidRequiredStringProvider')]
    public function rejectsInvalidStateRoot(mixed $state): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/modules\.paths\.state.*must be a non-empty string path/');

        ModulePathsConfig::fromRepository($this->config([
            'directories' => ['app/Modules'],
            'state' => $state,
            'backup' => 'storage/backup',
        ]));
    }

    #[Test]
    public function rejectsMissingStateRoot(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/modules\.paths\.state.*must be a non-empty string path/');

        ModulePathsConfig::fromRepository($this->config([
            'directories' => ['app/Modules'],
            'backup' => 'storage/backup',
        ]));
    }

    #[Test]
    #[DataProvider('invalidRequiredStringProvider')]
    public function rejectsInvalidBackupRoot(mixed $backup): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/modules\.paths\.backup.*must be a non-empty string path/');

        ModulePathsConfig::fromRepository($this->config([
            'directories' => ['app/Modules'],
            'state' => 'storage/state',
            'backup' => $backup,
        ]));
    }

    #[Test]
    public function rejectsMissingBackupRoot(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/modules\.paths\.backup.*must be a non-empty string path/');

        ModulePathsConfig::fromRepository($this->config([
            'directories' => ['app/Modules'],
            'state' => 'storage/state',
        ]));
    }

    /**
     * @param array<string, mixed> $paths
     */
    private function config(array $paths): Repository
    {
        return new Repository(['modules' => ['paths' => $paths]]);
    }
}
