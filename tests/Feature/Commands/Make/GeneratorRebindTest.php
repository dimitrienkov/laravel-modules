<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeComponent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeController;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeFactory;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMail;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeSeeder;
use DimitrienkoV\LaravelModules\Providers\ModuleGeneratorCommandsServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

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
    public function rebindsNativeGeneratorFqcnsToModuleAwareSubclasses(): void
    {
        // Boot the console application so Console\Application::starting fires (and
        // the deferred ArtisanServiceProvider loads) — the moment the rebind
        // targets, mirroring how `php artisan make:*` actually resolves commands.
        $this->app->make(Kernel::class)->call('list');

        self::assertInstanceOf(MakeModel::class, $this->app->make(ModelMakeCommand::class));
        self::assertInstanceOf(MakeFactory::class, $this->app->make(FactoryMakeCommand::class));
        self::assertInstanceOf(MakeSeeder::class, $this->app->make(SeederMakeCommand::class));
        self::assertInstanceOf(MakeMigration::class, $this->app->make(MigrateMakeCommand::class));
        self::assertInstanceOf(MakeController::class, $this->app->make(ControllerMakeCommand::class));
        self::assertInstanceOf(MakeComponent::class, $this->app->make(ComponentMakeCommand::class));
        self::assertInstanceOf(MakeMail::class, $this->app->make(MailMakeCommand::class));
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
}
