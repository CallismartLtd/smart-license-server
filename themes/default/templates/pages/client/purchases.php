<?php
/**
 * My purchases: the signed-in user's purchase requests.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, product: string, price: string, status: string, status_key: string,
 *     submitted: string, decided: string, message_label: string, message: string,
 *     license_url: string, receipt: string,
 *     notes: list<array{date: string, body: string}>}> $purchases
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
 * @var bool $verified Whether the user's email address is verified.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'pending'  => 'neutral',
	'approved' => 'success',
	'rejected' => 'failure',
];
?>
<p class="dash-lead">Purchases you paid for by transfer, and what became of them.</p>

<?php if ( [] === $purchases ) : ?>
	<div class="cell">
		<h3>No purchases yet</h3>
		<p>
			<?php echo escHtml( $verified
				? 'When you buy a license and send your payment receipt, it appears here while it is reviewed.'
				: 'When you buy a license and send your payment receipt, it appears here while it is reviewed. Verify your email address to see purchases made with it before you signed in.' ); ?>
		</p>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $purchases as $purchase ) : ?>
			<li class="record">
				<div class="record-head">
					<h3><?php echo escHtml( $purchase['product'] ); ?></h3>
					<span class="badge" data-tone="<?php echo escAttr( $tones[ $purchase['status_key'] ] ?? 'neutral' ); ?>"><?php echo escHtml( 'pending' === $purchase['status_key'] ? 'Awaiting review' : $purchase['status'] ); ?></span>
				</div>

				<dl class="record-facts">
					<div>
						<dt>Request</dt>
						<dd>#<?php echo escHtml( (string) $purchase['id'] ); ?></dd>
					</div>
					<div>
						<dt>Price</dt>
						<dd><?php echo escHtml( $purchase['price'] ); ?></dd>
					</div>
					<div>
						<dt>Sent</dt>
						<dd><?php echo escHtml( $purchase['submitted'] ); ?></dd>
					</div>
					<?php if ( '' !== $purchase['decided'] ) : ?>
						<div>
							<dt><?php echo escHtml( 'approved' === $purchase['status_key'] ? 'Approved' : 'Decided' ); ?></dt>
							<dd><?php echo escHtml( $purchase['decided'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $purchase['receipt'] ) : ?>
						<div>
							<dt>Receipt</dt>
							<dd><?php echo escHtml( $purchase['receipt'] ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( '' !== $purchase['message'] ) : ?>
					<div class="record-message">
						<strong><?php echo escHtml( $purchase['message_label'] ); ?></strong>
						<p><?php echo nl2br( escHtml( $purchase['message'] ) ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( [] !== $purchase['notes'] ) : ?>
					<ol class="record-notes" aria-label="Messages about this purchase">
						<?php foreach ( $purchase['notes'] as $note ) : ?>
							<li>
								<span class="muted"><?php echo escHtml( $note['date'] ); ?></span>
								<p><?php echo nl2br( escHtml( $note['body'] ) ); ?></p>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>

				<?php if ( '' !== $purchase['license_url'] ) : ?>
					<div class="record-actions">
						<a class="btn btn-small" href="<?php echo escUrl( $purchase['license_url'] ); ?>">
							<i class="ti ti-certificate" aria-hidden="true"></i>
							<span>See your license</span>
						</a>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php endif; ?>