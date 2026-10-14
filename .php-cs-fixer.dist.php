<?php
/**
 * PHP-CS-Fixer configuration.
 *
 * Formatting only: every rule changes layout, never behaviour, so no
 * "risky" rule is enabled.
 *
 * Run:   vendor/bin/php-cs-fixer fix
 * Check: vendor/bin/php-cs-fixer fix --dry-run --diff
 */

$finder = PhpCsFixer\Finder::create()
	->in( __DIR__ )
	->exclude( [ 'vendor', 'node_modules', 'storage' ] )
	->name( '*.php' );

return ( new PhpCsFixer\Config() )
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	->setRiskyAllowed( false )
	->setFinder( $finder )
	->setRules( [
		// Tabs for indentation.
		'indentation_type'                  => true,

		// [] instead of array().
		'array_syntax'                      => [ 'syntax' => 'short' ],

		// Opening braces on the same line: class Foo {, function bar() {.
		'braces_position'                   => [
			'classes_opening_brace'   => 'same_line',
			'functions_opening_brace' => 'same_line',
		],

		// One space inside parentheses: if ( $a ), foo( $b ).
		'spaces_inside_parentheses'         => [ 'space' => 'single' ],

		// ! empty( $x ), not !empty( $x ).
		'not_operator_with_successor_space' => true,

		// null === $x, not $x === null.
		'yoda_style'                        => true,

		// function foo() : string.
		'return_type_declaration'           => [ 'space_before' => 'one' ],

		// (int) $x.
		'cast_spaces'                       => [ 'space' => 'single' ],

		// $a . $b.
		'concat_space'                      => [ 'spacing' => 'one' ],

		// Align = and => across consecutive lines.
		'binary_operator_spaces'            => [
			'default'   => 'single_space',
			'operators' => [
				'='  => 'align_single_space_minimal',
				'=>' => 'align_single_space_minimal',
			],
		],

		// Empty bodies stay on one line: private function __construct() {}.
		'single_line_empty_body'            => true,

		// File hygiene.
		'no_trailing_whitespace'            => true,
		'single_blank_line_at_eof'          => true,
		'line_ending'                       => true,
	] );