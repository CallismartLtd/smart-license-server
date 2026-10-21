<?php
/**
 * The repository: every app type, or search results across them.
 *
 * @author Callistus Nwachukwu
 * @var list<array{slug: string, label: string, plural: string, url: string, count: int}> $types
 * @var string           $search     The search term; empty for none.
 * @var list<array>      $sections   Without a search: per app type with apps, array{type: array, apps: list<array>}.
 * @var list<array>      $apps       With a search: the cards found.
 * @var array|null       $pagination With a search: the pagination.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="page-heading">
	<h1><?php echo escHtml( $view->get_title() ); ?></h1>
	<p><?php echo escHtml( $view->get_description() ); ?></p>
</div>

<?php $this->partial( 'repository.toolbar', [ 'types' => $types, 'search' => $search, 'current' => null ] ); ?>

<?php if ( '' !== $search ) : ?>
	<?php $this->partial( 'repository.cards', [ 'apps' => $apps, 'empty' => 'Nothing matches your search.' ] ); ?>
	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php elseif ( [] === $sections ) : ?>
	<p class="muted">Nothing is hosted here yet.</p>
<?php else : ?>
	<?php foreach ( $sections as $section ) : ?>
		<section class="repo-section">
			<div class="repo-section-heading">
				<h2><?php echo escHtml( $section['type']['plural'] ); ?> <span class="repo-count"><?php echo escHtml( (string) $section['type']['count'] ); ?></span></h2>

				<?php if ( $section['type']['count'] > count( $section['apps'] ) ) : ?>
					<a class="btn btn-small" href="<?php echo escUrl( $section['type']['url'] ); ?>"><?php echo escHtml( sprintf( 'All %s', $section['type']['plural'] ) ); ?></a>
				<?php endif; ?>
			</div>

			<?php $this->partial( 'repository.cards', [ 'apps' => $section['apps'], 'empty' => '' ] ); ?>
		</section>
	<?php endforeach; ?>
<?php endif; ?>