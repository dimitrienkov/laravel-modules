<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Support;

use MoonShine\Contracts\UI\FieldContract;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;

/**
 * Write side of the admin feature-flags form — the mirror of the read-side
 * {@see FeatureFieldFactory}.
 *
 * Pulled out of {@see \DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource}
 * so the form's value-persistence path (read submitted columns -> coerce ->
 * normalize -> strip defaults -> persist explicit overrides) is one
 * unit-testable unit instead of inline resource glue. It depends only on the
 * package's own value objects plus the MoonShine {@see FieldsContract}/`FieldContract`
 * it reads submitted values from, never on the resource itself.
 *
 * Diagnostics are deliberately silent here: persisting feature values is not a
 * lifecycle operation (no {@see \DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation}),
 * it flows through the already-validating `ModuleStateRepository::writeValues()`,
 * and the {@see \DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface}
 * taxonomy is intentionally scoped to discovery/cache/pipeline/lifecycle events.
 */
final readonly class FeatureValueWriter
{
    public function __construct(
        private ModuleStateRepositoryInterface $state,
    ) {}

    /**
     * Read submitted feature values from the form fields. Each feature field's
     * column maps back to its bare key via {@see ModuleAdminDto::featureKeyFromColumn()};
     * non-feature columns return null and are skipped. A field absent from the
     * request is skipped so unchanged defaults are never forced into the explicit
     * value set.
     *
     * @param FieldsContract<FieldContract> $fields
     *
     * @return array<string, mixed> feature key => raw submitted value
     */
    public function submittedFeatureValues(FieldsContract $fields): array
    {
        $submitted = [];

        foreach ($fields as $field) {
            $key = ModuleAdminDto::featureKeyFromColumn($field->getColumn());
            if ($key === null) {
                continue;
            }
            if (! $field->hasRequestValue()) {
                continue;
            }

            $value = $field->getRequestValue();
            $submitted[$key] = $value === false ? null : $value;
        }

        return $submitted;
    }

    /**
     * Persist ONLY explicit overrides through `ModuleStateRepository::writeValues()`.
     * `enabled` is never touched here (that is a lifecycle concern). Values equal
     * to the schema default, and cleared non-boolean fields, are stripped so the
     * value set never duplicates defaults.
     *
     * @param array<string, mixed> $submitted feature key => raw submitted value
     */
    public function write(Module $module, array $submitted): void
    {
        $manifestPath = $module->manifestPath();
        $explicit = [];

        foreach ($module->features->all() as $key => $definition) {
            if (! \array_key_exists($key, $submitted)) {
                continue;
            }

            $raw = $submitted[$key];

            // A cleared field (null or a blank string from ConvertEmptyStringsToNull
            // or a disabled middleware) reverts the override to the schema default:
            // drop it instead of feeding null/'' to the strict normalizer. Boolean is
            // exempt — it has no "empty" state; off arrives as a coercible false.
            if ($definition->type !== FeatureType::Boolean && $this->isCleared($raw)) {
                continue;
            }

            // Form transport delivers raw scalars (strings, "on" toggles); coerce to
            // the schema's PHP type before the strict normalizer validates ranges.
            $normalized = $definition->normalize(
                $this->coerce($definition, $raw),
                $manifestPath,
            );

            // Defaults stay in the schema; only genuine overrides are persisted.
            if ($definition->hasDefault && $normalized === $definition->default) {
                continue;
            }

            $explicit[$key] = $normalized;
        }

        $this->state->writeValues(
            $module,
            FeatureValues::fromArray($explicit, $module->features, $module->name, $manifestPath),
        );
    }

    /**
     * Coerce a raw submitted value to the PHP type the feature schema expects.
     * Range/option enforcement stays in {@see FeatureDefinition::normalize()}; a
     * value that cannot be coerced is passed through unchanged so the normalizer
     * rejects it with the canonical error.
     */
    private function coerce(FeatureDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type) {
            FeatureType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FeatureType::Integer => is_numeric($value) ? (int) $value : $value,
            FeatureType::Enum, FeatureType::String => \is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * A non-boolean field is "cleared" when the form sent no value: null (the
     * ConvertEmptyStringsToNull outcome) or a blank string (middleware disabled).
     */
    private function isCleared(mixed $value): bool
    {
        return $value === null || (\is_string($value) && trim($value) === '');
    }
}
