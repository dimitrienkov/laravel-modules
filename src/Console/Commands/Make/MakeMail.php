<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use Override;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
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

    /**
     * In module mode the mailable's runtime view reference must resolve through
     * the module's view namespace (e.g. `markdown: 'blog::mail.digest'`), or it
     * would resolve against the host views the loader never registered. The
     * parent already stamped the bare view (`'mail.digest'`) into the class, so
     * we only repoint that single quoted reference. The Blade file path stays on
     * the clean relative view — `getView()`/`writeView()` are intentionally left
     * untouched so the template still writes to `Resources/views/...`.
     *
     * @param string $name
     */
    #[Override]
    protected function buildClass($name): string
    {
        $class = parent::buildClass($name);
        $module = $this->module();

        if (! $module instanceof Module || ! $this->generatesView()) {
            return $class;
        }

        $view = $this->getView();

        return str_replace("'{$view}'", "'{$module->name}::{$view}'", $class);
    }

    private function generatesView(): bool
    {
        if ($this->option('markdown') !== false) {
            return true;
        }

        return $this->option('view') !== false;
    }
}
