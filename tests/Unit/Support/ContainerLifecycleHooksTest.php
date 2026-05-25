<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainerLifecycleHooksTest extends TestCase
{
    #[Test]
    public function it_runs_callback_for_future_resolutions(): void
    {
        $app = new Application(sys_get_temp_dir());
        $service = new \stdClass();
        $calls = [];

        (new ContainerLifecycleHooks($app))->callAfterResolving(
            'service',
            static function (object $resolved, ApplicationContract $callbackApp) use (&$calls): void {
                $calls[] = [$resolved, $callbackApp];
            },
        );

        $app->singleton('service', static fn (): \stdClass => $service);
        $app->make('service');

        self::assertSame([[$service, $app]], $calls);
    }

    #[Test]
    public function it_runs_callback_immediately_for_already_resolved_services(): void
    {
        $app = new Application(sys_get_temp_dir());
        $service = new \stdClass();
        $calls = [];

        $app->instance('service', $service);
        $app->make('service');

        (new ContainerLifecycleHooks($app))->callAfterResolving(
            'service',
            static function (object $resolved, ApplicationContract $callbackApp) use (&$calls): void {
                $calls[] = [$resolved, $callbackApp];
            },
        );

        self::assertSame([[$service, $app]], $calls);
    }
}
