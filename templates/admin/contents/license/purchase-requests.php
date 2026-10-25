<?php
/**
 * Admin purchase requests list: receipts buyers sent for tiers sold by manual payment.
 *
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var \SmartLicenseServer\Monetization\PurchaseRequest[] $purchase_requests
 * @var string $status The status shown: all (the default), pending, approved or rejected.
 * @var array{pending: int, approved: int, rejected: int} $status_counts Requests per status.
 * @var string $delete_url URL the bulk delete form posts to.
 * @var array $pagination
 * @var \SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Admin\ContentHandlers\LicensePage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

use SmartLicenseServer\Monetization\PurchaseRequest;

defined( 'SMLISER_ROOT' ) || exit;

$args = $page_handler->get_menu_args( $request );

$filters = [
	'all'                            => sprintf( 'All (%d)', array_sum( $status_counts ) ),
	PurchaseRequest::STATUS_PENDING  => sprintf( 'Pending (%d)', $status_counts[ PurchaseRequest::STATUS_PENDING ] ),
	PurchaseRequest::STATUS_APPROVED => sprintf( 'Approved (%d)', $status_counts[ PurchaseRequest::STATUS_APPROVED ] ),
	PurchaseRequest::STATUS_REJECTED => sprintf( 'Rejected (%d)', $status_counts[ PurchaseRequest::STATUS_REJECTED ] ),
];
$last_filter = array_key_last( $filters );
?>
<div class="smliser-admin-page">
	<?php smliser_print_admin_content_header( $args ); ?>
	<div class="smliser-table-wrapper">
		<p class="smliser-purchase-request-filters">
			<?php foreach ( $filters as $value => $label ) : ?>
				<?php if ( $value === $status ) : ?>
					<strong><?php echo escHtml( $label ); ?></strong>
				<?php else : ?>
					<a href="<?php echo escUrl( $urlmanager->admin_license_page_url( 'purchase-requests', [ 'status' => $value ] )->url() ); ?>"><?php echo escHtml( $label ); ?></a>
				<?php endif; ?>
				<?php echo $last_filter !== $value ? ' | ' : ''; ?>
			<?php endforeach; ?>
		</p>

		<?php if ( empty( $purchase_requests ) ) : ?>
			<?php echo sanitize_html( smliser_not_found_container( 'Purchase requests buyers send for tiers sold by manual payment will appear here.' ) ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo escUrl( $delete_url ); ?>" data-smliser-form
				data-confirm="Delete the selected purchase requests, with their notes and receipts? Licenses issued from them are kept. This cannot be undone." data-confirm-label="Delete" data-confirm-danger>
			<div class="notice" data-smliser-form-message hidden></div>
			<input type="hidden" name="status" value="<?php echo escAttr( $status ); ?>">
			<div class="smliser-actions-wrapper">
				<div class="smliser-bulk-actions">
					<button type="submit" class="smliser-btn smliser-btn-danger"><i class="ti ti-trash"></i> Delete selected</button>
				</div>
			</div>
			<table class="smliser-table widefat striped">
				<thead>
					<tr>
						<th><input type="checkbox" id="smliser-select-all" aria-label="Select all"></th>
						<th><?php echo escHtml( 'Request' ); ?></th>
						<th><?php echo escHtml( 'Buyer' ); ?></th>
						<th><?php echo escHtml( 'Bought' ); ?></th>
						<th><?php echo escHtml( 'Price' ); ?></th>
						<th><?php echo escHtml( 'Status' ); ?></th>
						<th><?php echo escHtml( 'Submitted' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $purchase_requests as $purchase ) :
						$tier = $purchase->get_tier();
						?>
						<tr>
							<td><input type="checkbox" class="smliser-checkbox" name="ids[]" value="<?php echo escAttr( (string) $purchase->get_id() ); ?>" aria-label="<?php echo escAttr( sprintf( 'Select request #%d', $purchase->get_id() ) ); ?>"></td>
							<td class="smliser-edit-row">
								#<?php echo escHtml( (string) $purchase->get_id() ); ?>
								<p class="smliser-edit-link">
									<a href="<?php echo escUrl( $urlmanager->admin_license_page_url( 'purchase-request', [ 'id' => $purchase->get_id() ] )->url() ); ?>">review</a>
								</p>
							</td>
							<td>
								<?php echo escHtml( $purchase->get_buyer_name() ); ?><br>
								<small><?php echo escHtml( $purchase->get_buyer_email() ); ?></small>
							</td>
							<td><?php echo escHtml( $tier ? $tier->get_name() : sprintf( 'Deleted tier #%d', $purchase->get_tier_id() ) ); ?></td>
							<td><?php echo escHtml( $purchase->get_price() ); ?></td>
							<td><?php echo escHtml( ucfirst( $purchase->get_status() ) ); ?></td>
							<td><?php echo escHtml( $purchase->get_created_at()?->format( smliser_datetime_format() ) ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</form>
			<?php smliser_render_pagination( $pagination ); ?>
		<?php endif; ?>
	</div>
</div>