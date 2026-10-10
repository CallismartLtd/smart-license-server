<?php
/**
 * The admin site health page template.
 *
 * Server-side checks render immediately. Slower checks run afterwards
 * via health.js.
 *
 * @author Callistus Nwachukwu
 * @var array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> $checks
 * @var array{product: string, version: string, schema: string, site: string} $report_meta Support report header.
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

$menu_args = $page_handler->get_top_menu_args( $request );

/*
|--------------
| STATUS GROUPS
|--------------
*/

// Display order, most severe first. Passing checks start collapsed.
// "label" is the singular badge text used for async results.
$status_groups = [
	'critical' => [
		'title' => 'Critical Issues',
		'label' => 'Critical',
		'icon'  => 'ti ti-circle-x',
		'open'  => true,
	],
	'warning'  => [
		'title' => 'Warnings',
		'label' => 'Warning',
		'icon'  => 'ti ti-alert-triangle',
		'open'  => true,
	],
	'info'     => [
		'title' => 'Recommendations',
		'label' => 'Recommended',
		'icon'  => 'ti ti-info-circle',
		'open'  => false,
	],
	'pass'     => [
		'title' => 'Passed Checks',
		'label' => 'Passed',
		'icon'  => 'ti ti-circle-check',
		'open'  => false,
	],
];

$grouped = \array_fill_keys( \array_keys( $status_groups ), [] );

foreach ( $checks as $check ) {
	// Anything with an unrecognized status is shown, not dropped.
	$status               = isset( $grouped[ $check['status'] ] ) ? $check['status'] : 'info';
	$grouped[ $status ][] = $check;
}

/*
|---------------
| OVERALL STATUS
|---------------
*/

$overall_states = [
	'critical' => [
		'icon'    => 'ti ti-circle-x',
		'title'   => 'Should be fixed now',
		'message' => 'One or more critical checks failed. Parts of the application may not work until these are resolved.',
	],
	'warning'  => [
		'icon'    => 'ti ti-alert-triangle',
		'title'   => 'Should be improved',
		'message' => 'The application is running, but some checks need attention.',
	],
	'pass'     => [
		'icon'    => 'ti ti-circle-check',
		'title'   => 'Good',
		'message' => 'All critical and warning checks passed.',
	],
];

$overall_status = match ( true ) {
	! empty( $grouped['critical'] ) => 'critical',
	! empty( $grouped['warning'] )  => 'warning',
	default                         => 'pass',
};

$overall = $overall_states[ $overall_status ];

/*
|-------------
| ASYNC CONFIG
|-------------
*/

// Everything health.js displays comes from here, so display strings
// live in one place.
$async_config = [
	'statuses'       => \array_map(
		static fn( array $group ) : array => [
			'label' => $group['label'],
			'icon'  => $group['icon'],
		],
		$status_groups
	),
	'overall'        => $overall_states,
	'overall_status' => $overall_status,
];
?>
<div class="smliser-admin-page">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<?php if ( empty( $checks ) ) : ?>
		<div class="smliser-not-found-container">
			<p>No health checks available.</p>
		</div>
	<?php else : ?>
		<div class="smliser-tools-actions">
			<button
				type="button"
				class="smliser-btn smliser-btn-glass"
				data-support-report="health"
				data-report-meta="<?php echo escAttr( (string) \json_encode( $report_meta ) ); ?>"
			><i class="ti ti-clipboard-copy"></i> Copy report for support</button>
			<span class="smliser-section-description">
				<i class="ti ti-info-circle"></i>
				Copies these checks and the System Diagnostics as plain text to paste into a support request. It contains no passwords or keys.
			</span>
		</div>

		<div class="smliser-health-overview smliser-health-tone--<?php echo escAttr( $overall_status ); ?>" id="smliser-health-overview">
			<span class="smliser-health-overview-icon">
				<i class="<?php echo escAttr( $overall['icon'] ); ?>"></i>
			</span>
			<div class="smliser-health-overview-text">
				<strong class="smliser-health-overview-title"><?php echo escHtml( $overall['title'] ); ?></strong>
				<p class="smliser-health-overview-message"><?php echo escHtml( $overall['message'] ); ?></p>
			</div>
			<ul class="smliser-health-overview-counts">
				<?php foreach ( $status_groups as $status => $group ) : ?>
					<li class="smliser-health-count smliser-health-tone--<?php echo escAttr( $status ); ?>" data-health-count="<?php echo escAttr( $status ); ?>">
						<span class="smliser-health-count-value"><?php echo escHtml( (string) \count( $grouped[ $status ] ) ); ?></span>
						<span class="smliser-health-count-label"><?php echo escHtml( $group['title'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="smliser-diagnostics-grid" id="smliser-health-grid">
			<details
				class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--pending"
				id="smliser-health-async"
				data-config="<?php echo escAttr( (string) \json_encode( $async_config ) ); ?>"
				open
			>
				<summary class="smliser-diagnostics-panel-summary">
					<span class="smliser-diagnostics-panel-summary-left">
						<span class="smliser-diagnostics-panel-icon">
							<i class="ti ti-clock"></i>
						</span>
						<span class="smliser-diagnostics-panel-title">Additional Checks</span>
					</span>
					<span class="smliser-diagnostics-panel-summary-right">
						<span class="smliser-diagnostics-panel-count">…</span>
						<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</span>
				</summary>
				<div class="smliser-diagnostics-panel-body">
					<ul class="smliser-health-checks"></ul>
					<noscript>
						<p class="smliser-health-check-message">JavaScript is required to run the additional checks.</p>
					</noscript>
				</div>
			</details>

			<?php foreach ( $status_groups as $status => $group ) : ?>
				<?php
				if ( empty( $grouped[ $status ] ) ) {
					continue;
				}
				?>
				<details
					class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo escAttr( $status ); ?>"
					id="smliser-health-<?php echo escAttr( $status ); ?>"
					<?php echo $group['open'] ? 'open' : ''; ?>
				>
					<summary class="smliser-diagnostics-panel-summary">
						<span class="smliser-diagnostics-panel-summary-left">
							<span class="smliser-diagnostics-panel-icon">
								<i class="<?php echo escAttr( $group['icon'] ); ?>"></i>
							</span>
							<span class="smliser-diagnostics-panel-title"><?php echo escHtml( $group['title'] ); ?></span>
						</span>
						<span class="smliser-diagnostics-panel-summary-right">
							<span class="smliser-diagnostics-panel-count"><?php echo escHtml( (string) \count( $grouped[ $status ] ) ); ?></span>
							<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="6 9 12 15 18 9" />
							</svg>
						</span>
					</summary>
					<div class="smliser-diagnostics-panel-body">
						<ul class="smliser-health-checks">
							<?php foreach ( $grouped[ $status ] as $check ) : ?>
								<li class="smliser-health-check smliser-health-tone--<?php echo escAttr( $status ); ?>" id="<?php echo escAttr( 'smliser-health-check-' . $check['id'] ); ?>">
									<span class="smliser-health-check-icon">
										<i class="<?php echo escAttr( $group['icon'] ); ?>"></i>
									</span>
									<div class="smliser-health-check-body">
										<div class="smliser-health-check-header">
											<span class="smliser-health-check-label"><?php echo escHtml( $check['label'] ); ?></span>
										</div>
										<p class="smliser-health-check-message"><?php echo escHtml( $check['message'] ); ?></p>
										<?php
										// Installer-sourced failures currently repeat the
										// message as the recommendation; don't print it twice.
										if ( null !== $check['recommendation'] && $check['recommendation'] !== $check['message'] ) :
											?>
											<p class="smliser-health-check-recommendation">
												<i class="ti ti-bulb"></i>
												<span><?php echo escHtml( $check['recommendation'] ); ?></span>
											</p>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>