<?php
/**
 * Development server class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Server
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Server;

use SmartLicenseServer\Core\Process\PhpRuntime;
use SmartLicenseServer\Core\Process\Process;
use SmartLicenseServer\Environments\Application\Update\PhpCli;
use SmartLicenseServer\Exceptions\ServerException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Runs the application on PHP's built-in web server, for local development.
 *
 * Philosophy: this class is the only place that knows how the server is
 * started, found and stopped. Callers (the `server` command today; an updater,
 * a file watcher or a service hook tomorrow) describe what they want with a
 * ServerConfig and ask for start, stop, restart or status. They never build a
 * command line, read the state file or signal a process themselves, so how any
 * of that is done can change without changing them.
 *
 * It takes decisions only about the server. It never prints and never asks:
 * problems are thrown as ServerException with a message fit to show, and
 * choices belong to the caller (which address to use when one is taken, how
 * a log line is displayed).
 *
 * Two ways to run, one description:
 *
 *  - Attached ({@see self::run_attached()}): the server is a child of the
 *    caller, which receives its output line by line until it exits. The caller
 *    owns its lifetime; nothing is recorded.
 *
 *  - Managed ({@see self::start()}): the server runs detached from the
 *    terminal in its own session, so closing the terminal does not end it,
 *    writing to SMLISER_LOGS_DIR/server.log. It is recorded in
 *    SMLISER_PRIVATE_DIR/server.json (a {@see ServerInfo}) through the
 *    FileSystem API. That record is what start, stop, restart and status work
 *    from, so no caller needs a process ID. One managed server runs per
 *    application.
 *
 * A recorded process is trusted only while its command line is still this
 * application's router, so a process ID the system has since given to another
 * program is never signalled, and a record whose process is gone is removed.
 * Managed servers need a POSIX system with setsid; elsewhere start() says so.
 *
 * The public URL reaches the application as SMLISER_APP_URL in the server
 * process's own environment; .env is never edited. This relies on DotEnv's
 * immutable mode letting the real environment win over the file. A change to
 * it, like any change to the environment, needs a restart to take effect: PHP
 * code is read again on every request, the environment is not.
 */
final class DevServer {

	/**
	 * Address listened on when none is asked for.
	 *
	 * @var string
	 */
	public const DEFAULT_HOST = '127.0.0.1';

	/**
	 * Port listened on when none is asked for.
	 *
	 * @var int
	 */
	public const DEFAULT_PORT = 8383;

	/**
	 * Probe result: the address can be listened on.
	 *
	 * @var string
	 */
	public const ADDRESS_FREE = 'free';

	/**
	 * Probe result: the host is usable, but something already listens on the port.
	 *
	 * @var string
	 */
	public const PORT_IN_USE = 'port_in_use';

	/**
	 * Probe result: this machine cannot listen on the host at all.
	 *
	 * @var string
	 */
	public const HOST_UNAVAILABLE = 'host_unavailable';

	/**
	 * Name of the state file, in SMLISER_PRIVATE_DIR.
	 *
	 * @var string
	 */
	private const STATE_FILE = 'server.json';

	/**
	 * Name of the managed server's log, in SMLISER_LOGS_DIR.
	 *
	 * @var string
	 */
	private const LOG_FILE = 'server.log';

	/**
	 * Size above which the log is replaced when a managed server starts, in bytes.
	 *
	 * @var int
	 */
	private const LOG_LIMIT = 1048576;

	/**
	 * Consecutive ports tried when looking for a free one.
	 *
	 * @var int
	 */
	private const PORT_SEARCH_LIMIT = 10;

	/**
	 * Seconds a managed server has to start listening.
	 *
	 * @var int
	 */
	private const START_WAIT = 10;

	/**
	 * Seconds a managed server has to exit after SIGTERM.
	 *
	 * @var int
	 */
	private const STOP_WAIT = 10;

	/**
	 * Seconds a managed server has to exit after SIGKILL.
	 *
	 * @var int
	 */
	private const KILL_WAIT = 3;

	/**
	 * Microseconds between checks while waiting.
	 *
	 * @var int
	 */
	private const POLL_INTERVAL = 100000;

	/**
	 * Bytes read from an attached server's stream at a time.
	 *
	 * @var int
	 */
	private const READ_CHUNK = 8192;

	/**
	 * Signal number asking a process to end. posix_kill() takes numbers, and
	 * the SIG* constants exist only with pcntl.
	 *
	 * @var int
	 */
	private const SIGTERM = 15;

	/**
	 * Signal number ending a process that ignored SIGTERM.
	 *
	 * @var int
	 */
	private const SIGKILL = 9;

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs Reads and writes the state file and the log.
	 */
	public function __construct( private FileSystem $fs ) {}

	/*
	|-------
	|Address
	|-------
	*/

	/**
	 * Check whether an address can be listened on, by binding it briefly.
	 *
	 * When the bind fails, binding the same host on port 0 (any free port)
	 * tells the two causes apart without platform-specific error codes. The
	 * check is not a reservation: another process can still take the port
	 * before the server binds it.
	 *
	 * @param string $host
	 * @param int    $port
	 * @return string One of the ADDRESS_FREE, PORT_IN_USE or HOST_UNAVAILABLE constants.
	 */
	public function probe( string $host, int $port ) : string {
		$socket = @stream_socket_server( 'tcp://' . ( new ServerConfig( $host, $port ) )->authority(), $errno, $errstr );

		if ( false !== $socket ) {
			fclose( $socket );
			return self::ADDRESS_FREE;
		}

		$any = @stream_socket_server( 'tcp://' . ( new ServerConfig( $host, 0 ) )->authority(), $errno, $errstr );

		if ( false === $any ) {
			return self::HOST_UNAVAILABLE;
		}

		fclose( $any );

		return self::PORT_IN_USE;
	}

	/**
	 * Find the first port this machine can listen on, starting at $from.
	 *
	 * @param string $host
	 * @param int    $from
	 * @param int    $limit How many consecutive ports to try.
	 * @return int|null Null when none of them is free.
	 */
	public function find_free_port( string $host, int $from, int $limit = self::PORT_SEARCH_LIMIT ) : ?int {
		$last = min( 65535, $from + $limit - 1 );

		for ( $candidate = $from; $candidate <= $last; $candidate++ ) {
			if ( self::ADDRESS_FREE === $this->probe( $host, $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/*
	|--------
	|Attached
	|--------
	*/

	/**
	 * Run the server as a child of the caller until it exits.
	 *
	 * The server gets no stdin and writes into socket pairs, the one
	 * transport that can be polled without blocking on every platform,
	 * Windows included. Its output reaches $on_line one complete line at a
	 * time, unfiltered; what to show is the caller's decision.
	 *
	 * @param ServerConfig  $config     Where to listen.
	 * @param callable      $on_line    Called with each output line: function ( string $line ) : void.
	 * @param callable|null $on_started Called once the server process exists, with a function that
	 *                                  sends the server a signal: function ( callable $send ) : void,
	 *                                  where $send is function ( int $signal ) : bool.
	 * @return array{signaled: bool, termsig: int, exitcode: int} How the server ended.
	 * @throws ServerException When the server process cannot be started.
	 */
	public function run_attached( ServerConfig $config, callable $on_line, ?callable $on_started = null ) : array {
		$php = $this->php();

		PhpRuntime::set_time_limit( 0 );

		$process = proc_open(
			$this->command( $php, $config ),
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'socket' ],
				2 => [ 'socket' ],
			],
			$pipes,
			SMLISER_ROOT,
			$this->environment( $config )
		);

		if ( ! is_resource( $process ) ) {
			throw new ServerException( 'The server process could not be started.' );
		}

		// The server never reads input; closing stdin gives it an immediate EOF.
		fclose( $pipes[0] );

		if ( null !== $on_started ) {
			$on_started( static fn ( int $signal ) : bool => proc_terminate( $process, $signal ) );
		}

		$status = $this->relay( $process, [ 1 => $pipes[1], 2 => $pipes[2] ], $on_line );

		return [
			'signaled' => (bool) $status['signaled'],
			'termsig'  => (int) $status['termsig'],
			'exitcode' => (int) $status['exitcode'],
		];
	}

	/*
	|-------
	|Managed
	|-------
	*/

	/**
	 * Start the managed server.
	 *
	 * Returns once the server accepts connections. It is not started on a
	 * different address when this one is taken: that is for the caller to
	 * decide before calling.
	 *
	 * @param ServerConfig $config Where to listen.
	 * @return ServerInfo The running server.
	 * @throws ServerException When one is already running, the address cannot be used, or the server does not come up.
	 */
	public function start( ServerConfig $config ) : ServerInfo {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			throw new ServerException( 'A background server needs Linux or macOS. On Windows, run the server in its own terminal.' );
		}

		$running = $this->status();

		if ( null !== $running ) {
			throw new ServerException( sprintf( 'A server is already running at %s (process %d).', $running->config->url(), $running->pid ) );
		}

		$setsid = Process::find_executable( 'setsid' );

		if ( null === $setsid ) {
			throw new ServerException( 'A background server needs the setsid utility, which this system does not provide.' );
		}

		$php   = $this->php();
		$probe = $this->probe( $config->host, $config->port );

		if ( self::HOST_UNAVAILABLE === $probe ) {
			throw new ServerException( sprintf( 'This machine cannot listen on %s.', $config->host ) );
		}

		if ( self::PORT_IN_USE === $probe ) {
			throw new ServerException( sprintf( 'Port %d is already in use on %s.', $config->port, $config->host ) );
		}

		$log = $this->prepare_log();

		$process = @proc_open(
			array_merge( [ $setsid ], $this->command( $php, $config ) ),
			[
				0 => [ 'file', '/dev/null', 'r' ],
				1 => [ 'file', $log, 'a' ],
				2 => [ 'file', $log, 'a' ],
			],
			$pipes,
			SMLISER_ROOT,
			$this->environment( $config )
		);

		if ( ! is_resource( $process ) ) {
			throw new ServerException( 'The server process could not be started.' );
		}

		$pid = (int) proc_get_status( $process )['pid'];

		if ( ! $this->wait_until_listening( $process, $config ) ) {
			$tail = $this->log_tail();

			throw new ServerException(
				sprintf( 'The server did not start listening. See %s.', $log )
				. ( [] === $tail ? '' : PHP_EOL . 'Last log lines:' . PHP_EOL . implode( PHP_EOL, $tail ) )
			);
		}

		$info = new ServerInfo( $config, $pid, gmdate( 'Y-m-d H:i:s' ), $log );

		// A server nothing can find again would have to be hunted down by hand.
		if ( ! $this->fs->put_contents( $this->state_path(), (string) json_encode( $info->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) ) {
			$this->signal( $pid, self::SIGTERM );
			throw new ServerException( sprintf( 'The server was started but could not be recorded in %s, so it was stopped again.', $this->state_path() ) );
		}

		return $info;
	}

	/**
	 * Stop the managed server.
	 *
	 * @param bool $force Send SIGKILL when it has not exited after SIGTERM.
	 * @return ServerInfo|null The server that was stopped; null when none was running.
	 * @throws ServerException When it does not stop.
	 */
	public function stop( bool $force = false ) : ?ServerInfo {
		$info = $this->status();

		if ( null === $info ) {
			return null;
		}

		$this->signal( $info->pid, self::SIGTERM );

		if ( ! $this->wait_until_gone( $info->pid, self::STOP_WAIT ) ) {
			if ( ! $force ) {
				throw new ServerException( sprintf( 'The server (process %d) did not stop within %d seconds. Stop it anyway with force.', $info->pid, self::STOP_WAIT ) );
			}

			$this->signal( $info->pid, self::SIGKILL );

			if ( ! $this->wait_until_gone( $info->pid, self::KILL_WAIT ) ) {
				throw new ServerException( sprintf( 'The server (process %d) could not be stopped.', $info->pid ) );
			}
		}

		$this->fs->delete( $this->state_path() );

		return $info;
	}

	/**
	 * Stop the managed server and start it again.
	 *
	 * Starts it again at the address it had, unless another is given. The new
	 * process reads the environment afresh, which is what makes a restart the
	 * way to apply a changed public URL or .env. If nothing was running and no
	 * config is given, there is nothing to restart.
	 *
	 * @param ServerConfig|null $config Where to listen; null for where it was.
	 * @return ServerInfo The running server.
	 * @throws ServerException When there is nothing to restart, it does not stop, or it does not start.
	 */
	public function restart( ?ServerConfig $config = null ) : ServerInfo {
		$config ??= $this->status()?->config;

		if ( null === $config ) {
			throw new ServerException( 'No server is running, so there is no address to restart it at.' );
		}

		$this->stop();

		return $this->start( $config );
	}

	/**
	 * The managed server, when it is running.
	 *
	 * @return ServerInfo|null Null when none is running. A record whose
	 *                         process is gone is removed.
	 */
	public function status() : ?ServerInfo {
		$path = $this->state_path();

		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}

		$info = ServerInfo::from_array( json_decode( (string) $this->fs->get_contents( $path ), true ) );

		if ( null !== $info && $this->is_server_process( $info->pid ) ) {
			return $info;
		}

		$this->fs->delete( $path );

		return null;
	}

	/**
	 * Whether a managed server is running.
	 *
	 * @return bool
	 */
	public function is_running() : bool {
		return null !== $this->status();
	}

	/**
	 * The file the managed server writes to.
	 *
	 * @return string
	 */
	public function log_path() : string {
		return rtrim( SMLISER_LOGS_DIR, '/\\' ) . DIRECTORY_SEPARATOR . self::LOG_FILE;
	}

	/**
	 * The last lines of the managed server's log.
	 *
	 * @param int $lines How many.
	 * @return string[] Oldest first; empty when there is no log.
	 */
	public function log_tail( int $lines = 10 ) : array {
		$path = $this->log_path();

		if ( ! $this->fs->is_file( $path ) ) {
			return [];
		}

		$contents = $this->fs->get_contents( $path );

		if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
			return [];
		}

		return array_slice( preg_split( '/\R/', rtrim( $contents ) ) ?: [], -max( 1, $lines ) );
	}

	/*
	|--------
	|Internal
	|--------
	*/

	/**
	 * The PHP command-line binary.
	 *
	 * @return string
	 * @throws ServerException When none can be started.
	 */
	private function php() : string {
		$php = PhpCli::find();

		if ( null === $php ) {
			throw new ServerException( 'No PHP command-line binary could be started (proc_open may be disabled).' );
		}

		return $php;
	}

	/**
	 * The command that runs the server.
	 *
	 * @param string       $php
	 * @param ServerConfig $config
	 * @return list<string>
	 */
	private function command( string $php, ServerConfig $config ) : array {
		return [ $php, '-S', $config->authority(), '-t', SMLISER_ROOT . 'public', $this->router_path() ];
	}

	/**
	 * The environment the server runs with.
	 *
	 * @param ServerConfig $config
	 * @return array<string,mixed>
	 */
	private function environment( ServerConfig $config ) : array {
		$env                    = (array) getenv();
		$env['SMLISER_APP_URL'] = $config->app_url();

		return $env;
	}

	/**
	 * The router the server runs. It is also how the server is recognised in the process list.
	 *
	 * @return string
	 */
	private function router_path() : string {
		return SMLISER_ROOT . 'server/server.php';
	}

	/**
	 * @return string
	 */
	private function state_path() : string {
		return rtrim( SMLISER_PRIVATE_DIR, '/\\' ) . DIRECTORY_SEPARATOR . self::STATE_FILE;
	}

	/**
	 * Make sure the log can be written, replacing one that has grown too large.
	 *
	 * @return string The log path.
	 * @throws ServerException When it cannot be prepared.
	 */
	private function prepare_log() : string {
		$log       = $this->log_path();
		$directory = dirname( $log );

		if ( ! $this->fs->is_dir( $directory ) && ! $this->fs->mkdir( $directory ) && ! $this->fs->is_dir( $directory ) ) {
			throw new ServerException( sprintf( 'The log directory %s could not be created.', $directory ) );
		}

		if ( $this->fs->is_file( $log ) && (int) $this->fs->filesize( $log ) > self::LOG_LIMIT ) {
			$this->fs->delete( $log );
		}

		return $log;
	}

	/**
	 * Wait for a just-started server to accept connections.
	 *
	 * @param resource     $process The started process.
	 * @param ServerConfig $config
	 * @return bool False when it exited, or was not reachable in time. A server that did not exit is stopped.
	 */
	private function wait_until_listening( $process, ServerConfig $config ) : bool {
		$probe    = match ( $config->host ) {
			'0.0.0.0' => '127.0.0.1',
			'::'      => '::1',
			default   => $config->host,
		};
		$target   = 'tcp://' . ( new ServerConfig( $probe, $config->port ) )->authority();
		$deadline = microtime( true ) + self::START_WAIT;

		do {
			if ( ! proc_get_status( $process )['running'] ) {
				proc_close( $process );
				return false;
			}

			$socket = @stream_socket_client( $target, $errno, $errstr, 0.1 );

			if ( false !== $socket ) {
				fclose( $socket );
				return true;
			}

			usleep( self::POLL_INTERVAL );
		} while ( microtime( true ) < $deadline );

		proc_terminate( $process );
		proc_close( $process );

		return false;
	}

	/**
	 * Wait for a process to end.
	 *
	 * @param int $pid
	 * @param int $seconds
	 * @return bool Whether it is gone.
	 */
	private function wait_until_gone( int $pid, int $seconds ) : bool {
		$deadline = microtime( true ) + $seconds;

		while ( $this->is_server_process( $pid ) ) {
			if ( microtime( true ) >= $deadline ) {
				return false;
			}

			usleep( self::POLL_INTERVAL );
		}

		return true;
	}

	/**
	 * Whether a process is this application's server.
	 *
	 * Checks the process's command line, not only that the ID exists, so an ID
	 * the system has since given to another program is never taken for it.
	 *
	 * @param int $pid
	 * @return bool
	 */
	private function is_server_process( int $pid ) : bool {
		if ( $pid < 1 ) {
			return false;
		}

		$ps = Process::run( [ 'ps', '-ww', '-p', (string) $pid, '-o', 'args=' ], 5 );

		if ( 0 === $ps['exit_code'] ) {
			return str_contains( $ps['output'], $this->router_path() );
		}

		// ps exits 1 when there is no such process; anything else means ps itself is unavailable.
		if ( 1 === $ps['exit_code'] ) {
			return false;
		}

		return function_exists( 'posix_kill' ) && posix_kill( $pid, 0 );
	}

	/**
	 * Send a signal to a process.
	 *
	 * @param int $pid
	 * @param int $signal
	 * @return void
	 */
	private function signal( int $pid, int $signal ) : void {
		if ( function_exists( 'posix_kill' ) ) {
			posix_kill( $pid, $signal );
			return;
		}

		Process::run( [ 'kill', '-' . $signal, (string) $pid ], 5 );
	}

	/**
	 * Hand an attached server's output to $on_line until it has exited and both streams are drained.
	 *
	 * @param resource             $process
	 * @param array<int, resource> $streams The server's stdout and stderr, keyed by descriptor.
	 * @param callable             $on_line
	 * @return array<string, mixed> The proc_get_status() result that saw the server exit.
	 */
	private function relay( $process, array $streams, callable $on_line ) : array {
		$buffers = array_fill_keys( array_keys( $streams ), '' );

		foreach ( $streams as $stream ) {
			stream_set_blocking( $stream, false );
		}

		while ( true ) {
			$read   = array_values( $streams );
			$write  = null;
			$except = null;

			// False when a signal interrupts the wait; the loop just checks again.
			$ready = empty( $read ) ? 0 : (int) @stream_select( $read, $write, $except, 0, self::POLL_INTERVAL );

			foreach ( $ready > 0 ? $read : [] as $stream ) {
				$key   = array_search( $stream, $streams, true );
				$chunk = fread( $stream, self::READ_CHUNK );

				if ( false === $chunk || ( '' === $chunk && feof( $stream ) ) ) {
					$this->emit_rest( $buffers[ $key ], $on_line );
					fclose( $stream );
					unset( $streams[ $key ] );
					continue;
				}

				$buffers[ $key ] .= $chunk;
				$this->emit_lines( $buffers[ $key ], $on_line );
			}

			$status = proc_get_status( $process );

			if ( ! $status['running'] && ( empty( $streams ) || 0 === $ready ) ) {
				break;
			}

			if ( empty( $streams ) ) {
				usleep( self::POLL_INTERVAL );
			}
		}

		foreach ( $streams as $key => $stream ) {
			$buffers[ $key ] .= (string) stream_get_contents( $stream );
			$this->emit_rest( $buffers[ $key ], $on_line );
			fclose( $stream );
		}

		proc_close( $process );

		return $status;
	}

	/**
	 * Pass every complete line in $buffer on, leaving any partial line in it.
	 *
	 * @param string   $buffer
	 * @param callable $on_line
	 * @return void
	 */
	private function emit_lines( string &$buffer, callable $on_line ) : void {
		$lines  = explode( "\n", $buffer );
		$buffer = (string) array_pop( $lines );

		foreach ( $lines as $line ) {
			$on_line( $line );
		}
	}

	/**
	 * Pass on what is left in $buffer once its stream has ended.
	 *
	 * @param string   $buffer
	 * @param callable $on_line
	 * @return void
	 */
	private function emit_rest( string &$buffer, callable $on_line ) : void {
		$this->emit_lines( $buffer, $on_line );

		if ( '' !== $buffer ) {
			$on_line( $buffer );
			$buffer = '';
		}
	}
}
