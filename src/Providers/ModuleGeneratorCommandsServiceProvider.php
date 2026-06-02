<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeCast;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeChannel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeComponent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeConsoleCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeController;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeEvent;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeException;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeFactory;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeJob;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeListener;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMail;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMiddleware;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeModel;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeNotification;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeObserver;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakePolicy;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeProvider;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRequest;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeResource;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeRule;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeSeeder;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Foundation\Console\CastMakeCommand;
use Illuminate\Foundation\Console\ChannelMakeCommand;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Console\ConsoleMakeCommand;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Foundation\Console\ExceptionMakeCommand;
use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\NotificationMakeCommand;
use Illuminate\Foundation\Console\ObserverMakeCommand;
use Illuminate\Foundation\Console\PolicyMakeCommand;
use Illuminate\Foundation\Console\ProviderMakeCommand;
use Illuminate\Foundation\Console\RequestMakeCommand;
use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Foundation\Console\RuleMakeCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Routing\Console\MiddlewareMakeCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\ServiceProvider;

/**
 * Shadows the native `make:*` generators with their module-aware subclasses so the
 * familiar command names gain a transparent `--module` option without changing
 * their signature or behaviour in host mode.
 *
 * Laravel's (deferred) `ArtisanServiceProvider` binds every generator as a
 * `singleton(<ParentFqcn>::class, …)` and resolves commands from that FQCN. We
 * rebind each parent FQCN to our subclass; re-applying the rebind on
 * `Console\Application::starting` (the concrete console application class, not the
 * `Artisan` facade) guarantees we win regardless of when the deferred provider
 * loads. `make:migration` keeps its special `MigrationCreator`/`Composer`
 * constructor wiring.
 */
final class ModuleGeneratorCommandsServiceProvider extends ServiceProvider
{
    /**
     * Native generator FQCN => module-aware subclass.
     *
     * @var array<class-string, class-string>
     */
    private const array GENERATORS = [
        ModelMakeCommand::class => MakeModel::class,
        ControllerMakeCommand::class => MakeController::class,
        RequestMakeCommand::class => MakeRequest::class,
        ResourceMakeCommand::class => MakeResource::class,
        EventMakeCommand::class => MakeEvent::class,
        ListenerMakeCommand::class => MakeListener::class,
        JobMakeCommand::class => MakeJob::class,
        MailMakeCommand::class => MakeMail::class,
        NotificationMakeCommand::class => MakeNotification::class,
        ObserverMakeCommand::class => MakeObserver::class,
        MiddlewareMakeCommand::class => MakeMiddleware::class,
        ConsoleMakeCommand::class => MakeConsoleCommand::class,
        RuleMakeCommand::class => MakeRule::class,
        CastMakeCommand::class => MakeCast::class,
        ChannelMakeCommand::class => MakeChannel::class,
        ProviderMakeCommand::class => MakeProvider::class,
        ExceptionMakeCommand::class => MakeException::class,
        PolicyMakeCommand::class => MakePolicy::class,
        ComponentMakeCommand::class => MakeComponent::class,
        FactoryMakeCommand::class => MakeFactory::class,
        SeederMakeCommand::class => MakeSeeder::class,
    ];

    public function register(): void
    {
        $this->shadowGenerators();
    }

    public function boot(): void
    {
        ConsoleApplication::starting(function (): void {
            $this->shadowGenerators();
        });
    }

    private function shadowGenerators(): void
    {
        foreach (self::GENERATORS as $native => $moduleAware) {
            $this->app->singleton($native, $moduleAware);
        }

        $this->app->singleton(MigrateMakeCommand::class, static function (Application $app): MakeMigration {
            return new MakeMigration($app->make('migration.creator'), $app->make(Composer::class));
        });
    }
}
