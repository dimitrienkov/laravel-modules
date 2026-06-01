<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Support\StringKeyedObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StringKeyedObject::class)]
#[Group('support')]
final class StringKeyedObjectTest extends TestCase
{
    #[Test]
    public function returnsStringKeyedArrayUnchangedWhenAllKeysAreStrings(): void
    {
        $input = ['name' => 'blog', 'version' => '1.0.0'];

        $result = StringKeyedObject::toStringKeyedObject(
            $input,
            static fn (): RuntimeException => new RuntimeException('should not be called'),
        );

        self::assertSame($input, $result);
    }

    #[Test]
    public function returnsEmptyArrayWithoutInvokingOnErrorForEmptyInput(): void
    {
        $called = false;

        $result = StringKeyedObject::toStringKeyedObject(
            [],
            static function () use (&$called): RuntimeException {
                $called = true;

                return new RuntimeException('should not be called');
            },
        );

        self::assertSame([], $result);
        self::assertFalse($called);
    }

    #[Test]
    public function throwsExactExceptionInstanceFromOnErrorOnFirstIntegerKey(): void
    {
        // PHP coerces numeric-string JSON keys to integers, so {"1": ...} decodes
        // to [1 => ...]. The first integer key must trigger $onError exactly once,
        // and the thrown value must be the exact instance the callback produced.
        $expected = new RuntimeException('integer key rejected');
        $callCount = 0;

        try {
            StringKeyedObject::toStringKeyedObject(
                ['valid' => 'ok', 1 => 'bad', 2 => 'also bad'],
                static function () use ($expected, &$callCount): RuntimeException {
                    $callCount++;

                    return $expected;
                },
            );
            self::fail('Expected the onError exception to be thrown.');
        } catch (RuntimeException $e) {
            self::assertSame($expected, $e);
            self::assertSame(1, $callCount);
        }
    }
}
