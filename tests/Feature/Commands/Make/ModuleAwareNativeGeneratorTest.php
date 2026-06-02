<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeComponent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeController;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeFactory;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeListener;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMail;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRequest;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeSeeder;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ListenerMakeCommand;
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
        $this->registerGeneratorCommand(MakeListener::class);

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
    public function factoryPinsEveryModuleTokenAgainstNativeDrift(): void
    {
        // MakeFactory::buildClass reimplements the parent's token substitution:
        // the parent derives {{ factoryNamespace }} from the host root namespace
        // via replaceFirst, which double-mangles once getDefaultNamespace points
        // at the module. This pins the full token set so a future native stub or
        // token-name change can't silently drift the generated factory.
        $this->artisan('make:factory', [
            'name' => 'PostFactory',
            '--module' => 'blog',
            '--model' => 'Post',
        ])->assertSuccessful();

        $contents = (string) file_get_contents($this->modulePath('Database/Factories/PostFactory.php'));

        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Database\\Factories;', $contents);
        $this->assertStringContainsString('use App\\Modules\\Blog\\Domain\\Models\\Post;', $contents);
        $this->assertStringContainsString('@extends Factory<Post>', $contents);
        $this->assertStringContainsString('class PostFactory extends Factory', $contents);

        // No placeholder token or host-namespace residue survived substitution.
        $this->assertStringNotContainsString('{{', $contents);
        $this->assertStringNotContainsString('Dummy', $contents);
        $this->assertStringNotContainsString('namespace Database\\Factories;', $contents);
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
    public function migrationWithModuleAndExplicitPathFailsWithoutWriting(): void
    {
        // --module already pins the migration to the module's directory, so an
        // explicit --path is a conflicting instruction; it must fail loudly
        // rather than silently overwrite the user's --path.
        $this->artisan('make:migration', [
            'name' => 'create_posts_table',
            '--module' => 'blog',
            '--path' => 'foo',
        ])
            ->assertFailed()
            ->expectsOutputToContain('--path option cannot be combined with --module');

        $migrations = glob($this->modulePath('Database/Migrations') . '/*_create_posts_table.php') ?: [];
        $this->assertCount(0, $migrations);
        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/foo');
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

        // The host reference must be fully repointed, not just shadowed: if a
        // future Laravel renamed the stub's view literal the str_replace would
        // become a silent no-op, and this negative side would catch it.
        $this->assertStringNotContainsString("view('components.alert')", $classContents);

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

        // Both sides of the repoint are pinned: the host reference is gone, so a
        // changed Laravel stub literal turning the replace into a no-op fails here
        // instead of silently shipping a host-resolving mailable.
        $this->assertStringNotContainsString("markdown: 'mail.digest'", $classContents);

        $this->assertFileExists($this->modulePath('Resources/views/mail/digest.blade.php'));
        $this->assertFileDoesNotExist($this->generatorTempDir . '/resources/views/mail/digest.blade.php');
    }

    #[Test]
    public function listenerWithModuleEventReferencesTheModuleEvent(): void
    {
        $this->artisan('make:listener', [
            'name' => 'PaymentListener',
            '--event' => 'PaymentReceived',
            '--module' => 'blog',
        ])->assertSuccessful();

        $file = $this->modulePath('Domain/Listeners/PaymentListener.php');
        $this->assertFileExists($file);
        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Domain\\Listeners;', $contents);

        // The bare --event is auto-qualified by the parent against the host
        // App\Events namespace; module mode must repoint that import (and the
        // type-hint resolves to it) at the module's own Domain\Events.
        $this->assertStringContainsString('use App\\Modules\\Blog\\Domain\\Events\\PaymentReceived;', $contents);
        $this->assertStringContainsString('handle(PaymentReceived $event)', $contents);

        // The host event import must never leak into a module listener.
        $this->assertStringNotContainsString('use App\\Events\\PaymentReceived;', $contents);
    }

    #[Test]
    public function listenerWithFullyQualifiedEventIsNotRepointed(): void
    {
        // An --event already rooted in the app namespace is one the parent leaves
        // untouched (no auto-qualification), so we must leave it untouched too —
        // the developer asked for that exact event, module mode or not.
        $this->artisan('make:listener', [
            'name' => 'AuditListener',
            '--event' => 'App\\Domain\\Custom\\ThingHappened',
            '--module' => 'blog',
        ])->assertSuccessful();

        $file = $this->modulePath('Domain/Listeners/AuditListener.php');
        $this->assertFileExists($file);
        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString('use App\\Domain\\Custom\\ThingHappened;', $contents);
        $this->assertStringNotContainsString('App\\Modules\\Blog\\Domain\\Events\\ThingHappened', $contents);
    }

    #[Test]
    public function hostModeMakeListenerIsByteForByteIdenticalToNative(): void
    {
        // The buildClass override must be inert without --module: a host-mode
        // listener (the path most likely to drift) has to match the parent
        // byte-for-byte, event repoint included.
        $this->artisan('make:listener', ['name' => 'PaymentListener', '--event' => 'PaymentReceived'])
            ->assertSuccessful();
        $shadowed = (string) file_get_contents($this->appPath('Listeners/PaymentListener.php'));
        unlink($this->appPath('Listeners/PaymentListener.php'));

        $this->app->make(Kernel::class)->registerCommand(new ListenerMakeCommand(new Filesystem()));
        $this->artisan('make:listener', ['name' => 'PaymentListener', '--event' => 'PaymentReceived'])
            ->assertSuccessful();
        $native = (string) file_get_contents($this->appPath('Listeners/PaymentListener.php'));

        self::assertSame($native, $shadowed);
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

    #[Test]
    public function pathOverridingGeneratorsResolveModuleCaseInsensitively(): void
    {
        // make:model's case-insensitivity is covered above; the path-overriding
        // generators (their own getPath / --path remap) must normalise the name
        // through the same resolver, so `Blog` still lands in the `blog` module.
        $this->artisan('make:factory', ['name' => 'PostFactory', '--module' => 'Blog', '--model' => 'Post'])
            ->assertSuccessful();
        $this->assertFileExists($this->modulePath('Database/Factories/PostFactory.php'));

        $this->artisan('make:migration', ['name' => 'create_tags_table', '--module' => 'Blog'])
            ->assertSuccessful();
        $migrations = glob($this->modulePath('Database/Migrations') . '/*_create_tags_table.php') ?: [];
        $this->assertCount(1, $migrations);
    }

    #[Test]
    public function matchingTestOptionsAreRejectedAcrossGeneratorsAndFlags(): void
    {
        // Every matching-test flag, on a representative spread of generators,
        // must fail before any artifact is written and never spill a host test.
        $this->artisan('make:model', ['name' => 'Post', '--module' => 'blog', '--pest' => true])
            ->assertFailed()
            ->expectsOutputToContain('do not create matching tests');
        $this->assertFileDoesNotExist($this->modulePath('Domain/Models/Post.php'));

        $this->artisan('make:component', ['name' => 'Alert', '--module' => 'blog', '--phpunit' => true])
            ->assertFailed()
            ->expectsOutputToContain('do not create matching tests');
        $this->assertFileDoesNotExist($this->modulePath('View/Components/Alert.php'));

        $this->artisan('make:mail', ['name' => 'Digest', '--module' => 'blog', '--test' => true])
            ->assertFailed()
            ->expectsOutputToContain('do not create matching tests');
        $this->assertFileDoesNotExist($this->modulePath('Mail/Digest.php'));

        $this->assertDirectoryDoesNotExist($this->generatorTempDir . '/tests');
    }

    #[Test]
    public function repeatedGeneratorsIntoTheSameModuleDirectorySucceed(): void
    {
        // makeDirectory must be idempotent: generating a second factory into an
        // already-created module sub-directory must not fail on the existing dir.
        $this->artisan('make:factory', ['name' => 'PostFactory', '--module' => 'blog', '--model' => 'Post'])
            ->assertSuccessful();
        $this->artisan('make:factory', ['name' => 'CommentFactory', '--module' => 'blog', '--model' => 'Comment'])
            ->assertSuccessful();

        $this->assertFileExists($this->modulePath('Database/Factories/PostFactory.php'));
        $this->assertFileExists($this->modulePath('Database/Factories/CommentFactory.php'));
    }
}
