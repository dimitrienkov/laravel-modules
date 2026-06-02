<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Enums;

use DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScaffoldComponent::class)]
#[Group('application')]
final class ScaffoldComponentTest extends TestCase
{
    #[Test]
    public function parsesValidCommaSeparatedValues(): void
    {
        $components = ScaffoldComponent::parseList('routes,views,domain,application,database');

        self::assertSame(
            [
                ScaffoldComponent::Routes,
                ScaffoldComponent::Views,
                ScaffoldComponent::Domain,
                ScaffoldComponent::Application,
                ScaffoldComponent::Database,
            ],
            $components,
        );
    }

    #[Test]
    public function trimsWhitespaceAroundTokens(): void
    {
        $components = ScaffoldComponent::parseList(' routes ,  http ');

        self::assertSame([ScaffoldComponent::Routes, ScaffoldComponent::Http], $components);
    }

    #[Test]
    public function emptyStringYieldsEmptySelection(): void
    {
        self::assertSame([], ScaffoldComponent::parseList(''));
        self::assertSame([], ScaffoldComponent::parseList('   '));
        self::assertSame([], ScaffoldComponent::parseList(', ,'));
    }

    #[Test]
    public function unknownValueFailsFast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown module component [moonshine]');

        ScaffoldComponent::parseList('routes,moonshine');
    }

    #[Test]
    public function duplicateValueFailsFast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate module component [routes]');

        ScaffoldComponent::parseList('routes,views,routes');
    }

    #[Test]
    public function fromValuesValidatesInteractiveSelection(): void
    {
        $components = ScaffoldComponent::fromValues(['application', 'console']);

        self::assertSame([ScaffoldComponent::Application, ScaffoldComponent::Console], $components);
    }

    #[Test]
    public function everyCaseHasANonEmptyLabel(): void
    {
        foreach (ScaffoldComponent::cases() as $case) {
            self::assertNotSame('', $case->label());
        }
    }

    #[Test]
    public function allowedValuesListContainsEveryCase(): void
    {
        $list = ScaffoldComponent::allowedValuesList();

        foreach (ScaffoldComponent::cases() as $case) {
            self::assertStringContainsString($case->value, $list);
        }
    }
}
