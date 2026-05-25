<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\Pipeline;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;

final readonly class ModuleLoaderPipeline
{
    /**
     * @param iterable<LoaderInterface> $loaders
     */
    public function __construct(
        private ModuleRegistryInterface $registry,
        private iterable $loaders,
        private ExceptionHandler $exceptionHandler,
    ) {
    }

    public function boot(): void
    {
        $sorted = $this->sortedLoaders();
        $modules = $this->registry->loadOrder();

        foreach ($sorted as $loader) {
            foreach ($modules as $module) {
                if (! $module->isEnabled()) {
                    continue;
                }

                try {
                    $loader->load($module);
                } catch (\Throwable $exception) {
                    $this->exceptionHandler->report($exception);
                }
            }
        }
    }

    /**
     * @return array<int, LoaderInterface>
     */
    private function sortedLoaders(): array
    {
        $loaders = [];
        foreach ($this->loaders as $loader) {
            $loaders[] = $loader;
        }

        usort(
            $loaders,
            static fn (LoaderInterface $left, LoaderInterface $right): int => $left->priority() <=> $right->priority(),
        );

        return $loaders;
    }
}
