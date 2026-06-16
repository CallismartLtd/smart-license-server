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
 *
 * Repeated options are collected into arrays:
 *
 * --role admin --role editor
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

			if ( ! str_starts_with( $arg, '--' ) ) {
				$arguments[] = $arg;
				continue;
			}

			$arg = substr( $arg, 2 );

			// --key=value
			if ( str_contains( $arg, '=' ) ) {

				[ $key, $value ] = explode( '=', $arg, 2 );

				$this->store_option(
					$options,
					$key,
					$this->normalize( $value )
				);

				continue;
			}

			// --key value
			$next = $args[ $i + 1 ] ?? null;

			if (
				is_string( $next ) &&
				! str_starts_with( $next, '--' )
			) {

				$this->store_option(
					$options,
					$arg,
					$this->normalize( $next )
				);

				$i++;
				continue;
			}

			// --flag
			$this->store_option(
				$options,
				$arg,
				true
			);
		}

		return [
			'arguments' => $arguments,
			'options'   => $options,
		];
	}

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