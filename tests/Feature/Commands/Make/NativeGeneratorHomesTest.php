<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeCast;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeChannel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeConsoleCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeController;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeEvent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeException;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeJob;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeListener;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMiddleware;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeNotification;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeObserver;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakePolicy;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeProvider;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRequest;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeResource;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRule;
use DimitrienkoV\LaravelModules\Tests\Support\InteractsWithModuleGenerators;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * One smoke per namespace-only command home: each generator places its class in
 * the expected module sub-namespace. Model/factory/seeder/migration/component/mail
 * are covered with their richer scenarios in {@see ModuleAwareNativeGeneratorTest}.
 */
#[Group('feature')]
final class NativeGeneratorHomesTest extends TestCase
{
    use InteractsWithModuleGenerators;

    private const array COMMANDS = [
        MakeController::class,
        MakeRequest::class,
        MakeResource::class,
        MakeEvent::class,
        MakeListener::class,
        MakeJob::class,
        MakeNotification::class,
        MakeObserver::class,
        MakeMiddleware::class,
        MakeConsoleCommand::class,
        MakeRule::class,
        MakeCast::class,
        MakeChannel::class,
        MakeProvider::class,
        MakeException::class,
        MakePolicy::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootModuleGeneratorEnvironment();
        $this->registerModuleForGenerators('blog');

        foreach (self::COMMANDS as $command) {
            $this->registerGeneratorCommand($command);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanModuleGeneratorEnvironment();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('homes')]
    public function generatorPlacesClassInItsModuleHome(string $command, string $name, string $relativePath, string $namespace): void
    {
        $this->artisan($command, ['name' => $name, '--module' => 'blog'])
            ->assertSuccessful();

        $file = $this->modulePath($relativePath);
        $this->assertFileExists($file);
        $this->assertStringContainsString("namespace {$namespace};", (string) file_get_contents($file));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function homes(): array
    {
        return [
            'controller' => ['make:controller', 'PostController', 'Http/Controllers/PostController.php', 'App\\Modules\\Blog\\Http\\Controllers'],
            'request' => ['make:request', 'StorePostRequest', 'Http/Requests/StorePostRequest.php', 'App\\Modules\\Blog\\Http\\Requests'],
            'resource' => ['make:resource', 'PostResource', 'Http/Resources/PostResource.php', 'App\\Modules\\Blog\\Http\\Resources'],
            'event' => ['make:event', 'PostPublished', 'Domain/Events/PostPublished.php', 'App\\Modules\\Blog\\Domain\\Events'],
            'listener' => ['make:listener', 'SendDigest', 'Domain/Listeners/SendDigest.php', 'App\\Modules\\Blog\\Domain\\Listeners'],
            'job' => ['make:job', 'ProcessPost', 'Jobs/ProcessPost.php', 'App\\Modules\\Blog\\Jobs'],
            'notification' => ['make:notification', 'PostApproved', 'Notifications/PostApproved.php', 'App\\Modules\\Blog\\Notifications'],
            'observer' => ['make:observer', 'PostObserver', 'Domain/Observers/PostObserver.php', 'App\\Modules\\Blog\\Domain\\Observers'],
            'middleware' => ['make:middleware', 'EnsureAuthor', 'Http/Middleware/EnsureAuthor.php', 'App\\Modules\\Blog\\Http\\Middleware'],
            'command' => ['make:command', 'SyncPosts', 'Console/Commands/SyncPosts.php', 'App\\Modules\\Blog\\Console\\Commands'],
            'rule' => ['make:rule', 'Slug', 'Rules/Slug.php', 'App\\Modules\\Blog\\Rules'],
            'cast' => ['make:cast', 'MoneyCast', 'Casts/MoneyCast.php', 'App\\Modules\\Blog\\Casts'],
            'channel' => ['make:channel', 'PostChannel', 'Broadcasting/PostChannel.php', 'App\\Modules\\Blog\\Broadcasting'],
            'provider' => ['make:provider', 'BlogServiceProvider', 'Providers/BlogServiceProvider.php', 'App\\Modules\\Blog\\Providers'],
            'exception' => ['make:exception', 'PostException', 'Exceptions/PostException.php', 'App\\Modules\\Blog\\Exceptions'],
            'policy' => ['make:policy', 'PostPolicy', 'Domain/Policies/PostPolicy.php', 'App\\Modules\\Blog\\Domain\\Policies'],
        ];
    }
}
