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

	<?php if ( 'upload' === $section ) :
		$form_fields	= [
			[
				'label'	=> 'Package (.zip)',
				'help'	=> '',
				'input'	=> [
					'type'	=> 'file',
					'name'	=> 'package',
					'attr'	=> [
						'accept'				=> '.zip, application/zip',
						'required'				=> true,
						'data-smliser-uploader'	=> true,
					]
				]
			],
			[
				'label'	=> 'Checksums (.sha256)',
				'help'	=> '',
				'input'	=> [
					'type'	=> 'file',
					'name'	=> 'checksums',
					'attr'	=> [
						'accept'				=> '.sha256',
						'data-smliser-uploader'	=> true
					]
				]
			],
			[
				'label'	=> 'Signature (.sha256.sig)',
				'help'	=> '',
				'input'	=> [
					'type'	=> 'file',
					'name'	=> 'signature',
					'attr'	=> [
						'accept'				=> '.sig',
						'data-smliser-uploader'	=> true
					]
				]
			],
			[
				'label'	=> 'Overwrite',
				'help'	=> 'Replace the installed theme of the same slug (same or newer version; downgrades are refused)',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'replace',
					'value'	=> '0'
				]
			],
			[
				'label'	=> 'Trust Package',
				'help'	=> 'Trust this package without a valid signature (only for a package you built or received directly: its templates run on every public page)',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'trust_unsigned',
					'value'	=> '0'
				]
			],
			[
				'label'	=> 'Activate it once installed',
				'help'	=> 'Activate this package after installing?',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'activate',
					'value'	=> '0'
				]
			],
		];
	
	
	?>
		<form class="smliser-form-container" data-module-form="upload" enctype="multipart/form-data">

			<div class="smliser-flex-form">
				<?php foreach ( $form_fields as $field ) : ?>
					<?php if ( 'checkbox' !== $field['input']['type'] ) : ?>
						<?php smliser_render_input_field( $field ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="smliser-form-row" style="gap: 20px;">
				<?php foreach ( $form_fields as $field ) : ?>
					<?php if ( 'checkbox' === $field['input']['type'] ) : ?>
						<span class="smliser-form-row">
							<?php smliser_render_field_help_icon( $field['help'], $field['input']['name'] ); ?>
							<?php smliser_render_toggle_switch( $field['input'] ); ?>
						</span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<button type="submit" class="smliser-btn smliser-btn-glass">
				<i class="ti ti-upload"></i> Upload and install
			</button>

		</form>
	<?php else : ?>
		<div class="smliser-modules-toolbar">
			<p class="smliser-section-description">
				<i class="ti <?php echo $fallback ? 'ti-alert-triangle' : 'ti-info-circle'; ?>"></i>
				<?php if ( $fallback ) : ?>
					<?php echo escHtml( sprintf( 'The active theme "%s" cannot be used, so the default theme renders the public pages. The error log says why.', $recorded ) ); ?>
				<?php else : ?>
					Themes change how the public pages look.
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