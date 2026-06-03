<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

final readonly class ModuleConfigKeys
{
    public const string DIRECTORIES = 'modules.paths.directories';

    public const string STATE_ROOT = 'modules.paths.state';

    public const string BACKUP_ROOT = 'modules.paths.backup';

    private function __construct() {}
}
