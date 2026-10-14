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

		// One space around binary operators. = and => are left as written,
		// so hand alignment of consecutive lines is kept: automatic
		// alignment also lines up unrelated statements and long match arms.
		'binary_operator_spaces'            => [
			'default'   => 'single_space',
			'operators' => [
				'='  => null,
				'=>' => null,
			],
		],

		// Empty bodies stay on one line: private function __construct() {}.
		'single_line_empty_body'            => true,

		// elseif, not else if.
		'elseif'                            => true,

		// [ 'a', 'b' ]: one space after each comma, none before.
		'whitespace_after_comma_in_array'   => true,
		'no_whitespace_before_comma_in_array' => true,

		// Indentation of array elements, statements and method chains.
		'array_indentation'                 => true,
		'statement_indentation'             => true,
		'method_chaining_indentation'       => true,

		// Docblocks: aligned with their code, no blank first or last line.
		'phpdoc_indent'                     => true,
		'phpdoc_trim'                       => true,

		// File hygiene.
		'no_trailing_whitespace'            => true,
		'single_blank_line_at_eof'          => true,
		'line_ending'                       => true,
		'no_closing_tag'                    => true,
	] );