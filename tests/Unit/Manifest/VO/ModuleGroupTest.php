<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleGroup::class)]
#[Group('manifest')]
final class ModuleGroupTest extends TestCase
{
    #[Test]
    #[DataProvider('validGroupProvider')]
    public function acceptsValidKebabCaseGroups(string $value): void
    {
        $group = new ModuleGroup($value);

        self::assertSame($value, $group->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validGroupProvider(): array
    {
        return [
            'single word' => ['content'],
            'hyphenated' => ['e-commerce'],
            'alphanumeric segments' => ['a1-b2'],
            'leading digit' => ['1content'],
            'single char' => ['a'],
            'multi-segment' => ['core-services'],
        ];
    }

    #[Test]
    #[DataProvider('invalidGroupProvider')]
    public function rejectsNonKebabCaseGroups(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be kebab-case/');

        new ModuleGroup($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGroupProvider(): array
    {
        return [
            'PascalCase' => ['Foo'],
            'trailing hyphen' => ['foo-'],
            'double hyphen' => ['foo--bar'],
            'leading hyphen' => ['-foo'],
            'empty string' => [''],
            'snake_case' => ['my_group'],
            'contains space' => ['my group'],
        ];
    }

    #[Test]
    public function equalsIsNullSafeAndComparesValue(): void
    {
        $group = new ModuleGroup('content');

        self::assertTrue($group->equals(new ModuleGroup('content')));
        self::assertFalse($group->equals(new ModuleGroup('billing')));
        self::assertFalse($group->equals(null));
    }
}
