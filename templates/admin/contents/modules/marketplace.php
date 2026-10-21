<?php
/**
 * The admin Modules > Marketplace page template.
 *
 * What the update server publishes, read from its repository REST
 * endpoints, as module cards. Section "themes" is the only one for now;
 * extensions will add theirs. modules.js posts Install to
 * admin/json/<kind>s/install and reloads the page.
 *
 * @author Callistus Nwachukwu
 * @var string                     $section    A ModulesPage::MARKETPLACE_SECTIONS key.
 * @var string                     $search     The search term.
 * @var list<array<string, mixed>> $cards      Module cards (ModulesPage::card()).
 * @var array{total: int, page: int, limit: int, total_pages: int}|null $pagination
 * @var string|null                $error      Why the marketplace could not be read.
 * @var string                     $page_url   This section's URL.
 * @var array<string, mixed>       $menu_args  The content header.
 * @var SmartLicenseServer\Admin\ContentHandlers\ModulesPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Admin\ContentHandlers\ModulesPage;
use SmartLicenseServer\Core\URL;

/**
 * This section's URL for another page of results.
 *
 * @param int $paged
 * @return string
 */
$paged_url = static function ( int $paged ) use ( $page_url, $search ) : string {
	$query = array_filter( [ 'search' => $search, 'paged' => $paged > 1 ? $paged : null ] );

	return URL::from( $page_url )->add_query_params( $query )->url();
};
?>
<div class="smliser-admin-page smliser-modules" id="smliser-modules" data-module-kind="theme">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-modules-toolbar">
		<form class="smliser-modules-search" method="get" action="<?php echo escUrl( $page_url ); ?>" role="search">
			<input type="hidden" name="<?php echo escAttr( ModulesPage::SECTION_VAR ); ?>" value="<?php echo escAttr( $section ); ?>">
			<input type="search" name="search" class="smliser-form-input" value="<?php echo escAttr( $search ); ?>" placeholder="Search by the start of a theme slug" aria-label="Search the marketplace">
			<button type="submit" class="smliser-btn smliser-btn-glass"><i class="ti ti-search"></i> Search</button>
		</form>
		<p class="smliser-section-description">
			<i class="ti ti-shield-check"></i>
			Published by the update server and always signed: every package is verified before it is installed.
		</p>
	</div>

	<?php if ( null !== $error ) : ?>
		<p class="smliser-modules-empty"><i class="ti ti-cloud-off"></i> <?php echo escHtml( sprintf( 'The marketplace could not be read: %s', $error ) ); ?></p>
	<?php elseif ( [] === $cards ) : ?>
		<p class="smliser-modules-empty"><i class="ti ti-mood-empty"></i> <?php echo escHtml( '' === $search ? 'The marketplace publishes no themes yet.' : sprintf( 'No theme slug starts with "%s".', $search ) ); ?></p>
	<?php else : ?>
		<div class="smliser-module-grid">
			<?php foreach ( $cards as $card ) : ?>
				<?php include __DIR__ . '/card.php'; ?>
			<?php endforeach; ?>
		</div>

		<?php if ( null !== $pagination && $pagination['total_pages'] > 1 ) : ?>
			<nav class="smliser-modules-pagination" aria-label="Pages">
				<?php if ( $pagination['page'] > 1 ) : ?>
					<a class="smliser-btn smliser-btn-glass" href="<?php echo escUrl( $paged_url( $pagination['page'] - 1 ) ); ?>"><i class="ti ti-chevron-left"></i> Previous</a>
				<?php endif; ?>
				<span><?php echo escHtml( sprintf( 'Page %d of %d', $pagination['page'], $pagination['total_pages'] ) ); ?></span>
				<?php if ( $pagination['page'] < $pagination['total_pages'] ) : ?>
					<a class="smliser-btn smliser-btn-glass" href="<?php echo escUrl( $paged_url( $pagination['page'] + 1 ) ); ?>">Next <i class="ti ti-chevron-right"></i></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>