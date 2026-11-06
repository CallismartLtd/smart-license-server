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
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Exceptions\ServerException;
use SmartLicenseServer\Server\DevServer;
use SmartLicenseServer\Server\ServerConfig;
use SmartLicenseServer\Server\ServerInfo;

/**
 * Runs the application on PHP's built-in web server, for local development.
 *
 * Philosophy: this command is only the operator's side of {@see DevServer}.
 * It turns options into a ServerConfig, asks the operator what to do when an
 * address is taken, and shows what the API returns. Everything about how the
 * server is started, found and stopped lives in DevServer, so none of it
 * changes here when that does.
 *
 * Two ways to run:
 *
 *   server           In the foreground: the server is a child of this
 *                    command, which shows its log until Ctrl+C.
 *   server start     In the background (also `server --daemon`): the server
 *                    outlives the terminal and is managed with stop, restart
 *                    and status, which find it through its recorded state, not
 *                    a process ID. Its output goes to its log file.
 *
 * Where it listens: the server listens where the operator asked, or the
 * operator agrees to where it listens instead. When nothing was asked
 * for, the canonical default (DevServer::DEFAULT_HOST:DEFAULT_PORT) is used,
 * moving up to the next free port without asking. When a --host or --port the
 * operator gave cannot be used, an alternative is offered and only taken on
 * confirmation, or straight away with -f/--force. A non-interactive run
 * without --force therefore never ends up somewhere the operator did not
 * choose. A restart keeps the address the server has; to change it, stop the
 * server and start it again.
 *
 * What it prints: everything reaches the operator through OutputInterface.
 * In the foreground, the server's lines are received from DevServer and this
 * command decides what is shown:
 *
 *   quiet    nothing from the server; if it stops unexpectedly, its last
 *            lines are reported through error().
 *   normal   request lines and server messages.
 *   verbose  also the per-connection "Accepted" / "Closing" lines.
 *
 * Request lines are coloured by status (5xx red, 4xx yellow, others green)
 * and connection lines dimmed, when the Terminal supports ANSI. This is the
 * command's own scheme: the server does not colour its log. Every shown line
 * is re-stamped with this process's clock, so server and router lines agree
 * on the time. A background server's log is the server's own output, as it
 * wrote it.
 *
 * Request lines for application routes come from server/server.php; the
 * built-in server only logs requests for files it serves itself.
 *
 * --url states the address the application is publicly reached at, such as a
 * tunnel's. It is not where the server listens and never changes where it
 * does. See {@see DevServer} for how it reaches the application.
 */
final class ServerCommand extends AbstractCommand {

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
	 * @var int
	 */
	private const TAIL_LINES = 20;

	/**
	 * Server lines kept for the unexpected-stop report.
	 *
	 * @var string[]
	 */
	private array $tail = [];

	/**
	 * @param InputInterface  $io
	 * @param OutputInterface $output
	 * @param ScriptName      $script_name
	 * @param Terminal        $terminal Decides whether server lines are coloured.
	 * @param SignalManager   $signals  Forwards stop signals to a foreground server.
	 * @param DevServer       $server   Starts, finds and stops the server.
	 */
	public function __construct(
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name,
		private Terminal $terminal,
		private SignalManager $signals,
		private DevServer $server
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
	 * The command itself runs the server in the foreground, so the usage
	 * line does not advertise a required <subcommand>.
	 */
	public function synopsis() : string {
		return sprintf( '%s [start|stop|restart|status] [options]', $this->command_prefix() );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Handlers must stay public: AbstractCommandRouter invokes them as
	 * callables from outside this class.
	 */
	public function get_subcommands() : array {
		return [
			'start'   => [ $this, 'handle_start' ],
			'stop'    => [ $this, 'handle_stop' ],
			'restart' => [ $this, 'handle_restart' ],
			'status'  => [ $this, 'handle_status' ],
			'help'    => [ $this, 'handle_help' ],
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
				'Run it in the foreground (Ctrl+C stops it), or in the background and manage it:',
				'',
				'Subcommands:',
				'  start              Run in the background, so it keeps running when the terminal closes.',
				'  stop               Stop the background server. -f/--force ends it if it will not stop.',
				'  restart            Stop it and start it again at the same address. Applies a changed --url or .env.',
				'                     Starts one if none is running.',
				'  status             Show the background server. Exits 1 when none is running.',
				'  help               Show this help message.',
				'',
				'Options:',
				sprintf( '  --host=<host>      Address to listen on. Default: %s. Use 0.0.0.0 to accept other machines.', DevServer::DEFAULT_HOST ),
				sprintf( '  -p, --port=<port>  Port to listen on. Default: %d, or the next free port.', DevServer::DEFAULT_PORT ),
				'  -f, --force        Use the offered alternative address without asking.',
				'  --daemon           Same as the start subcommand.',
				'  --url=<url>        The URL the application is publicly reached at, e.g. through a tunnel.',
				'                     Sets SMLISER_APP_URL. Does not change the host or port listened on.',
				'',
				sprintf( 'The background server writes to %s.', $this->server->log_path() ),
				'Quiet verbosity hides the foreground server log (failures are still reported);',
				'verbose verbosity adds the per-connection lines.',
				'',
				'SMLISER_APP_URL is set for the server process only; .env is not changed. It is --url when',
				'given, otherwise the address listened on.',
				'Never use PHP\'s built-in server in production.',
				'',
				'Examples:',
				"  {$prefix}",
				"  {$prefix} --port=9000",
				"  {$prefix} --host=0.0.0.0 -p 9000 --force",
				"  {$prefix} start --port=9000 --url=https://my-tunnel.example.dev",
				"  {$prefix} status",
				"  {$prefix} restart",
				"  {$prefix} stop",
				"  {$prefix} help",
			]
		);
	}

	/**
	 * Run the server in the foreground.
	 *
	 * {@inheritdoc}
	 */
	public function run( CommandInput $input ) : int {
		if ( $this->read_flag( $input, 'daemon' ) ) {
			return $this->handle_start( $input );
		}

		$config = $this->configure( $input );

		return null === $config ? 1 : $this->serve( $config );
	}

	/**
	 * Start the server in the background.
	 *
	 * @param CommandInput $input
	 * @return int Exit code.
	 */
	public function handle_start( CommandInput $input ) : int {
		$running = $this->server->status();

		// Settled before the address is: with no --port, a free one further up would be taken.
		if ( null !== $running ) {
			$this->output->error( sprintf( 'A server is already running at %s (process %d). To restart it: %s restart', $running->config->url(), $running->pid, $this->command_prefix() ) );
			return 1;
		}

		$config = $this->configure( $input );

		if ( null === $config ) {
			return 1;
		}

		try {
			$info = $this->server->start( $config );
		} catch ( ServerException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->report_running( $info, 'running in the background' );

		return 0;
	}

	/**
	 * Stop the background server.
	 *
	 * @param CommandInput $input
	 * @return int Exit code.
	 */
	public function handle_stop( CommandInput $input ) : int {
		try {
			$info = $this->server->stop( $this->read_flag( $input, 'force', 'f' ) );
		} catch ( ServerException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		if ( null === $info ) {
			$this->output->info( 'No server is running.' );
			return 0;
		}

		$this->output->success( sprintf( 'Server stopped (process %d).', $info->pid ) );

		return 0;
	}

	/**
	 * Restart the background server at the address it has.
	 *
	 * Only --url may be given: it is the one setting that needs a restart to
	 * take effect and does not move the server. With nothing running this is
	 * a start, with every option.
	 *
	 * @param CommandInput $input
	 * @return int Exit code.
	 */
	public function handle_restart( CommandInput $input ) : int {
		$running = $this->server->status();

		if ( null === $running ) {
			$this->output->info( 'No server was running; starting one.' );
			return $this->handle_start( $input );
		}

		if ( $input->has_option( 'host' ) || $input->has_option( 'port' ) || $input->has_option( 'p' ) ) {
			$this->output->error( sprintf( 'A restart keeps the server\'s address. To change it: %1$s stop, then %1$s start.', $this->command_prefix() ) );
			return 1;
		}

		try {
			$url    = $this->read_url( $input );
			$config = null === $url ? null : $running->config->with_public_url( $url );
			$info   = $this->server->restart( $config );
		} catch ( InvalidArgumentException | ServerException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->report_running( $info, 'restarted in the background' );

		return 0;
	}

	/**
	 * Show the background server.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_status( CommandInput $input ) : int {
		$info = $this->server->status();

		if ( null === $info ) {
			$this->output->info( 'No server is running.' );
			return 0;
		}

		$this->output->success( sprintf( 'Server running at %s (process %d)', $info->config->url(), $info->pid ) );

		if ( null !== $info->config->public_url ) {
			$this->output->info( sprintf( 'Application URL (SMLISER_APP_URL): %s', $info->config->public_url ) );
		}

		$this->output->info( sprintf( 'Started: %s UTC', $info->started_at ) );
		$this->output->info( sprintf( 'Log: %s', $info->log ) );

		return 0;
	}

	/*
	|-----------------
	|Foreground server
	|-----------------
	*/

	/**
	 * Run the server as this command's child, show its log until it stops, and report how it ended.
	 *
	 * @param ServerConfig $config
	 * @return int Exit code.
	 */
	private function serve( ServerConfig $config ) : int {
		$this->tail = [];
		$forward    = null;

		try {
			$status = $this->server->run_attached(
				$config,
				fn ( string $line ) => $this->relay_line( $line ),
				function ( callable $send ) use ( $config, &$forward ) : void {
					$this->output->success( sprintf( 'Server running at %s', $config->url() ) );
					$this->announce_public_url( $config );
					$this->output->info( 'Press Ctrl+C to stop.' );

					$forward = static function ( int $signal, SignalInfo $info ) use ( $send ) : void {
						$send( $signal );
					};

					foreach ( self::STOP_SIGNALS as $signal ) {
						$this->signals->on( $signal, $forward );
					}
				}
			);
		} catch ( ServerException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		} finally {
			if ( null !== $forward ) {
				foreach ( self::STOP_SIGNALS as $signal ) {
					$this->signals->remove_listener( $signal, $forward );
				}
			}
		}

		return $this->conclude( $status );
	}

	/**
	 * Turn the server's exit status into this command's exit code, reporting
	 * an unexpected stop.
	 *
	 * Being stopped by Ctrl+C, SIGTERM or SIGHUP is how the server is meant
	 * to end, so it counts as success.
	 *
	 * @param array{signaled: bool, termsig: int, exitcode: int} $status How the server ended.
	 * @return int
	 */
	private function conclude( array $status ) : int {
		if ( $status['signaled'] ) {
			$stops = array_map( 'constant', array_filter( self::STOP_SIGNALS, 'defined' ) );

			if ( in_array( $status['termsig'], $stops, true ) ) {
				$this->output->info( 'Server stopped.' );
				return 0;
			}

			$code   = 128 + $status['termsig'];
			$reason = sprintf( 'The server was killed by signal %d.', $status['termsig'] );
		} else {
			$code   = $status['exitcode'];
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
	|----------
	|Server log
	|----------
	*/

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
	|--------------
	|Address choice
	|--------------
	*/

	/**
	 * Build the config the options describe, settling where to listen.
	 *
	 * @param CommandInput $input
	 * @return ServerConfig|null Null when the server should not start; the reason has been shown.
	 */
	private function configure( CommandInput $input ) : ?ServerConfig {
		try {
			$host  = $this->read_value( $input, 'host' );
			$port  = $this->read_value( $input, 'port', 'p' );
			$force = $this->read_flag( $input, 'force', 'f' );
			$url   = $this->read_url( $input );
		} catch ( InvalidArgumentException $e ) {
			$this->output->error( $e->getMessage() );
			return null;
		}

		$host_given = null !== $host;
		$port_given = null !== $port;
		$host       = $host_given ? trim( $host, '[]' ) : DevServer::DEFAULT_HOST;

		if ( $port_given && ! $this->is_valid_port( $port ) ) {
			$this->output->error( sprintf( '--port must be a number from 1 to 65535, "%s" given.', $port ) );
			return null;
		}

		$port    = $port_given ? (int) $port : DevServer::DEFAULT_PORT;
		$address = $this->resolve_address( $host, $port, $host_given, $port_given, $force );

		return null === $address ? null : new ServerConfig( $address[0], $address[1], $url );
	}

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
		$status = $this->server->probe( $host, $port );

		if ( DevServer::ADDRESS_FREE === $status ) {
			return [ $host, $port ];
		}

		$alt_host   = $host;
		$first_port = $port + 1;

		if ( DevServer::HOST_UNAVAILABLE === $status ) {
			if ( DevServer::DEFAULT_HOST === $host ) {
				$this->output->error( sprintf( 'This machine cannot listen on %s.', $host ) );
				return null;
			}

			$alt_host   = DevServer::DEFAULT_HOST;
			$first_port = $port;
		}

		$alt_port = $this->server->find_free_port( $alt_host, $first_port );

		if ( null === $alt_port ) {
			$this->output->error( sprintf( 'No free port found on %s near %d.', $alt_host, $port ) );
			return null;
		}

		$reason = DevServer::HOST_UNAVAILABLE === $status
			? sprintf( 'This machine cannot listen on %s', $host )
			: sprintf( 'Port %d is already in use', $port );

		$alternative = ( new ServerConfig( $alt_host, $alt_port ) )->authority();
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

	/*
	|-------
	|Helpers
	|-------
	*/

	/**
	 * Show a background server that is now running.
	 *
	 * @param ServerInfo $info
	 * @param string     $what What happened to it, in words: "running in the background".
	 * @return void
	 */
	private function report_running( ServerInfo $info, string $what ) : void {
		$this->output->success( sprintf( 'Server %s at %s (process %d)', $what, $info->config->url(), $info->pid ) );
		$this->announce_public_url( $info->config );
		$this->output->info( sprintf( 'Log: %s', $info->log ) );
		$this->output->info( sprintf( 'Stop it with: %s stop', $this->command_prefix() ) );
	}

	/**
	 * Print the public application URL, when one was given.
	 *
	 * @param ServerConfig $config
	 * @return void
	 */
	private function announce_public_url( ServerConfig $config ) : void {
		if ( null === $config->public_url ) {
			return;
		}

		$this->output->info( sprintf( 'Application URL (SMLISER_APP_URL): %s', $config->public_url ) );
		$this->output->info( sprintf( 'Point your tunnel or proxy at %s.', $config->authority() ) );
	}

	/**
	 * Read --url, normalized the way the installer normalizes the site address.
	 *
	 * @param CommandInput $input
	 * @return string|null Null when not given.
	 * @throws InvalidArgumentException When the value is not a usable URL.
	 */
	private function read_url( CommandInput $input ) : ?string {
		$url = $this->read_value( $input, 'url' );

		return null === $url ? null : AppInstaller::normalize_app_url( $url );
	}

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
	 * @param string       $short Single-letter name, without the dash; '' for none.
	 * @return bool True when given, unless given as =false.
	 */
	private function read_flag( CommandInput $input, string $long, string $short = '' ) : bool {
		foreach ( array_filter( [ $long, $short ] ) as $name ) {
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
}
