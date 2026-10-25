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
 * @var int $receipt_size The receipt's size in bytes; 0 when missing.
 * @var string[] $receipt_pages URLs of a PDF receipt's page images; empty when not rendered.
 * @var \SmartLicenseServer\Monetization\PurchaseRequestNote[] $notes Oldest first.
 * @var array<int, string> $authors Reviewer names by user ID.
 * @var array{note: string, approve: string, reject: string} $actions URLs the review forms post to.
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
								<td><?php echo escHtml( sprintf( '%s on %s by %s', ucfirst( $purchase->get_status() ), $purchase->get_reviewed_at()?->format( smliser_datetime_format() ) ?? 'N/A', $authors[ $purchase->get_reviewed_by() ] ?? 'Unknown' ) ); ?></td>
							</tr>
							<?php if ( '' !== $purchase->get_review_note() ) : ?>
								<tr>
									<th><?php echo escHtml( 'approved' === $purchase->get_status() ? 'Message sent with the license' : 'Reason sent to the buyer' ); ?></th>
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
					<p class="smliser-review-help">The receipt file is missing from storage.</p>
				<?php else : ?>
					<div class="smliser-review-receipt-row">
						<span class="smliser-review-receipt-type"><?php echo escHtml( strtoupper( (string) ( explode( '/', $purchase->get_receipt_mime() )[1] ?? 'file' ) ) ); ?></span>
						<span>
							<strong><?php echo escHtml( $purchase->get_receipt_name() ?: 'receipt' ); ?></strong><br>
							<small><?php echo escHtml( \SmartLicenseServer\Utils\Format::bytes( $receipt_size ) ); ?></small>
						</span>
						<button type="button" class="smliser-btn smliser-surface-btn" data-smliser-receipt
							data-url="<?php echo escAttr( $receipt_url ); ?>"
							data-mime="<?php echo escAttr( $purchase->get_receipt_mime() ); ?>"
							data-pages="<?php echo escAttr( (string) smliser_safe_json_encode( $receipt_pages ) ); ?>"
							data-name="<?php echo escAttr( sprintf( 'purchase-request-%d-receipt.%s', $purchase->get_id(), pathinfo( $purchase->get_receipt_file(), PATHINFO_EXTENSION ) ) ); ?>">
							<i class="ti ti-eye"></i> View receipt
						</button>
					</div>
				<?php endif; ?>

				<?php if ( $purchase->can_approve() ) : ?>
					<h3><?php echo escHtml( $purchase->is_pending() ? 'Decision' : 'Reconsider' ); ?></h3>
					<?php if ( $purchase->is_pending() ) : ?>
						<p class="smliser-review-help">Check the receipt against your account for <?php echo escHtml( $purchase->get_price() ?: 'the price shown' ); ?>. The decision is emailed to the buyer. A rejected request can still be approved later; an approval is final.</p>
					<?php else : ?>
						<p class="smliser-review-help">This request was rejected. If the payment has since been confirmed (e.g. a resolved dispute), approve it: the license is issued and emailed, and the rejection stays in the notes.</p>
					<?php endif; ?>
					<div class="smliser-review-decisions">
						<form class="smliser-review-form" method="post" action="<?php echo escUrl( $actions['approve'] ); ?>" data-smliser-form>
							<div class="notice" data-smliser-form-message hidden></div>
							<input type="hidden" name="request_id" value="<?php echo escAttr( (string) $purchase->get_id() ); ?>">
							<label>
								<span>Message to the buyer (optional)</span>
								<textarea name="message" rows="3" placeholder="Sent with the license."></textarea>
							</label>
							<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-circle-check"></i> <?php echo escHtml( $purchase->is_pending() ? 'Approve and issue the license' : 'Approve after all and issue the license' ); ?></button>
						</form>

						<?php if ( $purchase->can_reject() ) : ?>
							<form class="smliser-review-form" method="post" action="<?php echo escUrl( $actions['reject'] ); ?>" data-smliser-form>
								<div class="notice" data-smliser-form-message hidden></div>
								<input type="hidden" name="request_id" value="<?php echo escAttr( (string) $purchase->get_id() ); ?>">
								<label>
									<span>Reason (sent to the buyer)</span>
									<textarea name="reason" rows="3" required placeholder="E.g. no payment matching this receipt was received."></textarea>
								</label>
								<button type="submit" class="smliser-btn smliser-btn-danger"><i class="ti ti-circle-x"></i> Reject</button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<h3>Notes</h3>
				<?php if ( [] === $notes ) : ?>
					<p class="smliser-review-help">No notes yet.</p>
				<?php else : ?>
					<ol class="smliser-review-notes">
						<?php foreach ( $notes as $note ) : ?>
							<li class="smliser-review-note<?php echo $note->is_sent_to_buyer() ? ' is-sent' : ''; ?>">
								<p class="smliser-review-note__meta">
									<strong><?php echo escHtml( $authors[ $note->get_author_id() ] ?? 'Unknown' ); ?></strong>
									· <?php echo escHtml( $note->get_created_at()?->format( smliser_datetime_format() ) ?? '' ); ?>
									· <?php echo escHtml( $note->is_sent_to_buyer() ? 'Emailed to the buyer' : 'Internal' ); ?>
								</p>
								<p><?php echo nl2br( escHtml( $note->get_body() ) ); ?></p>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>

				<form class="smliser-review-form" method="post" action="<?php echo escUrl( $actions['note'] ); ?>" data-smliser-form>
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="hidden" name="request_id" value="<?php echo escAttr( (string) $purchase->get_id() ); ?>">
					<label>
						<span>Add a note</span>
						<textarea name="note" rows="3" required></textarea>
					</label>
					<label class="smliser-review-check">
						<input type="checkbox" name="notify_buyer" value="1">
						<span>Also email this note to the buyer (<?php echo escHtml( $purchase->get_buyer_email() ); ?>)</span>
					</label>
					<button type="submit" class="smliser-btn smliser-surface-btn"><i class="ti ti-note"></i> Add note</button>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>