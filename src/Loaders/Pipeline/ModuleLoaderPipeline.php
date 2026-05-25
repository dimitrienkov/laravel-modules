<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\Pipeline;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleLoaderException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

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
                } catch (Throwable $exception) {
                    $this->exceptionHandler->report(
                        ModuleLoaderException::forLoaderFailure($loader, $module, $exception),
                    );
                }
            }
        }
    }

    /**
     * @return array<int, LoaderInterface>
     */
    private function sortedLoaders(): array
    {
        $indexed = [];
        $position = 0;
        foreach ($this->loaders as $loader) {
            $indexed[] = ['loader' => $loader, 'position' => $position++];
        }

        usort(
            $indexed,
            static fn (array $left, array $right): int => $left['loader']->priority() <=> $right['loader']->priority()
                ?: $left['position'] <=> $right['position'],
        );

        return array_column($indexed, 'loader');
    }
}
