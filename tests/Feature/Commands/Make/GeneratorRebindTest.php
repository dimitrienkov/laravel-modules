<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Providers\ModuleGeneratorCommandsServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

#[Group('feature')]
final class GeneratorRebindTest extends TestCase
{
    use InteractsWithModuleGenerators;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootModuleGeneratorEnvironment();
        $this->registerModuleForGenerators('blog');
    }

    protected function tearDown(): void
    {
        $this->cleanModuleGeneratorEnvironment();

        parent::tearDown();
    }

    #[Test]
    public function rebindsEveryNativeGeneratorFqcnToItsModuleAwareSubclass(): void
    {
        // Boot the console application so Console\Application::starting fires (and
        // the deferred ArtisanServiceProvider loads) — the moment the rebind
        // targets, mirroring how `php artisan make:*` actually resolves commands.
        $this->app->make(Kernel::class)->call('list');

        foreach ($this->shadowedGenerators() as $nativeFqcn => $moduleAwareFqcn) {
            self::assertInstanceOf(
                $moduleAwareFqcn,
                $this->app->make($nativeFqcn),
                "Resolving [{$nativeFqcn}] must yield the module-aware shadow; the rebind silently stopped winning.",
            );
        }
    }

    #[Test]
    public function nativeMakeModelNameResolvesToModuleAwareCommand(): void
    {
        $this->artisan('make:model', ['name' => 'Post', '--module' => 'blog'])
            ->assertSuccessful();

        $this->assertFileExists($this->modulePath('Domain/Models/Post.php'));
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Domain\\Models;',
            (string) file_get_contents($this->modulePath('Domain/Models/Post.php')),
        );
    }

    #[Test]
    public function nativeMakeModelWithoutModuleKeepsHostBehaviour(): void
    {
        mkdir($this->appPath('Models'), 0755, true);

        $this->artisan('make:model', ['name' => 'Widget'])
            ->assertSuccessful();

        $this->assertFileExists($this->appPath('Models/Widget.php'));
        $this->assertStringContainsString(
            'namespace App\\Models;',
            (string) file_get_contents($this->appPath('Models/Widget.php')),
        );
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ModuleGeneratorCommandsServiceProvider::class];
    }

    /**
     * Every native => module-aware pairing the package shadows, sourced from the
     * provider's own GENERATORS constant (so a newly shadowed command is covered
     * automatically and can never silently drift), plus make:migration, which is
     * rebound outside that table through its own constructor wiring.
     *
     * @return array<class-string, class-string>
     */
    private function shadowedGenerators(): array
    {
        /** @var array<class-string, class-string> $generators */
        $generators = (new ReflectionClass(ModuleGeneratorCommandsServiceProvider::class))->getConstant('GENERATORS');

        $generators[MigrateMakeCommand::class] = MakeMigration::class;

        return $generators;
    }
}
