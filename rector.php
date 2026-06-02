<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictStringParamConcatRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Architecture',
    ])
    ->withSkip([
        __DIR__ . '/tests/*/Fixtures',
        // Generator traits deliberately mirror the untyped parameter signatures of
        // Laravel's GeneratorCommand (e.g. getDefaultNamespace($rootNamespace)).
        // Adding a native string type there breaks LSP against the vendor parent.
        StrictStringParamConcatRector::class => [
            __DIR__ . '/src/Console/Concerns',
        ],
    ])
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        SetList::TYPE_DECLARATION,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: true,
        removeUnusedImports: true,
    );
