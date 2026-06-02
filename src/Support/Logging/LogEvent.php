<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support\Logging;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;

/**
 * The diagnostic event taxonomy as one source of truth.
 *
 * Every event name followed a `category.subject.verb` shape that used to live as
 * duplicated string literals in the producer ({@see ModuleLogger}) and in two
 * test files, with no compiler protection against a typo drifting them apart.
 * The fixed-shape names are enum cases; the lifecycle family is dynamic
 * (`lifecycle.{op}.{phase}`) so it is built by {@see self::lifecycle()} from a
 * {@see LifecycleOperation} and a {@see LifecyclePhase} rather than enumerated.
 *
 * Host log parsers depend on these exact strings — treat the `value`s as a
 * published contract.
 */
enum LogEvent: string
{
    case DiscoveryRootMissing = 'discovery.root.missing';
    case DiscoveryRootRejected = 'discovery.root.rejected';
    case DiscoveryModuleFound = 'discovery.module.found';
    case DiscoveryCompleted = 'discovery.completed';
    case CacheHit = 'cache.hit';
    case CacheMiss = 'cache.miss';
    case CacheWritten = 'cache.written';
    case CacheCleared = 'cache.cleared';
    case CacheInvalid = 'cache.invalid';
    case PipelineStarted = 'pipeline.started';
    case PipelineLoaderApplied = 'pipeline.loader.applied';
    case PipelineLoaderSkipped = 'pipeline.loader.skipped';
    case PipelineLoaderFailed = 'pipeline.loader.failed';
    case PipelineFinished = 'pipeline.finished';

    /**
     * The `lifecycle.{op}.{phase}` event name for an operation/phase pair. Kept
     * as a string (not an enum case) because the op axis is open-ended — each of
     * the eight {@see LifecycleOperation} cases combines with five phases.
     */
    public static function lifecycle(LifecycleOperation $operation, LifecyclePhase $phase): string
    {
        return 'lifecycle.' . $operation->value . '.' . $phase->value;
    }
}
