<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use Illuminate\Foundation\Console\MailMakeCommand;

/**
 * Module-aware `make:mail`.
 *
 * The mailable lands in the module's `Mail` namespace (trait), and any
 * `--markdown`/`--view` template is redirected into the module's `Resources/views`
 * by the trait's `viewPath()` override, so no Blade file leaks to the host.
 */
final class MakeMail extends MailMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Mail';
    }
}
