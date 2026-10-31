<?php
/**
 * The admin Modules > Extensions page template.
 *
 * Section "installed" (the default) shows every installed extension as a
 * module card; section "upload" shows the package upload form. modules.js
 * posts the actions to admin/json/extensions/* (ExtensionManagement) and
 * reloads the page.
 *
 * @author Callistus Nwachukwu
 * @var string                     $section   A ModulesPage::EXTENSION_SECTIONS key.
 * @var list<array<string, mixed>> $cards     Module cards (ModulesPage::card()); empty outside "installed".
 * @var array<string, mixed>       $menu_args The content header.
 * @var SmartLicenseServer\Admin\ContentHandlers\ModulesPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

?>
<div class="smliser-admin-page smliser-modules" id="smliser-modules" data-module-kind="extension">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<?php if ( 'upload' === $section ) :
		$form_fields	= [
			[
				'label'	=> 'Package (.zip)',
				'help'	=> 'A zip holding one folder named after the extension (extension.json, src/ and an optional assets/). PHP is allowed anywhere except assets/; links, server configuration files and paths outside the extension folder are refused.',
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
				'help'	=> 'Replace the installed extension of the same slug (same or newer version; downgrades are refused)',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'replace',
					'value'	=> '0'
				]
			],
			[
				'label'	=> 'Trust Package',
				'help'	=> 'Trust this package without a valid signature (only for a package you built or received directly: once enabled, its PHP runs on every request)',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'trust_unsigned',
					'value'	=> '0'
				]
			],
			[
				'label'	=> 'Enable it once installed',
				'help'	=> 'Enable this extension after installing? It loads from the next request on.',
				'input'	=> [
					'type'	=> 'checkbox',
					'name'	=> 'enable',
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
				<i class="ti ti-info-circle"></i>
				Extensions add features to this installation. An enabled extension's PHP runs on every request, so enable only extensions you trust; changes apply from the next request.
			</p>
			<div class="smliser-modules-toolbar-actions">
				<button type="button" class="smliser-btn smliser-btn-glass" data-module-page-action="check"><i class="ti ti-refresh"></i> Check for updates</button>
				<button type="button" class="smliser-btn smliser-btn-glass" data-module-page-action="publish" data-confirm="Publish every installed extension's assets in the public folder again?"><i class="ti ti-world-upload"></i> Republish assets</button>
			</div>
		</div>

		<?php if ( [] === $cards ) : ?>
			<p class="smliser-modules-empty"><i class="ti ti-mood-empty"></i> No extension is installed. Upload a package, or install one from the marketplace.</p>
		<?php else : ?>
			<div class="smliser-module-grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php include __DIR__ . '/card.php'; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>