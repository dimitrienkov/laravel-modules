<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureValueWriter;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the write side directly (no booted MoonShine page): given an already
 * extracted submitted map, {@see FeatureValueWriter::write()} coerces each value to
 * its schema type, reverts cleared non-boolean fields, strips values equal to the
 * default, and persists only the explicit overrides as a {@see FeatureValues}.
 */
#[CoversClass(FeatureValueWriter::class)]
#[Group('moonshine')]
final class FeatureValueWriterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function coercesEachScalarToItsSchemaType(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        $values = $this->writeAndCapture($module, [
            'retries' => '7',     // int from a string transport
            'driver' => 'file',   // enum override
            'flag' => '1',        // bool "on"
            'label' => 'hello',   // free string
        ]);

        self::assertSame(
            ['driver' => 'file', 'flag' => true, 'label' => 'hello', 'retries' => 7],
            $values->explicitValues(),
        );
    }

    #[Test]
    public function stripsValuesEqualToTheSchemaDefault(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        // retries=3 (default), driver=redis (default), flag=0 (default false).
        $values = $this->writeAndCapture($module, [
            'retries' => '3',
            'driver' => 'redis',
            'flag' => '0',
        ]);

        self::assertSame([], $values->explicitValues());
    }

    #[Test]
    public function revertsClearedNonBooleanFieldsToDefault(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        // '' and null are both "cleared": the override is dropped, never normalized.
        $values = $this->writeAndCapture($module, [
            'retries' => '',
            'driver' => null,
        ]);

        self::assertSame([], $values->explicitValues());
    }

    #[Test]
    public function keepsBooleanOffOverrideAgainstADefaultTrue(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: new FeatureSchema([
            'flag' => new FeatureDefinition('flag', FeatureType::Boolean, true, true, null, null, [], null, null, null),
        ]));

        // Boolean is exempt from the clear guard: a coercible false against default
        // true is a genuine override, not a clear.
        $values = $this->writeAndCapture($module, ['flag' => '0']);

        self::assertSame(['flag' => false], $values->explicitValues());
    }

    #[Test]
    public function preservesUnrelatedExistingOverrideOnPartialSubmit(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        // Only retries is posted; the pre-existing driver override must survive,
        // not be wiped just because its field was absent from this submit.
        $values = $this->writeAndCapture(
            $module,
            ['retries' => '7'],
            existing: ['driver' => 'file'],
        );

        self::assertSame(['driver' => 'file', 'retries' => 7], $values->explicitValues());
    }

    #[Test]
    public function clearingASubmittedKeyRemovesOnlyThatKeyAndKeepsOthers(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        // retries is cleared (revert to default) while driver is not posted at all:
        // only retries is dropped, the unrelated driver override is preserved.
        $values = $this->writeAndCapture(
            $module,
            ['retries' => null],
            existing: ['driver' => 'file', 'retries' => 7],
        );

        self::assertSame(['driver' => 'file'], $values->explicitValues());
    }

    #[Test]
    public function rejectsAnUnrecognisedBooleanTokenInsteadOfCoercingToFalse(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        $this->expectException(InvalidManifestException::class);

        $this->writeExpectingNoPersist($module, ['flag' => 'maybe']);
    }

    #[Test]
    public function rejectsAFractionalIntegerStringInsteadOfTruncating(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        $this->expectException(InvalidManifestException::class);

        $this->writeExpectingNoPersist($module, ['retries' => '3.5']);
    }

    #[Test]
    public function rejectsAnExponentIntegerStringInsteadOfTruncating(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        $this->expectException(InvalidManifestException::class);

        $this->writeExpectingNoPersist($module, ['retries' => '1e2']);
    }

    /**
     * @param array<string, mixed>           $submitted
     * @param array<string, bool|int|string> $existing  pre-existing explicit overrides in state.json
     */
    private function writeAndCapture(Module $module, array $submitted, array $existing = []): FeatureValues
    {
        $captured = null;
        $state = $this->stateReading($module, $existing);
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::on(static function (FeatureValues $values) use (&$captured): bool {
                $captured = $values;

                return true;
            }));

        (new FeatureValueWriter($state))->write($module, $submitted);

        self::assertInstanceOf(FeatureValues::class, $captured);

        return $captured;
    }

    /**
     * Write a submit that must be rejected before persistence: readValues() is
     * answered but writeValues() must never fire.
     *
     * @param array<string, mixed> $submitted
     */
    private function writeExpectingNoPersist(Module $module, array $submitted): void
    {
        $state = $this->stateReading($module, []);
        $state->shouldNotReceive('writeValues');

        (new FeatureValueWriter($state))->write($module, $submitted);
    }

    /**
     * State double whose readValues() returns the given pre-existing explicit
     * overrides — the baseline write() merges this submit onto.
     *
     * @param array<string, bool|int|string> $existing
     *
     * @return ModuleStateRepositoryInterface&Mockery\MockInterface
     */
    private function stateReading(Module $module, array $existing): ModuleStateRepositoryInterface&Mockery\MockInterface
    {
        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        $state->shouldReceive('readValues')->once()
            ->with(Mockery::type(Module::class))
            ->andReturn(FeatureValues::fromArray($existing, $module->features, $module->name, $module->manifestPath()));

        return $state;
    }

    private function schema(): FeatureSchema
    {
        return new FeatureSchema([
            'retries' => new FeatureDefinition('retries', FeatureType::Integer, true, 3, 1, 10, [], null, null, null),
            'driver' => new FeatureDefinition('driver', FeatureType::Enum, true, 'redis', null, null, ['redis', 'file'], null, null, null),
            'flag' => new FeatureDefinition('flag', FeatureType::Boolean, true, false, null, null, [], null, null, null),
            'label' => new FeatureDefinition('label', FeatureType::String, false, null, null, null, [], null, null, null),
        ]);
    }
}
