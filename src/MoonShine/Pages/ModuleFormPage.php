<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Pages;

use Illuminate\Validation\Rules\In;
use Override;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureFieldFactory;
use Illuminate\Contracts\Translation\Translator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Pages\FormPage;
use MoonShine\UI\Components\Layout\Box;

/**
 * Feature-flags form page for {@see ModulesResource}.
 *
 * Fields are built dynamically from the selected module's `settings.schema` via
 * {@see FeatureFieldFactory} and grouped by `settings.schema.*.group`. Validation
 * lives on the page (`rules()`), so the native Store/Update form-request pipeline
 * enforces the schema (int min/max, enum options, types) before persistence.
 * Persistence itself flows through {@see ModulesResource::save()},
 * which writes only explicit overrides through `ModuleStateRepository::writeValues()`.
 *
 * @extends FormPage<ModulesResource>
 */
final class ModuleFormPage extends FormPage
{
    public function __construct(
        CoreContract $core,
        private readonly ModuleRegistryInterface $registry,
        private readonly FeatureFieldFactory $fieldFactory,
        private readonly ModuleGroupLabelResolver $groupLabels,
        private readonly Translator $translator,
    ) {
        parent::__construct($core);
    }

    /**
     * @return list<ComponentContract>
     */
    #[Override]
    protected function fields(): iterable
    {
        $module = $this->selectedModule();

        if (! $module instanceof Module) {
            return [];
        }

        $components = [];

        foreach ($this->fieldFactory->groupedFields($module->features) as $groupCode => $fields) {
            $components[] = Box::make($this->groupHeading($groupCode), $fields);
        }

        return $components;
    }

    /**
     * @param DataWrapperContract<ModuleAdminDto> $item
     *
     * @return array<string, list<string|In>>
     */
    #[Override]
    protected function rules(DataWrapperContract $item): array
    {
        $module = $this->selectedModule();

        if (! $module instanceof Module) {
            return [];
        }

        $rules = [];

        foreach ($module->features->all() as $definition) {
            $rules[ModuleAdminDto::featureColumn($definition->key)] = $this->rulesFor($definition);
        }

        return $rules;
    }

    /**
     * Validation rules must be a superset of {@see FeatureDefinition::normalize()}:
     * whatever the strict normalizer would reject has to fail here first, on the
     * form, rather than surface as an unhandled normalizer exception on submit.
     *
     * @return list<string|In>
     */
    private function rulesFor(FeatureDefinition $definition): array
    {
        return match ($definition->type) {
            FeatureType::Boolean => ['nullable', 'boolean'],
            FeatureType::Integer => ['nullable', 'integer', ...$this->boundsRules($definition)],
            FeatureType::Enum => ['nullable', 'string', ...$this->optionRules($definition)],
            FeatureType::String => ['nullable', 'string', ...$this->boundsRules($definition)],
        };
    }

    /**
     * Laravel's `min`/`max` compare an integer's value and a string's length
     * alike, so the same rule strings mirror both the Integer range and the
     * String length bounds the normalizer enforces via `mb_strlen`.
     *
     * @return list<string>
     */
    private function boundsRules(FeatureDefinition $definition): array
    {
        $rules = [];

        if ($definition->min !== null) {
            $rules[] = 'min:' . $definition->min;
        }

        if ($definition->max !== null) {
            $rules[] = 'max:' . $definition->max;
        }

        return $rules;
    }

    /**
     * Build the enum membership rule via {@see Rule::in()} rather than a string
     * `in:a,b`: the string form splits on commas, so an enum option that itself
     * contains a comma would be validated as two separate values. `Rule::in()`
     * takes the options as an array and keeps each one intact.
     *
     * @return list<In>
     */
    private function optionRules(FeatureDefinition $definition): array
    {
        if ($definition->options === []) {
            return [];
        }

        return [Rule::in($definition->options)];
    }

    private function selectedModule(): ?Module
    {
        $id = $this->getResource()?->getItemID();

        if ($id === null || ! $this->registry->has((string) $id)) {
            return null;
        }

        return $this->registry->find((string) $id);
    }

    private function groupHeading(string $groupCode): string
    {
        if ($groupCode === FeatureFieldFactory::UNGROUPED) {
            return $this->adminLabel('ungrouped');
        }

        return $this->groupLabels->displayLabel(new ModuleGroup($groupCode));
    }

    private function adminLabel(string $key): string
    {
        $label = $this->translator->get("module-loader::admin.{$key}");

        return \is_string($label) ? $label : $key;
    }
}
