<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureFieldFactory;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use Illuminate\Contracts\Translation\Translator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Field construction touches MoonShine's static {@see \MoonShine\Core\Core}
 * singleton, so the factory is exercised under a booted panel even though the
 * mapping logic itself is pure.
 */
#[CoversClass(FeatureFieldFactory::class)]
#[Group('moonshine')]
final class FeatureFieldFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

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
    public function mapsBooleanToSwitcherWithDotPathColumn(): void
    {
        $field = $this->factory()->field($this->definition('dark_mode', FeatureType::Boolean));

        self::assertInstanceOf(Switcher::class, $field);
        self::assertSame('featureValues.dark_mode', $field->getColumn());
    }

    #[Test]
    public function mapsIntegerToNumberWithMinAndMaxApplied(): void
    {
        $field = $this->factory()->field(
            $this->definition('retries', FeatureType::Integer, min: 1, max: 9),
        );

        self::assertInstanceOf(Number::class, $field);
        self::assertSame('1', $field->getAttributes()->get('min'));
        self::assertSame('9', $field->getAttributes()->get('max'));
    }

    #[Test]
    public function omitsMinAndMaxWhenTheDefinitionLeavesThemUnset(): void
    {
        $field = $this->factory()->field($this->definition('retries', FeatureType::Integer));

        self::assertInstanceOf(Number::class, $field);
        self::assertNull($field->getAttributes()->get('min'));
        self::assertNull($field->getAttributes()->get('max'));
    }

    #[Test]
    public function mapsEnumToSelectOverDeclaredOptions(): void
    {
        $field = $this->factory()->field(
            $this->definition('driver', FeatureType::Enum, options: ['redis', 'file']),
        );

        self::assertInstanceOf(Select::class, $field);

        $values = $field->getValues()->getValues()
            ->map(static fn(object $option): string => $option->getValue())
            ->values()
            ->all();

        self::assertSame(['redis', 'file'], $values);
    }

    #[Test]
    public function mapsStringToText(): void
    {
        $field = $this->factory()->field($this->definition('label', FeatureType::String));

        self::assertInstanceOf(Text::class, $field);
    }

    #[Test]
    public function resolvesTheDeclaredLabelThroughTheTranslator(): void
    {
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')->with('module-loader::admin.feature.cache')->andReturn('Cache enabled');

        $field = (new FeatureFieldFactory($translator))->field(
            $this->definition('cache', FeatureType::Boolean, label: 'module-loader::admin.feature.cache'),
        );

        self::assertSame('Cache enabled', $field->getLabel());
    }

    #[Test]
    public function fallsBackToAHumanizedKeyWhenNoLabelIsDeclared(): void
    {
        $field = $this->factory()->field($this->definition('enable_cache', FeatureType::Boolean));

        self::assertSame('Enable cache', $field->getLabel());
    }

    #[Test]
    public function groupsFieldsBySchemaGroupWithUngroupedFirst(): void
    {
        $schema = new FeatureSchema([
            'cache' => $this->definition('cache', FeatureType::Boolean, group: 'performance'),
            'name' => $this->definition('name', FeatureType::String),
            'retries' => $this->definition('retries', FeatureType::Integer, group: 'performance'),
        ]);

        $grouped = $this->factory()->groupedFields($schema);

        self::assertSame(['', 'performance'], array_keys($grouped));
        self::assertCount(1, $grouped['']);
        self::assertCount(2, $grouped['performance']);
    }

    private function factory(): FeatureFieldFactory
    {
        $translator = Mockery::mock(Translator::class);
        // Labels declared on a definition pass through unchanged; humanized
        // fallbacks never hit the translator.
        $translator->shouldReceive('get')->andReturnUsing(static fn(string $key): string => $key);

        return new FeatureFieldFactory($translator);
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
        ?string $label = null,
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
            label: $label,
            description: null,
            group: $group,
        );
    }
}
