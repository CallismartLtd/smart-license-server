<?php
/**
 * Health monitor class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Tui
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Tui;

use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\SignalInfo;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Environments\Application\Health\HostResources;
use SmartLicenseServer\Environments\Application\Health\SystemHealth;
use SmartLicenseServer\Utils\Format;

/**
 * A full-screen, live view of the site health, like htop.
 *
 * Philosophy:
 *
 *  - Problems first. The verdict and the count of each status sit at the
 *    top; the checks are listed worst first, and any check opens to its
 *    full message and what to do about it.
 *  - Live, and honest about it. The host figures refresh every second and
 *    the checks every --interval seconds; the status line says when the
 *    checks last ran and how long they took. Nothing is invented: a figure
 *    this machine cannot provide shows as "n/a".
 *  - The terminal is always given back. Whatever ends the view (Q, Ctrl+C,
 *    an error, a signal, exit() from elsewhere), the alternate screen,
 *    cursor and cooked mode are restored. Ctrl+C and Ctrl+Z arrive as keys
 *    (raw mode without signals), so they quit cleanly instead of killing
 *    or suspending the process with the screen still taken over.
 *  - Same data as everywhere else. Checks and diagnostics come from
 *    SystemHealth, as on the admin pages and in `diagnose health`.
 *  - One look, live or printed. print() draws the same title bar and
 *    panels once, inline, followed by every check that needs attention in
 *    full: the report `diagnose health` shows, readable in scrollback,
 *    piped to a file, and without colour where the terminal has none.
 *
 * Needs an interactive terminal with ANSI support and stty (not Windows):
 * see supported(). Signals (resize, SIGTERM) need pcntl; without it the
 * size is polled and SIGTERM ends the process as usual.
 */
final class HealthMonitor {

	/**
	 * Smallest usable terminal width, in columns.
	 */
	public const MIN_COLS = 80;

	/**
	 * Smallest usable terminal height, in rows.
	 */
	public const MIN_ROWS = 24;

	/**
	 * Widest printed report, in columns.
	 */
	public const PRINT_MAX_COLS = 160;

	/**
	 * Gap between the two host samples of a printed report, in microseconds.
	 */
	private const PRINT_CPU_SAMPLE = 250000;

	/**
	 * Longest wait for a key before the screen is checked again, in microseconds.
	 */
	private const TICK = 250000;

	/**
	 * Seconds between host resource samples.
	 */
	private const HOST_INTERVAL = 1;

	/**
	 * Seconds between terminal size checks when SIGWINCH cannot be caught.
	 */
	private const SIZE_POLL_INTERVAL = 1;

	/**
	 * Check statuses, worst first: label, badge text and SGR colours.
	 *
	 * @var array<string, array{label: string, badge: string, sgr: string, text: string}>
	 */
	private const STATUSES = [
		'critical' => [ 'label' => 'Critical', 'badge' => 'CRIT', 'sgr' => '41;97;1', 'text' => '31;1' ],
		'warning'  => [ 'label' => 'Warnings', 'badge' => 'WARN', 'sgr' => '43;30', 'text' => '33;1' ],
		'info'     => [ 'label' => 'Info', 'badge' => 'INFO', 'sgr' => '46;30', 'text' => '36' ],
		'pass'     => [ 'label' => 'Passed', 'badge' => 'PASS', 'sgr' => '42;30', 'text' => '32' ],
	];

	/**
	 * Check filters, cycled with F: label and the statuses shown.
	 *
	 * @var array<int, array{label: string, statuses: string[]}>
	 */
	private const FILTERS = [
		[ 'label' => 'All', 'statuses' => [ 'critical', 'warning', 'info', 'pass' ] ],
		[ 'label' => 'Problems', 'statuses' => [ 'critical', 'warning' ] ],
		[ 'label' => 'Critical', 'statuses' => [ 'critical' ] ],
		[ 'label' => 'Warnings', 'statuses' => [ 'warning' ] ],
		[ 'label' => 'Info', 'statuses' => [ 'info' ] ],
		[ 'label' => 'Passed', 'statuses' => [ 'pass' ] ],
	];

	/**
	 * Theme, as SGR parameters.
	 */
	private const SGR_BORDER = '36';
	private const SGR_TITLE  = '36;1';
	private const SGR_LABEL  = '37';
	private const SGR_DIM    = '2';
	private const SGR_BAR    = '44;97;1';
	private const SGR_SELECT = '44;97';
	private const SGR_KEY    = '46;30';

	/*
	|-------
	| STATE
	|-------
	*/

	private int $cols = 0;
	private int $rows = 0;
	private bool $active = false;
	private bool $quit = false;
	private ?int $stop_signal = null;
	private bool $resized = true;
	private bool $dirty = true;

	/** @var string 'checks' or 'diagnostics'. */
	private string $view = 'checks';
	private bool $detail = false;
	private int $detail_scroll = 0;

	/** @var array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> Worst first. */
	private array $checks = [];
	private ?float $checks_at = null;
	private float $checks_seconds = 0.0;
	private int $filter = 0;
	private int $selected = 0;
	private int $scroll = 0;

	/** @var string[] */
	private array $sections = [];
	/** @var array<string, array<string, string>> Loaded diagnostics sections. */
	private array $diagnostics = [];
	private int $section = 0;
	private int $section_scroll = 0;

	/** @var array<string, mixed>|null */
	private ?array $host = null;
	private float $host_at = 0.0;
	private int $host_samples = 0;
	private float $size_at = 0.0;

	private string $status = '';

	/** Whether colour codes are written; off when printing to a terminal without ANSI. */
	private bool $colour = true;

	/** @var array{product: string, version: string, schema: string, site: string}|null */
	private ?array $meta = null;

	/**
	 * Constructor.
	 *
	 * @param SystemHealth    $health   Checks and diagnostics.
	 * @param HostResources   $resources Host CPU, memory, disk and load.
	 * @param Terminal        $terminal Raw mode, size and capabilities.
	 * @param SignalManager   $signals  Resize and termination signals.
	 * @param OutputInterface $output   Where frames are written.
	 * @param int             $interval Seconds between check runs.
	 */
	public function __construct(
		private SystemHealth $health,
		private HostResources $resources,
		private Terminal $terminal,
		private SignalManager $signals,
		private OutputInterface $output,
		private int $interval = 10
	) {
		$this->interval = \max( 2, $interval );
	}

	/**
	 * Whether this terminal can show the monitor.
	 *
	 * @param Terminal $terminal
	 * @return bool
	 */
	public static function supported( Terminal $terminal ) : bool {
		return ! $terminal->is_windows()
			&& $terminal->is_tty( \STDIN )
			&& $terminal->is_tty( \STDOUT )
			&& $terminal->supports_ansi()
			&& $terminal->stty_available()
			&& $terminal->function_available( 'system' );
	}

	/**
	 * Show the monitor until the operator quits.
	 *
	 * @return int The verdict of the last check run (0 fine, 1 warning, 2 critical),
	 *             or 128 + the signal number when a signal ended it.
	 */
	public function run() : int {
		if ( ! $this->enter() ) {
			return 1;
		}

		try {
			$this->meta     = $this->health->report_meta();
			$this->sections = $this->health->diagnostic_sections();
			$this->measure();
			$this->sample_host();
			$this->refresh_checks();

			while ( ! $this->quit ) {
				$this->tick();
			}
		} finally {
			$this->leave();
		}

		if ( null !== $this->stop_signal ) {
			return 128 + $this->stop_signal;
		}

		return match ( $this->worst() ) {
			'critical' => 2,
			'warning'  => 1,
			default    => 0,
		};
	}

	/**
	 * Print the monitor once, inline: the title bar, the panels, and the checks.
	 *
	 * Problems are shown in full (message and what to do); passing checks
	 * by name only, unless $verbose. Nothing is read from the keyboard and
	 * the screen is not taken over, so this works when piped too. Colour is
	 * used only where the terminal supports it.
	 *
	 * The width is the terminal's, from MIN_COLS up to PRINT_MAX_COLS.
	 *
	 * @param array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> $checks  Checks to show, in any order.
	 * @param float                                                                                                       $seconds How long the checks took.
	 * @param bool                                                                                                        $verbose Show the message of passing checks too.
	 * @return void
	 */
	public function print( array $checks, float $seconds, bool $verbose = false ) : void {
		$this->colour         = $this->terminal->supports_ansi();
		$this->cols           = \max( self::MIN_COLS, \min( self::PRINT_MAX_COLS, $this->terminal->terminal_width() ) );
		$this->meta           = $this->health->report_meta();
		$this->checks         = self::worst_first( $checks );
		$this->checks_seconds = $seconds;
		$this->checks_at      = \microtime( true );

		// CPU usage is a rate: two samples a moment apart.
		$this->sample_host();
		\usleep( self::PRINT_CPU_SAMPLE );
		$this->sample_host();

		$lines = [ $this->title_bar() ];

		foreach ( $this->top_panels() as $line ) {
			$lines[] = $line;
		}

		foreach ( $this->checks_report( $verbose ) as $line ) {
			$lines[] = $line;
		}

		foreach ( $lines as $line ) {
			$this->output->write( $line . ( $this->colour ? "\033[0m" : '' ), true );
		}
	}

	/*
	|--------------------
	| SCREEN LIFECYCLE
	|--------------------
	*/

	/**
	 * Take over the terminal.
	 *
	 * @return bool False when raw mode cannot be engaged.
	 */
	private function enter() : bool {
		if ( ! $this->terminal->enable_raw_mode( false ) ) {
			return false;
		}

		$this->active = true;

		\stream_set_blocking( \STDIN, false );

		// Runs on any exit, including exit() from another signal handler.
		\register_shutdown_function( [ $this, 'restore' ] );

		$this->signals->on( 'SIGWINCH', [ $this, 'on_resize' ] );
		$this->signals->on( 'SIGTERM', [ $this, 'on_stop' ] );
		$this->signals->on( 'SIGHUP', [ $this, 'on_stop' ] );

		// Alternate screen, hidden cursor, no line wrap.
		$this->output->write( "\033[?1049h\033[?25l\033[?7l\033[2J" );

		return true;
	}

	/**
	 * Give the terminal back, as it was.
	 *
	 * @return void
	 */
	private function leave() : void {
		$this->signals->remove_listener( 'SIGWINCH', [ $this, 'on_resize' ] );
		$this->signals->remove_listener( 'SIGTERM', [ $this, 'on_stop' ] );
		$this->signals->remove_listener( 'SIGHUP', [ $this, 'on_stop' ] );

		$this->restore();
	}

	/**
	 * Restore the terminal. Safe to call more than once.
	 *
	 * Public for register_shutdown_function() only.
	 *
	 * @return void
	 */
	public function restore() : void {
		if ( ! $this->active ) {
			return;
		}

		$this->active = false;
		\stream_set_blocking( \STDIN, true );
		$this->output->write( "\033[0m\033[?7h\033[?25h\033[?1049l" );
		$this->terminal->restore_cooked_mode();
	}

	/**
	 * SIGWINCH listener: redraw at the new size.
	 *
	 * @return void
	 */
	public function on_resize() : void {
		$this->resized = true;
	}

	/**
	 * SIGTERM / SIGHUP listener: stop at the next tick.
	 *
	 * @param int        $signal
	 * @param SignalInfo $info
	 * @return void
	 */
	public function on_stop( int $signal, SignalInfo $info ) : void {
		$this->quit        = true;
		$this->stop_signal = $signal;
	}

	/*
	|-----------
	| MAIN LOOP
	|-----------
	*/

	/**
	 * One turn of the loop: input, timed refreshes, then a redraw if anything changed.
	 *
	 * @return void
	 */
	private function tick() : void {
		$now = \microtime( true );

		if ( ! $this->signals->is_supported() && $now - $this->size_at >= self::SIZE_POLL_INTERVAL ) {
			$this->resized = true;
		}

		if ( $this->resized ) {
			$this->measure();
		}

		if ( $this->dirty ) {
			$this->render();
		}

		foreach ( $this->read_keys( self::TICK ) as $key ) {
			$this->handle_key( $key );

			if ( $this->quit ) {
				return;
			}
		}

		$now = \microtime( true );

		if ( $now - $this->host_at >= self::HOST_INTERVAL ) {
			$this->sample_host();
		}

		if ( null !== $this->checks_at && $now - $this->checks_at >= $this->interval ) {
			$this->refresh_checks();
		}
	}

	/**
	 * Read the terminal size; a change forces a full redraw.
	 *
	 * @return void
	 */
	private function measure() : void {
		$size          = $this->terminal->terminal_size( \STDOUT );
		$this->resized = false;
		$this->size_at = \microtime( true );

		if ( $size['cols'] !== $this->cols || $size['rows'] !== $this->rows ) {
			$this->cols  = $size['cols'];
			$this->rows  = $size['rows'];
			$this->dirty = true;
			$this->output->write( "\033[2J" );
		}
	}

	/*
	|-------
	| DATA
	|-------
	*/

	/**
	 * Take a host resources sample.
	 *
	 * @return void
	 */
	private function sample_host() : void {
		$this->host    = $this->resources->snapshot();
		$this->host_at = \microtime( true );
		$this->dirty   = true;
		++$this->host_samples;
	}

	/**
	 * Run the checks again, keeping the selected check selected.
	 *
	 * @return void
	 */
	private function refresh_checks() : void {
		$this->flash( 'Running checks...' );

		$selected_id = $this->visible_checks()[ $this->selected ]['id'] ?? null;
		$started     = \microtime( true );

		try {
			$checks = $this->health->checks();
			\array_unshift( $checks, $this->health->database_check() );
		} catch ( \Throwable $e ) {
			$this->checks_at = \microtime( true );
			$this->status    = 'The checks failed: ' . $e->getMessage();
			$this->dirty     = true;
			return;
		}

		$this->checks         = self::worst_first( $checks );
		$this->checks_seconds = \microtime( true ) - $started;
		$this->checks_at      = \microtime( true );
		$this->status         = '';
		$this->dirty          = true;

		$visible        = $this->visible_checks();
		$this->selected = 0;

		foreach ( $visible as $index => $check ) {
			if ( $check['id'] === $selected_id ) {
				$this->selected = $index;
				break;
			}
		}
	}

	/**
	 * Order checks worst first; checks of the same status keep their order.
	 *
	 * @param array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> $checks
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private static function worst_first( array $checks ) : array {
		$order = \array_flip( \array_keys( self::STATUSES ) );
		$keys  = \array_keys( $checks );

		\usort(
			$keys,
			static fn( int|string $a, int|string $b ) : int => [ $order[ $checks[ $a ]['status'] ] ?? 2, $a ] <=> [ $order[ $checks[ $b ]['status'] ] ?? 2, $b ]
		);

		return \array_map( static fn( int|string $key ) : array => $checks[ $key ], $keys );
	}

	/**
	 * Load the current diagnostics section, unless it is loaded.
	 *
	 * @param bool $reload Load it again even if loaded.
	 * @return void
	 */
	private function load_section( bool $reload = false ) : void {
		$name = $this->sections[ $this->section ] ?? null;

		if ( null === $name || ( ! $reload && isset( $this->diagnostics[ $name ] ) ) ) {
			return;
		}

		$this->flash( \sprintf( 'Collecting %s...', $name ) );

		try {
			$this->diagnostics[ $name ] = $this->health->diagnostics( [ $name ] )[ $name ] ?? [];
			$this->status               = '';
		} catch ( \Throwable $e ) {
			$this->diagnostics[ $name ] = [ 'Error' => $e->getMessage() ];
		}

		$this->dirty = true;
	}

	/**
	 * Show a status message now, before slow work blocks the loop.
	 *
	 * @param string $message
	 * @return void
	 */
	private function flash( string $message ) : void {
		$this->status = $message;

		if ( $this->active && $this->cols > 0 ) {
			$this->render();
		}
	}

	/**
	 * The checks the current filter shows.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function visible_checks() : array {
		$statuses = self::FILTERS[ $this->filter ]['statuses'];

		return \array_values( \array_filter( $this->checks, static fn( array $check ) : bool => \in_array( $check['status'], $statuses, true ) || ( ! isset( self::STATUSES[ $check['status'] ] ) && \in_array( 'info', $statuses, true ) ) ) );
	}

	/**
	 * Number of checks of each status.
	 *
	 * @return array<string, int>
	 */
	private function counts() : array {
		$counts = \array_fill_keys( \array_keys( self::STATUSES ), 0 );

		foreach ( $this->checks as $check ) {
			$status = isset( self::STATUSES[ $check['status'] ] ) ? $check['status'] : 'info';
			++$counts[ $status ];
		}

		return $counts;
	}

	/**
	 * The worst status among the checks.
	 *
	 * @return string
	 */
	private function worst() : string {
		foreach ( $this->counts() as $status => $count ) {
			if ( $count > 0 ) {
				return $status;
			}
		}

		return 'pass';
	}

	/*
	|-------
	| INPUT
	|-------
	*/

	/**
	 * Wait up to $timeout for input and decode the keys that arrived.
	 *
	 * @param int $timeout Microseconds.
	 * @return string[] Key names: 'up', 'down', 'left', 'right', 'pageup', 'pagedown',
	 *                  'home', 'end', 'enter', 'esc', 'tab', 'backtab', 'backspace',
	 *                  'ctrl-c', 'ctrl-z', or the character typed.
	 */
	private function read_keys( int $timeout ) : array {
		$read   = [ \STDIN ];
		$write  = null;
		$except = null;

		// False when a signal interrupts the wait; the loop just runs again.
		if ( ! @\stream_select( $read, $write, $except, 0, $timeout ) ) {
			return [];
		}

		$bytes = @\fread( \STDIN, 256 );

		return false === $bytes || '' === $bytes ? [] : $this->decode_keys( $bytes );
	}

	/**
	 * Decode raw terminal input into key names.
	 *
	 * @param string $bytes
	 * @return string[]
	 */
	private function decode_keys( string $bytes ) : array {
		$keys   = [];
		$length = \strlen( $bytes );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = $bytes[ $i ];

			if ( "\033" === $byte ) {
				// CSI ("ESC [ params final") or SS3 ("ESC O final"); a lone ESC is the Esc key.
				if ( \preg_match( '/\G\033(?:\[([0-9;]*)([@-~])|O([A-Za-z]))/', $bytes, $match, 0, $i ) ) {
					$keys[] = $this->sequence_key( $match[1], '' !== ( $match[3] ?? '' ) ? $match[3] : $match[2] );
					$i     += \strlen( $match[0] ) - 1;
				} else {
					$keys[] = 'esc';
				}

				continue;
			}

			$keys[] = match ( $byte ) {
				"\r", "\n"    => 'enter',
				"\t"          => 'tab',
				"\x7f", "\x08" => 'backspace',
				"\x03"        => 'ctrl-c',
				"\x1a"        => 'ctrl-z',
				default       => $byte,
			};
		}

		return $keys;
	}

	/**
	 * Name the key an escape sequence stands for.
	 *
	 * @param string $params Numeric parameters, e.g. "5" in "ESC [ 5 ~".
	 * @param string $final  Final character.
	 * @return string
	 */
	private function sequence_key( string $params, string $final ) : string {
		return match ( $final ) {
			'A'     => 'up',
			'B'     => 'down',
			'C'     => 'right',
			'D'     => 'left',
			'H'     => 'home',
			'F'     => 'end',
			'Z'     => 'backtab',
			'~'     => match ( \explode( ';', $params )[0] ) {
				'1', '7' => 'home',
				'4', '8' => 'end',
				'5'      => 'pageup',
				'6'      => 'pagedown',
				default  => 'unknown',
			},
			default => 'unknown',
		};
	}

	/**
	 * Act on one key.
	 *
	 * @param string $key
	 * @return void
	 */
	private function handle_key( string $key ) : void {
		$this->dirty = true;

		switch ( $key ) {
			case 'q':
			case 'Q':
			case 'ctrl-c':
				$this->quit = true;
				return;

			case 'ctrl-z':
				$this->status = 'Suspending is not available here; press Q to quit.';
				return;

			case 'r':
			case 'R':
				if ( 'diagnostics' === $this->view ) {
					$this->load_section( true );
				} else {
					$this->refresh_checks();
				}
				return;

			case 'tab':
			case 'backtab':
				$this->view   = 'checks' === $this->view ? 'diagnostics' : 'checks';
				$this->detail = false;

				if ( 'diagnostics' === $this->view ) {
					$this->load_section();
				}
				return;
		}

		if ( 'diagnostics' === $this->view ) {
			$this->handle_diagnostics_key( $key );
		} elseif ( $this->detail ) {
			$this->handle_detail_key( $key );
		} else {
			$this->handle_checks_key( $key );
		}
	}

	/**
	 * Keys of the checks list.
	 *
	 * @param string $key
	 * @return void
	 */
	private function handle_checks_key( string $key ) : void {
		$count = \count( $this->visible_checks() );
		$page  = \max( 1, $this->main_height() - 4 );

		match ( $key ) {
			'up', 'k'   => $this->selected--,
			'down', 'j' => $this->selected++,
			'pageup'    => $this->selected -= $page,
			'pagedown'  => $this->selected += $page,
			'home', 'g' => $this->selected = 0,
			'end', 'G'  => $this->selected = $count - 1,
			default     => null,
		};

		if ( 'enter' === $key && $count > 0 ) {
			$this->detail        = true;
			$this->detail_scroll = 0;
		}

		if ( 'f' === $key || 'F' === $key ) {
			$this->filter   = ( $this->filter + 1 ) % \count( self::FILTERS );
			$this->selected = 0;
			$this->scroll   = 0;
			$count          = \count( $this->visible_checks() );
		}

		$this->selected = \max( 0, \min( $this->selected, $count - 1 ) );
	}

	/**
	 * Keys of an open check.
	 *
	 * @param string $key
	 * @return void
	 */
	private function handle_detail_key( string $key ) : void {
		$count = \count( $this->visible_checks() );

		switch ( $key ) {
			case 'esc':
			case 'enter':
			case 'backspace':
				$this->detail = false;
				break;

			case 'up':
			case 'k':
				$this->detail_scroll = \max( 0, $this->detail_scroll - 1 );
				break;

			case 'down':
			case 'j':
				++$this->detail_scroll;
				break;

			case 'left':
			case 'h':
				$this->selected      = \max( 0, $this->selected - 1 );
				$this->detail_scroll = 0;
				break;

			case 'right':
			case 'l':
				$this->selected      = \min( $count - 1, $this->selected + 1 );
				$this->detail_scroll = 0;
				break;
		}
	}

	/**
	 * Keys of the diagnostics browser.
	 *
	 * @param string $key
	 * @return void
	 */
	private function handle_diagnostics_key( string $key ) : void {
		$count = \count( $this->sections );
		$page  = \max( 1, $this->main_height() - 4 );

		$section = match ( $key ) {
			'left', 'h'  => ( $this->section - 1 + $count ) % \max( 1, $count ),
			'right', 'l' => ( $this->section + 1 ) % \max( 1, $count ),
			default      => $this->section,
		};

		if ( $section !== $this->section ) {
			$this->section        = $section;
			$this->section_scroll = 0;
			$this->load_section();
			return;
		}

		match ( $key ) {
			'up', 'k'   => $this->section_scroll--,
			'down', 'j' => $this->section_scroll++,
			'pageup'    => $this->section_scroll -= $page,
			'pagedown'  => $this->section_scroll += $page,
			'home', 'g' => $this->section_scroll = 0,
			'end', 'G'  => $this->section_scroll = \PHP_INT_MAX,
			default     => null,
		};

		// Clamped against the content when drawn.
		$this->section_scroll = \max( 0, $this->section_scroll );
	}

	/*
	|-----------
	| RENDERING
	|-----------
	*/

	/**
	 * Draw the whole frame in one write.
	 *
	 * @return void
	 */
	private function render() : void {
		$this->dirty = false;

		if ( $this->cols < self::MIN_COLS || $this->rows < self::MIN_ROWS ) {
			$lines = \array_fill( 0, \max( 1, $this->rows ), '' );
			$text  = \sprintf( 'Enlarge the terminal to %dx%d (it is %dx%d). Q quits.', self::MIN_COLS, self::MIN_ROWS, $this->cols, $this->rows );

			$lines[ (int) \floor( \count( $lines ) / 2 ) ] = $this->fit( \str_repeat( ' ', \max( 0, (int) ( ( $this->cols - $this->length( $text ) ) / 2 ) ) ) . $text, \max( 1, $this->cols ) );
			$this->flush( $lines );
			return;
		}

		$lines = [ $this->title_bar() ];

		foreach ( $this->top_panels() as $line ) {
			$lines[] = $line;
		}

		foreach ( 'diagnostics' === $this->view ? $this->diagnostics_panel() : ( $this->detail ? $this->detail_panel() : $this->checks_panel() ) as $line ) {
			$lines[] = $line;
		}

		$lines[] = $this->status_line();
		$lines[] = $this->key_bar();

		$this->flush( $lines );
	}

	/**
	 * Write frame lines from the top-left corner.
	 *
	 * @param string[] $lines Each exactly $cols columns wide.
	 * @return void
	 */
	private function flush( array $lines ) : void {
		$frame = '';

		foreach ( \array_slice( $lines, 0, $this->rows ) as $row => $line ) {
			$frame .= "\033[" . ( $row + 1 ) . ';1H' . $line . "\033[0m";
		}

		$this->output->write( $frame );
	}

	/**
	 * Top bar: product, page, site and clock.
	 *
	 * @return string
	 */
	private function title_bar() : string {
		$meta  = $this->meta ?? [ 'product' => '', 'site' => '' ];
		$left  = ' ' . \strtoupper( $meta['product'] ) . '  │  ' . ( $this->active ? 'Site Health Monitor' : 'Site Health' );
		$right = $meta['site'] . '  ' . \date( 'Y-m-d H:i:s' ) . ' ';
		$space = $this->cols - $this->length( $left ) - $this->length( $right );

		$text = $space >= 2
			? $left . \str_repeat( ' ', $space ) . $right
			: $left;

		return $this->sgr( self::SGR_BAR, $this->fit( $text, $this->cols ) );
	}

	/**
	 * The row of summary panels: health, host and application.
	 *
	 * Three panels from 120 columns; below that, the application panel
	 * is left out (the same facts are under Diagnostics).
	 *
	 * @return string[] 7 lines.
	 */
	private function top_panels() : array {
		if ( $this->cols >= 120 ) {
			$a      = (int) \floor( $this->cols * 0.28 );
			$b      = (int) \floor( $this->cols * 0.36 );
			$panels = [
				$this->box( 'Health', $a, $this->health_lines( $a - 4 ) ),
				$this->box( 'Host', $b, $this->host_lines( $b - 4 ) ),
				$this->box( 'Application', $this->cols - $a - $b, $this->app_lines( $this->cols - $a - $b - 4 ) ),
			];
		} else {
			$a      = (int) \floor( $this->cols * 0.42 );
			$panels = [
				$this->box( 'Health', $a, $this->health_lines( $a - 4 ) ),
				$this->box( 'Host', $this->cols - $a, $this->host_lines( $this->cols - $a - 4 ) ),
			];
		}

		$lines = [];

		for ( $i = 0; $i < 7; $i++ ) {
			$lines[] = \implode( '', \array_column( $panels, $i ) );
		}

		return $lines;
	}

	/**
	 * Health panel: the verdict and a bar per status.
	 *
	 * @param int $width Inner width.
	 * @return string[] 5 lines.
	 */
	private function health_lines( int $width ) : array {
		$counts = $this->counts();
		$total  = \max( 1, \array_sum( $counts ) );
		$worst  = $this->worst();

		$verdict = match ( true ) {
			[] === $this->checks => [ 'text' => 'CHECKING', 'sgr' => self::SGR_DIM ],
			'critical' === $worst => [ 'text' => 'CRITICAL', 'sgr' => '31;1' ],
			'warning' === $worst  => [ 'text' => 'NEEDS ATTENTION', 'sgr' => '33;1' ],
			default               => [ 'text' => 'HEALTHY', 'sgr' => '32;1' ],
		};

		$age = match ( true ) {
			null === $this->checks_at => '',
			! $this->active           => \date( 'H:i:s', (int) $this->checks_at ),
			default                   => $this->age( \microtime( true ) - $this->checks_at ) . ' ago',
		};
		$lines = [ $this->spread( $this->sgr( $verdict['sgr'], '● ' . $verdict['text'] ), $this->sgr( self::SGR_DIM, $age ), $width ) ];

		foreach ( self::STATUSES as $status => $style ) {
			$label   = \str_pad( $style['label'], 9 );
			$number  = \str_pad( (string) $counts[ $status ], 3, ' ', \STR_PAD_LEFT );
			$bar_w   = \max( 4, $width - 9 - 1 - 1 - 3 );
			$lines[] = $this->sgr( self::SGR_LABEL, $label ) . ' ' . $this->bar( $counts[ $status ] / $total, $bar_w, $style['text'] ) . ' ' . $this->sgr( $counts[ $status ] > 0 ? $style['text'] : self::SGR_DIM, $number );
		}

		return $lines;
	}

	/**
	 * Host panel: CPU, memory, swap and disk bars, then load and uptime.
	 *
	 * @param int $width Inner width.
	 * @return string[] 5 lines.
	 */
	private function host_lines( int $width ) : array {
		$host  = $this->host ?? [];
		$lines = [];

		$meters = [
			'CPU'  => null === ( $host['cpu_percent'] ?? null ) ? null : [ $host['cpu_percent'] / 100, \sprintf( '%.1f%%', $host['cpu_percent'] ) ],
			'Mem'  => $this->usage( $host['memory'] ?? null ),
			'Swap' => $this->usage( $host['swap'] ?? null ),
			'Disk' => $this->usage( $host['disk'] ?? null ),
		];

		foreach ( $meters as $label => $meter ) {
			$value_w = 16;
			$bar_w   = \max( 4, $width - 5 - 1 - $value_w );
			$prefix  = $this->sgr( self::SGR_LABEL, \str_pad( $label, 5 ) );

			if ( null === $meter ) {
				// CPU usage is a rate: the first sample has nothing to compare with.
				$pending = 'CPU' === $label && $this->host_samples < 2;
				$lines[] = $prefix . $this->sgr( self::SGR_DIM, $this->fit( $pending ? 'measuring...' : 'n/a', $width - 5 ) );
				continue;
			}

			$lines[] = $prefix . $this->bar( $meter[0], $bar_w, $this->level_sgr( $meter[0] ) ) . ' ' . $this->fit( \str_pad( $meter[1], $value_w, ' ', \STR_PAD_LEFT ), $value_w );
		}

		$load    = $host['load'] ?? null;
		$parts   = [];
		$parts[] = null === $load ? 'Load n/a' : \sprintf( 'Load %.2f %.2f %.2f', $load[0], $load[1], $load[2] );

		if ( null !== ( $host['cpus'] ?? null ) ) {
			$parts[] = $host['cpus'] . ' CPU' . ( 1 === $host['cpus'] ? '' : 's' );
		}

		if ( null !== ( $host['uptime'] ?? null ) ) {
			$parts[] = 'up ' . $this->age( (float) $host['uptime'] );
		}

		$lines[] = $this->sgr( self::SGR_LABEL, $this->fit( \implode( ' · ', $parts ), $width ) );

		return $lines;
	}

	/**
	 * Application panel: version, PHP, database, site and the last run.
	 *
	 * @param int $width Inner width.
	 * @return string[] 5 lines.
	 */
	private function app_lines( int $width ) : array {
		$meta = $this->meta ?? [ 'version' => '', 'schema' => '', 'site' => '' ];
		$php  = $this->host['php_memory'] ?? [ 'used' => 0, 'limit' => null ];
		$db   = '';

		foreach ( $this->checks as $check ) {
			if ( 'database_connection' === $check['id'] ) {
				$db = \rtrim( $check['message'], '.' );
				break;
			}
		}

		$rows = [
			'Version'  => \sprintf( '%s (schema %s)', $meta['version'], $meta['schema'] ),
			'PHP'      => \sprintf( '%s %s · %s / %s', \PHP_VERSION, \PHP_SAPI, Format::bytes( $php['used'] ), null === $php['limit'] ? 'no limit' : Format::bytes( $php['limit'] ) ),
			'Database' => '' === $db ? '...' : $db,
			'Site'     => $meta['site'],
			'Checks'   => $this->active
				? \sprintf( 'every %ds · last took %s', $this->interval, $this->duration( $this->checks_seconds ) )
				: \sprintf( 'ran in %s', $this->duration( $this->checks_seconds ) ),
		];

		$lines = [];

		foreach ( $rows as $label => $value ) {
			$lines[] = $this->sgr( self::SGR_LABEL, \str_pad( $label, 9 ) ) . $this->fit( $value, $width - 9 );
		}

		return $lines;
	}

	/**
	 * The checks table.
	 *
	 * @return string[] main_height() lines.
	 */
	private function checks_panel() : array {
		$height  = $this->main_height();
		$inner   = $this->cols - 4;
		$visible = $this->visible_checks();
		$count   = \count( $visible );
		$body    = $height - 4;

		// Keep the selected row on screen.
		if ( $this->selected < $this->scroll ) {
			$this->scroll = $this->selected;
		} elseif ( $this->selected >= $this->scroll + $body ) {
			$this->scroll = $this->selected - $body + 1;
		}

		$this->scroll = \max( 0, \min( $this->scroll, \max( 0, $count - $body ) ) );

		$label_w = \min( 30, \max( 12, (int) \floor( $inner * 0.28 ) ) );
		$msg_w   = \max( 10, $inner - 4 - 1 - 6 - 2 - $label_w - 2 );
		$header  = \str_pad( '#', 4, ' ', \STR_PAD_LEFT ) . ' ' . 'STATUS' . '  ' . \str_pad( 'CHECK', $label_w ) . '  ' . 'DETAILS';
		$lines   = [ $this->sgr( self::SGR_TITLE, $this->fit( $header, $inner ) ) ];

		for ( $i = 0; $i < $body; $i++ ) {
			$index = $this->scroll + $i;
			$check = $visible[ $index ] ?? null;

			if ( null === $check ) {
				$lines[] = 0 === $count && 0 === $i
					? $this->sgr( self::SGR_DIM, $this->fit( [] === $this->checks ? '  Running checks...' : '  No check matches this filter. Press F to change it.', $inner ) )
					: \str_repeat( ' ', $inner );
				continue;
			}

			$status   = isset( self::STATUSES[ $check['status'] ] ) ? $check['status'] : 'info';
			$selected = $index === $this->selected;
			$row_sgr  = $selected ? self::SGR_SELECT : '';
			$number   = \str_pad( (string) ( $index + 1 ), 4, ' ', \STR_PAD_LEFT ) . ' ';
			$label    = '  ' . $this->fit( $check['label'], $label_w ) . '  ';
			$message  = $this->fit( $check['message'], $msg_w );

			$lines[] = $this->sgr( $row_sgr, $number )
				. $this->sgr( self::STATUSES[ $status ]['sgr'], ' ' . self::STATUSES[ $status ]['badge'] . ' ' )
				. $this->sgr( $row_sgr . ( '' === $row_sgr ? '' : ';' ) . '1', $label )
				. $this->sgr( '' === $row_sgr ? self::SGR_LABEL : $row_sgr, $message );
		}

		$lines[] = $this->sgr( self::SGR_DIM, $this->fit( $count > $body ? \sprintf( '  %d-%d of %d', $this->scroll + 1, \min( $count, $this->scroll + $body ), $count ) : '', $inner ) );

		$title = \sprintf( 'Checks · %s (%d)', self::FILTERS[ $this->filter ]['label'], $count );
		$right = $count > 0 ? \sprintf( '%d/%d', $this->selected + 1, $count ) : '';

		return $this->box( $title, $this->cols, $lines, $right );
	}

	/**
	 * The checks for a printed report: problems in full, grouped worst first.
	 *
	 * @param bool $verbose Show the message of passing checks too.
	 * @return string[]
	 */
	private function checks_report( bool $verbose ) : array {
		$inner   = $this->cols - 4;
		$indent  = \str_repeat( ' ', 7 );
		$counts  = $this->counts();
		$lines   = [];
		$passing = [];

		foreach ( $this->checks as $check ) {
			$status = isset( self::STATUSES[ $check['status'] ] ) ? $check['status'] : 'info';
			$style  = self::STATUSES[ $status ];

			if ( 'pass' === $status && ! $verbose ) {
				$passing[] = $check['label'];
				continue;
			}

			$lines[] = $this->badge( $status ) . ' ' . $this->sgr( '1', $this->fit( $check['label'], $inner - 7, false ) );

			foreach ( $this->wrap( $check['message'], $inner - 7 ) as $line ) {
				$lines[] = $indent . $this->sgr( self::SGR_LABEL, $line );
			}

			$recommendation = (string) $check['recommendation'];

			if ( 'pass' !== $status && '' !== $recommendation && $recommendation !== $check['message'] ) {
				foreach ( $this->wrap( '→ ' . $recommendation, $inner - 7 ) as $n => $line ) {
					$lines[] = $indent . ( 0 === $n ? '' : '  ' ) . $this->sgr( $style['text'], $line );
				}
			}
		}

		if ( [] !== $passing ) {
			foreach ( $this->wrap_items( $passing, ' · ', $inner - 7 ) as $n => $line ) {
				$lines[] = ( 0 === $n ? $this->badge( 'pass' ) . ' ' : $indent ) . $this->sgr( self::STATUSES['pass']['text'], $line );
			}
		}

		$summary = [];

		foreach ( self::STATUSES as $status => $style ) {
			$summary[] = $counts[ $status ] . ' ' . ( 'warning' === $status && 1 === $counts[ $status ] ? 'warning' : \strtolower( $style['label'] ) );
		}

		return $this->box( 'Checks', $this->cols, $lines, \implode( ' · ', $summary ) );
	}

	/**
	 * A status badge, e.g. " CRIT ", coloured; bracketed when there is no colour.
	 *
	 * @param string $status A STATUSES key.
	 * @return string 6 columns.
	 */
	private function badge( string $status ) : string {
		$badge = self::STATUSES[ $status ]['badge'];

		return $this->colour ? $this->sgr( self::STATUSES[ $status ]['sgr'], ' ' . $badge . ' ' ) : '[' . $badge . ']';
	}

	/**
	 * One check in full: message and recommendation.
	 *
	 * @return string[] main_height() lines.
	 */
	private function detail_panel() : array {
		$visible = $this->visible_checks();
		$check   = $visible[ $this->selected ] ?? null;
		$height  = $this->main_height();
		$inner   = $this->cols - 4;

		if ( null === $check ) {
			$this->detail = false;
			return $this->checks_panel();
		}

		$status = isset( self::STATUSES[ $check['status'] ] ) ? $check['status'] : 'info';
		$style  = self::STATUSES[ $status ];
		$text   = [];

		$text[] = $this->sgr( $style['sgr'], ' ' . $style['badge'] . ' ' ) . ' ' . $this->sgr( '1', $this->fit( $check['label'], $inner - 7 ) );
		$text[] = '';
		$text[] = $this->sgr( self::SGR_TITLE, 'What was found' );

		foreach ( $this->wrap( $check['message'], $inner - 2 ) as $line ) {
			$text[] = '  ' . $line;
		}

		$text[] = '';
		$text[] = $this->sgr( self::SGR_TITLE, 'What to do' );

		$recommendation = null === $check['recommendation'] || '' === $check['recommendation'] || $check['recommendation'] === $check['message']
			? match ( $status ) {
				'pass'  => 'Nothing: this check passes.',
				'info'  => 'Nothing is required; this is for your information.',
				default => 'See what was found above.',
			}
			: $check['recommendation'];

		foreach ( $this->wrap( $recommendation, $inner - 2 ) as $line ) {
			$text[] = '  ' . $line;
		}

		$text[] = '';
		$text[] = $this->sgr( self::SGR_DIM, 'Check ID: ' . $check['id'] );

		$body                = $height - 2;
		$this->detail_scroll = \min( $this->detail_scroll, \max( 0, \count( $text ) - $body ) );
		$lines               = \array_slice( $text, $this->detail_scroll, $body );

		while ( \count( $lines ) < $body ) {
			$lines[] = '';
		}

		$title = \sprintf( 'Check %d of %d · %s', $this->selected + 1, \count( $visible ), self::FILTERS[ $this->filter ]['label'] );

		return $this->box( $title, $this->cols, $lines );
	}

	/**
	 * The diagnostics browser: section tabs, then label/value rows.
	 *
	 * @return string[] main_height() lines.
	 */
	private function diagnostics_panel() : array {
		$height = $this->main_height();
		$inner  = $this->cols - 4;
		$name   = $this->sections[ $this->section ] ?? '';
		$rows   = $this->diagnostics[ $name ] ?? null;

		$lines = [ $this->section_tabs( $inner ), $this->sgr( self::SGR_BORDER, \str_repeat( '─', $inner ) ) ];
		$text  = [];

		if ( null === $rows ) {
			$text[] = $this->sgr( self::SGR_DIM, 'Collecting...' );
		} elseif ( [] === $rows ) {
			$text[] = $this->sgr( self::SGR_DIM, 'Nothing to report.' );
		} else {
			$label_w = \min( 30, \max( \array_map( fn( $label ) : int => $this->length( (string) $label ), \array_keys( $rows ) ) ) );
			$value_w = \max( 10, $inner - $label_w - 2 );

			foreach ( $rows as $label => $value ) {
				$wrapped = $this->wrap( (string) $value, $value_w );

				foreach ( $wrapped as $n => $part ) {
					$text[] = ( 0 === $n ? $this->sgr( self::SGR_LABEL, $this->fit( (string) $label, $label_w ) ) : \str_repeat( ' ', $label_w ) ) . '  ' . $part;
				}
			}
		}

		$body                 = $height - 4;
		$this->section_scroll = \min( $this->section_scroll, \max( 0, \count( $text ) - $body ) );

		foreach ( \array_slice( $text, $this->section_scroll, $body ) as $line ) {
			$lines[] = $line;
		}

		while ( \count( $lines ) < $height - 2 ) {
			$lines[] = '';
		}

		$right = \count( $text ) > $body ? \sprintf( '%d-%d of %d', $this->section_scroll + 1, \min( \count( $text ), $this->section_scroll + $body ), \count( $text ) ) : '';

		return $this->box( 'Diagnostics · ' . $name, $this->cols, $lines, $right );
	}

	/**
	 * The section names, the current one highlighted and kept in view.
	 *
	 * @param int $width
	 * @return string
	 */
	private function section_tabs( int $width ) : string {
		$tabs  = [];
		$start = 0;

		foreach ( $this->sections as $index => $name ) {
			$tabs[ $index ] = ' ' . $name . ' ';
		}

		// Drop tabs from the left until the current one fits.
		while ( $start < $this->section && $this->length( \implode( ' ', \array_slice( $tabs, $start, $this->section - $start + 1 ) ) ) > $width ) {
			++$start;
		}

		$line = '';
		$used = 0;

		foreach ( \array_slice( $tabs, $start, null, true ) as $index => $tab ) {
			$size = $this->length( $tab ) + ( '' === $line ? 0 : 1 );

			if ( $used + $size > $width ) {
				break;
			}

			$line .= ( '' === $line ? '' : ' ' ) . ( $index === $this->section ? $this->sgr( self::SGR_KEY, $tab ) : $this->sgr( self::SGR_LABEL, $tab ) );
			$used += $size;
		}

		return $line;
	}

	/**
	 * Status line: a message, or when the checks last ran and run next.
	 *
	 * @return string
	 */
	private function status_line() : string {
		$left = $this->status;

		if ( '' === $left && null !== $this->checks_at ) {
			$left = \sprintf(
				'Checks ran %s in %s · next in %ds · values from the console\'s PHP %s',
				\date( 'H:i:s', (int) $this->checks_at ),
				$this->duration( $this->checks_seconds ),
				\max( 0, (int) \ceil( $this->interval - ( \microtime( true ) - $this->checks_at ) ) ),
				\PHP_VERSION
			);
		}

		$right = 'diagnostics' === $this->view ? 'DIAGNOSTICS' : ( $this->detail ? 'DETAIL' : 'CHECKS' );

		return $this->spread( $this->sgr( self::SGR_DIM, ' ' . $left ), $this->sgr( self::SGR_TITLE, '  ' . $right . ' ' ), $this->cols );
	}

	/**
	 * Key hints for the current view, htop style.
	 *
	 * @return string
	 */
	private function key_bar() : string {
		$keys = match ( true ) {
			'diagnostics' === $this->view => [ '←→' => 'Section', '↑↓' => 'Scroll', 'Tab' => 'Checks', 'R' => 'Reload', 'Q' => 'Quit' ],
			$this->detail                 => [ '←→' => 'Prev/Next', '↑↓' => 'Scroll', 'Esc' => 'Back', 'Tab' => 'Diagnostics', 'Q' => 'Quit' ],
			default                       => [ '↑↓' => 'Select', 'Enter' => 'Details', 'F' => 'Filter', 'Tab' => 'Diagnostics', 'R' => 'Run now', 'Q' => 'Quit' ],
		};

		$line = '';
		$used = 0;

		foreach ( $keys as $key => $label ) {
			$size = $this->length( $key ) + $this->length( $label ) + 2;

			if ( $used + $size > $this->cols ) {
				break;
			}

			$line .= $this->sgr( self::SGR_KEY, $key ) . ' ' . $label . ' ';
			$used += $size;
		}

		return $line . \str_repeat( ' ', \max( 0, $this->cols - $used ) );
	}

	/*
	|------------------
	| DRAWING HELPERS
	|------------------
	*/

	/**
	 * Height of the main panel: the rows left by the title bar, the top
	 * panels and the two footer lines.
	 *
	 * @return int
	 */
	private function main_height() : int {
		return \max( 5, $this->rows - 1 - 7 - 2 );
	}

	/**
	 * Draw a box around content lines.
	 *
	 * Lines are padded or cut to the inner width; the box is exactly $width
	 * columns, and as tall as the content plus its borders.
	 *
	 * @param string   $title
	 * @param int      $width Outer width.
	 * @param string[] $lines Content, without the borders.
	 * @param string   $right Text at the right of the top border.
	 * @return string[]
	 */
	private function box( string $title, int $width, array $lines, string $right = '' ) : array {
		$inner = $width - 4;
		$title = $this->fit( ' ' . $title . ' ', \max( 0, $inner - 2 ), false );
		$right = '' === $right ? '' : ' ' . $right . ' ';
		$fill  = \max( 0, $width - 3 - $this->length( $title ) - $this->length( $right ) - 1 );

		$box = [
			$this->sgr( self::SGR_BORDER, '┌─' ) . $this->sgr( self::SGR_TITLE, $title ) . $this->sgr( self::SGR_BORDER, \str_repeat( '─', $fill ) ) . $this->sgr( self::SGR_DIM, $right ) . $this->sgr( self::SGR_BORDER, '─┐' ),
		];

		foreach ( $lines as $line ) {
			$box[] = $this->sgr( self::SGR_BORDER, '│' ) . ' ' . $this->pad( $line, $inner ) . ' ' . $this->sgr( self::SGR_BORDER, '│' );
		}

		$box[] = $this->sgr( self::SGR_BORDER, '└' . \str_repeat( '─', $width - 2 ) . '┘' );

		return $box;
	}

	/**
	 * A meter: filled and empty blocks for a 0-1 fraction.
	 *
	 * @param float  $fraction
	 * @param int    $width
	 * @param string $sgr Colour of the filled part.
	 * @return string
	 */
	private function bar( float $fraction, int $width, string $sgr ) : string {
		$fraction = \max( 0.0, \min( 1.0, $fraction ) );
		$filled   = (int) \round( $fraction * $width );

		// Anything above zero shows at least one block.
		if ( 0 === $filled && $fraction > 0 ) {
			$filled = 1;
		}

		return $this->sgr( $sgr, \str_repeat( '█', $filled ) ) . $this->sgr( self::SGR_DIM, \str_repeat( '░', $width - $filled ) );
	}

	/**
	 * A used/total meter value, or null when unavailable.
	 *
	 * @param array{total: int, used: int}|null $usage
	 * @return array{0: float, 1: string}|null Fraction and label.
	 */
	private function usage( ?array $usage ) : ?array {
		if ( null === $usage ) {
			return null;
		}

		if ( $usage['total'] <= 0 ) {
			return [ 0.0, 'none' ];
		}

		return [ $usage['used'] / $usage['total'], $this->short_bytes( $usage['used'] ) . '/' . $this->short_bytes( $usage['total'] ) ];
	}

	/**
	 * Meter colour by how full it is.
	 *
	 * @param float $fraction
	 * @return string
	 */
	private function level_sgr( float $fraction ) : string {
		return match ( true ) {
			$fraction >= 0.9 => '31;1',
			$fraction >= 0.7 => '33',
			default          => '32',
		};
	}

	/**
	 * Compact byte size: 512M, 7.8G.
	 *
	 * @param int $bytes
	 * @return string
	 */
	private function short_bytes( int $bytes ) : string {
		foreach ( [ 'T' => 1024 ** 4, 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024 ] as $unit => $size ) {
			if ( $bytes >= $size ) {
				$value = $bytes / $size;
				return ( $value >= 10 ? \number_format( $value, 0 ) : \number_format( $value, 1 ) ) . $unit;
			}
		}

		return $bytes . 'B';
	}

	/**
	 * How long something took: 85 ms, 1.42 s.
	 *
	 * @param float $seconds
	 * @return string
	 */
	private function duration( float $seconds ) : string {
		return $seconds < 1 ? \sprintf( '%d ms', (int) \round( $seconds * 1000 ) ) : \sprintf( '%.2f s', $seconds );
	}

	/**
	 * Compact duration: 45s, 12m, 3h 20m, 4d 2h.
	 *
	 * @param float $seconds
	 * @return string
	 */
	private function age( float $seconds ) : string {
		$seconds = (int) $seconds;

		return match ( true ) {
			$seconds < 60    => $seconds . 's',
			$seconds < 3600  => \intdiv( $seconds, 60 ) . 'm',
			$seconds < 86400 => \sprintf( '%dh %dm', \intdiv( $seconds, 3600 ), \intdiv( $seconds % 3600, 60 ) ),
			default          => \sprintf( '%dd %dh', \intdiv( $seconds, 86400 ), \intdiv( $seconds % 86400, 3600 ) ),
		};
	}

	/**
	 * Put $left at the start and $right at the end of a $width-column line.
	 *
	 * Either may hold colour codes; $left is cut when both do not fit.
	 *
	 * @param string $left
	 * @param string $right
	 * @param int    $width
	 * @return string
	 */
	private function spread( string $left, string $right, int $width ) : string {
		$right_w = $this->length( $right );

		if ( $right_w >= $width ) {
			return $this->pad( $right, $width );
		}

		return $this->pad( $left, $width - $right_w ) . $right;
	}

	/**
	 * Wrap plain text in SGR codes. An empty code leaves the text as it is.
	 *
	 * @param string $sgr  SGR parameters, e.g. "31;1".
	 * @param string $text
	 * @return string
	 */
	private function sgr( string $sgr, string $text ) : string {
		return '' === $sgr || '' === $text || ! $this->colour ? $text : "\033[" . $sgr . 'm' . $text . "\033[0m";
	}

	/**
	 * Cut or pad plain text to exactly $width columns.
	 *
	 * @param string $text
	 * @param int    $width
	 * @param bool   $pad  Pad short text with spaces.
	 * @return string
	 */
	private function fit( string $text, int $width, bool $pad = true ) : string {
		if ( $width <= 0 ) {
			return '';
		}

		$text   = \str_replace( [ "\r", "\n", "\t" ], ' ', $text );
		$length = $this->length( $text );

		if ( $length > $width ) {
			return $this->cut( $text, $width - 1 ) . '…';
		}

		return $pad ? $text . \str_repeat( ' ', $width - $length ) : $text;
	}

	/**
	 * Pad text that may hold colour codes to exactly $width columns.
	 *
	 * Too long, it is cut as plain text (its colours are dropped).
	 *
	 * @param string $text
	 * @param int    $width
	 * @return string
	 */
	private function pad( string $text, int $width ) : string {
		$length = $this->length( $text );

		if ( $length > $width ) {
			return $this->fit( $this->strip( $text ), $width );
		}

		return $text . \str_repeat( ' ', $width - $length );
	}

	/**
	 * Word-wrap plain text to $width columns. A longer word is cut into pieces.
	 *
	 * @param string $text
	 * @param int    $width
	 * @return string[]
	 */
	private function wrap( string $text, int $width ) : array {
		$width   = \max( 1, $width );
		$lines   = [];
		$current = '';

		foreach ( \preg_split( '/\s+/u', \trim( $text ) ) ?: [] as $word ) {
			while ( $this->length( $word ) > $width ) {
				if ( '' !== $current ) {
					$lines[] = $current;
					$current = '';
				}

				$lines[] = $this->cut( $word, $width );
				$word    = \substr( $word, \strlen( $lines[ \count( $lines ) - 1 ] ) );
			}

			if ( '' === $current ) {
				$current = $word;
			} elseif ( $this->length( $current ) + 1 + $this->length( $word ) <= $width ) {
				$current .= ' ' . $word;
			} else {
				$lines[] = $current;
				$current = $word;
			}
		}

		$lines[] = $current;

		return $lines;
	}

	/**
	 * Join items into lines of at most $width columns, never splitting an item.
	 *
	 * An item longer than the width gets a line of its own, cut to fit.
	 *
	 * @param string[] $items
	 * @param string   $separator
	 * @param int      $width
	 * @return string[]
	 */
	private function wrap_items( array $items, string $separator, int $width ) : array {
		$lines   = [];
		$current = '';

		foreach ( $items as $item ) {
			$candidate = '' === $current ? $item : $current . $separator . $item;

			if ( '' === $current || $this->length( $candidate ) <= $width ) {
				$current = $candidate;
				continue;
			}

			$lines[] = $current;
			$current = $item;
		}

		$lines[] = $current;

		return \array_map( fn( string $line ) : string => $this->fit( $line, $width, false ), $lines );
	}

	/**
	 * Visible width: characters, not counting colour codes.
	 *
	 * @param string $text
	 * @return int
	 */
	private function length( string $text ) : int {
		return (int) \preg_match_all( '/./us', $this->strip( $text ) );
	}

	/**
	 * The first $count characters of plain text.
	 *
	 * @param string $text
	 * @param int    $count
	 * @return string
	 */
	private function cut( string $text, int $count ) : string {
		return 1 === \preg_match( '/^.{0,' . \max( 0, $count ) . '}/us', $text, $match ) ? $match[0] : '';
	}

	/**
	 * Remove colour codes.
	 *
	 * @param string $text
	 * @return string
	 */
	private function strip( string $text ) : string {
		return (string) \preg_replace( '/\033\[[0-9;?]*[A-Za-z]/', '', $text );
	}
}