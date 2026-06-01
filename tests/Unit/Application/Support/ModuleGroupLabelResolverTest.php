<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleGroupLabelResolver::class)]
#[Group('lifecycle')]
final class ModuleGroupLabelResolverTest extends TestCase
{
    #[Test]
    public function rendersLabelWithCodeWhenMappingExists(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('Content Management (content)', $resolver->displayLabel('content'));
    }

    #[Test]
    public function fallsBackToCodeWhenMappingMissing(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('billing', $resolver->displayLabel('billing'));
    }

    #[Test]
    public function returnsEmptyStringForNullGroup(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('', $resolver->displayLabel(null));
    }

    #[Test]
    public function throwsWhenGroupsConfigIsNotArray(): void
    {
        $resolver = $this->makeResolver('not-an-array');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/modules\.groups/');

        $resolver->displayLabel('content');
    }

    #[Test]
    public function throwsWhenLabelForRequestedGroupIsNotString(): void
    {
        $resolver = $this->makeResolver(['content' => ['nested' => 'value']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/group \[content\]/');

        $resolver->displayLabel('content');
    }

    #[Test]
    public function throwsWhenLabelForRequestedGroupIsBlank(): void
    {
        $resolver = $this->makeResolver(['content' => '   ']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/group \[content\]/');

        $resolver->displayLabel('content');
    }

    #[Test]
    public function fallsBackToCodeWhenOtherGroupHasMalformedLabel(): void
    {
        // A malformed label for a group that is not requested must not fail the
        // lookup of a different, unmapped group — that stays a bare-code fallback.
        $resolver = $this->makeResolver(['content' => ['nested' => 'value']]);

        self::assertSame('billing', $resolver->displayLabel('billing'));
    }

    private function makeResolver(mixed $groups): ModuleGroupLabelResolver
    {
        return new ModuleGroupLabelResolver(new Repository(['modules' => ['groups' => $groups]]));
    }
}
