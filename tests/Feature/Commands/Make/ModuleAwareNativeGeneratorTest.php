<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeComponent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeController;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeFactory;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMail;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRequest;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeSeeder;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ModuleAwareNativeGeneratorTest extends TestCase
{
    use InteractsWithModuleGenerators;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootModuleGeneratorEnvironment();
        $this->registerModuleForGenerators('blog');

        // Register the sub-generators too, so make:model's internal $this->call()
        // resolves to the module-aware versions exactly as the rebind provider
        // (task #8) will wire it at runtime.
        $this->registerGeneratorCommand(MakeModel::class);
        $this->registerGeneratorCommand(MakeFactory::class);
        $this->registerGeneratorCommand(MakeSeeder::class);
        $this->registerGeneratorCommand(MakeComponent::class);
        $this->registerGeneratorCommand(MakeMail::class);
        $this->registerGeneratorCommand(MakeController::class);
        $this->registerGeneratorCommand(MakeRequest::class);

        // make:migration's parent needs the framework's MigrationCreator (which
        // carries a stub path) and Composer — the same deps the rebind provider
        // (task #8) supplies. Auto-resolution can't build MigrationCreator alone.
        $this->app->make(Kernel::class)->registerCommand(
            new MakeMigration($this->app->make('migration.creator'), $this->app->make('composer')),
        );
    }

    protected function tearDown(): void
    {
        $this->cleanModuleGeneratorEnvironment();

        parent::tearDown();
    }

    #[Test]
    public function modelWithModuleLandsInsideModuleNamespace(): void
    {
        $this->artisan('make:model', ['name' => 'Post', '--module' => 'blog'])
            ->assertSuccessful();

        $file = $this->modulePath('Domain/Models/Post.php');
        $this->assertFileExists($file);

        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Domain\\Models;', $contents);
        $this->assertStringContainsString('class Post', $contents);
    }

    #[Test]
    public function moduleNameIsResolvedCaseInsensitively(): void
    {
        $this->artisan('make:model', ['name' => 'Tag', '--module' => 'Blog'])
            ->assertSuccessful();

        $this->assertFileExists($this->modulePath('Domain/Models/Tag.php'));
    }

    #[Test]
    public function modelWithoutModuleStaysInHostNamespace(): void
    {
        mkdir($this->appPath('Models'), 0755, true);

        $this->artisan('make:model', ['name' => 'Widget'])
            ->assertSuccessful();

        $file = $this->appPath('Models/Widget.php');
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace App\\Models;', (string) file_get_contents($file));

        $this->assertFileDoesNotExist($this->modulePath('Domain/Models/Widget.php'));
    }

    #[Test]
    public function unknownModuleFailsWithoutWritingFiles(): void
    {
        $this->artisan('make:model', ['name' => 'Ghost', '--module' => 'nope'])
            ->assertFailed()
            ->expectsOutputToContain('Module [nope] was not found');

        $this->assertFileDoesNotExist($this->modulePath('Domain/Models/Ghost.php', 'nope'));
        $this->assertFileDoesNotExist($this->appPath('Models/Ghost.php'));
    }

    #[Test]
    public function modelWithMfsCreatesEveryArtifactInsideTheModule(): void
    {
        $this->artisan('make:model', [
            'name' => 'Post',
            '--module' => 'blog',
            '--migration' => true,
            '--factory' => true,
            '--seed' => true,
        ])->assertSuccessful();

        // Model references the module factory namespace, not the host one.
        $model = (string) file_get_contents($this->modulePath('Domain/Models/Post.php'));
        $this->assertStringContainsString(
            '@use HasFactory<\\App\\Modules\\Blog\\Database\\Factories\\PostFactory>',
            $model,
        );

        // Factory inside the module, pointing at the module model.
        $factory = $this->modulePath('Database/Factories/PostFactory.php');
        $this->assertFileExists($factory);
        $factoryContents = (string) file_get_contents($factory);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Database\\Factories;', $factoryContents);
        $this->assertStringContainsString('use App\\Modules\\Blog\\Domain\\Models\\Post;', $factoryContents);

        // Seeder inside the module.
        $seeder = $this->modulePath('Database/Seeders/PostSeeder.php');
        $this->assertFileExists($seeder);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Database\\Seeders;',
            (string) file_get_contents($seeder),
        );

        // Migration inside the module, nothing leaked to the host database path.
        $migrations = glob($this->modulePath('Database/Migrations') . '/*_create_posts_table.php') ?: [];
        $this->assertCount(1, $migrations);
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/database/factories');
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/database/seeders');
    }

    #[Test]
    public function factoryDirectlyTargetsTheModule(): void
    {
        $this->artisan('make:factory', [
            'name' => 'PostFactory',
            '--module' => 'blog',
            '--model' => 'Post',
        ])->assertSuccessful();

        $factory = $this->modulePath('Database/Factories/PostFactory.php');
        $this->assertFileExists($factory);
        $contents = (string) file_get_contents($factory);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Database\\Factories;', $contents);
        $this->assertStringContainsString('use App\\Modules\\Blog\\Domain\\Models\\Post;', $contents);
    }

    #[Test]
    public function seederDirectlyTargetsTheModule(): void
    {
        $this->artisan('make:seeder', ['name' => 'PostSeeder', '--module' => 'blog'])
            ->assertSuccessful();

        $seeder = $this->modulePath('Database/Seeders/PostSeeder.php');
        $this->assertFileExists($seeder);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Database\\Seeders;',
            (string) file_get_contents($seeder),
        );
        $this->assertStringContainsString(
            'class PostSeeder extends Seeder',
            (string) file_get_contents($seeder),
        );
    }

    #[Test]
    public function migrationDirectlyTargetsTheModule(): void
    {
        $this->artisan('make:migration', ['name' => 'create_posts_table', '--module' => 'blog'])
            ->assertSuccessful();

        $migrations = glob($this->modulePath('Database/Migrations') . '/*_create_posts_table.php') ?: [];
        $this->assertCount(1, $migrations);
    }

    #[Test]
    public function componentWritesClassAndViewInsideTheModule(): void
    {
        $this->artisan('make:component', ['name' => 'Alert', '--module' => 'blog'])
            ->assertSuccessful();

        $class = $this->modulePath('View/Components/Alert.php');
        $this->assertFileExists($class);
        $classContents = (string) file_get_contents($class);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\View\\Components;',
            $classContents,
        );

        // The class body must reference the module-namespaced view, otherwise the
        // component resolves against the host views the loader never registered.
        $this->assertStringContainsString("view('blog::components.alert')", $classContents);

        $this->assertFileExists($this->modulePath('Resources/views/components/alert.blade.php'));

        // Nothing leaked into the host view path.
        $this->assertFileDoesNotExist($this->generatorTempDir . '/resources/views/components/alert.blade.php');
    }

    #[Test]
    public function mailWritesMarkdownTemplateInsideTheModule(): void
    {
        $this->artisan('make:mail', ['name' => 'Digest', '--module' => 'blog', '--markdown' => 'mail.digest'])
            ->assertSuccessful();

        $class = $this->modulePath('Mail/Digest.php');
        $this->assertFileExists($class);
        $classContents = (string) file_get_contents($class);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Mail;',
            $classContents,
        );

        // The mailable must reference the module-namespaced markdown view; the
        // Blade path itself stays on the clean relative view (no `::` leak).
        $this->assertStringContainsString("markdown: 'blog::mail.digest'", $classContents);

        $this->assertFileExists($this->modulePath('Resources/views/mail/digest.blade.php'));
        $this->assertFileDoesNotExist($this->generatorTempDir . '/resources/views/mail/digest.blade.php');
    }

    #[Test]
    public function matchingTestOptionIsRejectedInModuleMode(): void
    {
        $this->artisan('make:model', ['name' => 'Post', '--module' => 'blog', '--test' => true])
            ->assertFailed()
            ->expectsOutputToContain('do not create matching tests');

        $this->assertFileDoesNotExist($this->modulePath('Domain/Models/Post.php'));
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/tests');
    }

    #[Test]
    public function controllerWithRequestsKeepsControllerAndRequestsInModule(): void
    {
        $this->artisan('make:controller', [
            'name' => 'PostController',
            '--module' => 'blog',
            '--model' => 'Post',
            '--requests' => true,
        ])
            ->expectsConfirmation(
                'A App\\Modules\\Blog\\Domain\\Models\\Post model does not exist. Do you want to generate it?',
                'yes',
            )
            ->assertSuccessful();

        // Controller, form requests and the generated model all live in the module.
        $controller = $this->modulePath('Http/Controllers/PostController.php');
        $this->assertFileExists($controller);
        $contents = (string) file_get_contents($controller);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Http\\Controllers;', $contents);
        $this->assertStringContainsString('use App\\Modules\\Blog\\Http\\Requests\\StorePostRequest;', $contents);

        $this->assertFileExists($this->modulePath('Http/Requests/StorePostRequest.php'));
        $this->assertFileExists($this->modulePath('Http/Requests/UpdatePostRequest.php'));
        $this->assertFileExists($this->modulePath('Domain/Models/Post.php'));

        // Nothing leaked into the host.
        $this->assertFileDoesNotExist($this->appPath('Http/Requests/StorePostRequest.php'));
    }

    #[Test]
    public function unknownModuleFailsWithoutWritingFilesForPathOverridingGenerators(): void
    {
        // make:model already guards this; the path-overriding generators (their
        // host leak is the more dangerous one) must fail just as cleanly.
        $this->artisan('make:factory', ['name' => 'PostFactory', '--module' => 'ghost'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        $this->artisan('make:seeder', ['name' => 'PostSeeder', '--module' => 'ghost'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        $this->artisan('make:component', ['name' => 'Alert', '--module' => 'ghost'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        $this->artisan('make:mail', ['name' => 'Digest', '--module' => 'ghost', '--markdown' => 'mail.digest'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        $this->artisan('make:migration', ['name' => 'create_ghosts_table', '--module' => 'ghost'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        // No artifact reached the (non-existent) ghost module …
        $this->assertDirectoryDoesNotExist($this->modulePath('', 'ghost'));

        // … and nothing leaked into the host either.
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/database/factories');
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/database/seeders');
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/database/migrations');
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/resources/views');
        $this->assertFileDoesNotExist($this->appPath('Mail/Digest.php'));
        $this->assertFileDoesNotExist($this->appPath('View/Components/Alert.php'));
    }

    #[Test]
    public function hostModeMakeModelIsByteForByteIdenticalToNative(): void
    {
        mkdir($this->appPath('Models'), 0755, true);

        // The shadow in host mode (no --module) must delegate to the parent
        // byte-for-byte. Generate, capture, and remove so the native run can
        // reuse the exact same destination path.
        $this->artisan('make:model', ['name' => 'Widget'])->assertSuccessful();
        $shadowed = (string) file_get_contents($this->appPath('Models/Widget.php'));
        unlink($this->appPath('Models/Widget.php'));

        // Re-register the genuine native command under make:model so the second
        // run resolves to it instead of the shadow, then compare full contents.
        $this->app->make(Kernel::class)->registerCommand(new ModelMakeCommand(new Filesystem()));
        $this->artisan('make:model', ['name' => 'Widget'])->assertSuccessful();
        $native = (string) file_get_contents($this->appPath('Models/Widget.php'));

        self::assertSame($native, $shadowed);
    }

    #[Test]
    public function nestedGeneratorsNeverLeakHostTestFiles(): void
    {
        // Top-level matching-test options are rejected outright (see
        // matchingTestOptionIsRejectedInModuleMode). This guards the complementary
        // path: the trait's call() override strips --test/--pest/--phpunit from
        // every nested sub-generator, so a module's -mfs scaffolding can never
        // spill a host tests/ file.
        $this->artisan('make:model', [
            'name' => 'Post',
            '--module' => 'blog',
            '--migration' => true,
            '--factory' => true,
            '--seed' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->modulePath('Domain/Models/Post.php'));
        $this->assertFileExists($this->modulePath('Database/Factories/PostFactory.php'));
        $this->assertFileExists($this->modulePath('Database/Seeders/PostSeeder.php'));
        $migrations = glob($this->modulePath('Database/Migrations') . '/*_create_posts_table.php') ?: [];
        $this->assertCount(1, $migrations);

        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/tests');
    }
}
