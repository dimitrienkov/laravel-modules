<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleStateRepositoryTest extends TestCase
{
    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    private ModuleStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/state_repo_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/state';
        $this->filesystem->makeDirectory($this->stateRoot, 0755, true);

        $config = new Repository([
            'modules' => ['paths' => ['state' => $this->stateRoot, 'directories' => ['app/Modules']]],
        ]);

        $this->repository = new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new Filesystem(),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function throwsOnScalarSettings(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": "invalid"}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnListSettings(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": ["a", "b"]}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnScalarSettingsValues(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": {"values": 42}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings\.values must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnListSettingsValues(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": {"values": [1, 2, 3]}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings\.values must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnUnknownSettingsKey(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": {"values": {}, "extra": "bad"}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/unknown key \[extra\]/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function acceptsEmptySettings(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": {}}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function acceptsMissingSettings(): void
    {
        $this->writeState('blog', '{"enabled": true}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function acceptsNullSettings(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": null}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
    }

    #[Test]
    public function acceptsNullSettingsValues(): void
    {
        $this->writeState('blog', '{"enabled": true, "settings": {"values": null}}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function deleteChecksResult(): void
    {
        $stateDir = $this->stateRoot . '/blog';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/state.json', '{}');

        $this->repository->delete('blog');

        $this->assertDirectoryDoesNotExist($stateDir);
    }

    private function writeState(string $name, string $json): void
    {
        $dir = $this->stateRoot . '/' . $name;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/state.json', $json);
    }

    private function makeModule(string $name): Module
    {
        return new Module(
            name: $name,
            displayName: ucfirst($name),
            namespace: 'App\\Modules\\' . ucfirst($name),
            path: $this->tempDir . '/app/Modules/' . ucfirst($name),
            meta: new ManifestMeta(
                name: $name,
                displayName: ucfirst($name),
                version: '1.0.0',
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
            ),
            state: ModuleState::disabledDefault(),
            features: new FeatureSchema([]),
        );
    }
}
