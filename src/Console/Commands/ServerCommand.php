<?php
/**
 * Server command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use InvalidArgumentException;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\SignalInfo;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Core\Process\PhpRuntime;
use SmartLicenseServer\Environments\Application\Update\PhpCli;

/**
 * Runs the application on PHP's built-in web server, for local development.
 *
 * Philosophy, in two parts.
 *
 * Where it listens: the server listens where the operator asked, or the
 * operator agrees to where it listens instead. When nothing was asked
 * for, the canonical default (DEFAULT_HOST:DEFAULT_PORT) is used, moving up to the
 * next free port without asking. When a --host or --port the operator
 * gave cannot be used, an alternative is offered and only taken on
 * confirmation, or straight away with -f/--force. A non-interactive run
 * without --force therefore never ends up somewhere the operator did not
 * choose.
 *
 * What it prints: everything reaches the operator through OutputInterface.
 * The server process gets no stdin and writes into socket pairs (the one
 * transport that can be polled without blocking on every platform, Windows
 * included); this command reads them and decides what is shown:
 *
 *   quiet    nothing from the server; if it stops unexpectedly, its last
 *            lines are reported through error().
 *   normal   request lines and server messages.
 *   verbose  also the per-connection "Accepted" / "Closing" lines.
 *
 * Request lines are coloured by status (5xx red, 4xx yellow, others green)
 * and connection lines dimmed, when the Terminal supports ANSI. This is the
 * command's own scheme: behind a socket the server does not colour its log.
 * Every shown line is re-stamped with this process's clock, so server and
 * router lines agree on the time.
 *
 * Request lines for application routes come from server/server.php; the
 * built-in server only logs requests for files it serves itself.
 *
 * The chosen URL reaches the application as SMLISER_APP_URL in the server
 * process's own environment; .env is never edited. This relies on DotEnv's
 * immutable mode letting the real environment win over the file.
 */
final class ServerCommand extends AbstractCommand {

	/**
	 * Address listened on when --host is not given.
	 *
	 * @var string
	 */
	public const DEFAULT_HOST = '127.0.0.1';

	/**
	 * Port listened on when --port is not given.
	 *
	 * @var int
	 */
	public const DEFAULT_PORT = 8383;

	/**
	 * How many consecutive ports are tried when looking for a free one.
	 *
	 * @var int
	 */
	private const PORT_SEARCH_LIMIT = 10;

	/**
	 * Microseconds to wait for server output before checking on the process.
	 *
	 * @var int
	 */
	private const POLL_INTERVAL = 100000;

	/**
	 * Bytes read from a server stream at a time.
	 *
	 * @var int
	 */
	private const READ_CHUNK = 8192;

	/**
	 * Server lines kept for the report when it stops unexpectedly.
	 *
	 * @var int
	 */
	private const TAIL_LINES = 20;

	/**
	 * Probe result: the address can be listened on.
	 *
	 * @var string
	 */
	private const ADDRESS_FREE = 'free';

	/**
	 * Probe result: the host is usable, but something already listens on the port.
	 *
	 * @var string
	 */
	private const PORT_IN_USE = 'port_in_use';

	/**
	 * Probe result: this machine cannot listen on the host at all.
	 *
	 * @var string
	 */
	private const HOST_UNAVAILABLE = 'host_unavailable';

	/**
	 * Signals that stop the server when sent to this process.
	 *
	 * SIGINT is included even though Ctrl+C already reaches the server
	 * (same foreground process group): listening for it keeps this process
	 * alive to read the server's last output and report how it ended, and
	 * forwarding it covers a SIGINT sent to this process alone.
	 *
	 * @var string[]
	 */
	private const STOP_SIGNALS = [ 'SIGINT', 'SIGTERM', 'SIGHUP' ];

	/**
	 * Matches the "[date] " prefix of a server line.
	 *
	 * @var string
	 */
	private const TIMESTAMP = '/^\[[^\]]+\] /';

	/**
	 * Matches a per-connection server line: "[date] 127.0.0.1:50070 Accepted".
	 *
	 * @var string
	 */
	private const CONNECTION_LINE = '/^\[[^\]]+\] \S+:\d+ (?:Accepted|Closing)$/';

	/**
	 * Matches the status in a server request line: "[date] 127.0.0.1:50070 [404]: GET /x".
	 *
	 * @var string
	 */
	private const REQUEST_STATUS = '/^\[[^\]]+\] \S+:\d+ \[(\d{3})\]: /';

	/**
	 * Server lines kept for the unexpected-stop report, oldest first.
	 *
	 * @var string[]
	 */
	private array $tail = [];

	/**
	 * @param InputInterface  $io
	 * @param OutputInterface $output
	 * @param ScriptName      $script_name
	 * @param Terminal        $terminal Decides whether server lines are coloured.
	 * @param SignalManager   $signals  Forwards stop signals to the server.
	 */
	public function __construct(
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name,
		private Terminal $terminal,
		private SignalManager $signals
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function name() : string {
		return 'server';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Run the application on PHP\'s built-in web server (local development only).';
	}

	/**
	 * {@inheritdoc}
	 *
	 * The command itself starts the server; `help` is its only subcommand,
	 * so the usage line does not advertise a required <subcommand>.
	 */
	public function synopsis() : string {
		return sprintf( '%s [options]', $this->command_prefix() );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Handlers must stay public: AbstractCommandRouter invokes them as
	 * callables from outside this class.
	 */
	public function get_subcommands() : array {
		return [
			'help' => [ $this, 'handle_help' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$prefix = $this->command_prefix();

		return implode(
			PHP_EOL,
			[
				'',
				'Subcommands:',
				'  help               Show this help message.',
				'',
				'Options:',
				sprintf( '  --host=<host>      Address to listen on. Default: %s. Use 0.0.0.0 to accept other machines.', self::DEFAULT_HOST ),
				sprintf( '  -p, --port=<port>  Port to listen on. Default: %d, or the next free port.', self::DEFAULT_PORT ),
				'  -f, --force        Use the offered alternative address without asking.',
				'',
				'Quiet verbosity hides the server log (failures are still reported);',
				'verbose verbosity adds the per-connection lines.',
				'',
				'SMLISER_APP_URL is set for the server process only; .env is not changed.',
				'Never use PHP\'s built-in server in production.',
				'',
				'Examples:',
				"  {$prefix}",
				"  {$prefix} --port=9000",
				"  {$prefix} --host=0.0.0.0 -p 9000 --force",
				"  {$prefix} help",
			]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function run( CommandInput $input ) : int {
		try {
			$host  = $this->read_value( $input, 'host' );
			$port  = $this->read_value( $input, 'port', 'p' );
			$force = $this->read_flag( $input, 'force', 'f' );
		} catch ( InvalidArgumentException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$host_given = null !== $host;
		$port_given = null !== $port;
		$host       = $host_given ? trim( $host, '[]' ) : self::DEFAULT_HOST;

		if ( $port_given && ! $this->is_valid_port( $port ) ) {
			$this->output->error( sprintf( '--port must be a number from 1 to 65535, "%s" given.', $port ) );
			return 1;
		}

		$port    = $port_given ? (int) $port : self::DEFAULT_PORT;
		$address = $this->resolve_address( $host, $port, $host_given, $port_given, $force );

		if ( null === $address ) {
			return 1;
		}

		[ $host, $port ] = $address;

		$php = PhpCli::find();

		if ( null === $php ) {
			$this->output->error( 'No PHP command-line binary could be started (proc_open may be disabled).' );
			return 1;
		}

		return $this->serve( $php, $host, $port );
	}

	/*
	|-----------------
	| ADDRESS CHOICE
	|-----------------
	*/

	/**
	 * Settle on the host and port to listen on.
	 *
	 * @param string $host       Requested host.
	 * @param int    $port       Requested port.
	 * @param bool   $host_given Whether the operator supplied the host.
	 * @param bool   $port_given Whether the operator supplied the port.
	 * @param bool   $force      Whether to take an alternative without asking.
	 * @return array{0: string, 1: int}|null Host and port, or null when the server should not start.
	 */
	private function resolve_address( string $host, int $port, bool $host_given, bool $port_given, bool $force ) : ?array {
		$status = $this->probe( $host, $port );

		if ( self::ADDRESS_FREE === $status ) {
			return [ $host, $port ];
		}

		$alt_host   = $host;
		$first_port = $port + 1;

		if ( self::HOST_UNAVAILABLE === $status ) {
			if ( self::DEFAULT_HOST === $host ) {
				$this->output->error( sprintf( 'This machine cannot listen on %s.', $host ) );
				return null;
			}

			$alt_host   = self::DEFAULT_HOST;
			$first_port = $port;
		}

		$alt_port = $this->find_free_port( $alt_host, $first_port );

		if ( null === $alt_port ) {
			$this->output->error( sprintf( 'No free port found on %s near %d.', $alt_host, $port ) );
			return null;
		}

		$reason = self::HOST_UNAVAILABLE === $status
			? sprintf( 'This machine cannot listen on %s', $host )
			: sprintf( 'Port %d is already in use', $port );

		$alternative = $this->authority( $alt_host, $alt_port );
		$overrides   = ( $host_given && $alt_host !== $host ) || ( $port_given && $alt_port !== $port );

		if ( ! $overrides ) {
			$this->output->warning( sprintf( '%s; using %s instead.', $reason, $alternative ) );
			return [ $alt_host, $alt_port ];
		}

		if ( $force ) {
			$this->output->warning( sprintf( '%s; using %s instead (--force).', $reason, $alternative ) );
			return [ $alt_host, $alt_port ];
		}

		if ( ! $this->io->confirm( sprintf( '%s. Use %s instead?', $reason, $alternative ), false ) ) {
			$this->output->info( 'Server not started.' );
			return null;
		}

		return [ $alt_host, $alt_port ];
	}

	/**
	 * Find the first port this machine can listen on, starting at $from.
	 *
	 * @param string $host
	 * @param int    $from
	 * @return int|null
	 */
	private function find_free_port( string $host, int $from ) : ?int {
		$last = min( 65535, $from + self::PORT_SEARCH_LIMIT - 1 );

		for ( $candidate = $from; $candidate <= $last; $candidate++ ) {
			if ( self::ADDRESS_FREE === $this->probe( $host, $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Check whether an address can be listened on, by binding it briefly.
	 *
	 * When the bind fails, binding the same host on port 0 (any free port)
	 * tells the two causes apart without platform-specific error codes. The
	 * check is not a reservation: another process can still take the port
	 * before the server binds it, and the server then reports that itself.
	 *
	 * @param string $host
	 * @param int    $port
	 * @return string One of the ADDRESS_FREE, PORT_IN_USE or HOST_UNAVAILABLE constants.
	 */
	private function probe( string $host, int $port ) : string {
		$socket = @stream_socket_server( 'tcp://' . $this->authority( $host, $port ), $errno, $errstr );

		if ( false !== $socket ) {
			fclose( $socket );
			return self::ADDRESS_FREE;
		}

		$any = @stream_socket_server( 'tcp://' . $this->authority( $host, 0 ), $errno, $errstr );

		if ( false === $any ) {
			return self::HOST_UNAVAILABLE;
		}

		fclose( $any );

		return self::PORT_IN_USE;
	}

	/*
	|-----------------
	| SERVER PROCESS
	|-----------------
	*/

	/**
	 * Start the server, relay its output until it stops, and report how it ended.
	 *
	 * @param string $php  PHP CLI binary.
	 * @param string $host
	 * @param int    $port
	 * @return int Exit code.
	 */
	private function serve( string $php, string $host, int $port ) : int {
		PhpRuntime::set_time_limit( 0 );

		$url = $this->url( $host, $port );
		$env = (array) getenv();

		$env['SMLISER_APP_URL'] = $url;

		$command = [
			$php,
			'-S',
			$this->authority( $host, $port ),
			'-t',
			SMLISER_ROOT . 'public',
			SMLISER_ROOT . 'server/server.php',
		];

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'socket' ],
			2 => [ 'socket' ],
		];

		$process = proc_open( $command, $descriptors, $pipes, SMLISER_ROOT, $env );

		if ( ! is_resource( $process ) ) {
			$this->output->error( 'The server process could not be started.' );
			return 1;
		}

		// The server never reads input; closing stdin gives it an immediate EOF.
		fclose( $pipes[0] );

		$this->output->success( sprintf( 'Server running at %s', $url ) );
		$this->output->info( 'Press Ctrl+C to stop.' );

		$forward = static function ( int $signal, SignalInfo $info ) use ( $process ) : void {
			proc_terminate( $process, $signal );
		};

		foreach ( self::STOP_SIGNALS as $signal ) {
			$this->signals->on( $signal, $forward );
		}

		$this->tail = [];
		$status     = $this->relay( $process, [ 1 => $pipes[1], 2 => $pipes[2] ] );

		foreach ( self::STOP_SIGNALS as $signal ) {
			$this->signals->remove_listener( $signal, $forward );
		}

		return $this->conclude( $status );
	}

	/**
	 * Relay the server's output until it has exited and both streams are drained.
	 *
	 * @param resource             $process
	 * @param array<int, resource> $streams The server's stdout and stderr, keyed by descriptor.
	 * @return array<string, mixed> The proc_get_status() result that saw the server exit.
	 */
	private function relay( $process, array $streams ) : array {
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
					$this->relay_rest( $buffers[ $key ] );
					fclose( $stream );
					unset( $streams[ $key ] );
					continue;
				}

				$buffers[ $key ] .= $chunk;
				$this->relay_lines( $buffers[ $key ] );
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
			$this->relay_rest( $buffers[ $key ] );
			fclose( $stream );
		}

		proc_close( $process );

		return $status;
	}

	/**
	 * Turn the server's exit status into this command's exit code, reporting
	 * an unexpected stop.
	 *
	 * Being stopped by Ctrl+C, SIGTERM or SIGHUP is how the server is meant
	 * to end, so it counts as success.
	 *
	 * @param array<string, mixed> $status A proc_get_status() result for the exited server.
	 * @return int
	 */
	private function conclude( array $status ) : int {
		if ( $status['signaled'] ) {
			$stops = array_map( 'constant', array_filter( self::STOP_SIGNALS, 'defined' ) );

			if ( in_array( $status['termsig'], $stops, true ) ) {
				$this->output->info( 'Server stopped.' );
				return 0;
			}

			$code   = 128 + (int) $status['termsig'];
			$reason = sprintf( 'The server was killed by signal %d.', $status['termsig'] );
		} else {
			$code   = (int) $status['exitcode'];
			$reason = sprintf( 'The server stopped with exit code %d.', $code );
		}

		if ( 0 === $code ) {
			$this->output->info( 'Server stopped.' );
			return 0;
		}

		$this->output->error( $reason );

		// At normal verbosity or above, these lines were already shown.
		if ( $this->output->get_verbosity() < OutputInterface::VERBOSITY_NORMAL ) {
			foreach ( $this->tail as $line ) {
				$this->output->error( $line );
			}
		}

		return $code;
	}

	/*
	|-------------
	| SERVER LOG
	|-------------
	*/

	/**
	 * Relay every complete line in $buffer, leaving any partial line in it.
	 *
	 * @param string $buffer
	 * @return void
	 */
	private function relay_lines( string &$buffer ) : void {
		$lines  = explode( "\n", $buffer );
		$buffer = (string) array_pop( $lines );

		foreach ( $lines as $line ) {
			$this->relay_line( $line );
		}
	}

	/**
	 * Relay what is left in $buffer once its stream has ended.
	 *
	 * @param string $buffer
	 * @return void
	 */
	private function relay_rest( string &$buffer ) : void {
		$this->relay_lines( $buffer );

		if ( '' !== $buffer ) {
			$this->relay_line( $buffer );
			$buffer = '';
		}
	}

	/**
	 * Remember one server line for the failure report and show it if the
	 * verbosity allows.
	 *
	 * @param string $line
	 * @return void
	 */
	private function relay_line( string $line ) : void {
		$line = rtrim( $line, "\r" );

		if ( '' === $line ) {
			return;
		}

		$this->tail[] = $line;

		if ( count( $this->tail ) > self::TAIL_LINES ) {
			array_shift( $this->tail );
		}

		$is_connection = 1 === preg_match( self::CONNECTION_LINE, $line );
		$required      = $is_connection ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;

		if ( $this->output->get_verbosity() < $required ) {
			return;
		}

		$this->output->write( $this->colourise( $this->restamp( $line ), $is_connection ), true );
	}

	/**
	 * Replace a server line's timestamp with one from this process's clock.
	 *
	 * The server stamps its own lines in the system's local time, while the
	 * router's request lines use PHP's configured timezone; the two differ
	 * whenever date.timezone is not the system's. Re-stamping on arrival
	 * gives every shown line the same clock.
	 *
	 * @param string $line
	 * @return string
	 */
	private function restamp( string $line ) : string {
		return (string) preg_replace( self::TIMESTAMP, '[' . date( 'D M d H:i:s Y' ) . '] ', $line, 1 );
	}

	/**
	 * Colour a server line by its meaning, when the terminal supports ANSI.
	 *
	 * @param string $line
	 * @param bool   $is_connection Whether it is a per-connection line.
	 * @return string
	 */
	private function colourise( string $line, bool $is_connection ) : string {
		if ( ! $this->terminal->supports_ansi() ) {
			return $line;
		}

		$code = null;

		if ( $is_connection ) {
			$code = ConsoleOutput::ANSI_DIM;
		} elseif ( 1 === preg_match( self::REQUEST_STATUS, $line, $match ) ) {
			$status = (int) $match[1];

			$code = match ( true ) {
				$status >= 500 => ConsoleOutput::ANSI_RED,
				$status >= 400 => ConsoleOutput::ANSI_YELLOW,
				default        => ConsoleOutput::ANSI_GREEN,
			};
		}

		return null === $code ? $line : $code . $line . ConsoleOutput::ANSI_RESET;
	}

	/*
	|----------
	| HELPERS
	|----------
	*/

	/**
	 * Read a single-valued option given as --long or -short.
	 *
	 * @param CommandInput $input
	 * @param string       $long  Long name, without dashes.
	 * @param string       $short Single-letter name, without the dash; '' for none.
	 * @return string|null The trimmed value, or null when the option was not given.
	 * @throws InvalidArgumentException When the option was repeated or given without a value.
	 */
	private function read_value( CommandInput $input, string $long, string $short = '' ) : ?string {
		$name = null;

		if ( $input->has_option( $long ) ) {
			$name = $long;
		} elseif ( '' !== $short && $input->has_option( $short ) ) {
			$name = $short;
		}

		if ( null === $name ) {
			return null;
		}

		$value = $input->get_option( $name );
		$label = 1 === strlen( $name ) ? "-{$name}" : "--{$name}";

		if ( is_array( $value ) ) {
			throw new InvalidArgumentException( sprintf( '%s was given more than once.', $label ) );
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new InvalidArgumentException( sprintf( '%s needs a value.', $label ) );
		}

		return trim( $value );
	}

	/**
	 * Read a boolean flag given as --long or -short.
	 *
	 * @param CommandInput $input
	 * @param string       $long  Long name, without dashes.
	 * @param string       $short Single-letter name, without the dash.
	 * @return bool True when given, unless given as =false.
	 */
	private function read_flag( CommandInput $input, string $long, string $short ) : bool {
		foreach ( [ $long, $short ] as $name ) {
			if ( $input->has_option( $name ) ) {
				return false !== $input->get_option( $name, true );
			}
		}

		return false;
	}

	/**
	 * Whether a string is a TCP port number.
	 *
	 * @param string $port
	 * @return bool
	 */
	private function is_valid_port( string $port ) : bool {
		return 1 === preg_match( '/^\d{1,5}$/', $port ) && (int) $port >= 1 && (int) $port <= 65535;
	}

	/**
	 * host:port, with IPv6 hosts in brackets.
	 *
	 * @param string $host
	 * @param int    $port
	 * @return string
	 */
	private function authority( string $host, int $port ) : string {
		return str_contains( $host, ':' ) ? "[{$host}]:{$port}" : "{$host}:{$port}";
	}

	/**
	 * The URL the application is reached at.
	 *
	 * A wildcard host (0.0.0.0, ::) is where the server listens, not an
	 * address a browser can open, so the URL uses localhost instead.
	 *
	 * @param string $host
	 * @param int    $port
	 * @return string
	 */
	private function url( string $host, int $port ) : string {
		$url_host = in_array( $host, [ '0.0.0.0', '::' ], true ) ? 'localhost' : $host;

		return 'http://' . $this->authority( $url_host, $port );
	}
}
