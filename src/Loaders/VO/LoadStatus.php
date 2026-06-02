<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\VO;

/**
 * Outcome of applying a single loader to a single module.
 *
 * Exactly two terminal states: the loader either contributed something
 * (`Applied`) or short-circuited because a precondition was absent (`Skipped`).
 * A thrown exception is NOT a status — pipeline error isolation reports it
 * separately and is orthogonal to this enum.
 */
enum LoadStatus: string
{
    case Applied = 'applied';
    case Skipped = 'skipped';
}
