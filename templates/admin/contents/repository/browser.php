<?php
/**
 * Repository browser page.
 *
 * A shell the repository browser script fills from the
 * admin/json/repository-browser endpoint.
 *
 * @author Callistus Nwachukwu
 *
 * @see \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage::files_page()
 * @var array $menu_args
 * @var string $type
 * @var string $slug
 */

defined( 'SMLISER_ROOT' ) || exit;
?>

<div class="application-uploader-page smliser-repo-browser-page">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div
		id="smliser-repo-browser"
		class="smliser-repo-browser"
		data-app-type="<?php echo escAttr( $type ); ?>"
		data-app-slug="<?php echo escAttr( $slug ); ?>"
	>
		<div class="smliser-repo-browser__bar">
			<nav class="smliser-repo-browser__path" aria-label="Location"></nav>
			<div class="smliser-repo-browser__app-actions"></div>
		</div>

		<div class="smliser-repo-browser__body">
			<div class="smliser-repo-browser__listing" aria-live="polite"></div>
			<aside class="smliser-repo-browser__preview" aria-label="Selected file" hidden></aside>
		</div>
	</div>
</div>