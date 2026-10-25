<?php
/**
 * Process class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Process
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core\Process;

/**
 * Runs an external program and reports what it did.
 *
 * Programs are started without a shell: the command is an argument list
 * passed straight to proc_open(), so nothing in an argument (a file name,
 * an option value) is ever interpreted by a shell. A program that runs
 * past its time limit is stopped. Its output and error output are kept,
 * so a failure can say why.
 */
final class Process {

	/**
	 * Directories searched for programs besides PATH, which is often short
	 * under PHP-FPM.
	 *
	 * @var string[]
	 */
	private const COMMON_DIRS = [ '/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin', '/opt/local/bin' ];

	/**
	 * Whether this PHP can start programs at all (proc_open() exists and is not disabled).
	 *
	 * @return bool
	 */
	public static function can_run() : bool {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return ! in_array( 'proc_open', $disabled, true );
	}

	/**
	 * The path of the first program found by one of the names.
	 *
	 * Searches PATH, then COMMON_DIRS. On Windows a name is also tried with ".exe".
	 *
	 * @param string ...$names Program names, most preferred first, e.g. "gs".
	 * @return string|null Null when none is found or programs cannot be run.
	 */
	public static function find_executable( string ...$names ) : ?string {
		if ( ! self::can_run() ) {
			return null;
		}

		$dirs = array_unique( array_filter( array_merge( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ), self::COMMON_DIRS ) ) );

		foreach ( $names as $name ) {
			foreach ( $dirs as $dir ) {
				foreach ( '\\' === DIRECTORY_SEPARATOR ? [ $name . '.exe', $name ] : [ $name ] as $file ) {
					$path = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $file;

					if ( @is_file( $path ) && @is_executable( $path ) ) {
						return $path;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Run a program and wait for it, at most $timeout seconds.
	 *
	 * @param list<string> $command The program, then its arguments.
	 * @param int          $timeout Seconds before the program is stopped.
	 * @param string|null  $cwd     Working directory; null for this process's.
	 * @return array{exit_code: int, output: string, error: string, timed_out: bool}
	 *         exit_code is -1 when the program could not be started or was stopped.
	 */
	public static function run( array $command, int $timeout = 60, ?string $cwd = null ) : array {
		$result = [ 'exit_code' => -1, 'output' => '', 'error' => '', 'timed_out' => false ];

		if ( ! self::can_run() || [] === $command ) {
			$result['error'] = 'Programs cannot be started from this PHP (proc_open is unavailable).';
			return $result;
		}

		$process = @proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes, $cwd );

		if ( ! is_resource( $process ) ) {
			$result['error'] = sprintf( 'The program "%s" could not be started.', $command[0] );
			return $result;
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$deadline = microtime( true ) + max( 1, $timeout );
		$status   = proc_get_status( $process );

		// Read while it runs, so a chatty program never blocks on a full pipe.
		while ( $status['running'] ) {
			$result['output'] .= (string) stream_get_contents( $pipes[1] );
			$result['error']  .= (string) stream_get_contents( $pipes[2] );

			if ( microtime( true ) >= $deadline ) {
				proc_terminate( $process, 9 );
				$result['timed_out'] = true;
				break;
			}

			usleep( 20000 );
			$status = proc_get_status( $process );
		}

		$result['output'] .= (string) stream_get_contents( $pipes[1] );
		$result['error']  .= (string) stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$closed = proc_close( $process );

		// proc_get_status() holds the exit code once the program has ended; proc_close() then returns -1.
		$result['exit_code'] = $result['timed_out'] ? -1 : ( $status['running'] ? $closed : (int) $status['exitcode'] );

		return $result;
	}
}