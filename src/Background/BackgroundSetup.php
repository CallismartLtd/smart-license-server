<?php
/**
 * Background setup class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\Application\Update\PhpCli;

/**
 * How background work can run on this server, which way it runs now, and
 * the configuration for each way, written for this installation.
 *
 * Modes, most reliable first:
 *
 *  - server:  crontab runs `smliser schedule run` every minute and a
 *             supervised worker (systemd or supervisord) runs `smliser queue work`.
 *             Jobs start at once. Needs root or a VPS.
 *  - panel:   a hosting panel cron job (cPanel, Plesk, DirectAdmin) runs
 *             `smliser cron` every minute, or every 5 when that is the minimum.
 *             Tasks and jobs run within that interval.
 *  - url:     an external cron service (cron-job.org, for example) requests
 *             the public cron URL every 5 to 10 minutes. Works on any host;
 *             depends on that service and on web request limits.
 *  - manual:  nothing runs on its own; an administrator uses the Schedules
 *             and Queue Monitor buttons.
 *
 * Which mode runs is read from the heartbeats (BackgroundStatus), never
 * from a saved choice: the page shows what actually happens.
 */
final class BackgroundSetup {

	/**
	 * Modes.
	 */
	public const MODE_SERVER = 'server';
	public const MODE_PANEL  = 'panel';
	public const MODE_URL    = 'url';
	public const MODE_MANUAL = 'manual';

	/**
	 * Console script, in the application root.
	 */
	public const CONSOLE = 'smliser';

	/**
	 * Seconds since its last heartbeat after which the worker counts as stopped
	 * (it records one every minute).
	 *
	 * @var int
	 */
	public const WORKER_ALIVE = 180;

	/**
	 * Seconds since their last run after which cron and the cron URL count as
	 * stopped. Allows a 10-minute interval, plus a slow run.
	 *
	 * @var int
	 */
	public const CRON_ALIVE = 1200;

	/**
	 * Constructor.
	 *
	 * @param BackgroundStatus $status   Heartbeats.
	 * @param string           $root     Application root (SMLISER_ROOT).
	 * @param URL|null         $cron_url The public cron URL; null where the environment does not serve one.
	 */
	public function __construct(
		private BackgroundStatus $status,
		private string $root,
		private ?URL $cron_url
	) {}

	/*
	|------------
	| DETECTION
	|------------
	*/

	/**
	 * What runs background work now.
	 *
	 * "mode" is null when nothing runs the scheduled tasks: never set up, or
	 * stopped (a worker without cron runs jobs but not the schedules).
	 *
	 * @return array{mode: ?string, cron: bool, worker: bool, url: bool, last: ?array{source: string, at: string, age: int}}
	 */
	public function detect() : array {
		$cron = $this->alive( BackgroundStatus::SOURCE_CLI_CRON, self::CRON_ALIVE )
			|| $this->alive( BackgroundStatus::SOURCE_SCHEDULE_RUN, self::CRON_ALIVE );
		$worker = $this->alive( BackgroundStatus::SOURCE_WORKER, self::WORKER_ALIVE );
		$url    = $this->alive( BackgroundStatus::SOURCE_CRON_URL, self::CRON_ALIVE );

		$mode = match ( true ) {
			$cron && $worker => self::MODE_SERVER,
			$cron            => self::MODE_PANEL,
			$url             => self::MODE_URL,
			default          => null,
		};

		return [
			'mode'   => $mode,
			'cron'   => $cron,
			'worker' => $worker,
			'url'    => $url,
			'last'   => $this->status->latest_runner(),
		];
	}

	/**
	 * The page's status banner for a detection: tone, icon, title and message.
	 *
	 * Shared by the page and its live status endpoint, so both say the same.
	 *
	 * @param array|null $detected detect(); detected now when null.
	 * @return array{tone: string, icon: string, title: string, message: string}
	 */
	public function overview( ?array $detected = null ) : array {
		$detected ??= $this->detect();
		$mode = $detected['mode'];

		if ( null !== $mode ) {
			return [
				'tone'    => 'pass',
				'icon'    => 'ti ti-circle-check',
				'title'   => 'Running: ' . ( $this->modes()[ $mode ]['label'] ?? $mode ),
				'message' => self::MODE_URL === $mode
					? 'Scheduled tasks and queued jobs run each time the external cron service calls this site. Cron on the server is more reliable when your hosting offers it.'
					: 'Scheduled tasks and queued jobs run on their own.',
			];
		}

		if ( $detected['worker'] ) {
			return [
				'tone'    => 'warning',
				'icon'    => 'ti ti-alert-triangle',
				'title'   => 'Partly running',
				'message' => 'The queue worker runs jobs, but nothing runs the scheduled tasks: add the scheduler cron job below.',
			];
		}

		$never = null === $detected['last'];

		return [
			'tone'    => 'critical',
			'icon'    => 'ti ti-circle-x',
			'title'   => $never ? 'Not set up' : 'Stopped',
			'message' => $never
				? 'Nothing has run background work on this site yet. Choose how it should run below and follow the steps.'
				: 'Nothing has run background work recently. Check the cron job or external service, or set one up below.',
		];
	}

	/**
	 * The mode to show first: the one running, else the one a new
	 * installation should start with (the cron URL works on any host).
	 *
	 * @return string
	 */
	public function suggested_mode() : string {
		return $this->detect()['mode'] ?? ( null !== $this->cron_url ? self::MODE_URL : self::MODE_PANEL );
	}

	/**
	 * Every runner's last heartbeat, for live verification.
	 *
	 * @return array<int, array{source: string, label: string, alive: bool, age: ?int, at: ?string, summary: string}>
	 */
	public function runners() : array {
		$sources = [
			BackgroundStatus::SOURCE_CLI_CRON     => [ 'Cron (smliser cron)', self::CRON_ALIVE ],
			BackgroundStatus::SOURCE_SCHEDULE_RUN => [ 'Cron (smliser schedule run)', self::CRON_ALIVE ],
			BackgroundStatus::SOURCE_WORKER       => [ 'Queue worker (smliser queue work)', self::WORKER_ALIVE ],
		];

		if ( null !== $this->cron_url ) {
			$sources[ BackgroundStatus::SOURCE_CRON_URL ] = [ 'Cron URL (external service)', self::CRON_ALIVE ];
		}

		$runners = [];

		foreach ( $sources as $source => [ $label, $limit ] ) {
			$entry = $this->status->last( $source );
			$age   = $this->status->age( $source );

			$runners[] = [
				'source'  => $source,
				'label'   => $label,
				'alive'   => null !== $age && $age <= $limit,
				'age'     => $age,
				'at'      => null === $entry ? null : (string) ( $entry['at'] ?? '' ),
				'summary' => null === $entry || null === $age ? 'Never ran' : $this->summarize( $entry, $age ),
			];
		}

		return $runners;
	}

	/*
	|--------
	| MODES
	|--------
	*/

	/**
	 * The modes this environment offers, with what each needs and gives.
	 *
	 * @return array<string, array{label: string, badge: string, icon: string, description: string}>
	 */
	public function modes() : array {
		$modes = [
			self::MODE_SERVER => [
				'label'       => 'Server cron and queue worker',
				'badge'       => 'Best',
				'icon'        => 'ti ti-server-2',
				'description' => 'For a VPS or dedicated server. Cron runs the scheduled tasks every minute and a worker, kept running by systemd or supervisord, starts each job as soon as it is queued.',
			],
			self::MODE_PANEL => [
				'label'       => 'Hosting panel cron job',
				'badge'       => 'Shared hosting',
				'icon'        => 'ti ti-layout-dashboard',
				'description' => 'For shared hosting with a "Cron Jobs" page (cPanel, Plesk, DirectAdmin). One cron job runs the due tasks and then the queued jobs, every minute or every 5 minutes.',
			],
		];

		if ( null !== $this->cron_url ) {
			$modes[ self::MODE_URL ] = [
				'label'       => 'External cron service',
				'badge'       => 'Works anywhere',
				'icon'        => 'ti ti-world-www',
				'description' => 'For hosting without cron. A free service such as cron-job.org requests this site\'s cron URL every 5 to 10 minutes; each request runs what is due. Visits to the site never run it.',
			];
		}

		$modes[ self::MODE_MANUAL ] = [
			'label'       => 'Manual',
			'badge'       => 'Not recommended',
			'icon'        => 'ti ti-hand-click',
			'description' => 'Nothing runs on its own. Update checks, security updates, license expiry, emails and clean-up wait until an administrator runs them from the Schedules and Queue Monitor pages.',
		];

		return $modes;
	}

	/**
	 * The configuration for a mode, written for this installation.
	 *
	 * @param string $mode One of the MODE_* constants.
	 * @return array{steps: array<int, array{title: string, content: ?string, note: ?string}>, notes: string[]}
	 */
	public function config( string $mode ) : array {
		return match ( $mode ) {
			self::MODE_SERVER => $this->server_config(),
			self::MODE_PANEL  => $this->panel_config(),
			self::MODE_URL    => $this->url_config(),
			default           => $this->manual_config(),
		};
	}

	/**
	 * What the generated configuration is based on.
	 *
	 * @return array{root: string, php: string, php_found: bool, user: ?string, group: ?string, cron_url: ?string}
	 */
	public function environment() : array {
		$php     = PhpCli::find();
		$process = $this->process_user();

		return [
			'root'      => $this->root_dir(),
			'php'       => $php ?? 'php',
			'php_found' => null !== $php,
			'user'      => $process['user'],
			'group'     => $process['group'],
			'cron_url'  => null === $this->cron_url ? null : (string) $this->cron_url,
		];
	}

	/*
	|----------------
	| CONFIGURATIONS
	|----------------
	*/

	/**
	 * Crontab line plus a systemd unit or a supervisord program.
	 *
	 * @return array
	 */
	private function server_config() : array {
		$env     = $this->environment();
		$root    = $env['root'];
		$php     = $env['php'];
		$user    = $env['user'] ?? 'www-data';
		$group   = $env['group'] ?? $user;
		$console = $root . '/' . self::CONSOLE;

		return [
			'steps' => [
				[
					'title'   => 'Run the scheduler every minute',
					'content' => "* * * * * cd {$root} && {$php} " . self::CONSOLE . ' schedule run >> /dev/null 2>&1',
					'note'    => sprintf( 'Add it to the crontab of %s (crontab -u %s -e), the user PHP runs as for this site, so files it creates stay writable.', $user, $user ),
				],
				[
					'title'   => 'systemd: /etc/systemd/system/smliser-worker.service',
					'content' => implode(
						"\n",
						[
							'[Unit]',
							'Description=Smart License Server queue worker',
							'After=network-online.target',
							'Wants=network-online.target',
							'',
							'[Service]',
							"User={$user}",
							"Group={$group}",
							"WorkingDirectory={$root}",
							"ExecStart={$php} {$console} queue work",
							'# Exits with code 75 after an update; restart on the new code.',
							'Restart=always',
							'RestartSec=5',
							'KillSignal=SIGTERM',
							'TimeoutStopSec=90',
							'',
							'[Install]',
							'WantedBy=multi-user.target',
						]
					),
					'note' => 'Then run: systemctl daemon-reload && systemctl enable --now smliser-worker',
				],
				[
					'title'   => 'Or supervisord: /etc/supervisor/conf.d/smliser-worker.conf',
					'content' => implode(
						"\n",
						[
							'[program:smliser-worker]',
							"command={$php} {$console} queue work",
							"directory={$root}",
							"user={$user}",
							'autostart=true',
							'; Exits with code 75 after an update; restart on the new code.',
							'autorestart=true',
							'stopsignal=TERM',
							'stopwaitsecs=90',
							'redirect_stderr=true',
							'stdout_logfile=/var/log/smliser-worker.log',
						]
					),
					'note' => 'Then run: supervisorctl reread && supervisorctl update',
				],
			],
			'notes' => $this->php_notes( $env ),
		];
	}

	/**
	 * One hosting panel cron job.
	 *
	 * @return array
	 */
	private function panel_config() : array {
		$env = $this->environment();

		return [
			'steps' => [
				[
					'title'   => 'Open your hosting panel\'s cron jobs',
					'content' => null,
					'note'    => 'cPanel: Advanced > Cron Jobs. Plesk: Websites & Domains > Scheduled Tasks. DirectAdmin: Advanced Features > Cron Jobs.',
				],
				[
					'title'   => 'Schedule',
					'content' => '* * * * *',
					'note'    => 'Every minute. If your host allows every 5 minutes at most, use */5 * * * *; tasks and jobs then wait up to 5 minutes.',
				],
				[
					'title'   => 'Command',
					'content' => "cd {$env['root']} && {$env['php']} " . self::CONSOLE . ' cron >/dev/null 2>&1',
					'note'    => 'Runs the due scheduled tasks, then queued jobs for up to 45 seconds. Runs never overlap.',
				],
			],
			'notes' => $this->php_notes( $env ),
		];
	}

	/**
	 * The cron URL for an external service.
	 *
	 * @return array
	 */
	private function url_config() : array {
		$env = $this->environment();

		return [
			'steps' => [
				[
					'title'   => 'Create a cron job at an external service',
					'content' => null,
					'note'    => 'For example cron-job.org, which is free. Any service that requests a URL on a schedule works.',
				],
				[
					'title'   => 'URL',
					'content' => (string) $env['cron_url'],
					'note'    => 'Method GET. It answers 204 No Content at once and then runs what is due, so a short timeout in the service is fine.',
				],
				[
					'title'   => 'Schedule',
					'content' => '*/5 * * * *',
					'note'    => 'Every 5 minutes, or at most every 10. Calls less than a minute apart are answered but run nothing.',
				],
			],
			'notes' => [
				'The URL needs no secret: every caller gets the same empty answer, and it only ever runs work that is already due.',
				'Each call runs as a web request, so jobs are processed for up to 25 seconds per call. If your hosting offers cron jobs, the hosting panel option is more reliable.',
			],
		];
	}

	/**
	 * Manual: nothing to configure.
	 *
	 * @return array
	 */
	private function manual_config() : array {
		return [
			'steps' => [
				[
					'title'   => 'Run due tasks',
					'content' => null,
					'note'    => 'Tools > Schedules > "Run due tasks". Do this at least twice a day so update checks and license expiry happen.',
				],
				[
					'title'   => 'Process queued jobs',
					'content' => null,
					'note'    => 'Tools > Queue Monitor > "Process queue". Emails and update installs are queued jobs.',
				],
			],
			'notes' => [
				'Security updates cannot install themselves in this mode.',
			],
		];
	}

	/*
	|----------
	| HELPERS
	|----------
	*/

	/**
	 * Notes about the PHP binary the commands use.
	 *
	 * @param array $env environment().
	 * @return string[]
	 */
	private function php_notes( array $env ) : array {
		$version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		if ( $env['php_found'] ) {
			return [ sprintf( '%s is the PHP %s command-line binary on this server, the same version this site runs on.', $env['php'], $version ) ];
		}

		return [
			sprintf(
				'The PHP %s command-line binary could not be located from here, so the commands use "php". If "php -v" on this server shows another version, replace it with the path your host gives for PHP %s (for example /usr/local/bin/ea-php%d%d on cPanel).',
				$version,
				$version,
				PHP_MAJOR_VERSION,
				PHP_MINOR_VERSION
			),
		];
	}

	/**
	 * Whether a runner ran within a number of seconds.
	 *
	 * @param string $source
	 * @param int    $limit
	 * @return bool
	 */
	private function alive( string $source, int $limit ) : bool {
		$age = $this->status->age( $source );

		return null !== $age && $age <= $limit;
	}

	/**
	 * One line about a heartbeat: when, what it did, on which PHP.
	 *
	 * @param array<string, mixed> $entry
	 * @param int                  $age
	 * @return string
	 */
	private function summarize( array $entry, int $age ) : string {
		$parts = [ self::describe_age( $age ) . ' ago' ];

		if ( isset( $entry['skipped'] ) ) {
			$parts[] = 'skipped (' . $entry['skipped'] . ')';
		} elseif ( isset( $entry['tasks'] ) || isset( $entry['jobs'] ) ) {
			$parts[] = sprintf( '%d task(s), %d job(s) in %ss', (int) ( $entry['tasks'] ?? 0 ), (int) ( $entry['jobs'] ?? 0 ), $entry['seconds'] ?? '?' );
		}

		$parts[] = sprintf( 'PHP %s %s', $entry['php'] ?? '?', $entry['sapi'] ?? '?' );

		if ( ! empty( $entry['release'] ) ) {
			$parts[] = 'released with ' . $entry['release'];
		}

		return implode( ', ', $parts );
	}

	/**
	 * A duration in words: "40 seconds", "3 minutes", "2 hours", "5 days".
	 *
	 * @param int $seconds
	 * @return string
	 */
	public static function describe_age( int $seconds ) : string {
		[ $value, $unit ] = match ( true ) {
			$seconds < 60    => [ $seconds, 'second' ],
			$seconds < 3600  => [ intdiv( $seconds, 60 ), 'minute' ],
			$seconds < 86400 => [ intdiv( $seconds, 3600 ), 'hour' ],
			default          => [ intdiv( $seconds, 86400 ), 'day' ],
		};

		return sprintf( '%d %s%s', $value, $unit, 1 === $value ? '' : 's' );
	}

	/**
	 * The application root without a trailing slash.
	 *
	 * @return string
	 */
	private function root_dir() : string {
		return rtrim( $this->root, '/\\' );
	}

	/**
	 * The user and group PHP runs as for this site: background work must
	 * run as the same user, or the files it creates are not writable by the
	 * site (and the other way round).
	 *
	 * @return array{user: ?string, group: ?string}
	 */
	private function process_user() : array {
		$user  = null;
		$group = null;

		if ( function_exists( 'posix_geteuid' ) && function_exists( 'posix_getpwuid' ) ) {
			$info = @posix_getpwuid( posix_geteuid() );
			$user = is_array( $info ) ? (string) $info['name'] : null;
		}

		if ( function_exists( 'posix_getegid' ) && function_exists( 'posix_getgrgid' ) ) {
			$info  = @posix_getgrgid( posix_getegid() );
			$group = is_array( $info ) ? (string) $info['name'] : null;
		}

		if ( null === $user ) {
			$owner = get_current_user();
			$user  = '' === $owner ? null : $owner;
		}

		return [ 'user' => $user, 'group' => $group ];
	}
}
