<?php
/**
 * Env file writer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Environments\Application\Installation;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class EnvFileWriter
 *
 * Safe, comment-preserving .env configuration writer.
 */
class EnvFileWriter {

	/**
	 * Path to the target production .env file.
	 *
	 * @var string
	 */
	private string $target_path;

	/**
	 * File content represented as an array of individual lines.
	 *
	 * @var array<int, string>
	 */
	private array $lines = array();

	/**
	 * Map of key names to line indices in $this->lines for fast lookup.
	 *
	 * @var array<string, int>
	 */
	private array $key_index_map = array();

	/*
	|--------------------------
	| CONSTRUCTOR & FACTORY
	|--------------------------
	*/

	/**
	 * EnvFileWriter constructor.
	 *
	 * @param string $target_path Absolute or relative path to the target .env file.
	 */
	public function __construct( string $target_path ) {
		$this->target_path = $target_path;

		if ( file_exists( $this->target_path ) ) {
			$this->load_from_file( $this->target_path );
		}
	}

	/**
	 * Create an instance pre-populated with the production block extracted from an example file.
	 *
	 * @param string $example_path Path to the template file (.env.example).
	 * @param string $target_path  Path to the output .env file.
	 * @return static
	 * @throws InvalidArgumentException If the example template file does not exist.
	 * @throws RuntimeException         If reading the template file fails.
	 */
	public static function create_from_example( string $example_path, string $target_path ): static {
		if ( ! file_exists( $example_path ) ) {
			throw new InvalidArgumentException( "Example template not found at path: {$example_path}" );
		}

		$content = file_get_contents( $example_path );
		if ( false === $content ) {
			throw new RuntimeException( "Failed to read content from example path: {$example_path}" );
		}

		$pattern = '/#\s*<SMLISER-PRODUCTION-BEGIN>(.*?)#\s*<SMLISER-PRODUCTION-END>/s';
		if ( preg_match( $pattern, $content, $matches ) ) {
			$extracted_content = trim( $matches[1] );
		} else {
			$extracted_content = $content;
		}

		$instance = new static( $target_path );
		$instance->parse_lines( explode( "\n", $extracted_content ) );

		return $instance;
	}

	/*
	|-----------
	| SETTERS & MODIFIERS
	|-----------
	*/

	/**
	 * Set or update a single environment variable key-value pair.
	 *
	 * @param string      $key   Environment variable key name.
	 * @param string|null $value Variable value. Null will result in an empty value.
	 * @return static
	 * @throws InvalidArgumentException If the provided key is empty.
	 */
	public function set( string $key, ?string $value ): static {
		if ( '' === trim( $key ) ) {
			throw new InvalidArgumentException( 'Environment key cannot be empty.' );
		}

		$formatted_value = $this->format_value( $value );

		if ( isset( $this->key_index_map[ $key ] ) ) {
			$line_index                  = $this->key_index_map[ $key ];
			$this->lines[ $line_index ] = "{$key}={$formatted_value}";
		} else {
			$this->lines[]               = "{$key}={$formatted_value}";
			$this->key_index_map[ $key ] = count( $this->lines ) - 1;
		}

		return $this;
	}

	/**
	 * Set multiple key-value pairs at once.
	 *
	 * @param array<string, string|null> $values Associative array of keys and values.
	 * @return static
	 */
	public function set_multiple( array $values ): static {
		foreach ( $values as $key => $value ) {
			$this->set( $key, $value );
		}

		return $this;
	}

	/*
	|--------------
	| PERSISTENCE
	|--------------
	*/

	/**
	 * Write the current state to the target file atomically.
	 *
	 * @return bool True on successful write, false on failure.
	 * @throws RuntimeException If target directory is not writable.
	 */
	public function save(): bool {
		$target_dir = dirname( $this->target_path );
		if ( ! is_dir( $target_dir ) || ! is_writable( $target_dir ) ) {
			throw new RuntimeException( "Target directory is not writable: {$target_dir}" );
		}

		$content   = implode( "\n", $this->lines ) . "\n";
		$temp_path = $this->target_path . '.' . uniqid( 'tmp_', true );

		if ( false === file_put_contents( $temp_path, $content, LOCK_EX ) ) {
			return false;
		}

		return rename( $temp_path, $this->target_path );
	}

	/*
	|-----------------------------
	| INTERNAL PARSING & HELPERS
	|-----------------------------
	*/

	/**
	 * Read file content and delegate line-by-line parsing.
	 *
	 * @param string $file_path Absolute or relative file path.
	 * @return void
	 * @throws RuntimeException If unable to read target file.
	 */
	private function load_from_file( string $file_path ): void {
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			throw new RuntimeException( "Failed to read target file: {$file_path}" );
		}

		$this->parse_lines( explode( "\n", $content ) );
	}

	/**
	 * Parse array of raw lines into memory and index existing keys.
	 *
	 * @param array<int, string> $raw_lines Raw lines from configuration template or file.
	 * @return void
	 */
	private function parse_lines( array $raw_lines ): void {
		$this->lines         = array();
		$this->key_index_map = array();

		foreach ( $raw_lines as $line ) {
			$trimmed_line  = trim( $line );
			$this->lines[] = rtrim( $line, "\r\n" );

			if ( '' === $trimmed_line || str_starts_with( $trimmed_line, '#' ) ) {
				continue;
			}

			if ( str_contains( $trimmed_line, '=' ) ) {
				$parts                       = explode( '=', $trimmed_line, 2 );
				$key                         = trim( $parts[0] );
				$this->key_index_map[ $key ] = count( $this->lines ) - 1;
			}
		}
	}

	/**
	 * Format values safely to comply with .env parsing rules.
	 *
	 * @param string|null $value The value to format.
	 * @return string Formatted value suitable for .env format.
	 */
	private function format_value( ?string $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( in_array( strtolower( $value ), array( 'true', 'false' ), true ) ) {
			return strtolower( $value );
		}

		if ( preg_match( '/\s|[#$"\'\\\\]/', $value ) ) {
			return '"' . addcslashes( $value, '"\\$' ) . '"';
		}

		return $value;
	}
}