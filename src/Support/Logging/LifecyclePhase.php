<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support\Logging;

/**
 * The phase segment of a `lifecycle.{op}.{phase}` event name.
 *
 * `started`/`succeeded`/`failed` are the three terminal-bracket phases every
 * lifecycle path emits; `rolled_back` and `backup_created` are the intermediate
 * markers on compensating paths. Kept here next to {@see LogEvent::lifecycle()},
 * the single place these segments are turned into an event string.
 */
enum LifecyclePhase: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case BackupCreated = 'backup_created';
}
