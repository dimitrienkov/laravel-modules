<?php

declare(strict_types=1);

$finder = Symfony\Component\Finder\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

if (is_dir(__DIR__ . '/stubs')) {
    $finder->in(__DIR__ . '/stubs');
}

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'ordered_class_elements' => true,
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'phpdoc_align' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'void_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
