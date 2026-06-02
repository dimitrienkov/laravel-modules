<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
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
        $this->registerGeneratorCommand(MakeModel::class);
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
}
