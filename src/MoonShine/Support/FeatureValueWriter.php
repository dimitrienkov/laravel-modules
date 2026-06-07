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
     * A partial submit (only some schema fields posted) is merged onto the current
     * explicit value set: keys absent from `$submitted` are left untouched, so a
     * form that posts one field never silently drops the module's other overrides.
     *
     * @param array<string, mixed> $submitted feature key => raw submitted value
     */
    public function write(Module $module, array $submitted): void
    {
        $manifestPath = $module->manifestPath();

        // Start from the current explicit overrides so fields not present in this
        // submit are preserved; only submitted keys are added, replaced, or removed.
        $explicit = $this->state->readValues($module)->explicitValues();

        foreach ($module->features->all() as $key => $definition) {
            if (! \array_key_exists($key, $submitted)) {
                continue;
            }

            $raw = $submitted[$key];

            // A cleared field (null or a blank string from ConvertEmptyStringsToNull
            // or a disabled middleware) reverts the override to the schema default:
            // drop only this key instead of feeding null/'' to the strict normalizer.
            // Boolean is exempt — it has no "empty" state; off arrives as a coercible false.
            if ($definition->type !== FeatureType::Boolean && $this->isCleared($raw)) {
                unset($explicit[$key]);

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
                unset($explicit[$key]);

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
     * Coercion fails closed: range/option enforcement stays in
     * {@see FeatureDefinition::normalize()}, and a value that is not an
     * unambiguous transport encoding of the schema type is passed through
     * unchanged so the strict normalizer rejects it with the canonical error
     * instead of being silently truncated to a wrong value.
     */
    private function coerce(FeatureDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type) {
            FeatureType::Boolean => $this->coerceBool($value),
            FeatureType::Integer => $this->coerceInt($value),
            FeatureType::Enum, FeatureType::String => \is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * Accept real booleans, an absent/off switch (null, mapped by
     * {@see self::submittedFeatureValues()} from `false`), and the standard
     * HTML/Laravel bool tokens `FILTER_VALIDATE_BOOLEAN` recognises ("1"/"0",
     * "true"/"false", "on"/"off", "yes"/"no"). Anything else (e.g. "maybe")
     * passes through so the normalizer rejects it — `FILTER_NULL_ON_FAILURE`
     * stops an unrecognised token from being silently coerced to `false`.
     */
    private function coerceBool(mixed $value): mixed
    {
        if (\is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    /**
     * Accept real integers and strings that are an exact integer encoding
     * (`FILTER_VALIDATE_INT`). Fractional, exponent, or otherwise non-integer
     * strings ("3.5", "1e2") pass through unchanged so the normalizer rejects
     * them rather than being truncated by a permissive `(int)` cast.
     */
    private function coerceInt(mixed $value): mixed
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $coerced = filter_var($value, FILTER_VALIDATE_INT);

            return $coerced === false ? $value : $coerced;
        }

        return $value;
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
