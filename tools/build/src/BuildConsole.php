<?php
/**
 * Build console class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Minimal ANSI-aware console writer for the builder.
 */
final class BuildConsole {

	/**
	 * Whether ANSI colors are enabled.
	 *
	 * @var bool
	 */
	private bool $colors;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->colors = false === getenv( 'NO_COLOR' )
			&& function_exists( 'stream_isatty' )
			&& stream_isatty( STDOUT );
	}

	/**
	 * Print a step heading.
	 *
	 * @param string $message Message.
	 */
	public function step( string $message ): void {
		$this->write( PHP_EOL . $this->paint( '==> ', '1;36' ) . $this->paint( $message, '1' ) );
	}

	/**
	 * Print an informational line.
	 *
	 * @param string $message Message.
	 */
	public function info( string $message ): void {
		$this->write( '    ' . $message );
	}

	/**
	 * Print a success line.
	 *
	 * @param string $message Message.
	 */
	public function success( string $message ): void {
		$this->write( '    ' . $this->paint( '✔ ', '32' ) . $message );
	}

	/**
	 * Print a warning line to STDERR.
	 *
	 * @param string $message Message.
	 */
	public function warn( string $message ): void {
		fwrite( STDERR, '    ' . $this->paint( '! ', '33' ) . $message . PHP_EOL );
	}

	/**
	 * Print a fatal error to STDERR.
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void {
		fwrite( STDERR, PHP_EOL . $this->paint( 'Build failed: ', '1;31' ) . $message . PHP_EOL );
	}

	/**
	 * Write a raw line to STDOUT.
	 *
	 * @param string $line Line.
	 */
	public function write( string $line ): void {
		fwrite( STDOUT, $line . PHP_EOL );
	}

	/**
	 * Wrap text in an SGR sequence when colors are enabled.
	 *
	 * @param string $text Text.
	 * @param string $code SGR code.
	 * @return string
	 */
	private function paint( string $text, string $code ): string {
		return $this->colors ? "\033[{$code}m{$text}\033[0m" : $text;
	}
}