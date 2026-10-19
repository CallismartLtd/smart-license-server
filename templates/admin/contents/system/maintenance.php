<?php
/**
 * The admin maintenance page template.
 *
 * Shows whether the site is in maintenance and why. Operator maintenance
 * is switched on and off here; maintenance.js posts the actions to
 * admin/json/maintenance/* and shows the bypass link once.
 *
 * @author Callistus Nwachukwu
 * @var array $status MaintenanceMode::status().
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;

$menu_args = $page_handler->get_top_menu_args( $request );
$reason    = $status['reason'];
$operator  = MaintenanceFlag::REASON_MAINTENANCE === $reason;
$owned     = $status['active'] && ! $operator;

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

[ $tone, $icon, $title, $message ] = match ( true ) {
	MaintenanceFlag::REASON_UPDATE === $reason => [
		'pending',
		'ti ti-loader-2 smliser-health-spin',
		'An update holds maintenance mode',
		'The site is in maintenance while an update is installed or finished. The update ends it; see the Updates page.',
	],
	MaintenanceFlag::REASON_INSTALLATION === $reason => [
		'pending',
		'ti ti-loader-2 smliser-health-spin',
		'An installation holds maintenance mode',
		'The site is in maintenance while it is being installed.',
	],
	$operator => [
		'warning',
		'ti ti-barrier-block',
		'Maintenance mode is on',
		sprintf(
			'Visitors get the maintenance page%s. You are using the site through the bypass.',
			$status['background_paused'] ? ', and scheduled tasks and queued jobs wait' : ''
		),
	],
	default => [
		'pass',
		'ti ti-circle-check',
		'The site is open',
		'Switch maintenance mode on before a database backup or import, a server move, or other work that needs the site quiet.',
	],
};

$chevron = '<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>';
?>
<div class="smliser-admin-page" id="smliser-maintenance">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-health-overview smliser-health-tone--<?php echo escAttr( $tone ); ?>">
		<span class="smliser-health-overview-icon"><i class="<?php echo escAttr( $icon ); ?>"></i></span>
		<div class="smliser-health-overview-text">
			<strong class="smliser-health-overview-title"><?php echo escHtml( $title ); ?></strong>
			<p class="smliser-health-overview-message"><?php echo escHtml( $message ); ?></p>
		</div>
	</div>

	<div class="smliser-diagnostics-grid">

		<?php if ( $status['active'] ) : ?>
			<?php /* Current maintenance. */ ?>
			<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo escAttr( $tone ); ?>" open>
				<summary class="smliser-diagnostics-panel-summary">
					<span class="smliser-diagnostics-panel-summary-left">
						<span class="smliser-diagnostics-panel-icon"><i class="ti ti-info-circle"></i></span>
						<span class="smliser-diagnostics-panel-title">Current maintenance</span>
					</span>
					<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
				</summary>
				<div class="smliser-diagnostics-panel-body">
					<ul class="smliser-health-checks">
						<?php
						$rows = [
							'Since'           => $when( $status['since'] ),
							'Switched on by'  => $status['by'] ?? '—',
							'Visitors read'   => '' === $status['message'] ? 'The default maintenance notice.' : $status['message'],
							'Retry-After'     => $status['retry_after'] > 0 ? $status['retry_after'] . ' seconds' : 'Default',
							'Background work' => $status['background_paused'] ? 'Paused until maintenance ends.' : 'Running.',
						];
						?>
						<?php foreach ( $rows as $label => $value ) : ?>
							<li class="smliser-health-check">
								<div class="smliser-health-check-body">
									<div class="smliser-health-check-header"><span class="smliser-health-check-label"><?php echo escHtml( $label ); ?></span></div>
									<p class="smliser-health-check-message"><?php echo escHtml( (string) $value ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $operator ) : ?>
						<div class="smliser-health-async-footer">
							<span class="smliser-health-async-time">The bypass link lets another browser in; a new link stops the earlier ones.</span>
							<button type="button" class="smliser-btn smliser-btn-glass" data-maintenance-action="bypass" data-confirm="Make a new bypass link? Earlier links stop working; this browser keeps access.">
								<i class="ti ti-link"></i> New bypass link
							</button>
							<button type="button" class="smliser-btn smliser-btn-glass" data-maintenance-action="disable" data-confirm="Open the site to visitors again?">
								<i class="ti ti-lock-open"></i> Turn maintenance off
							</button>
						</div>
					<?php endif; ?>
				</div>
			</details>
		<?php endif; ?>

		<?php if ( ! $owned ) : ?>
			<?php /* Switch on, or switch on again with new settings. */ ?>
			<details class="smliser-diagnostics-panel smliser-health-panel" <?php echo $status['active'] ? '' : 'open'; ?>>
				<summary class="smliser-diagnostics-panel-summary">
					<span class="smliser-diagnostics-panel-summary-left">
						<span class="smliser-diagnostics-panel-icon"><i class="ti ti-barrier-block"></i></span>
						<span class="smliser-diagnostics-panel-title"><?php echo escHtml( $status['active'] ? 'Change maintenance' : 'Switch maintenance on' ); ?></span>
					</span>
					<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
				</summary>
				<div class="smliser-diagnostics-panel-body">
					<form id="smliser-maintenance-enable" data-active="<?php echo $status['active'] ? '1' : '0'; ?>">
						<ul class="smliser-health-checks">
							<li class="smliser-health-check">
								<label class="smliser-health-check-body">
									<span class="smliser-health-check-header"><span class="smliser-health-check-label">Message for visitors</span></span>
									<input type="text" name="message" class="smliser-form-input" maxlength="500" value="<?php echo escAttr( $status['message'] ); ?>" placeholder="<?php echo escAttr( sprintf( '%s is undergoing scheduled maintenance. Please try again shortly.', \SMLISER_APP_NAME ) ); ?>">
								</label>
							</li>
							<li class="smliser-health-check">
								<label class="smliser-health-check-body">
									<span class="smliser-health-check-header"><span class="smliser-health-check-label">Ask visitors to retry after (seconds)</span></span>
									<input type="number" name="retry_after" class="smliser-form-input" min="0" step="1" value="<?php echo escAttr( (string) ( $status['retry_after'] > 0 ? $status['retry_after'] : BootModeResolver::DEFAULT_RETRY_AFTER ) ); ?>">
								</label>
							</li>
							<li class="smliser-health-check">
								<label class="smliser-health-check-body">
									<span class="smliser-health-check-header">
										<input type="checkbox" name="keep_background" value="1" <?php echo $status['active'] && ! $status['background_paused'] ? 'checked' : ''; ?>>
										<span class="smliser-health-check-label">Keep background work running</span>
									</span>
									<span class="smliser-health-check-message">By default, scheduled tasks and queued jobs wait, so nothing writes to the database during a backup or an import.</span>
								</label>
							</li>
						</ul>
						<div class="smliser-health-async-footer">
							<span class="smliser-health-async-time">This browser keeps access; you get a bypass link for others. The console works throughout.</span>
							<button type="submit" class="smliser-btn smliser-btn-glass">
								<i class="ti ti-barrier-block"></i> <?php echo escHtml( $status['active'] ? 'Apply' : 'Turn maintenance on' ); ?>
							</button>
						</div>
					</form>
				</div>
			</details>
		<?php endif; ?>

	</div>
</div>