<?php
/**
 * One app of the current owner: its details, a new version, its images and its status.
 *
 * @author Callistus Nwachukwu
 * @var array{id: int, type: string, type_label: string, name: string, slug: string, version: string,
 *     status: string, status_label: string, created: string, updated: string, public_url: string} $app
 * @var list<array{name: string, label: string, value: string, type: string, required: bool}> $fields
 * @var array{update: bool, change_status: bool, delete: bool, upload_assets: bool, delete_assets: bool} $can
 * @var array<string, string> $statuses Statuses the owner may choose, status => label.
 * @var list<array{type: string, label: string, hint: string, items: list<array{name: string, url: string}>}> $assets
 * @var int $max_size Largest upload, in bytes.
 * @var string $list_url The list of apps.
 * @var array<string, string> $actions
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'active'    => 'success',
	'suspended' => 'failure',
	'trash'     => 'failure',
];

$in_trash  = 'trash' === $app['status'];
$suspended = 'suspended' === $app['status'];
?>
<div class="record">
	<div class="record-head">
		<div>
			<h3><?php echo escHtml( $app['name'] ); ?></h3>
			<span class="muted"><?php echo escHtml( $app['type_label'] ); ?></span>
		</div>
		<span class="badge" data-tone="<?php echo escAttr( $tones[ $app['status'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $app['status_label'] ); ?></span>
	</div>

	<dl class="record-facts">
		<div>
			<dt>Slug</dt>
			<dd><code><?php echo escHtml( $app['slug'] ); ?></code></dd>
		</div>
		<div>
			<dt>Version</dt>
			<dd><?php echo escHtml( '' !== $app['version'] ? $app['version'] : '—' ); ?></dd>
		</div>
		<div>
			<dt>Uploaded</dt>
			<dd><?php echo escHtml( $app['created'] ); ?></dd>
		</div>
		<div>
			<dt>Updated</dt>
			<dd><?php echo escHtml( $app['updated'] ); ?></dd>
		</div>
	</dl>

	<div class="record-actions">
		<a class="btn btn-small" href="<?php echo escUrl( $list_url ); ?>">
			<i class="ti ti-arrow-left" aria-hidden="true"></i>
			<span>Your apps</span>
		</a>
		<?php if ( 'active' === $app['status'] ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $app['public_url'] ); ?>" target="_blank" rel="noopener">
				<i class="ti ti-external-link" aria-hidden="true"></i>
				<span>Public page</span>
			</a>
		<?php endif; ?>
	</div>
</div>

<?php if ( $suspended ) : ?>
	<div class="notice" data-state="error" role="status">This app is suspended by the site's administrators, so it is not offered. Contact them to have the suspension lifted.</div>
<?php elseif ( $in_trash ) : ?>
	<div class="notice" data-state="error" role="status">This app is in the trash. It is deleted for good after a while, unless you restore it.</div>
<?php endif; ?>

<?php if ( $can['update'] && ! $in_trash ) : ?>
	<section class="dash-section" aria-labelledby="details-title">
		<h2 id="details-title">Details</h2>
		<p class="muted">Upload a new zip file to publish a new version; its version and requirements are read from the package.</p>
		<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $actions['save'] ); ?>" enctype="multipart/form-data" data-smliser-form>
			<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
			<input type="hidden" name="app_id" value="<?php echo escAttr( (string) $app['id'] ); ?>">
			<div class="notice" data-smliser-form-message hidden></div>

			<?php foreach ( $fields as $field ) : ?>
				<label class="field">
					<span class="field-label"><?php echo escHtml( $field['label'] ); ?></span>
					<input type="<?php echo escAttr( $field['type'] ); ?>" name="<?php echo escAttr( $field['name'] ); ?>" value="<?php echo escAttr( $field['value'] ); ?>"<?php echo $field['required'] ? ' required' : ''; ?><?php echo 'url' === $field['type'] ? ' placeholder="https://"' : ''; ?>>
				</label>
			<?php endforeach; ?>

			<div class="field">
				<span class="field-label">New version <span class="muted">(optional)</span></span>
				<input type="file" name="app_zip_file" accept=".zip" data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="Drop the new zip file here, or choose it">
				<span class="field-hint">Leave empty to keep the current version.</span>
			</div>

			<div class="field">
				<span class="field-label">app.json <span class="muted">(optional)</span></span>
				<input type="file" name="app_json_file" accept=".json,application/json" data-smliser-uploader data-label="Drop an app.json file here, or choose it">
				<span class="field-hint">Replaces the app's manifest.</span>
			</div>

			<button type="submit" class="btn btn-accent">Save changes</button>
		</form>
	</section>
<?php endif; ?>

<?php if ( [] !== $assets && ! $in_trash && ( $can['upload_assets'] || $can['delete_assets'] ) ) : ?>
	<section class="dash-section" aria-labelledby="images-title">
		<h2 id="images-title">Images</h2>

		<?php foreach ( $assets as $group ) : ?>
			<div class="asset-group">
				<h3 class="dash-subheading"><?php echo escHtml( $group['label'] ); ?></h3>

				<?php if ( [] === $group['items'] ) : ?>
					<p class="muted">None yet.</p>
				<?php else : ?>
					<ul class="asset-grid">
						<?php foreach ( $group['items'] as $item ) : ?>
							<li class="asset-tile">
								<a href="<?php echo escUrl( $item['url'] ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo escUrl( $item['url'] ); ?>" alt="<?php echo escAttr( $item['name'] ); ?>" loading="lazy">
								</a>
								<span class="asset-name"><?php echo escHtml( $item['name'] ); ?></span>
								<?php if ( $can['delete_assets'] ) : ?>
									<form method="post" action="<?php echo escUrl( $actions['asset_delete'] ); ?>" data-smliser-form
										data-confirm="<?php echo escAttr( sprintf( 'Delete %s?', $item['name'] ) ); ?>" data-confirm-label="Delete" data-confirm-danger>
										<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
										<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
										<input type="hidden" name="asset_name" value="<?php echo escAttr( $item['name'] ); ?>">
										<div class="notice" data-smliser-form-message hidden></div>
										<button type="submit" class="btn btn-small">Delete</button>
									</form>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $can['upload_assets'] ) : ?>
					<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $actions['asset_upload'] ); ?>" enctype="multipart/form-data" data-smliser-form>
						<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
						<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
						<input type="hidden" name="asset_type" value="<?php echo escAttr( $group['type'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<div class="field">
							<?php if ( '' !== $group['hint'] ) : ?>
								<span class="field-hint"><?php echo escHtml( $group['hint'] ); ?></span>
							<?php endif; ?>
							<input type="file" name="asset_file[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple required data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php echo escAttr( sprintf( 'Drop %s images here, or choose them', strtolower( $group['label'] ) ) ); ?>">
						</div>
						<button type="submit" class="btn btn-small"><?php echo escHtml( sprintf( 'Add %s', strtolower( $group['label'] ) ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php if ( ! $suspended && ( $can['change_status'] || $can['delete'] ) ) : ?>
	<section class="dash-section dash-danger" aria-labelledby="status-title">
		<h2 id="status-title">Status</h2>

		<?php if ( $in_trash ) : ?>
			<div class="record-actions">
				<?php if ( $can['delete'] && $can['change_status'] ) : ?>
					<form method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form>
						<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
						<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
						<input type="hidden" name="app_status" value="active">
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn btn-accent">Restore</button>
					</form>
				<?php endif; ?>
				<?php if ( $can['delete'] ) : ?>
					<form method="post" action="<?php echo escUrl( $actions['delete'] ); ?>" data-smliser-form
						data-confirm="<?php echo escAttr( sprintf( 'Delete %s for good? Its files go too, and this cannot be undone.', $app['name'] ) ); ?>" data-confirm-label="Delete for good" data-confirm-danger>
						<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
						<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn">Delete for good</button>
					</form>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php if ( $can['change_status'] ) : ?>
				<p class="muted">An inactive app is not offered for download or sale.</p>
				<form class="inline-form" method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form>
					<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
					<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
					<label>
						<span class="screen-reader-text">Status</span>
						<select name="app_status">
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo escAttr( $key ); ?>"<?php echo $key === $app['status'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="btn btn-small">Change status</button>
					<div class="notice" data-smliser-form-message hidden></div>
				</form>
			<?php endif; ?>
			<?php if ( $can['delete'] && $can['change_status'] ) : ?>
				<div class="record-actions">
					<form method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form
						data-confirm="<?php echo escAttr( sprintf( 'Move %s to the trash? It stops being offered, and is deleted for good after a while unless you restore it.', $app['name'] ) ); ?>" data-confirm-label="Move to trash" data-confirm-danger>
						<input type="hidden" name="app_type" value="<?php echo escAttr( $app['type'] ); ?>">
						<input type="hidden" name="app_slug" value="<?php echo escAttr( $app['slug'] ); ?>">
						<input type="hidden" name="app_status" value="trash">
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn">Move to trash</button>
					</form>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</section>
<?php endif; ?>