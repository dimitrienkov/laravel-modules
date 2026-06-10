<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use Illuminate\Contracts\Translation\Translator;

/**
 * Resolves a {@see ModuleKind} into a translatable display label for the admin
 * UI (the index tab titles and the detail "kind" field).
 *
 * Labels live in the `module-loader::admin.kinds.*` lang group so they stay
 * translatable; the resolver reads them through the injected {@see Translator}
 * contract — never the `__()`/`trans()` global helpers, which the arch suite
 * forbids in `src/`. An unexpected non-string translation (e.g. a missing or
 * misconfigured group) falls back to the bare enum value so the UI never renders
 * an array.
 */
final readonly class ModuleKindLabelResolver
{
    public function __construct(
        private Translator $translator,
    ) {}

    public function label(ModuleKind $kind): string
    {
        $label = $this->translator->get("module-loader::admin.kinds.{$kind->value}");

        return \is_string($label) ? $label : $kind->value;
    }
}
