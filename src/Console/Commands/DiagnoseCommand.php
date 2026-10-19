<?php
/**
 * Diagnose command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Console\Tui\HealthMonitor;
use SmartLicenseServer\Environments\Application\Health\HostResources;
use SmartLicenseServer\Environments\Application\Health\SystemHealth;
use SmartLicenseServer\Utils\Format;

/**
 * Site health checks and system diagnostics, from the console.
 *
 * The console face of SystemHealth, which also feeds the admin Tools >
 * Site Health and System Diagnostics pages, so both always agree.
 *
 * Philosophy:
 *
 *  - Problems first. `diagnose health` prints the live view's screen once
 *    (see HealthMonitor::print()): the verdict, host and application
 *    panels, then the checks worst first, each problem with what to do.
 *    Passing checks are listed by name only, unless the output is verbose.
 *  - The exit code is the verdict. `diagnose health` exits 0 when nothing
 *    is worse than info, 1 when the worst is a warning and 2 when a check
 *    is critical, so cron or monitoring can act on it without parsing
 *    text. With --quiet only a critical summary is printed.
 *  - Honest about where it runs. The report describes this PHP process,
 *    the CLI's, whose version, ini limits and extensions can differ from
 *    the web server's. Every report says so.
 *  - Text for people, --json for machines. The JSON is the same data the
 *    admin pages render, plus the report header and the context.
 *  - A live view for watching. `diagnose top` shows the same checks full
 *    screen, refreshed, with the host's CPU, memory and disk (see
 *    HealthMonitor). Where the terminal cannot show it, it prints the
 *    health report instead, so it never fails for lack of a terminal.
 */
class DiagnoseCommand extends AbstractCommand {

	/**
	 * Check statuses, worst first.
	 *
	 * @var string[]
	 */
	private const STATUSES = [ 'critical', 'warning', 'info', 'pass' ];

	/**
	 * Exit code of `diagnose health` for the worst status found.
	 *
	 * @var array<string, int>
	 */
	private const EXIT_CODES = [
		'critical' => 2,
		'warning'  => 1,
		'info'     => 0,
		'pass'     => 0,
	];

	/**
	 * Narrowest text width used for wrapping, in characters.
	 *
	 * @var int
	 */
	private const MIN_WIDTH = 60;

	/**
	 * Widest text width used for wrapping, in characters.
	 *
	 * @var int
	 */
	private const MAX_WIDTH = 120;

	/**
	 * Diagnostics sections that walk the installation folder, which can take a while.
	 *
	 * @var string[]
	 */
	private const SLOW_SECTIONS = [ 'Server', 'Directories' ];

	/**
	 * Diagnostic labels are padded to at most this width.
	 *
	 * @var int
	 */
	private const MAX_LABEL_WIDTH = 30;

	/**
	 * Constructor.
	 *
	 * @param SystemHealth    $health      Builds the checks and diagnostics.
	 * @param HostResources   $resources   Host CPU, memory and disk, for the live view.
	 * @param Terminal        $terminal    Decides colours and the wrapping width.
	 * @param SignalManager   $signals     Resize and termination signals, for the live view.
	 * @param InputInterface  $io          CLI input interface stream.
	 * @param OutputInterface $output      CLI output interface stream.
	 * @param ScriptName      $script_name Executable binary alias context.
	 */
	public function __construct(
		protected SystemHealth $health,
		protected HostResources $resources,
		protected Terminal $terminal,
		protected SignalManager $signals,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function name() : string {
		return 'diagnose';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Run the site health checks, or show the system diagnostics.';
	}

	/**
	 * {@inheritdoc}
	 *
	 * The command runs the health checks on its own, so the subcommand is optional.
	 */
	public function synopsis() : string {
		return sprintf( '%s [health|info|top] [options]', $this->command_prefix() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'Subcommands:',
			'  health              Run the site health checks (the default).',
			'  info                Show the system diagnostics: server, database, directories, and more.',
			'  top                 Live, full-screen view of the checks and the host, like htop.',
			'  help                Show this help message.',
			'',
			'Options:',
			'  --json              Print the report as JSON.',
			'  --section=<name>    info only: show this section. Repeat for several.',
			'                      Names as listed, e.g. --section=database --section="loaded extensions".',
			'  -v, --verbose       health: also print the message of each passing check.',
			'  -q, --quiet         health: print nothing but a critical summary; use the exit code.',
			'  --interval=<sec>    top: seconds between check runs. Default: 10, at least 2.',
			'',
			'In top: Up/Down select, Enter opens a check, F filters, Tab shows the diagnostics,',
			'R runs the checks now, Q or Ctrl+C quits.',
			'',
			'Exit codes (health, and top from its last run):',
			'  0  Nothing worse than info.',
			'  1  At least one warning.',
			'  2  At least one critical problem.',
			'',
			'The report describes the console\'s PHP, which can differ from the web server\'s',
			'(version, php.ini limits, extensions). The admin Tools pages show the web server\'s.',
			'',
			'Examples:',
			"  {$prefix}",
			"  {$prefix} health --verbose",
			"  {$prefix} info --section=database",
			"  {$prefix} health --json",
			"  {$prefix} top --interval=5",
		] );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Handlers must stay public: AbstractCommandRouter invokes them as
	 * callables from outside this class.
	 */
	public function get_subcommands() : array {
		return [
			'health' => [ $this, 'handle_health' ],
			'info'   => [ $this, 'handle_info' ],
			'top'    => [ $this, 'handle_top' ],
			'help'   => [ $this, 'handle_help' ],
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Reached when no subcommand is given: runs the health checks.
	 */
	public function run( CommandInput $input ) : int {
		return $this->handle_health( $input );
	}

	/*
	|-------------
	| SUBCOMMANDS
	|-------------
	*/

	/**
	 * Run the health checks, including the database connection check.
	 *
	 * @param CommandInput $input
	 * @return int 0, 1 or 2; see EXIT_CODES.
	 */
	public function handle_health( CommandInput $input ) : int {
		$started = microtime( true );
		$checks  = $this->health->checks();
		$db      = $this->health->database_check();
		$seconds = microtime( true ) - $started;

		// The database connection is the first thing to know about.
		array_unshift( $checks, $db );

		$groups = $this->group_checks( $checks );
		$worst  = $this->worst_status( $groups );
		$counts = array_map( 'count', $groups );

		if ( $this->wants_json( $input ) ) {
			$this->print_json(
				$this->health->report_meta() + [
					'context' => $this->context(),
					'status'  => $worst,
					'summary' => $counts,
					'checks'  => $checks,
				]
			);

			return self::EXIT_CODES[ $worst ];
		}

		// Quiet: the summary alone, and only when critical (see print_summary()).
		if ( $this->output->get_verbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
			$this->monitor()->print( $checks, $seconds, $this->output->get_verbosity() >= OutputInterface::VERBOSITY_VERBOSE );

			$context = $this->context();

			$this->print_wrapped(
				sprintf(
					'Checked from the console with PHP %s (%s); the admin Tools pages show the web server\'s. Live view: `%s`.',
					$context['php_version'],
					null === $context['php_ini'] ? 'no php.ini loaded' : $context['php_ini'],
					$this->command_prefix( 'top' )
				),
				' ',
				' ',
				ConsoleOutput::ANSI_DIM
			);
		}

		$this->print_summary( $worst, $counts );

		return self::EXIT_CODES[ $worst ];
	}

	/**
	 * Show the system diagnostics, optionally only some sections.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_info( CommandInput $input ) : int {
		$sections = $this->requested_sections( $input );

		if ( null === $sections ) {
			return 1;
		}

		$json = $this->wants_json( $input );

		if ( ! $json && [] !== array_intersect( $sections ?: $this->health->diagnostic_sections(), self::SLOW_SECTIONS ) ) {
			$this->output->info( 'Collecting diagnostics; measuring the installation folder can take a while...' );
		}

		$diagnostics = $this->health->diagnostics( $sections ?: null );

		if ( $json ) {
			$this->print_json(
				$this->health->report_meta() + [
					'context'     => $this->context(),
					'diagnostics' => $diagnostics,
				]
			);

			return 0;
		}

		$this->print_header( 'System diagnostics' );

		foreach ( $diagnostics as $section => $rows ) {
			$this->print_section( $section, $rows );
		}

		return 0;
	}

	/**
	 * Show the live view, or the health report where the terminal cannot show it.
	 *
	 * @param CommandInput $input
	 * @return int The verdict of the last check run; see EXIT_CODES.
	 */
	public function handle_top( CommandInput $input ) : int {
		$interval = $input->get_option( 'interval', 10 );

		if ( ( ! is_string( $interval ) && ! is_int( $interval ) ) || ! ctype_digit( (string) $interval ) ) {
			$this->output->error( sprintf( 'Usage: %s [--interval=<seconds>]', $this->command_prefix( 'top' ) ) );
			return 1;
		}

		if ( ! HealthMonitor::supported( $this->terminal ) ) {
			$this->output->warning( 'The live view needs an interactive terminal with ANSI support and stty; showing the report instead.' );
			return $this->handle_health( $input );
		}

		return $this->monitor( (int) $interval )->run();
	}

	/**
	 * The monitor, for the live view or the printed report.
	 *
	 * @param int $interval Seconds between check runs in the live view.
	 * @return HealthMonitor
	 */
	private function monitor( int $interval = 10 ) : HealthMonitor {
		return new HealthMonitor( $this->health, $this->resources, $this->terminal, $this->signals, $this->output, $interval );
	}

	/*
	|-----------------
	| HEALTH CHECKS
	|-----------------
	*/

	/**
	 * Group checks by status, worst first. An unknown status counts as info.
	 *
	 * @param array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> $checks
	 * @return array<string, array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>>
	 */
	private function group_checks( array $checks ) : array {
		$groups = array_fill_keys( self::STATUSES, [] );

		foreach ( $checks as $check ) {
			$status              = in_array( $check['status'], self::STATUSES, true ) ? $check['status'] : 'info';
			$groups[ $status ][] = $check;
		}

		return $groups;
	}

	/**
	 * The worst status that has at least one check.
	 *
	 * @param array<string, array<int, mixed>> $groups Grouped worst first.
	 * @return string
	 */
	private function worst_status( array $groups ) : string {
		foreach ( $groups as $status => $group ) {
			if ( [] !== $group ) {
				return $status;
			}
		}

		return 'pass';
	}

	/**
	 * Print the closing verdict.
	 *
	 * A critical verdict goes through error(), so it shows even with --quiet.
	 *
	 * @param string             $worst  Worst status found.
	 * @param array<string, int> $counts Checks per status.
	 * @return void
	 */
	private function print_summary( string $worst, array $counts ) : void {
		$summary = sprintf(
			'%d critical, %d %s, %d info, %d passed.',
			$counts['critical'],
			$counts['warning'],
			1 === $counts['warning'] ? 'warning' : 'warnings',
			$counts['info'],
			$counts['pass']
		);

		match ( $worst ) {
			'critical' => $this->output->error( $summary ),
			'warning'  => $this->output->warning( $summary ),
			default    => $this->output->success( $summary ),
		};
	}

	/*
	|-----------------
	| DIAGNOSTICS
	|-----------------
	*/

	/**
	 * The sections asked for with --section, resolved to their names.
	 *
	 * A section is named whatever the case or separators: "database",
	 * "Loaded Extensions", "loaded-extensions" and "loaded_extensions"
	 * all work. Resolved before anything is collected, so a typo fails
	 * at once.
	 *
	 * @param CommandInput $input
	 * @return string[]|null Section names, empty for all; null after reporting an invalid option.
	 */
	private function requested_sections( CommandInput $input ) : ?array {
		if ( ! $input->has_option( 'section' ) ) {
			return [];
		}

		$by_slug = [];

		foreach ( $this->health->diagnostic_sections() as $section ) {
			$by_slug[ Format::slugify( $section ) ] = $section;
		}

		$sections = [];

		foreach ( (array) $input->get_option( 'section' ) as $name ) {
			if ( ! is_string( $name ) || '' === trim( $name ) ) {
				$this->output->error( sprintf( 'Usage: %s --section=<name>', $this->command_prefix( 'info' ) ) );
				return null;
			}

			$section = $by_slug[ Format::slugify( str_replace( '_', '-', $name ) ) ] ?? null;

			if ( null === $section ) {
				$this->output->error( sprintf( 'Unknown section "%s". Sections: %s.', $name, implode( ', ', array_values( $by_slug ) ) ) );
				return null;
			}

			$sections[ $section ] = $section;
		}

		return array_values( $sections );
	}

	/**
	 * Print one diagnostics section as aligned "label  value" lines.
	 *
	 * @param string                $section
	 * @param array<string, string> $rows
	 * @return void
	 */
	private function print_section( string $section, array $rows ) : void {
		$this->output->writeln( $this->colour( ConsoleOutput::ANSI_BOLD, $section ) );

		if ( [] === $rows ) {
			$this->output->writeln( '  (nothing to report)' );
			$this->output->newline();
			return;
		}

		$width = min( self::MAX_LABEL_WIDTH, max( array_map( fn( $label ) : int => $this->length( (string) $label ), array_keys( $rows ) ) ) );

		foreach ( $rows as $label => $value ) {
			$label  = (string) $label;
			$prefix = '  ' . $label . str_repeat( ' ', max( 1, $width - $this->length( $label ) + 2 ) );
			$indent = str_repeat( ' ', $this->length( $prefix ) );

			// A label longer than the column gets its value on the next line.
			if ( $this->length( $label ) > $width ) {
				$this->output->writeln( '  ' . $label );
				$prefix = str_repeat( ' ', $width + 4 );
				$indent = $prefix;
			}

			$this->print_wrapped( (string) $value, $prefix, $indent );
		}

		$this->output->newline();
	}

	/*
	|-----------------
	| OUTPUT
	|-----------------
	*/

	/**
	 * Print what was checked, where, and the console caveat.
	 *
	 * @param string $title
	 * @return void
	 */
	private function print_header( string $title ) : void {
		$meta    = $this->health->report_meta();
		$context = $this->context();

		$this->output->newline();
		$this->output->writeln( $this->colour( ConsoleOutput::ANSI_BOLD, sprintf( '%s: %s %s (schema %s)', $title, $meta['product'], $meta['version'], $meta['schema'] ) ) );
		$this->output->writeln( $meta['site'] );
		$this->print_wrapped(
			sprintf(
				'Checked from the console with PHP %s (%s). Values that depend on PHP are the console\'s; the admin Tools pages show the web server\'s.',
				$context['php_version'],
				null === $context['php_ini'] ? 'no php.ini loaded' : $context['php_ini']
			),
			'',
			'',
			ConsoleOutput::ANSI_DIM
		);
		$this->output->newline();
	}

	/**
	 * Print a value as pretty JSON. Always printed: it is the requested output.
	 *
	 * @param array<string, mixed> $data
	 * @return void
	 */
	private function print_json( array $data ) : void {
		$this->output->write(
			(string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ),
			true
		);
	}

	/**
	 * Where the report was built.
	 *
	 * @return array{sapi: string, php_version: string, php_ini: ?string}
	 */
	private function context() : array {
		$ini = php_ini_loaded_file();

		return [
			'sapi'        => PHP_SAPI,
			'php_version' => PHP_VERSION,
			'php_ini'     => false === $ini ? null : $ini,
		];
	}

	/**
	 * Whether --json was given.
	 *
	 * @param CommandInput $input
	 * @return bool
	 */
	private function wants_json( CommandInput $input ) : bool {
		return true === $input->get_option( 'json' );
	}

	/**
	 * Print text wrapped to the terminal width.
	 *
	 * @param string $text
	 * @param string $first  Prefix of the first line.
	 * @param string $indent Prefix of the following lines; defaults to $first.
	 * @param string $colour ANSI code for the text, or '' for none.
	 * @return void
	 */
	private function print_wrapped( string $text, string $first, ?string $indent = null, string $colour = '' ) : void {
		$indent ??= $first;
		$width    = max( 20, $this->width() - $this->length( $indent ) );
		$lines    = $this->wrap( $text, $width );

		foreach ( $lines as $i => $line ) {
			$this->output->writeln( ( 0 === $i ? $first : $indent ) . ( '' === $colour ? $line : $this->colour( $colour, $line ) ) );
		}
	}

	/**
	 * Word-wrap text to a width counted in characters, not bytes.
	 *
	 * A word longer than the width (a path, a URL) is kept whole on its own line.
	 *
	 * @param string $text
	 * @param int    $width
	 * @return string[]
	 */
	private function wrap( string $text, int $width ) : array {
		$lines   = [];
		$current = '';

		foreach ( preg_split( '/\s+/u', trim( $text ) ) ?: [] as $word ) {
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
	 * Text width: the terminal's, within MIN_WIDTH and MAX_WIDTH.
	 *
	 * @return int
	 */
	private function width() : int {
		return max( self::MIN_WIDTH, min( self::MAX_WIDTH, $this->terminal->terminal_width() ) );
	}

	/**
	 * Length in characters.
	 *
	 * @param string $text
	 * @return int
	 */
	private function length( string $text ) : int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Colour text when the terminal supports ANSI.
	 *
	 * @param string $code ANSI code from ConsoleOutput.
	 * @param string $text
	 * @return string
	 */
	private function colour( string $code, string $text ) : string {
		return $this->terminal->supports_ansi() ? $code . $text . ConsoleOutput::ANSI_RESET : $text;
	}
}