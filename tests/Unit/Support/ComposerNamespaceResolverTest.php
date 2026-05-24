<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComposerNamespaceResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-resolver-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules/Blog', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_module_namespace_from_root_psr4_mapping(): void
    {
        $this->writeComposer([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                ],
            ],
        ]);

        $namespace = (new ComposerNamespaceResolver($this->tempDir))
            ->resolve($this->tempDir . '/app/Modules/Blog');

        self::assertSame('App\\Modules\\Blog', $namespace);
    }

    #[Test]
    public function it_uses_the_most_specific_psr4_root(): void
    {
        $this->writeComposer([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Domain\\Blog\\' => 'app/Modules/Blog/',
                ],
            ],
        ]);

        $namespace = (new ComposerNamespaceResolver($this->tempDir))
            ->resolve($this->tempDir . '/app/Modules/Blog');

        self::assertSame('Domain\\Blog', $namespace);
    }

    #[Test]
    public function it_caches_psr4_roots_across_calls(): void
    {
        $this->writeComposer([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                ],
            ],
        ]);

        $resolver = new ComposerNamespaceResolver($this->tempDir);
        $first = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        $this->writeComposer([
            'autoload' => [
                'psr-4' => [
                    'Changed\\' => 'app/',
                ],
            ],
        ]);

        $second = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        self::assertSame($first, $second);
        self::assertSame('App\\Modules\\Blog', $second);
    }

    #[Test]
    public function it_throws_when_composer_json_is_missing(): void
    {
        $this->expectException(NamespaceResolutionException::class);
        $this->expectExceptionMessage('composer.json not found');

        (new ComposerNamespaceResolver($this->tempDir))->resolve($this->tempDir . '/app/Modules/Blog');
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function writeComposer(array $composer): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
