<?php
/**
 * The admin Modules > Themes page template.
 *
 * Section "installed" (the default) shows every theme as a module card;
 * section "upload" shows the package upload form. modules.js posts the
 * actions to admin/json/themes/* (ThemeManagement) and reloads the page.
 *
 * @author Callistus Nwachukwu
 * @var string                     $section   A ModulesPage::THEME_SECTIONS key.
 * @var list<array<string, mixed>> $cards     Module cards (ModulesPage::card()); empty outside "installed".
 * @var string                     $active    Slug of the theme rendering the pages.
 * @var string                     $recorded  Slug recorded as active.
 * @var array<string, mixed>       $menu_args The content header.
 * @var SmartLicenseServer\Admin\ContentHandlers\ModulesPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

$fallback = $recorded !== $active;
?>
<div class="smliser-admin-page smliser-modules" id="smliser-modules" data-module-kind="theme">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<?php if ( 'upload' === $section ) : ?>
		<p class="smliser-section-description">
			<i class="ti ti-info-circle"></i>
			A theme package is a zip holding one folder named after the theme. Upload its .sha256 and .sha256.sig beside it; PHP is allowed only in templates/, and links, server configuration files and paths outside the theme folder are refused.
		</p>

		<form class="smliser-modules-form" data-module-form="upload" enctype="multipart/form-data">
			<label class="smliser-modules-field">
				<span>Package (.zip)</span>
				<input type="file" name="package" accept=".zip,application/zip" required>
			</label>
			<label class="smliser-modules-field">
				<span>Checksums (.sha256)</span>
				<input type="file" name="checksums" accept=".sha256">
			</label>
			<label class="smliser-modules-field">
				<span>Signature (.sha256.sig)</span>
				<input type="file" name="signature" accept=".sig">
			</label>
			<label class="smliser-modules-check">
				<input type="checkbox" name="replace" value="1">
				<span>Replace the installed theme of the same slug <small>(same or newer version; downgrades are refused)</small></span>
			</label>
			<label class="smliser-modules-check">
				<input type="checkbox" name="trust_unsigned" value="1">
				<span>Trust this package without a valid signature <small>(only for a package you built or received directly: its templates run on every public page)</small></span>
			</label>
			<label class="smliser-modules-check">
				<input type="checkbox" name="activate" value="1">
				<span>Activate it once installed</span>
			</label>
			<div class="smliser-modules-form-footer">
				<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-upload"></i> Upload and install</button>
			</div>
		</form>
	<?php else : ?>
		<div class="smliser-modules-toolbar">
			<p class="smliser-section-description">
				<i class="ti <?php echo $fallback ? 'ti-alert-triangle' : 'ti-info-circle'; ?>"></i>
				<?php if ( $fallback ) : ?>
					<?php echo escHtml( sprintf( 'The active theme "%s" cannot be used, so the default theme renders the public pages. The error log says why.', $recorded ) ); ?>
				<?php else : ?>
					Themes change how the public pages look; the admin dashboard is never themed.
				<?php endif; ?>
			</p>
			<div class="smliser-modules-toolbar-actions">
				<button type="button" class="smliser-btn smliser-btn-glass" data-module-page-action="check"><i class="ti ti-refresh"></i> Check for updates</button>
				<button type="button" class="smliser-btn smliser-btn-glass" data-module-page-action="publish" data-confirm="Publish every installed theme's assets in the public folder again?"><i class="ti ti-world-upload"></i> Republish assets</button>
			</div>
		</div>

		<div class="smliser-module-grid">
			<?php foreach ( $cards as $card ) : ?>
				<?php include __DIR__ . '/card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>