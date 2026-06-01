<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\VO;

/**
 * Aggregate outcome of one full loader-pipeline run, reported on
 * `pipeline.finished`. Counts are accumulated in pipeline-local variables (the
 * pipeline stays `final readonly`); `durationMs` is the total wall time measured
 * with `hrtime(true)`. All fields are whitelisted scalars, safe to log.
 */
final readonly class PipelineRunSummary
{
    public function __construct(
        public int $modulesEnabled,
        public int $loaders,
        public int $applied,
        public int $skipped,
        public int $failed,
        public float $durationMs,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'modules_enabled' => $this->modulesEnabled,
            'loaders' => $this->loaders,
            'applied' => $this->applied,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'duration_ms' => $this->durationMs,
        ];
    }
}
