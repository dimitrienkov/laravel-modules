<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Validation\Rules\In;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use MoonShine\UI\Components\Layout\Box;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

/**
 * Covers the feature-flags form page: dynamic fields built from the selected
 * module's `settings.schema` (grouped by `*.group`) and page-level validation
 * rules derived from each feature definition.
 */
#[Group('feature')]
final class ModuleFormPageTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MoonShineServiceProvider::class, ModuleLoaderServiceProvider::class];
    }

    #[Test]
    public function buildsOneBoxPerGroupWithUngroupedFirst(): void
    {
        $components = $this->invoke('fields');

        self::assertCount(2, $components);
        self::assertContainsOnlyInstancesOf(Box::class, $components);

        // Ungrouped bucket first (ksort over group codes), then "performance".
        [$ungrouped, $performance] = $components;

        self::assertSame(['featureValues.driver', 'featureValues.label'], $this->columns($ungrouped));
        self::assertSame(['featureValues.cache', 'featureValues.retries'], $this->columns($performance));
    }

    #[Test]
    public function derivesValidationRulesFromEachFeatureDefinition(): void
    {
        $rules = $this->invoke('rules');

        self::assertSame(['nullable', 'boolean'], $rules['featureValues.cache']);
        self::assertSame(['nullable', 'integer', 'min:1', 'max:5'], $rules['featureValues.retries']);
        self::assertSame(['nullable', 'string'], $rules['featureValues.label']);

        // Enum membership is a Rule::in() object, not a string `in:redis,file`:
        // nullable + string keep their place, the In rule carries the options.
        $driver = $rules['featureValues.driver'];
        self::assertSame('nullable', $driver[0]);
        self::assertSame('string', $driver[1]);
        self::assertInstanceOf(In::class, $driver[2]);
        self::assertSame('in:"redis","file"', (string) $driver[2]);
    }

    #[Test]
    public function buildsEnumRuleViaRuleInSoCommaContainingOptionsStaySingle(): void
    {
        // A single enum option that contains a comma must stay one option. The old
        // string `in:redis,file` rule split on the comma; Rule::in() keeps it whole.
        $schema = new FeatureSchema([
            'driver' => $this->definition('driver', FeatureType::Enum, options: ['redis,file']),
        ]);

        $page = $this->page(schema: $schema);
        $method = new ReflectionMethod($page, 'rules');
        $method->setAccessible(true);
        $item = $page->getResource()->getCaster()->cast(ModuleAdminDto::empty());

        /** @var array<string, list<string|In>> $rules */
        $rules = $method->invoke($page, $item);

        $rule = $rules['featureValues.driver'][2];
        self::assertInstanceOf(In::class, $rule);
        self::assertSame('in:"redis,file"', (string) $rule);
    }

    #[Test]
    public function translatesStringLengthBoundsIntoValidationRules(): void
    {
        // A String with min/max must yield length rules so the form rejects an
        // out-of-range value before the normalizer (mb_strlen) would — rules() is
        // a superset of normalize().
        $schema = new FeatureSchema([
            'label' => $this->definition('label', FeatureType::String, min: 2, max: 16),
        ]);

        $page = $this->page(schema: $schema);
        $method = new ReflectionMethod($page, 'rules');
        $method->setAccessible(true);
        $item = $page->getResource()->getCaster()->cast(ModuleAdminDto::empty());

        /** @var array<string, list<string>> $rules */
        $rules = $method->invoke($page, $item);

        self::assertSame(['nullable', 'string', 'min:2', 'max:16'], $rules['featureValues.label']);
    }

    #[Test]
    public function rulesAreEmptyWhenNoModuleIsSelected(): void
    {
        $page = $this->page(selectModule: false);

        $method = new ReflectionMethod($page, 'rules');
        $method->setAccessible(true);

        $item = $page->getResource()->getCaster()->cast(ModuleAdminDto::empty());

        self::assertSame([], $method->invoke($page, $item));
    }

    /**
     * @return array<mixed>
     */
    private function invoke(string $method): array
    {
        $page = $this->page();
        $reflection = new ReflectionMethod($page, $method);
        $reflection->setAccessible(true);

        if ($method === 'rules') {
            $item = $page->getResource()->getCaster()->cast(ModuleAdminDto::empty());

            return $reflection->invoke($page, $item);
        }

        return $reflection->invoke($page);
    }

    private function page(bool $selectModule = true, ?FeatureSchema $schema = null): ModuleFormPage
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'blog', features: $schema ?? $this->schema()));
        $this->app->instance(ModuleRegistryInterface::class, $registry);

        $resource = $this->app->make(ModulesResource::class);

        if ($selectModule) {
            $resource->setItemID('blog');
        }

        $page = $this->app->make(ModuleFormPage::class);
        $page->setResource($resource);

        return $page;
    }

    private function schema(): FeatureSchema
    {
        return new FeatureSchema([
            'cache' => $this->definition('cache', FeatureType::Boolean, group: 'performance'),
            'retries' => $this->definition('retries', FeatureType::Integer, min: 1, max: 5, group: 'performance'),
            'driver' => $this->definition('driver', FeatureType::Enum, options: ['redis', 'file']),
            'label' => $this->definition('label', FeatureType::String),
        ]);
    }

    /**
     * @param array<int, string> $options
     */
    private function definition(
        string $key,
        FeatureType $type,
        ?int $min = null,
        ?int $max = null,
        array $options = [],
        ?string $group = null,
    ): FeatureDefinition {
        return new FeatureDefinition(
            key: $key,
            type: $type,
            hasDefault: false,
            default: null,
            min: $min,
            max: $max,
            options: $options,
            label: null,
            description: null,
            group: $group,
        );
    }

    /**
     * @return list<string>
     */
    private function columns(Box $box): array
    {
        $columns = [];

        foreach ($box->getComponents() as $field) {
            if ($field instanceof FieldContract) {
                $columns[] = $field->getColumn();
            }
        }

        return $columns;
    }
}
