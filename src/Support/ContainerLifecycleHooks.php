<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use Closure;
use Illuminate\Contracts\Foundation\Application;

final readonly class ContainerLifecycleHooks
{
    public function __construct(
        private Application $app,
    ) {
    }

    public function callAfterResolving(string $abstract, Closure $callback): void
    {
        $this->app->afterResolving($abstract, $callback);

        if ($this->app->resolved($abstract)) {
            $callback($this->app->make($abstract), $this->app);
        }
    }
}
