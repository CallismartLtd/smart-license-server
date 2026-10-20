<?php
/**
 * The admin Appearance > Themes page template.
 *
 * Lists the site themes of the public pages. themes.js posts the actions
 * to admin/json/themes/* (ThemeManagement) and reloads the page after each,
 * so this template stays the single source of what is shown.
 *
 * @author Callistus Nwachukwu
 * @var list<array<string, mixed>> $themes   AppearancePage::theme_rows().
 * @var string                     $active   Slug of the theme rendering the pages.
 * @var string                     $recorded Slug recorded as active.
 * @var SmartLicenseServer\Admin\ContentHandlers\AppearancePage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Environments\Application\Theming\ThemeInstaller;

$menu_args = $page_handler->get_top_menu_args( $request );
$current   = null;

foreach ( $themes as $row ) {
	if ( $row['active'] ) {
		$current = $row;
	}
}

$fallback = $recorded !== $active;
$chevron  = '<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>';
?>
<div class="smliser-admin-page" id="smliser-themes">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-health-overview smliser-health-tone--<?php echo $fallback ? 'warning' : 'pass'; ?>">
		<span class="smliser-health-overview-icon"><i class="ti <?php echo $fallback ? 'ti-alert-triangle' : 'ti-palette'; ?>"></i></span>
		<div class="smliser-health-overview-text">
			<strong class="smliser-health-overview-title"><?php echo escHtml( sprintf( '%s renders the public pages', $current['name'] ?? $active ) ); ?></strong>
			<p class="smliser-health-overview-message">
				<?php if ( $fallback ) : ?>
					<?php echo escHtml( sprintf( 'The active theme "%s" cannot be used, so the default theme renders the pages. The error log says why.', $recorded ) ); ?>
				<?php else : ?>
					Themes change how the public pages look: the home page, the repository, the sign-in pages and the client dashboard. The admin dashboard is never themed.
				<?php endif; ?>
			</p>
		</div>
	</div>

	<div class="smliser-tools-actions">
		<button type="button" class="smliser-btn smliser-btn-glass" data-theme-action="check">
			<i class="ti ti-refresh"></i> Check for updates
		</button>
		<button type="button" class="smliser-btn smliser-btn-glass" data-theme-action="publish" data-confirm="Publish every installed theme's assets in the public folder again?">
			<i class="ti ti-world-upload"></i> Republish assets
		</button>
		<span class="smliser-section-description">
			<i class="ti ti-info-circle"></i>
			Marketplace themes come from the update server and are always signed. Uploaded packages must be signed unless you trust them explicitly.
		</span>
	</div>

	<div class="smliser-table-wrapper">
		<table class="smliser-table widefat striped">
			<thead>
				<tr>
					<th>Theme</th>
					<th>Version</th>
					<th>Source</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $themes as $row ) : ?>
					<tr data-theme-row="<?php echo escAttr( $row['slug'] ); ?>">
						<td>
							<strong><?php echo escHtml( $row['name'] ); ?></strong>
							<code><?php echo escHtml( $row['slug'] ); ?></code>
							<?php if ( '' !== $row['description'] ) : ?>
								<br><span class="smliser-section-description"><?php echo escHtml( $row['description'] ); ?></span>
							<?php endif; ?>
							<?php if ( null !== $row['error'] ) : ?>
								<br><span class="smliser-section-description"><i class="ti ti-alert-triangle"></i> <?php echo escHtml( $row['error'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo escHtml( '' !== $row['version'] ? $row['version'] : '—' ); ?>
							<span data-theme-latest hidden></span>
						</td>
						<td>
							<?php echo escHtml( $row['source'] ); ?>
							<?php if ( null !== $row['signed_by'] ) : ?>
								<br><small><?php echo escHtml( sprintf( 'Signed by the "%s" key', $row['signed_by'] ) ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$status = [];

							if ( $row['active'] ) {
								$status[] = 'Active';
							}

							if ( null !== $row['parent'] ) {
								$status[] = sprintf( 'Child of %s', $row['parent'] );
							}

							if ( [] !== $row['children'] ) {
								$status[] = sprintf( 'Parent of %s', implode( ', ', $row['children'] ) );
							}

							if ( null === $row['error'] && ! $row['supported'] ) {
								$status[] = sprintf( 'Needs app %s', $row['requires'] );
							}

							if ( null !== $row['error'] ) {
								$status[] = 'Broken';
							}
							?>
							<?php echo escHtml( [] === $status ? '—' : implode( ' · ', $status ) ); ?>
						</td>
						<td>
							<?php if ( ! $row['active'] && null === $row['error'] && $row['supported'] ) : ?>
								<button type="button" class="smliser-btn-glass" data-theme-action="activate" data-slug="<?php echo escAttr( $row['slug'] ); ?>">
									<i class="ti ti-player-play"></i> Activate
								</button>
							<?php endif; ?>

							<?php if ( $row['marketplace'] ) : ?>
								<button type="button" class="smliser-btn-glass" data-theme-action="update" data-slug="<?php echo escAttr( $row['slug'] ); ?>" data-confirm="<?php echo escAttr( sprintf( 'Update %s from the marketplace? Its files are replaced with the latest version.', $row['name'] ) ); ?>">
									<i class="ti ti-download"></i> Update
								</button>
							<?php endif; ?>

							<?php if ( ! $row['builtin'] && ! $row['active'] && [] === $row['children'] ) : ?>
								<button type="button" class="smliser-btn-glass" data-theme-action="remove" data-slug="<?php echo escAttr( $row['slug'] ); ?>" data-confirm="<?php echo escAttr( sprintf( 'Remove %s and its files?', $row['name'] ) ); ?>" data-danger="1">
									<i class="ti ti-trash"></i> Remove
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="smliser-diagnostics-grid">

		<?php /* Install from the marketplace. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-building-store"></i></span>
					<span class="smliser-diagnostics-panel-title">Install from the marketplace</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<form data-theme-form="install">
					<ul class="smliser-health-checks">
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header"><span class="smliser-health-check-label">Theme slug</span></span>
								<input type="text" name="slug" class="smliser-form-input" required pattern="[a-z0-9][a-z0-9\-]*" placeholder="aurora">
								<span class="smliser-health-check-message"><?php echo escHtml( sprintf( 'Published on the update server as "%s<slug>". Installing an installed theme updates it.', ThemeInstaller::MARKETPLACE_PREFIX ) ); ?></span>
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header">
									<input type="checkbox" name="activate" value="1">
									<span class="smliser-health-check-label">Activate it once installed</span>
								</span>
							</label>
						</li>
					</ul>
					<div class="smliser-health-async-footer">
						<span class="smliser-health-async-time">The package is downloaded, its signature and checksum verified, then installed.</span>
						<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-download"></i> Install</button>
					</div>
				</form>
			</div>
		</details>

		<?php /* Upload a package. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-upload"></i></span>
					<span class="smliser-diagnostics-panel-title">Upload a theme package</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<form data-theme-form="upload" enctype="multipart/form-data">
					<ul class="smliser-health-checks">
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header"><span class="smliser-health-check-label">Package (.zip)</span></span>
								<input type="file" name="package" accept=".zip,application/zip" required>
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header"><span class="smliser-health-check-label">Checksums (.sha256)</span></span>
								<input type="file" name="checksums" accept=".sha256">
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header"><span class="smliser-health-check-label">Signature (.sha256.sig)</span></span>
								<input type="file" name="signature" accept=".sig">
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header">
									<input type="checkbox" name="replace" value="1">
									<span class="smliser-health-check-label">Replace the installed theme of the same slug</span>
								</span>
								<span class="smliser-health-check-message">With the same or a newer version; downgrades are refused.</span>
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header">
									<input type="checkbox" name="trust_unsigned" value="1">
									<span class="smliser-health-check-label">Trust this package without a valid signature</span>
								</span>
								<span class="smliser-health-check-message">Only for a package you built or received directly: its templates run on every public page.</span>
							</label>
						</li>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header">
									<input type="checkbox" name="activate" value="1">
									<span class="smliser-health-check-label">Activate it once installed</span>
								</span>
							</label>
						</li>
					</ul>
					<div class="smliser-health-async-footer">
						<span class="smliser-health-async-time">PHP is allowed only in templates/; links, server configuration files and paths outside the theme folder are refused.</span>
						<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-upload"></i> Upload and install</button>
					</div>
				</form>
			</div>
		</details>

	</div>
</div>