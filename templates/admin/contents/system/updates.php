<?php
/**
 * The admin updates page template.
 *
 * Renders the last update check and the installation's update state
 * without contacting the update server. updates.js runs the actions
 * (check, dry run, install, rollback, settings) and follows a queued
 * update until it ends.
 *
 * @author Callistus Nwachukwu
 * @var array $overview UpdateService::overview().
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Environments\Application\Update\UpdateService;

$menu_args = $page_handler->get_top_menu_args( $request );
$check     = $overview['check'];
$run       = $overview['run'];
$attempt   = $overview['attempt'];
$backup    = $overview['backup'];
$queued    = $overview['queued'];
$blockers  = $overview['blockers'];

/**
 * Format a stored UTC timestamp for display.
 *
 * @param string|null $iso ISO 8601 timestamp.
 * @return string
 */
$when = static function ( ?string $iso ) : string {
	$time = $iso ? \strtotime( $iso ) : false;

	return false === $time ? '—' : \gmdate( 'Y-m-d H:i', $time ) . ' UTC';
};

/*
|---------------
| OVERALL STATUS
|---------------
*/

$busy = $overview['in_progress'] || null !== $queued;

[ $tone, $icon, $title, $message ] = match ( true ) {
	$overview['in_progress'] => array(
		'pending',
		'ti ti-loader-2 smliser-health-spin',
		'Update in progress',
		sprintf( 'Version %s is being installed. The site is briefly unavailable while files are swapped.', $run['to'] ?? '' ),
	),
	null !== $queued => array(
		'pending',
		'ti ti-loader-2 smliser-health-spin',
		'Update queued',
		sprintf( 'Version %s was queued %s; the queue worker will process it shortly.', $queued['version'] ?? '', $when( $queued['at'] ?? null ) ),
	),
	! empty( $blockers ) => array(
		'critical',
		'ti ti-circle-x',
		'Updates are blocked',
		'This installation cannot update until the problems below are fixed.',
	),
	$check['available'] && $check['security'] => array(
		'critical',
		'ti ti-shield-exclamation',
		sprintf( 'Security update available: %s', $check['latest'] ),
		'Install it as soon as possible.',
	),
	$check['available'] => array(
		'warning',
		'ti ti-arrow-up-circle',
		sprintf( 'Version %s is available', $check['latest'] ),
		sprintf( 'You are running %s.', $overview['installed'] ),
	),
	null !== $check['error'] => array(
		'warning',
		'ti ti-alert-triangle',
		'Could not check for updates',
		$check['error'],
	),
	default => array(
		'pass',
		'ti ti-circle-check',
		'Up to date',
		sprintf( '%s %s is the latest version.', \SMLISER_APP_NAME, $overview['installed'] ),
	),
};

$auto_modes = array(
	UpdateService::AUTO_OFF      => array( 'Off', 'Updates are installed only when you start them.' ),
	UpdateService::AUTO_SECURITY => array( 'Security releases', 'Security releases are installed automatically; other releases wait for you.' ),
	UpdateService::AUTO_ALL      => array( 'All releases', 'Every new release is installed automatically.' ),
);

$chevron = '<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>';
?>
<div class="smliser-admin-page" id="smliser-updates" data-busy="<?php echo $busy ? '1' : '0'; ?>">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-health-overview smliser-health-tone--<?php echo escAttr( $tone ); ?>">
		<span class="smliser-health-overview-icon">
			<i class="<?php echo escAttr( $icon ); ?>"></i>
		</span>
		<div class="smliser-health-overview-text">
			<strong class="smliser-health-overview-title"><?php echo escHtml( $title ); ?></strong>
			<p class="smliser-health-overview-message"><?php echo escHtml( $message ); ?></p>
		</div>
		<ul class="smliser-health-overview-counts">
			<li class="smliser-health-count">
				<span class="smliser-health-count-value"><?php echo escHtml( $overview['installed'] ); ?></span>
				<span class="smliser-health-count-label">Installed</span>
			</li>
			<li class="smliser-health-count">
				<span class="smliser-health-count-value"><?php echo escHtml( (string) ( $check['latest'] ?? '—' ) ); ?></span>
				<span class="smliser-health-count-label">Latest</span>
			</li>
		</ul>
	</div>

	<div class="smliser-diagnostics-grid">

		<?php /* Release and actions. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo escAttr( $check['available'] ? ( $check['security'] ? 'critical' : 'warning' ) : 'pass' ); ?>" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-package"></i></span>
					<span class="smliser-diagnostics-panel-title">Release</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks">
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Latest version</span></div>
							<p class="smliser-health-check-message">
								<?php echo escHtml( (string) ( $check['latest'] ?? 'Unknown — check for updates.' ) ); ?>
								<?php echo $check['security'] ? escHtml( ' (security release)' ) : ''; ?>
							</p>
						</div>
					</li>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Last checked</span></div>
							<p class="smliser-health-check-message"><?php echo escHtml( $when( $check['checked_at'] ?: null ) ); ?></p>
							<?php if ( null !== $check['error'] ) : ?>
								<p class="smliser-health-check-recommendation"><i class="ti ti-alert-triangle"></i><span><?php echo escHtml( $check['error'] ); ?></span></p>
							<?php endif; ?>
						</div>
					</li>
					<?php if ( null !== $overview['ready'] ) : ?>
						<li class="smliser-health-check">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header"><span class="smliser-health-check-label">Ready to install</span></div>
								<p class="smliser-health-check-message">
									<?php echo escHtml( sprintf( 'Version %s was downloaded and verified %s; installing it will not download it again.', $overview['ready']['version'] ?? '', $when( $overview['ready']['prepared_at'] ?? null ) ) ); ?>
								</p>
							</div>
						</li>
					<?php endif; ?>
				</ul>
				<div class="smliser-health-async-footer">
					<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="check" <?php echo $busy ? 'disabled' : ''; ?>>
						<i class="ti ti-refresh"></i> Check now
					</button>
					<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="dry-run" <?php echo $busy || ! empty( $blockers ) ? 'disabled' : ''; ?>>
						<i class="ti ti-flask"></i> Dry run
					</button>
					<?php if ( $check['available'] ) : ?>
						<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="install" data-confirm="<?php echo escAttr( sprintf( 'Install version %s now? The site will be unavailable for the few seconds it takes. Back up your database first.', $check['latest'] ) ); ?>" <?php echo $busy || ! empty( $blockers ) ? 'disabled' : ''; ?>>
							<i class="ti ti-download"></i> <?php echo escHtml( sprintf( 'Install %s', $check['latest'] ) ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="install" data-reinstall="1" data-confirm="<?php echo escAttr( sprintf( 'Reinstall version %s to repair its files? The site will be unavailable for the few seconds it takes.', $overview['installed'] ) ); ?>" <?php echo $busy || ! empty( $blockers ) ? 'disabled' : ''; ?>>
							<i class="ti ti-tool"></i> Reinstall
						</button>
					<?php endif; ?>
				</div>
			</div>
		</details>

		<?php /* What blocks updates. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo empty( $blockers ) ? 'pass' : 'critical'; ?>" <?php echo empty( $blockers ) ? '' : 'open'; ?>>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="<?php echo empty( $blockers ) ? 'ti ti-circle-check' : 'ti ti-circle-x'; ?>"></i></span>
					<span class="smliser-diagnostics-panel-title">Requirements</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right">
					<span class="smliser-diagnostics-panel-count"><?php echo escHtml( (string) \count( $blockers ) ); ?></span>
					<?php echo $chevron; // phpcs:ignore -- static markup. ?>
				</span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks">
					<?php if ( empty( $blockers ) ) : ?>
						<li class="smliser-health-check smliser-health-tone--pass">
							<span class="smliser-health-check-icon"><i class="ti ti-circle-check"></i></span>
							<div class="smliser-health-check-body">
								<p class="smliser-health-check-message">Nothing on this server blocks an update. A dry run also checks the release itself.</p>
							</div>
						</li>
					<?php endif; ?>
					<?php foreach ( $blockers as $blocker ) : ?>
						<li class="smliser-health-check smliser-health-tone--critical">
							<span class="smliser-health-check-icon"><i class="ti ti-circle-x"></i></span>
							<div class="smliser-health-check-body">
								<p class="smliser-health-check-message"><?php echo escHtml( $blocker ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="smliser-health-check-recommendation">
					<i class="ti ti-bulb"></i>
					<span>These are checked as the web server's PHP user. Updates run in the queue worker; if it runs as another user, check from the console with <code>smliser update status</code>.</span>
				</p>
			</div>
		</details>

		<?php /* Automatic updates. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--info" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-clock-cog"></i></span>
					<span class="smliser-diagnostics-panel-title">Automatic updates</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<form id="smliser-update-auto">
					<ul class="smliser-health-checks">
						<?php foreach ( $auto_modes as $mode => [ $label, $description ] ) : ?>
							<li class="smliser-health-check">
								<label class="smliser-health-check-body">
									<span class="smliser-health-check-header">
										<input type="radio" name="mode" value="<?php echo escAttr( $mode ); ?>" <?php echo $mode === $overview['auto'] ? 'checked' : ''; ?>>
										<span class="smliser-health-check-label"><?php echo escHtml( $label ); ?></span>
									</span>
									<span class="smliser-health-check-message"><?php echo escHtml( $description ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="smliser-health-async-footer">
						<span class="smliser-health-async-time">Checked twice a day by the scheduler; installed by the queue worker.</span>
						<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-device-floppy"></i> Save</button>
					</div>
				</form>
			</div>
		</details>

		<?php /* Last update and backup. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo escAttr( null !== $attempt && ! $attempt['ok'] ? 'warning' : 'pass' ); ?>" <?php echo null !== $attempt && ! $attempt['ok'] ? 'open' : ''; ?>>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-history"></i></span>
					<span class="smliser-diagnostics-panel-title">History and backup</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks">
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Last update</span></div>
							<p class="smliser-health-check-message">
								<?php
								echo escHtml(
									null === $run
										? 'No update has run on this installation.'
										: sprintf(
											'%s to %s: %s (started %s%s).',
											$run['from'] ?? '?',
											$run['to'] ?? '?',
											match ( $run['stage'] ?? '' ) {
												'finished'    => 'installed',
												'rolled_back' => 'rolled back',
												'swapped'     => 'installed, not finished',
												'applying'    => 'interrupted while installing',
												default       => (string) ( $run['stage'] ?? 'unknown' ),
											},
											$when( $run['started_at'] ?? null ),
											isset( $run['finished_at'] ) ? ', finished ' . $when( $run['finished_at'] ) : ''
										)
								);
								?>
							</p>
						</div>
					</li>
					<?php if ( null !== $attempt ) : ?>
						<li class="smliser-health-check smliser-health-tone--<?php echo escAttr( $attempt['ok'] ? 'pass' : 'warning' ); ?>">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header">
									<span class="smliser-health-check-label"><?php echo escHtml( sprintf( 'Last queued %s, %s', $attempt['action'] ?? 'update', $when( $attempt['at'] ?? null ) ) ); ?></span>
								</div>
								<p class="smliser-health-check-message"><?php echo escHtml( $attempt['ok'] ? 'Completed.' : 'Failed.' ); ?></p>
								<?php if ( '' !== (string) ( $attempt['message'] ?? '' ) ) : ?>
									<pre class="smliser-health-check-recommendation"><?php echo escHtml( (string) $attempt['message'] ); ?></pre>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Backup</span></div>
							<p class="smliser-health-check-message">
								<?php
								echo escHtml(
									null === $backup
										? 'There is no backup of a previous version.'
										: sprintf( 'The files of version %s, from %s. Kept for %d days, until the next update, or until you delete it.', $backup['version'], $when( $backup['made_at'] ), UpdateService::BACKUP_DAYS )
								);
								?>
							</p>
						</div>
					</li>
				</ul>
				<?php if ( null !== $backup ) : ?>
					<div class="smliser-health-async-footer">
						<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="rollback" data-confirm="<?php echo escAttr( sprintf( 'Restore version %s? This is only possible when the update did not change the database.', $backup['version'] ) ); ?>" <?php echo $busy ? 'disabled' : ''; ?>>
							<i class="ti ti-arrow-back-up"></i> <?php echo escHtml( sprintf( 'Restore %s', $backup['version'] ) ); ?>
						</button>
						<button type="button" class="smliser-btn smliser-btn-glass" data-update-action="backup-delete" data-confirm="Delete the backup? Restoring the previous version will no longer be possible." <?php echo $busy ? 'disabled' : ''; ?>>
							<i class="ti ti-trash"></i> Delete backup
						</button>
					</div>
				<?php endif; ?>
			</div>
		</details>
	</div>
</div>