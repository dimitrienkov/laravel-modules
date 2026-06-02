<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PartialModuleRollback::class)]
#[Group('lifecycle')]
final class PartialModuleRollbackTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDirectory('rollback');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();
        parent::tearDown();
    }

    #[Test]
    public function rollbackReturnsEmptyNoteOnSuccess(): void
    {
        $targetPath = $this->tempDir . '/Blog';
        mkdir($targetPath, 0755, true);
        file_put_contents($targetPath . '/dummy.txt', 'content');

        /** @var ModuleStateRepositoryInterface&Mockery\MockInterface $stateRepo */
        $stateRepo = Mockery::mock(ModuleStateRepositoryInterface::class);
        $stateRepo->shouldReceive('delete')->with('blog')->once();

        $rollback = new PartialModuleRollback($this->makeDirectoryOps(), $stateRepo);

        $note = $rollback->rollback('blog', $targetPath);

        $this->assertSame('', $note);
        $this->assertDirectoryDoesNotExist($targetPath);
    }

    #[Test]
    public function rollbackReturnsCleanupNoteWhenStateDeleteFails(): void
    {
        $targetPath = $this->tempDir . '/Blog';
        mkdir($targetPath, 0755, true);

        /** @var ModuleStateRepositoryInterface&Mockery\MockInterface $stateRepo */
        $stateRepo = Mockery::mock(ModuleStateRepositoryInterface::class);
        $stateRepo->shouldReceive('delete')
            ->andThrow(new RuntimeException('state delete failed'));

        $rollback = new PartialModuleRollback($this->makeDirectoryOps(), $stateRepo);

        $note = $rollback->rollback('blog', $targetPath);

        $this->assertStringContainsString('State cleanup also failed', $note);
        $this->assertStringContainsString('state delete failed', $note);
    }

    #[Test]
    public function rollbackHandlesNonExistentDirectory(): void
    {
        /** @var ModuleStateRepositoryInterface&Mockery\MockInterface $stateRepo */
        $stateRepo = Mockery::mock(ModuleStateRepositoryInterface::class);
        $stateRepo->shouldReceive('delete')->once();

        $rollback = new PartialModuleRollback($this->makeDirectoryOps(), $stateRepo);

        $note = $rollback->rollback('blog', $this->tempDir . '/Nonexistent');

        $this->assertSame('', $note);
    }

    private function makeDirectoryOps(): ModuleDirectoryOperations
    {
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->tempDir . '/state',
                    'backups' => $this->tempDir . '/backups',
                ],
            ],
        ]);

        return new ModuleDirectoryOperations(
            new LocalFilesystem(new Filesystem()),
            new ModuleDirectoryPaths($config, $this->tempDir, $this->tempDir . '/app'),
        );
    }
}
