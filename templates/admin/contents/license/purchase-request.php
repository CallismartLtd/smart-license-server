<?php
/**
 * Admin purchase request review page.
 *
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var \SmartLicenseServer\Monetization\PurchaseRequest|null $purchase
 * @var \SmartLicenseServer\Monetization\PricingTier|null $tier
 * @var \SmartLicenseServer\HostedApps\HostedAppsInterface|null $app
 * @var int $request_id
 * @var string $receipt_url Empty when the receipt file is missing.
 * @var \SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Admin\ContentHandlers\LicensePage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

defined( 'SMLISER_ROOT' ) || exit;

$args = $page_handler->get_menu_args( $request );

\array_splice(
	$args['breadcrumbs'],
	1,
	0,
	[
		[
			'label' => 'Purchase Requests',
			'url'   => $urlmanager->admin_license_page_url( 'purchase-requests' ),
		],
	]
);

$is_image = $purchase && str_starts_with( $purchase->get_receipt_mime(), 'image/' );
?>
<div class="smliser-admin-page">
	<?php smliser_print_admin_content_header( $args ); ?>
	<?php if ( ! $purchase ) : ?>
		<?php echo \smliser_not_found_container(
			\sprintf(
				'There is no purchase request #%d. <a href="%s"><i class="ti ti-arrow-narrow-left"></i> Go Back</a>',
				$request_id,
				$urlmanager->admin_license_page_url( 'purchase-requests' )->url()
			)
		); ?>
	<?php else : ?>
		<div class="smliser-admin-view-page-wrapper">
			<div class="smliser-admin-view-page-header">
				<div class="smliser-admin-view-page-header-child">
					<h2>Request</h2>
					<p><i class="ti ti-database"></i> Request ID: <?php echo escHtml( (string) $purchase->get_id() ); ?></p>
					<p><i class="ti ti-circle-check"></i> Status: <?php echo escHtml( ucfirst( $purchase->get_status() ) ); ?></p>
					<p><i class="ti ti-calendar"></i> Submitted: <?php echo escHtml( $purchase->get_created_at()?->format( smliser_datetime_format() ) ?? 'N/A' ); ?></p>
				</div>

				<div class="smliser-admin-view-page-header-child">
					<h2>Buyer</h2>
					<p><i class="ti ti-user"></i> <?php echo escHtml( $purchase->get_buyer_name() ); ?></p>
					<p><i class="ti ti-mail"></i> <?php echo escHtml( $purchase->get_buyer_email() ); ?></p>
					<p><i class="ti ti-id"></i> <?php echo escHtml( $purchase->get_user_id() ? sprintf( 'Signed in as user #%d', $purchase->get_user_id() ) : ( $purchase->wants_account() ? 'Guest; asked for an account' : 'Guest' ) ); ?></p>
				</div>

				<div class="smliser-admin-view-page-header-child">
					<h2>Bought</h2>
					<p><i class="ti ti-apps"></i> <?php echo escHtml( $app ? $app->get_name() : 'Unknown app' ); ?></p>
					<p><i class="ti ti-tag"></i> <?php echo escHtml( $tier ? $tier->get_name() : sprintf( 'Deleted tier #%d', $purchase->get_tier_id() ) ); ?></p>
					<p><i class="ti ti-cash"></i> Price shown: <?php echo escHtml( $purchase->get_price() ?: 'N/A' ); ?></p>
				</div>
			</div>

			<div class="smliser-admin-view-page-body">
				<h3>Payment</h3>
				<table class="widefat smliser-license-table">
					<tbody>
						<tr>
							<th>Payment reference</th>
							<td><?php echo escHtml( $purchase->get_payment_reference() ?: 'None given' ); ?></td>
						</tr>
						<tr>
							<th>Buyer's note</th>
							<td><?php echo nl2br( escHtml( $purchase->get_note() ?: 'None' ) ); ?></td>
						</tr>
						<tr>
							<th>Submitted from</th>
							<td><?php echo escHtml( $purchase->get_ip_address() ?: 'N/A' ); ?></td>
						</tr>
						<?php if ( ! $purchase->is_pending() ) : ?>
							<tr>
								<th>Reviewed</th>
								<td><?php echo escHtml( sprintf( '%s by user #%d', $purchase->get_reviewed_at()?->format( smliser_datetime_format() ) ?? 'N/A', $purchase->get_reviewed_by() ) ); ?></td>
							</tr>
							<?php if ( '' !== $purchase->get_review_note() ) : ?>
								<tr>
									<th>Review note</th>
									<td><?php echo nl2br( escHtml( $purchase->get_review_note() ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( $purchase->get_license_id() ) : ?>
								<tr>
									<th>License</th>
									<td><a href="<?php echo escUrl( $urlmanager->admin_license_page_url( 'view', [ 'id' => $purchase->get_license_id() ] )->url() ); ?>">License #<?php echo escHtml( (string) $purchase->get_license_id() ); ?></a></td>
								</tr>
							<?php endif; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<h3>Receipt</h3>
				<?php if ( '' === $receipt_url ) : ?>
					<p>The receipt file is missing from storage.</p>
				<?php else : ?>
					<p>
						<a class="smliser-btn smliser-surface-btn" href="<?php echo escUrl( $receipt_url ); ?>" target="_blank" rel="noopener">
							<i class="ti ti-file-invoice"></i> Open <?php echo escHtml( $purchase->get_receipt_name() ?: 'receipt' ); ?>
						</a>
					</p>
					<?php if ( $is_image ) : ?>
						<p><img src="<?php echo escUrl( $receipt_url ); ?>" alt="Payment receipt" style="max-width: 100%; max-height: 640px; border: 1px solid currentColor;"></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>