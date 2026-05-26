<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreatesModuleFilesTest extends TestCase
{
    use CreatesModuleFiles;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/creates_module_files_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function writeModuleManifestCreatesDirectoryAndJson(): void
    {
        $path = $this->writeModuleManifest($this->tempDir, 'blog');

        self::assertSame($this->tempDir . '/Blog', $path);
        self::assertFileExists($path . '/module.json');

        $manifest = json_decode((string) file_get_contents($path . '/module.json'), true);
        self::assertSame('blog', $manifest['meta']['name']);
        self::assertSame('Blog', $manifest['meta']['display_name']);
        self::assertSame('1.0.0', $manifest['meta']['version']);
        self::assertArrayHasKey('settings', $manifest);
    }

    #[Test]
    public function writeModuleManifestIncludesDependenciesWhenProvided(): void
    {
        $path = $this->writeModuleManifest($this->tempDir, 'blog', dependencies: ['users' => '^1.0']);

        $manifest = json_decode((string) file_get_contents($path . '/module.json'), true);
        self::assertSame(['users' => '^1.0'], $manifest['meta']['dependencies']);
    }

    #[Test]
    public function writeModuleManifestOmitsDependenciesWhenEmpty(): void
    {
        $path = $this->writeModuleManifest($this->tempDir, 'blog');

        $manifest = json_decode((string) file_get_contents($path . '/module.json'), true);
        self::assertArrayNotHasKey('dependencies', $manifest['meta']);
    }

    #[Test]
    public function writeModuleManifestAcceptsCustomSchema(): void
    {
        $schema = ['enable_comments' => ['type' => 'bool', 'default' => true]];
        $path = $this->writeModuleManifest($this->tempDir, 'blog', schema: $schema);

        $manifest = json_decode((string) file_get_contents($path . '/module.json'), true);
        self::assertSame($schema, $manifest['settings']['schema']);
    }

    #[Test]
    public function writeModuleStateCreatesStateJson(): void
    {
        $this->writeModuleState($this->tempDir, 'blog');

        $statePath = $this->tempDir . '/blog/state.json';
        self::assertFileExists($statePath);

        $state = json_decode((string) file_get_contents($statePath), true);
        self::assertTrue($state['enabled']);
        self::assertArrayNotHasKey('installed_at', $state);
        self::assertArrayNotHasKey('settings', $state);
    }

    #[Test]
    public function writeModuleStateWithDisabledAndValues(): void
    {
        $this->writeModuleState($this->tempDir, 'blog', enabled: false, values: ['key' => 'val']);

        $state = json_decode(
            (string) file_get_contents($this->tempDir . '/blog/state.json'),
            true,
        );

        self::assertFalse($state['enabled']);
        self::assertSame(['key' => 'val'], $state['settings']['values']);
    }

    #[Test]
    public function writeModuleStateWithInstalledAt(): void
    {
        $this->writeModuleState($this->tempDir, 'blog', installedAt: '2026-05-25T00:00:00+00:00');

        $state = json_decode(
            (string) file_get_contents($this->tempDir . '/blog/state.json'),
            true,
        );

        self::assertSame('2026-05-25T00:00:00+00:00', $state['installed_at']);
    }

    #[Test]
    public function writeModuleStateWithEmptyValuesObject(): void
    {
        $this->writeModuleState($this->tempDir, 'blog', values: new \stdClass());

        $state = json_decode(
            (string) file_get_contents($this->tempDir . '/blog/state.json'),
            true,
        );

        self::assertSame([], $state['settings']['values']);
    }

    #[Test]
    public function readStateFileReturnsParsedState(): void
    {
        $this->writeModuleState($this->tempDir, 'blog', installedAt: '2026-05-25T00:00:00+00:00', values: ['x' => 1]);

        $state = $this->readStateFile($this->tempDir, 'blog');

        self::assertTrue($state['enabled']);
        self::assertSame('2026-05-25T00:00:00+00:00', $state['installed_at']);
        self::assertSame(['x' => 1], $state['settings']['values']);
    }

    #[Test]
    public function writeModuleStateIsIdempotent(): void
    {
        $this->writeModuleState($this->tempDir, 'blog', enabled: true);
        $this->writeModuleState($this->tempDir, 'blog', enabled: false);

        $state = $this->readStateFile($this->tempDir, 'blog');
        self::assertFalse($state['enabled']);
    }
}
