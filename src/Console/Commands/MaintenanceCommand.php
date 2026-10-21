<?php
/**
 * Maintenance command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Maintenance\MaintenanceMode;

/**
 * Switch maintenance mode on and off, from the console.
 *
 * The console keeps working during maintenance: this is where a backup,
 * an import or other administrative work is done meanwhile. See
 * MaintenanceMode for what maintenance does.
 */
class MaintenanceCommand extends AbstractCommand {

	/**
	 * Constructor.
	 *
	 * @param MaintenanceMode $maintenance Switches maintenance mode.
	 * @param InputInterface  $io          CLI input interface stream.
	 * @param OutputInterface $output      CLI output interface stream.
	 * @param ScriptName      $script_name Executable binary alias context.
	 */
	public function __construct(
		protected MaintenanceMode $maintenance,
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
		return 'maintenance';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Put the site in maintenance mode for administrative work, and take it out again.';
	}

	/**
	 * {@inheritdoc}
	 *
	 * Without a subcommand the status is shown, so the subcommand is optional.
	 */
	public function synopsis() : string {
		return sprintf( '%s [on|off|status|bypass] [options]', $this->command_prefix() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'Subcommands:',
			'  status              Show whether the site is in maintenance, and why (the default).',
			'  on                  Take the site down for visitors; prints a bypass link for you.',
			'  off                 Open the site to visitors again.',
			'  bypass              Make a new bypass link; earlier links stop working.',
			'  help                Show this help message.',
			'',
			'Options for on:',
			'  --message=<text>      What visitors read. Default: a generic maintenance notice.',
			sprintf( '  --retry-after=<sec>   Seconds visitors are told to wait (Retry-After). Default: %d.', BootModeResolver::DEFAULT_RETRY_AFTER ),
			'  --keep-background     Keep scheduled tasks and queued jobs running.',
			'                        By default they wait, so nothing writes to the database meanwhile.',
			'  --yes, -y             Do not ask for confirmation.',
			'',
			'During maintenance, visitors get a 503 page. The console keeps working, and',
			'the bypass link opens the site in your browser as usual. An update or an',
			'installation also uses maintenance mode; while one runs, it cannot be',
			'switched here.',
			'',
			'Examples:',
			"  {$prefix} on --message=\"Back in 30 minutes.\" --retry-after=1800",
			"  {$prefix} on --keep-background --yes",
			"  {$prefix} status",
			"  {$prefix} off",
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
			'status' => [ $this, 'handle_status' ],
			'on'     => [ $this, 'handle_on' ],
			'off'    => [ $this, 'handle_off' ],
			'bypass' => [ $this, 'handle_bypass' ],
			'help'   => [ $this, 'handle_help' ],
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Reached when no subcommand is given: shows the status.
	 */
	public function run( CommandInput $input ) : int {
		return $this->handle_status( $input );
	}

	/*
	|-------------
	| SUBCOMMANDS
	|-------------
	*/

	/**
	 * Show whether the site is in maintenance, and why.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_status( CommandInput $input ) : int {
		$status = $this->maintenance->status();

		if ( ! $status['active'] ) {
			$this->output->success( 'The site is open: maintenance mode is off.' );
			return 0;
		}

		$this->output->warning( match ( $status['reason'] ) {
			MaintenanceFlag::REASON_UPDATE       => 'The site is in maintenance: an update is being installed or has not finished.',
			MaintenanceFlag::REASON_INSTALLATION => 'The site is in maintenance: an installation is in progress.',
			default                              => 'The site is in maintenance mode.',
		} );

		$rows = [
			[ 'Reason', (string) $status['reason'] ],
			[ 'Since', $status['since'] ?? 'unknown' ],
			[ 'Switched on by', $status['by'] ?? 'unknown' ],
			[ 'Visitors read', '' === $status['message'] ? '(the default notice)' : $status['message'] ],
			[ 'Retry-After', $status['retry_after'] > 0 ? $status['retry_after'] . ' seconds' : '(default)' ],
			[ 'Background work', $status['background_paused'] ? 'paused' : 'running' ],
			[ 'Bypass link', $status['bypass'] ? sprintf( 'active (make a new one with `%s`)', $this->command_prefix( 'bypass' ) ) : 'none' ],
		];

		$this->output->table( [ 'Field', 'Value' ], $rows );

		if ( MaintenanceFlag::REASON_UPDATE === $status['reason'] ) {
			$this->output->info( 'It ends when the update finishes or is rolled back: see `update status`.' );
		} elseif ( MaintenanceFlag::REASON_MAINTENANCE === $status['reason'] ) {
			$this->output->info( sprintf( 'Run `%s` to open the site again.', $this->command_prefix( 'off' ) ) );
		}

		return 0;
	}

	/**
	 * Take the site down for visitors.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_on( CommandInput $input ) : int {
		$message     = $input->get_option( 'message', '' );
		$retry_after = $input->get_option( 'retry-after', BootModeResolver::DEFAULT_RETRY_AFTER );
		$keep        = true === $input->get_option( 'keep-background' );
		$confirmed   = true === $input->get_option( 'y' ) || true === $input->get_option( 'yes' );

		if ( ! is_string( $message ) ) {
			$this->output->error( sprintf( 'Usage: %s --message="<text>"', $this->command_prefix( 'on' ) ) );
			return 1;
		}

		if ( ( ! is_string( $retry_after ) && ! is_int( $retry_after ) ) || ! ctype_digit( (string) $retry_after ) ) {
			$this->output->error( sprintf( 'Usage: %s --retry-after=<seconds>', $this->command_prefix( 'on' ) ) );
			return 1;
		}

		$was_on = MaintenanceFlag::REASON_MAINTENANCE === $this->maintenance->status()['reason'];
		$prompt = $was_on
			? 'Maintenance mode is already on. Switch it on again with these settings? Earlier bypass links will stop working.'
			: sprintf( 'Take the site down for visitors now?%s', $keep ? '' : ' Scheduled tasks and queued jobs will wait too.' );

		if ( ! $confirmed && ! $this->io->confirm( $prompt, false ) ) {
			$this->output->info( 'Nothing was changed.' );
			return 0;
		}

		try {
			$token = $this->maintenance->enable( $message, (int) $retry_after, ! $keep, $this->operator() );
		} catch ( \RuntimeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( sprintf( 'Maintenance mode is on.%s', $keep ? ' Background work keeps running.' : ' Background work is paused.' ) );
		$this->print_bypass( $token );
		$this->output->info( sprintf( 'Run `%s` when the work is done.', $this->command_prefix( 'off' ) ) );

		return 0;
	}

	/**
	 * Open the site to visitors again.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_off( CommandInput $input ) : int {
		try {
			$was_on = $this->maintenance->disable();
		} catch ( \RuntimeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( $was_on ? 'Maintenance mode is off; the site is open.' : 'Maintenance mode was already off.' );

		return 0;
	}

	/**
	 * Make a new bypass link for the current maintenance.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_bypass( CommandInput $input ) : int {
		try {
			$token = $this->maintenance->renew_bypass();
		} catch ( \RuntimeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( 'New bypass link made; earlier links and their cookies stop working.' );
		$this->print_bypass( $token );

		return 0;
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Print the bypass link. Only its hash is stored, so this is the one chance to copy it.
	 *
	 * @param string $token
	 * @return void
	 */
	private function print_bypass( string $token ) : void {
		$this->output->newline();
		$this->output->writeln( 'Open this link to use the site while visitors wait (keep it private; it is shown only now):' );
		$this->output->writeln( '  ' . $this->maintenance->bypass_url( $token ) );
		$this->output->newline();
	}

	/**
	 * Who switches maintenance on, for the status.
	 *
	 * @return string
	 */
	private function operator() : string {
		$user = getenv( 'USER' ) ?: getenv( 'USERNAME' ) ?: get_current_user();

		return '' === (string) $user ? 'console' : sprintf( 'console (%s)', $user );
	}
}
