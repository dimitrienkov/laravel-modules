<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class DebugCallsTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $disallowedCalls = [
        'dd(',
        'dump(',
        'var_dump(',
        'print_r(',
        'exit(',
        'die(',
    ];

    #[Test]
    public function src_does_not_contain_debug_or_termination_calls(): void
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__ . '/../../src')
            ->name('*.php');

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getRealPath());

            foreach ($this->disallowedCalls as $call) {
                self::assertStringNotContainsString(
                    $call,
                    $contents,
                    "Found disallowed call [{$call}] in [{$file->getRealPath()}].",
                );
            }
        }
    }
}
