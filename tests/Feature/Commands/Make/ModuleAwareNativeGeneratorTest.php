<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeComponent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeFactory;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMail;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeSeeder;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Illuminate\Contracts\Console\Kernel;
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
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\View\\Components;',
            (string) file_get_contents($class),
        );

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
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Mail;',
            (string) file_get_contents($class),
        );

        $this->assertFileExists($this->modulePath('Resources/views/mail/digest.blade.php'));
        $this->assertFileDoesNotExist($this->generatorTempDir . '/resources/views/mail/digest.blade.php');
    }
}
