<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\VO;

/**
 * Why a loader skipped a module — the diagnostic answer to "why did this module
 * NOT load X?". Each case maps to a concrete early-return precondition audited
 * across the 15 default loaders:
 *
 * - NoDirectory: the convention directory is absent (most loaders).
 * - EmptyDirectory: the directory exists but holds no matching files. Only
 *   loaders that enumerate the directory's contents themselves report this —
 *   Config/Middleware/Observer/Policy and RouteLoader. Loaders that register a
 *   directory or namespace with Laravel without listing it
 *   (Migration/Event/Command/Factory/BladeComponent) never do.
 * - FileNotFound: a single expected file is absent (BroadcastLoader channels,
 *   ConsoleRouteLoader console routes).
 * - RoutesCached: the host cached its routes, so RouteLoader stands down.
 * - NotRunningInConsole: a console-only loader runs outside the CLI
 *   (CommandLoader, ConsoleRouteLoader).
 */
enum SkipReason: string
{
    case NoDirectory = 'no_directory';
    case EmptyDirectory = 'empty_directory';
    case FileNotFound = 'file_not_found';
    case RoutesCached = 'routes_cached';
    case NotRunningInConsole = 'not_running_in_console';
}
