<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use Illuminate\Contracts\Translation\Translator;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * Maps a {@see FeatureDefinition} to the MoonShine form field that edits it.
 *
 * Pulled out of {@see \DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage}
 * so the type → field mapping is a single, unit-testable unit (Architecture
 * Splitting) rather than inline page glue. The mapping mirrors the manifest
 * feature grammar: bool → Switcher, int → Number with optional min/max, enum →
 * Select over the declared options, string → Text.
 *
 * Every field's column is the dot-path `featureValues.<key>` so MoonShine reads
 * the value via `data_get($dto, 'featureValues.<key>')` and
 * {@see \DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource::save()}
 * maps it back to the bare feature key on submit. Labels resolve through the
 * injected {@see Translator} (never the `__()`/`trans()` helpers the arch suite
 * forbids), falling back to a humanized key when no explicit label is declared.
 */
final readonly class FeatureFieldFactory
{
    /**
     * Group code for features without an explicit `settings.schema.*.group`.
     * Shared with {@see \DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage}
     * so producer and consumer agree on the ungrouped bucket key.
     */
    public const string UNGROUPED = '';

    public function __construct(
        private Translator $translator,
    ) {}

    public function field(FeatureDefinition $definition): FieldContract
    {
        $label = $this->label($definition);
        $column = ModuleAdminDto::featureColumn($definition->key);

        return match ($definition->type) {
            FeatureType::Boolean => Switcher::make($label, $column),
            FeatureType::Integer => $this->numberField($label, $column, $definition),
            FeatureType::Enum => Select::make($label, $column)->options($this->options($definition)),
            FeatureType::String => Text::make($label, $column),
        };
    }

    /**
     * Group the schema's fields by `settings.schema.*.group`, preserving the
     * schema's (sorted) key order within each group. The ungrouped bucket uses an
     * empty-string key so callers render it under a neutral heading.
     *
     * @return array<string, list<FieldContract>>
     */
    public function groupedFields(FeatureSchema $schema): array
    {
        $grouped = [];

        foreach ($schema->all() as $definition) {
            $grouped[$definition->group ?? self::UNGROUPED][] = $this->field($definition);
        }

        ksort($grouped);

        return $grouped;
    }

    private function numberField(string $label, string $column, FeatureDefinition $definition): Number
    {
        $field = Number::make($label, $column);

        if ($definition->min !== null) {
            $field->min($definition->min);
        }

        if ($definition->max !== null) {
            $field->max($definition->max);
        }

        return $field;
    }

    /**
     * @return array<string, string> option value => option label (label = value)
     */
    private function options(FeatureDefinition $definition): array
    {
        $options = [];

        foreach ($definition->options as $value) {
            $options[$value] = $value;
        }

        return $options;
    }

    private function label(FeatureDefinition $definition): string
    {
        if ($definition->label !== null) {
            $resolved = $this->translator->get($definition->label);

            return \is_string($resolved) ? $resolved : $definition->label;
        }

        return $this->humanize($definition->key);
    }

    private function humanize(string $key): string
    {
        return ucfirst(trim(str_replace(['_', '-', '.'], ' ', $key)));
    }
}
