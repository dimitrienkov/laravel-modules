<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeAction;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeDto;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeQuery;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeUseCase;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeVo;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ArchitecturalGeneratorTest extends TestCase
{
    use InteractsWithModuleGenerators;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootModuleGeneratorEnvironment();
        $this->registerModuleForGenerators('blog');

        foreach ([MakeUseCase::class, MakeAction::class, MakeQuery::class, MakeDto::class, MakeVo::class] as $command) {
            $this->registerArchitecturalGeneratorCommand($command);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanModuleGeneratorEnvironment();

        parent::tearDown();
    }

    #[Test]
    public function useCaseWithoutModuleFallsBackToHostApplicationLayer(): void
    {
        $this->artisan('make:use-case', ['name' => 'Publish'])
            ->assertSuccessful();

        $file = $this->appPath('Application/UseCases/PublishUseCase.php');
        $this->assertFileExists($file);

        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\\Application\\UseCases;', $contents);
        $this->assertStringContainsString('final readonly class PublishUseCase', $contents);
        $this->assertStringContainsString('declare(strict_types=1);', $contents);
    }

    #[Test]
    public function useCaseWithModuleLandsInsideModuleApplicationLayer(): void
    {
        $this->artisan('make:use-case', ['name' => 'Publish', '--module' => 'blog'])
            ->assertSuccessful();

        $file = $this->modulePath('Application/UseCases/PublishUseCase.php');
        $this->assertFileExists($file);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Blog\\Application\\UseCases;',
            (string) file_get_contents($file),
        );

        $this->assertFileDoesNotExist($this->appPath('Application/UseCases/PublishUseCase.php'));
    }

    #[Test]
    public function suffixIsAppliedAndNotDuplicated(): void
    {
        $this->artisan('make:action', ['name' => 'PublishPost', '--module' => 'blog'])->assertSuccessful();
        $this->assertFileExists($this->modulePath('Application/Actions/PublishPostAction.php'));

        $this->artisan('make:query', ['name' => 'RecentPostsQuery', '--module' => 'blog'])->assertSuccessful();
        $this->assertFileExists($this->modulePath('Application/Queries/RecentPostsQuery.php'));

        $this->artisan('make:dto', ['name' => 'CreatePost', '--module' => 'blog'])->assertSuccessful();
        $dto = $this->modulePath('Application/DTOs/CreatePostDto.php');
        $this->assertFileExists($dto);

        $contents = (string) file_get_contents($dto);
        $this->assertStringContainsString('declare(strict_types=1);', $contents);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Application\\DTOs;', $contents);
        $this->assertStringContainsString('final readonly class CreatePostDto', $contents);
    }

    #[Test]
    public function valueObjectKeepsItsNameWithoutSuffix(): void
    {
        $this->artisan('make:vo', ['name' => 'Money', '--module' => 'blog'])
            ->assertSuccessful();

        $file = $this->modulePath('Domain/VO/Money.php');
        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($this->modulePath('Domain/VO/MoneyVo.php'));

        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Domain\\VO;', $contents);
        $this->assertStringContainsString('final readonly class Money', $contents);
    }

    #[Test]
    public function unknownModuleFailsWithoutWritingFiles(): void
    {
        $this->artisan('make:use-case', ['name' => 'Publish', '--module' => 'ghost'])
            ->assertFailed()
            ->expectsOutputToContain('Module [ghost] was not found');

        $this->assertFileDoesNotExist($this->appPath('Application/UseCases/PublishUseCase.php'));
    }
}
