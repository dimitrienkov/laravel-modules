<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ModuleRegistrySnapshotBuilder::class)]
#[Group('registry')]
final class ModuleRegistrySnapshotBuilderTest extends TestCase
{
    use CreatesModuleFiles;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-builder-' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function scansDirectoriesAndReturnsSortedSnapshot(): void
    {
        $this->writeModule('users', '1.2.0');
        $this->writeModule('blog', '1.0.0', ['users' => '^1.0']);

        $builder = $this->builder();
        $snapshot = $builder->build();

        self::assertSame(2, $snapshot->count());
        self::assertSame(['users', 'blog'], array_map(
            static fn($m): string => $m->name,
            $snapshot->all(),
        ));
    }

    #[Test]
    public function returnsEmptySnapshotForNoModules(): void
    {
        $builder = $this->builder();
        $snapshot = $builder->build();

        self::assertSame(0, $snapshot->count());
        self::assertSame([], $snapshot->all());
    }

    #[Test]
    public function ignoresDirectoriesWithoutManifest(): void
    {
        mkdir($this->tempDir . '/app/Modules/Empty', 0755, true);
        $this->writeModule('blog', '1.0.0');

        $builder = $this->builder();
        $snapshot = $builder->build();

        self::assertSame(1, $snapshot->count());
        self::assertSame('blog', $snapshot->find('blog')->name);
    }

    #[Test]
    public function reportsDiscoveredModulesAndCompletionToDiagnostics(): void
    {
        $this->writeModule('users', '1.2.0');
        $this->writeModule('blog', '1.0.0', ['users' => '^1.0']);

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $this->builder($diagnostics)->build();

        $diagnostics->shouldHaveReceived('discoveryModuleFound')->twice();
        $diagnostics->shouldHaveReceived('discoveryCompleted')->once()->with(2, 2, 0);
    }

    private function builder(?ModuleDiagnosticsInterface $diagnostics = null): ModuleRegistrySnapshotBuilder
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $stateRepo = new ModuleStateRepository(
            paths: new ModuleStatePaths(
                configuredStateRoot: $this->stateRoot,
                basePath: $this->tempDir,
            ),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );

        return new ModuleRegistrySnapshotBuilder(
            scanner: new ModuleDirectoryScanner(
                directories: ['app/Modules'],
                filesystem: new LocalFilesystem(new Filesystem()),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new FakeNamespaceResolver($this->tempDir),
                documentReader: new ManifestDocumentReader(),
                stateRepository: $stateRepo,
                filesystem: new LocalFilesystem(new Filesystem()),
            ),
            sorter: new TopologicalSorter(),
            diagnostics: $diagnostics ?? new NullModuleDiagnostics(),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function writeModule(string $name, string $version, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, $version, $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, values: new stdClass());
    }

}
