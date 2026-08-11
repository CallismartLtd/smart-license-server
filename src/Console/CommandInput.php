<?php
/**
 * Command input Data Transfer Object file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * Encapsulates parsed command-line input for a executed command handler.
 *
 * This Data Transfer Object (DTO) carries structured positional arguments and
 * named options/flags extracted from the raw CLI token stream by OptionParser.
 * It provides immutable accessor methods for reading arguments by index/name,
 * checking option presence, and retrieving default fallback values.
 */
final class CommandInput {

	/**
	 * Constructor.
	 *
	 * @param array<int|string, mixed> $arguments Positional arguments indexed numerically or by defined name.
	 * @param array<string, mixed>     $options   Named options and flags keyed by option name (without leading `--`).
	 */
	public function __construct(
		private array $arguments = array(),
		private array $options   = array(),
	) {}

	/*
	|--------------
	| ARGUMENTS
	|--------------
	*/

	/**
	 * Retrieve a positional argument by its numeric index or mapped name.
	 *
	 * @param int|string $key     Zero-based positional index or argument name.
	 * @param mixed      $default Fallback value returned if the argument was not supplied.
	 * @return mixed
	 */
	public function get_argument( int|string $key, mixed $default = null ): mixed {
		return $this->arguments[ $key ] ?? $default;
	}

	/**
	 * Determine whether a positional argument was supplied.
	 *
	 * @param int|string $key Zero-based positional index or argument name.
	 * @return bool
	 */
	public function has_argument( int|string $key ): bool {
		return array_key_exists( $key, $this->arguments );
	}

	/**
	 * Retrieve all parsed positional arguments.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_arguments(): array {
		return $this->arguments;
	}

	/*
	|--------------
	| OPTIONS
	|--------------
	*/

	/**
	 * Retrieve a named option or flag value.
	 *
	 * @param string $name    Option name without leading dashes (e.g. "format" or "force").
	 * @param mixed  $default Fallback value returned if the option was not provided.
	 * @return mixed
	 */
	public function get_option( string $name, mixed $default = null ): mixed {
		return $this->options[ $name ] ?? $default;
	}

	/**
	 * Determine whether a named option was explicitly provided in the token stream.
	 *
	 * Distinguishes between an option passed with an empty value (`--output=`) versus
	 * an option that was omitted entirely.
	 *
	 * @param string $name Option name without leading dashes.
	 * @return bool
	 */
	public function has_option( string $name ): bool {
		return array_key_exists( $name, $this->options );
	}

	/**
	 * Retrieve all parsed options and flags.
	 *
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		return $this->options;
	}
}