<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\Pipeline;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleLoaderException;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
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
        private ModuleDiagnosticsInterface $diagnostics,
    ) {
    }

    public function boot(): void
    {
        $startedAt = hrtime(true);

        $sorted = $this->sortedLoaders();
        $modules = $this->registry->all();
        $enabledCount = \count(array_filter($modules, static fn (Module $module): bool => $module->isEnabled()));
        $loaderCount = \count($sorted);

        $this->diagnostics->pipelineStarted($enabledCount, $loaderCount);

        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($sorted as $loader) {
            foreach ($modules as $module) {
                if (! $module->isEnabled()) {
                    continue;
                }

                try {
                    $report = $loader->load($module);
                    $this->diagnostics->loaderCompleted($module, $loader, $report);

                    if ($report->wasApplied()) {
                        $applied++;
                    } elseif ($report->wasSkipped()) {
                        $skipped++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    // Diagnostics are emitted in addition to — never instead of —
                    // the host exception handler, which still receives the full
                    // wrapped exception for its error tracker.
                    $this->diagnostics->loaderFailed($module, $loader, $exception);
                    $this->exceptionHandler->report(
                        ModuleLoaderException::forLoaderFailure($loader, $module, $exception),
                    );
                }
            }
        }

        $this->diagnostics->pipelineFinished(new PipelineRunSummary(
            modulesEnabled: $enabledCount,
            loaders: $loaderCount,
            applied: $applied,
            skipped: $skipped,
            failed: $failed,
            durationMs: (hrtime(true) - $startedAt) / 1_000_000,
        ));
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
