<?php
/**
 * The admin Modules > Marketplace page template.
 *
 * The page is a shell: it never waits for the update server. modules.js
 * reads what the update server publishes from admin/json/<kind>s/marketplace
 * (for the search term, the page and the section: Extensions or Themes) and
 * draws it as module cards into [data-marketplace-results], mirroring
 * card.php. Switching section, searching and paging do not reload the page;
 * modules.js keeps the address in step (?section=, ?search=, ?paged=).
 *
 * @author Callistus Nwachukwu
 * @var string                     $section   A ModulesPage::MARKETPLACE_SECTIONS key.
 * @var string                     $kind      The kind of module the section lists: "theme" or "extension".
 * @var string                     $search    The search term.
 * @var int                        $paged     The page number, from 1.
 * @var string                     $page_url  This section's URL.
 * @var array<string, mixed>       $menu_args The content header (its section links carry data-section).
 * @var SmartLicenseServer\Admin\ContentHandlers\ModulesPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Admin\ContentHandlers\ModulesPage;

?>
<div
	class="smliser-admin-page smliser-modules"
	id="smliser-modules"
	data-module-kind="<?php echo escAttr( $kind ); ?>"
	data-marketplace
	data-section="<?php echo escAttr( $section ); ?>"
	data-paged="<?php echo escAttr( (string) $paged ); ?>"
	data-kinds="<?php echo escAttr( (string) json_encode( ModulesPage::MARKETPLACE_KINDS ) ); ?>"
>
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-modules-toolbar">
		<form class="smliser-modules-search" method="get" action="<?php echo escUrl( $page_url ); ?>" role="search" data-marketplace-search>
			<input type="hidden" name="<?php echo escAttr( ModulesPage::SECTION_VAR ); ?>" value="<?php echo escAttr( $section ); ?>">
			<input type="search" name="search" class="smliser-form-input" value="<?php echo escAttr( $search ); ?>" placeholder="<?php echo escAttr( sprintf( 'Search by the start of a %s slug', $kind ) ); ?>" aria-label="Search the marketplace">
			<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-search"></i> Search</button>
		</form>
		<p class="smliser-section-description">
			<i class="ti ti-shield-check"></i>
			Published by the update server and always signed: every package is verified before it is installed.
		</p>
	</div>

	<div data-marketplace-results aria-live="polite">
		<noscript>
			<p class="smliser-modules-empty"><i class="ti ti-brand-javascript"></i> JavaScript is needed to browse the marketplace.</p>
		</noscript>
	</div>
</div>