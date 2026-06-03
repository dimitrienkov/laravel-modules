<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

final readonly class ModuleConfigKeys
{
    public const string DIRECTORIES = 'modules.paths.directories';

    public const string STATE = 'modules.paths.state';

    public const string BACKUP = 'modules.paths.backup';

    private function __construct() {}
}
