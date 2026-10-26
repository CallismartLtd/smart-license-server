<?php
/**
 * My licenses: the licenses linked to the signed-in user.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, app: string, tier: string, status: string, status_key: string,
 *     expires: string, sites: string, key: string, partial_key: string, document_url: string}> $licenses
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
 * @var bool $verified Whether the user's email address is verified.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'active'    => 'success',
	'lifetime'  => 'success',
	'expired'   => 'failure',
	'revoked'   => 'failure',
	'suspended' => 'failure',
];
?>
<p class="dash-lead">Licenses issued to you. Use the key on each site you activate.</p>

<?php if ( [] === $licenses ) : ?>
	<div class="cell">
		<h3>No licenses yet</h3>
		<p>
			<?php echo escHtml( $verified
				? 'Licenses you buy appear here once they are issued.'
				: 'Licenses you buy appear here once they are issued. Verify your email address to see licenses bought with it before you signed up.' ); ?>
		</p>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $licenses as $license ) : ?>
			<li class="record">
				<div class="record-head">
					<h3><?php echo escHtml( '' !== $license['tier'] ? sprintf( '%s — %s', $license['app'], $license['tier'] ) : $license['app'] ); ?></h3>
					<span class="badge" data-tone="<?php echo escAttr( $tones[ $license['status_key'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $license['status'] ); ?></span>
				</div>

				<dl class="record-facts">
					<div>
						<dt>License key</dt>
						<dd class="record-key">
							<code><?php echo escHtml( $license['partial_key'] ); ?></code>
							<button type="button" class="btn btn-small" data-copy="<?php echo escAttr( $license['key'] ); ?>">Copy key</button>
						</dd>
					</div>
					<div>
						<dt>Expires</dt>
						<dd><?php echo escHtml( $license['expires'] ); ?></dd>
					</div>
					<div>
						<dt>Sites</dt>
						<dd><?php echo escHtml( $license['sites'] ); ?></dd>
					</div>
				</dl>

				<div class="record-actions">
					<a class="btn btn-small" href="<?php echo escUrl( $license['document_url'] ); ?>" download>
						<i class="ti ti-file-download" aria-hidden="true"></i>
						<span>License document</span>
					</a>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php endif; ?>