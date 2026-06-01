<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Exceptions;

use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyEnabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleArchiveException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleRemoveException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('lifecycle')]
final class LifecycleExceptionsTest extends TestCase
{
    #[Test]
    public function moduleAlreadyEnabledContainsModuleName(): void
    {
        $exception = ModuleAlreadyEnabledException::forModule('blog');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('already enabled', $exception->getMessage());
    }

    #[Test]
    public function moduleAlreadyDisabledContainsModuleName(): void
    {
        $exception = ModuleAlreadyDisabledException::forModule('blog');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('already disabled', $exception->getMessage());
    }

    #[Test]
    public function dependentModulesExistForDisableListsDependents(): void
    {
        $exception = DependentModulesExistException::forDisable('users', ['blog', 'forum']);

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('users', $exception->getMessage());
        $this->assertStringContainsString('blog, forum', $exception->getMessage());
        $this->assertStringContainsString('disable', $exception->getMessage());
    }

    #[Test]
    public function dependentModulesExistForRemoveListsDependents(): void
    {
        $exception = DependentModulesExistException::forRemove('users', ['blog']);

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('users', $exception->getMessage());
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('remove', $exception->getMessage());
    }

    #[Test]
    public function moduleAlreadyExistsForNameContainsName(): void
    {
        $exception = ModuleAlreadyExistsException::forName('blog');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('already exists', $exception->getMessage());
    }

    #[Test]
    public function moduleAlreadyExistsForPathContainsNameAndPath(): void
    {
        $exception = ModuleAlreadyExistsException::forPath('blog', '/app/Modules/Blog');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('/app/Modules/Blog', $exception->getMessage());
    }

    #[Test]
    public function moduleScaffoldExceptionContainsModuleAndReason(): void
    {
        $exception = ModuleScaffoldException::forModule('blog', 'directory creation failed');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('directory creation failed', $exception->getMessage());
    }

    #[Test]
    public function moduleScaffoldExceptionChainsPrevious(): void
    {
        $previous = new RuntimeException('disk full');
        $exception = ModuleScaffoldException::forModule('blog', 'write failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function moduleInstallExceptionForSourceContainsPath(): void
    {
        $exception = ModuleInstallException::forSource('/tmp/blog.zip', 'invalid manifest');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('/tmp/blog.zip', $exception->getMessage());
        $this->assertStringContainsString('invalid manifest', $exception->getMessage());
    }

    #[Test]
    public function moduleInstallExceptionForModuleContainsName(): void
    {
        $exception = ModuleInstallException::forModule('blog', 'already installed');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('already installed', $exception->getMessage());
    }

    #[Test]
    public function moduleInstallExceptionChainsPrevious(): void
    {
        $previous = new RuntimeException('copy failed');
        $exception = ModuleInstallException::forSource('/tmp/blog', 'copy error', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function moduleUpdateExceptionContainsModuleAndReason(): void
    {
        $exception = ModuleUpdateException::forModule('blog', 'rollback triggered');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('rollback triggered', $exception->getMessage());
    }

    #[Test]
    public function moduleUpdateExceptionNameMismatch(): void
    {
        $exception = ModuleUpdateException::nameMismatch('blog', 'forum');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('forum', $exception->getMessage());
        $this->assertStringContainsString('mismatch', $exception->getMessage());
    }

    #[Test]
    public function moduleRemoveExceptionContainsModuleAndReason(): void
    {
        $exception = ModuleRemoveException::forModule('blog', 'directory not writable');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertStringContainsString('directory not writable', $exception->getMessage());
    }

    #[Test]
    public function moduleRemoveExceptionChainsPrevious(): void
    {
        $previous = new RuntimeException('permission denied');
        $exception = ModuleRemoveException::forModule('blog', 'delete failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function moduleSourceExceptionForPathContainsSourceAndReason(): void
    {
        $exception = ModuleSourceException::forPath('/tmp/invalid', 'manifest not found');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('/tmp/invalid', $exception->getMessage());
        $this->assertStringContainsString('manifest not found', $exception->getMessage());
    }

    #[Test]
    public function moduleSourceExceptionUnsupportedType(): void
    {
        $exception = ModuleSourceException::unsupportedType('/tmp/module.tar.gz');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('/tmp/module.tar.gz', $exception->getMessage());
        $this->assertStringContainsString('Unsupported', $exception->getMessage());
    }

    #[Test]
    public function moduleArchiveExceptionForPathContainsPathAndReason(): void
    {
        $exception = ModuleArchiveException::forPath('/tmp/broken.zip', 'corrupted archive');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('/tmp/broken.zip', $exception->getMessage());
        $this->assertStringContainsString('corrupted archive', $exception->getMessage());
    }

    #[Test]
    public function moduleArchiveExceptionExtensionMissing(): void
    {
        $exception = ModuleArchiveException::extensionMissing();

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('ext-zip', $exception->getMessage());
    }

    #[Test]
    public function moduleArchiveExceptionZipSlip(): void
    {
        $exception = ModuleArchiveException::zipSlip('/tmp/evil.zip', '../../../etc/passwd');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('/tmp/evil.zip', $exception->getMessage());
        $this->assertStringContainsString('../../../etc/passwd', $exception->getMessage());
        $this->assertStringContainsString('traversal', $exception->getMessage());
    }

    #[Test]
    public function moduleArchiveExceptionChainsPrevious(): void
    {
        $previous = new RuntimeException('ZipArchive error');
        $exception = ModuleArchiveException::forPath('/tmp/bad.zip', 'extraction failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
