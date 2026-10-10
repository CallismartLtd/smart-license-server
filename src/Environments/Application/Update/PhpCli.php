<?php
/**
 * PHP CLI locator class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

/**
 * Finds a PHP command-line binary that can run the application's console.
 *
 * In a CLI process that is simply PHP_BINARY. Under PHP-FPM, LiteSpeed or
 * mod_php, PHP_BINARY is the server binary (or empty), so the usual
 * install locations are tried instead: the same directory as this PHP,
 * versioned names (php8.4), and the layouts of cPanel (ea-php84), Plesk
 * and common distributions. A candidate is accepted only when it is the
 * CLI SAPI of the same PHP major.minor version as this process, so the
 * console never runs on a PHP the application was not checked against.
 *
 * Needs proc_open() to run a candidate; without it, nothing is found.
 */
final class PhpCli {

	/**
	 * Seconds allowed for a candidate to report its version.
	 *
	 * @var int
	 */
	private const PROBE_TIMEOUT = 5;

	/**
	 * Result for this process: a path, false when none was found, null before looking.
	 *
	 * @var string|false|null
	 */
	private static string|false|null $found = null;

	/**
	 * A usable PHP CLI binary, or null when there is none (or proc_open is disabled).
	 *
	 * @return string|null
	 */
	public static function find() : ?string {
		if ( null === self::$found ) {
			self::$found = self::search() ?? false;
		}

		return false === self::$found ? null : self::$found;
	}

	/**
	 * Whether a new PHP process can be started at all.
	 *
	 * @return bool
	 */
	public static function can_spawn() : bool {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return ! in_array( 'proc_open', $disabled, true );
	}

	/**
	 * Look through the candidates.
	 *
	 * @return string|null
	 */
	private static function search() : ?string {
		if ( ! self::can_spawn() ) {
			return null;
		}

		if ( 'cli' === PHP_SAPI && '' !== PHP_BINARY ) {
			return PHP_BINARY;
		}

		foreach ( self::candidates() as $candidate ) {
			if ( ! @is_file( $candidate ) || ! @is_executable( $candidate ) ) {
				continue;
			}

			if ( self::is_matching_cli( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Paths to try, most likely first.
	 *
	 * @return string[]
	 */
	private static function candidates() : array {
		$major_minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
		$compact     = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
		$bindir      = rtrim( PHP_BINDIR, '/' );

		return array_values(
			array_unique(
				array(
					"{$bindir}/php{$major_minor}",
					"{$bindir}/php",
					"/usr/bin/php{$major_minor}",
					"/usr/local/bin/php{$major_minor}",
					"/usr/local/bin/ea-php{$compact}",                   // cPanel EasyApache.
					"/opt/cpanel/ea-php{$compact}/root/usr/bin/php",     // cPanel EasyApache.
					"/opt/plesk/php/{$major_minor}/bin/php",              // Plesk.
					"/opt/alt/php{$compact}/usr/bin/php",                 // CloudLinux alt-php.
					"/usr/local/php{$compact}/bin/php",                   // DirectAdmin.
					'/usr/local/bin/php',
					'/usr/bin/php',
				)
			)
		);
	}

	/**
	 * Whether a binary is the CLI SAPI of this PHP's major.minor version.
	 *
	 * @param string $binary Path.
	 * @return bool
	 */
	private static function is_matching_cli( string $binary ) : bool {
		$expected = 'cli|' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		$process = @proc_open(
			array( $binary, '-r', 'echo PHP_SAPI, "|", PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);

		if ( ! is_resource( $process ) ) {
			return false;
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );

		$output   = '';
		$deadline = microtime( true ) + self::PROBE_TIMEOUT;

		while ( microtime( true ) < $deadline ) {
			$output .= (string) stream_get_contents( $pipes[1] );

			if ( ! proc_get_status( $process )['running'] ) {
				$output .= (string) stream_get_contents( $pipes[1] );
				break;
			}

			usleep( 20000 );
		}

		fclose( $pipes[1] );
		fclose( $pipes[2] );

		if ( proc_get_status( $process )['running'] ) {
			proc_terminate( $process );
		}

		proc_close( $process );

		return $expected === trim( $output );
	}
}