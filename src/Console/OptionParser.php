<?php
/**
 * CLI option parser.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * Parses command line arguments into positional arguments and options.
 *
 * Supported formats:
 *
 * --name=value
 * --name value
 * --flag
 * -f value
 * -f
 * -abc        (bundled boolean short flags: -a -b -c)
 *
 * Repeated options are collected into arrays:
 *
 * --role admin --role editor
 * -r admin -r editor
 *
 * Produces:
 *
 * [
 *     'arguments' => [...],
 *     'options'   => [...]
 * ]
 *
 * @since 0.2.0
 */
class OptionParser {

	/**
	 * Parse command arguments.
	 *
	 * @param array $args Raw command arguments.
	 * @return array{
	 *     arguments: array,
	 *     options: array
	 * }
	 */
	public function parse( array $args ): array {

		$options   = [];
		$arguments = [];

		$count = count( $args );

		for ( $i = 0; $i < $count; $i++ ) {

			$arg = $args[ $i ];

			if ( ! is_string( $arg ) ) {
				continue;
			}

			if ( str_starts_with( $arg, '--' ) ) {
				$this->parse_long_option( $arg, $args, $i, $options );
				continue;
			}

			if ( $this->is_short_flag( $arg ) ) {
				$this->parse_short_flag( $arg, $args, $i, $options );
				continue;
			}

			$arguments[] = $arg;
		}

		return [
			'arguments' => $arguments,
			'options'   => $options,
		];
	}

	/*
	|------------------
	| OPTION PARSING
	|------------------
	*/

	/**
	 * Parse a `--name`, `--name=value`, or `--name value` token.
	 *
	 * @param string $arg     The raw `--...` token.
	 * @param array  $args    The full argument list, for value lookahead.
	 * @param int    &$i      Current cursor position; advanced when a value is consumed.
	 * @param array  &$options Options collected so far.
	 * @return void
	 */
	protected function parse_long_option( string $arg, array $args, int &$i, array &$options ): void {

		$arg = substr( $arg, 2 );

		// --key=value
		if ( str_contains( $arg, '=' ) ) {

			[ $key, $value ] = explode( '=', $arg, 2 );

			$this->store_option(
				$options,
				$key,
				$this->normalize( $value )
			);

			return;
		}

		// --key value
		$next = $args[ $i + 1 ] ?? null;

		if (
			is_string( $next ) &&
			! $this->looks_like_option( $next )
		) {

			$this->store_option(
				$options,
				$arg,
				$this->normalize( $next )
			);

			$i++;
			return;
		}

		// --flag
		$this->store_option(
			$options,
			$arg,
			true
		);
	}

	/**
	 * Parse a `-f`, `-f value`, or bundled `-abc` token.
	 *
	 * A bundle of more than one letter is treated as separate boolean
	 * flags (`-abc` => `-a -b -c`); only a single-letter short flag can
	 * consume a following value. `-x=value` and attached values like
	 * `-x5` are not supported.
	 *
	 * @param string $arg     The raw `-...` token.
	 * @param array  $args    The full argument list, for value lookahead.
	 * @param int    &$i      Current cursor position; advanced when a value is consumed.
	 * @param array  &$options Options collected so far.
	 * @return void
	 */
	protected function parse_short_flag( string $arg, array $args, int &$i, array &$options ): void {

		$flags = substr( $arg, 1 );

		// Bundled boolean flags: -abc => -a -b -c
		if ( strlen( $flags ) > 1 ) {

			foreach ( str_split( $flags ) as $flag ) {
				$this->store_option( $options, $flag, true );
			}

			return;
		}

		// -f value
		$next = $args[ $i + 1 ] ?? null;

		if (
			is_string( $next ) &&
			! $this->looks_like_option( $next )
		) {

			$this->store_option(
				$options,
				$flags,
				$this->normalize( $next )
			);

			$i++;
			return;
		}

		// -f
		$this->store_option(
			$options,
			$flags,
			true
		);
	}

	/**
	 * Determine whether a token is a short flag (`-f`, `-abc`) rather than
	 * a negative-number positional argument (`-5`, `-5.2`).
	 *
	 * @param string $value
	 * @return bool
	 */
	protected function is_short_flag( string $value ): bool {
		return 1 === preg_match( '/^-[A-Za-z]+$/', $value );
	}

	/**
	 * Determine whether a token is itself an option (long or short),
	 * and therefore not eligible to be consumed as another option's value.
	 *
	 * @param string $value
	 * @return bool
	 */
	protected function looks_like_option( string $value ): bool {
		return str_starts_with( $value, '--' ) || $this->is_short_flag( $value );
	}

	/*
	|------------------
	| VALUE HANDLING
	|------------------
	*/

	/**
	 * Store an option value.
	 *
	 * Repeated keys are converted to arrays.
	 *
	 * @param array  $options
	 * @param string $key
	 * @param mixed  $value
	 * @return void
	 */
	protected function store_option(
		array &$options,
		string $key,
		$value
	): void {

		if ( ! isset( $options[ $key ] ) ) {
			$options[ $key ] = $value;
			return;
		}

		$options[ $key ] = array_merge(
			(array) $options[ $key ],
			[ $value ]
		);
	}

	/**
	 * Normalize common scalar values.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected function normalize( mixed $value ) : mixed {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		return match ( strtolower( $value ) ) {
			'true'  => true,
			'false' => false,
			'null'  => null,
			default => $value,
		};
	}
}