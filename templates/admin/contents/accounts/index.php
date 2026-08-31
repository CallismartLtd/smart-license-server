<?php
/**
 * Access control dashboard template (dynamic).
 *
 * Renders a complete summary of accounts & access data.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\Handlers\AccessControlPage
 * @var array $account_summaries Array result of
 * @var SmartLicenseServer\Core\Request $request
 * @see SmartLicenseServer\Security\Context\ContextServiceProvider::get_accounts_summary_report()
 * @var \SmartLicenseServer\Admin\Handlers\AccessControlPage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

use SmartLicenseServer\Utils\Format;

defined( 'SMLISER_ROOT' ) || exit;

/**
 * Helper: Render icon based on metric type.
 */
function smliser_get_metric_icon( string $label ) {
	$icons = array(
		'users'                => 'ti ti-users',
		'organizations'        => 'ti ti-building',
		'service_accounts'     => 'ti ti-user-cog',
		'organization_members' => 'ti ti-users-group',
		'resource_owners'      => 'ti ti-user-shield',
		'orphaned'             => 'ti ti-alert-triangle',
		'has_issues'           => 'ti ti-flag',
		'total'                => 'ti ti-chart-bar',
		'ever_used'            => 'ti ti-circle-check',
		'never_used'           => 'ti ti-circle-x',
		'most_recent_use'      => 'ti ti-clock',
		'oldest_use'           => 'ti ti-calendar',
	);

	$label_lower = strtolower( str_replace( ' ', '_', $label ) );

	return $icons[ $label_lower ] ?? 'ti ti-settings';
}

/**
 * Helper: Format metric value for display.
 */
function smliser_format_metric_value( mixed $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'Yes' : 'No';
	}

	if ( is_null( $value ) ) {
		return '—';
	}

	if ( is_numeric( $value ) ) {
		return Format::number( $value );
	}

	return $value;
}

/**
 * Helper: Get status class for integrity issues.
 */
function smliser_get_status_class( bool $has_issues ) {
	return $has_issues ? 'smliser-status-warning' : 'smliser-status-success';
}
?>

<div class="smliser-admin-repository-template">
	<?php $page_handler->print_header( $request ); ?>

	<div class="smliser-account-summary-wrapper">

		<?php if ( ! empty( $account_summaries ) ) : ?>

			<!-- Summary Section -->
			<?php if ( isset( $account_summaries['summary'] ) ) : ?>
				<section class="smliser-account-domain-section">
					<div class="smliser-account-domain-header">
						<h2 class="smliser-account-domain-title">
							<i class="ti ti-category"></i>
							Account Overview
						</h2>
					</div>

					<div class="smliser-account-domain-content">
						<div class="smliser-account-metrics-grid">
							<?php foreach ( $account_summaries['summary'] as $label => $value ) : ?>
								<div class="smliser-account-metric-card">
									<div class="smliser-account-metric-icon">
										<i class="<?php echo escAttr( smliser_get_metric_icon( $label ) ); ?>"></i>
									</div>

									<div class="smliser-account-metric-content">
										<h3 class="smliser-account-metric-label">
											<?php echo escHtml( smliser_format_label( $label ) ); ?>
										</h3>

										<p class="smliser-account-metric-value">
											<?php echo escHtml( smliser_format_metric_value( $value ) ); ?>
										</p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- Integrity Section -->
			<?php if ( isset( $account_summaries['integrity'] ) ) : ?>
				<section class="smliser-account-domain-section <?php echo escAttr( smliser_get_status_class( $account_summaries['integrity']['has_issues'] ?? false ) ); ?>">
					<div class="smliser-account-domain-header">
						<h2 class="smliser-account-domain-title">
							<i class="ti ti-shield"></i>
							Data Integrity
						</h2>
					</div>

					<div class="smliser-account-domain-content">
						<div class="smliser-account-metrics-grid">
							<?php foreach ( $account_summaries['integrity'] as $label => $value ) : ?>
								<?php if ( $label === 'has_issues' ) continue; ?>

								<div class="smliser-account-metric-card">
									<div class="smliser-account-metric-icon">
										<i class="<?php echo escAttr( smliser_get_metric_icon( $label ) ); ?>"></i>
									</div>

									<div class="smliser-account-metric-content">
										<h3 class="smliser-account-metric-label">
											<?php echo escHtml( smliser_format_label( $label ) ); ?>
										</h3>

										<p class="smliser-account-metric-value">
											<?php echo escHtml( smliser_format_metric_value( $value ) ); ?>
										</p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $account_summaries['integrity']['has_issues'] ?? false ) : ?>
							<div class="smliser-account-info-block smliser-account-warning">
								<i class="ti ti-info-circle"></i>
								<strong>Action Required:</strong>
								<span>Orphaned records detected. Please review and clean up data integrity issues.</span>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<!-- Usage Section -->
			<?php if ( isset( $account_summaries['usage']['service_accounts'] ) ) : ?>
				<section class="smliser-account-domain-section">
					<div class="smliser-account-domain-header">
						<h2 class="smliser-account-domain-title">
							<i class="ti ti-chart-line"></i>
							Service Account Usage
						</h2>
					</div>

					<div class="smliser-account-domain-content">
						<div class="smliser-account-metrics-grid">
							<?php foreach ( $account_summaries['usage']['service_accounts'] as $label => $value ) : ?>
								<div class="smliser-account-metric-card">
									<div class="smliser-account-metric-icon">
										<i class="<?php echo escAttr( smliser_get_metric_icon( $label ) ); ?>"></i>
									</div>

									<div class="smliser-account-metric-content">
										<h3 class="smliser-account-metric-label">
											<?php echo escHtml( smliser_format_label( $label ) ); ?>
										</h3>

										<p class="smliser-account-metric-value">
											<?php echo escHtml( smliser_format_metric_value( $value ) ); ?>
										</p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

		<?php else : ?>

			<div class="smliser-account-empty-state">
				<i class="ti ti-alert-triangle"></i>
				<h3>No Account Summary Data Available</h3>
				<p>There are currently no account summaries to display. Check back later or contact support if you believe this is an error.</p>
			</div>

		<?php endif; ?>

	</div>
</div>