<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesSourceArchive;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use ZipArchive;

#[CoversClass(ModuleSourcePreparer::class)]
#[Group('lifecycle')]
final class ModuleSourcePreparerTest extends TestCase
{
    use CreatesModuleFiles;
    use CreatesSourceArchive;
    use UsesTempDirectory;

    private ModuleSourcePreparer $preparer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDirectory('source_preparer');

        $localFs = new LocalFilesystem(new Filesystem());
        $this->preparer = new ModuleSourcePreparer(
            documentReader: new ManifestDocumentReader(),
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            zipExtractor: new ZipExtractor($localFs),
            filesystem: $localFs,
        );
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();
        parent::tearDown();
    }

    #[Test]
    public function prepareFromZipSource(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);

        try {
            $this->assertNotNull($prepared->temporaryRoot);
            $this->assertDirectoryExists($prepared->path);
            $this->assertSame('blog', $prepared->manifest['meta']['name']);
        } finally {
            $this->preparer->cleanup($prepared);
        }
    }

    #[Test]
    public function prepareFromZipComputesSha256Checksum(): void
    {
        $zipPath = $this->createModuleZip('blog');
        $expectedChecksum = hash_file('sha256', $zipPath);

        $prepared = $this->preparer->prepare($zipPath);

        try {
            $this->assertInstanceOf(Checksum::class, $prepared->checksum);
            $this->assertSame($expectedChecksum, $prepared->checksum->value);
            $this->assertSame(64, \strlen($prepared->checksum->value));
        } finally {
            $this->preparer->cleanup($prepared);
        }
    }

    #[Test]
    public function prepareThrowsOnDirectorySource(): void
    {
        $emptyDir = $this->tempDir . '/empty_module';
        mkdir($emptyDir, 0755, true);

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/Unsupported/');

        $this->preparer->prepare($emptyDir);
    }

    #[Test]
    public function prepareThrowsOnMissingManifestInZip(): void
    {
        $zipPath = $this->tempDir . '/no_manifest.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'no manifest here');
        $zip->close();

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/module\.json not found/');

        $this->preparer->prepare($zipPath);
    }

    #[Test]
    public function prepareThrowsWhenSourceContainsStateFile(): void
    {
        // state.json belongs to the host's private storage, never to a shippable
        // module artifact, so a source archive carrying it must be rejected.
        $zipPath = $this->zipModuleSource(
            $this->tempDir . '/with_state.zip',
            $this->moduleManifestArray('blog'),
            ['state.json' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR)],
        );

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/state\.json/');

        $this->preparer->prepare($zipPath);
    }

    #[Test]
    public function prepareThrowsOnUnsupportedSourceType(): void
    {
        $tarPath = $this->tempDir . '/module.tar.gz';
        file_put_contents($tarPath, 'fake tar');

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/Unsupported/');

        $this->preparer->prepare($tarPath);
    }

    #[Test]
    public function preparedSourceCleanupRemovesTemporaryDirectory(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);
        $tempRoot = $prepared->temporaryRoot;
        $this->assertNotNull($tempRoot);
        $this->assertDirectoryExists($tempRoot);

        $this->preparer->cleanup($prepared);

        $this->assertDirectoryDoesNotExist($tempRoot);
    }

    #[Test]
    public function preparedSourceCleanupIsIdempotent(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);
        $this->preparer->cleanup($prepared);
        $this->preparer->cleanup($prepared);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function prepareFromZipPreservesPrimaryExceptionWhenCleanupFails(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $primaryException = ModuleSourceException::forPath($zipPath, 'primary validation failure.');

        $validator = $this->createMock(ManifestValidatorInterface::class);
        $validator->method('validate')->willThrowException($primaryException);

        // deleteDirectory still removes the temp dir (no leak) but then throws,
        // simulating a cleanup double-fault that must not mask the primary error.
        $cleanupFails = new LocalFilesystem(new class extends Filesystem {
            public function deleteDirectory($directory, $preserve = false): void
            {
                parent::deleteDirectory($directory, $preserve);

                throw new RuntimeException('cleanup failure must not surface.');
            }
        });

        $preparer = new ModuleSourcePreparer(
            documentReader: new ManifestDocumentReader(),
            validator: $validator,
            zipExtractor: new ZipExtractor(new LocalFilesystem(new Filesystem())),
            filesystem: $cleanupFails,
        );

        try {
            $preparer->prepare($zipPath);
            $this->fail('Expected the primary validation exception to propagate.');
        } catch (Throwable $thrown) {
            $this->assertSame($primaryException, $thrown);
        }
    }

    private function createModuleZip(string $name): string
    {
        return $this->zipModuleSource(
            $this->tempDir . '/' . $name . '.zip',
            $this->moduleManifestArray($name),
        );
    }
}
