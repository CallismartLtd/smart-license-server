<?php
/**
 * The REST API documentation page.
 *
 * @author Callistus Nwachukwu
 * @var array<string, string> $categories Category key => label, every category.
 * @var string|null           $category   The category shown; null for all.
 * @var array<string, array<string, array<int, array>>> $versions Version => category => route descriptors.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="page-heading">
	<h1><?php echo escHtml( $view->get_title() ); ?></h1>
	<p><?php echo escHtml( $view->get_description() ); ?></p>
</div>

<?php if ( [] !== $categories ) : ?>
	<nav class="pills" aria-label="Categories">
		<a href="<?php echo escUrl( $this->url( 'documentation' )->url() ); ?>"<?php echo null === $category ? ' aria-current="page"' : ''; ?>>All</a>
		<?php foreach ( $categories as $key => $label ) : ?>
			<a href="<?php echo escUrl( $this->url( 'documentation/' . $key . '/' )->url() ); ?>"<?php echo $key === $category ? ' aria-current="page"' : ''; ?>><?php echo escHtml( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if ( [] === $versions ) : ?>
	<p class="muted">No routes are registered.</p>
<?php endif; ?>

<?php foreach ( $versions as $namespace => $groups ) : ?>
	<section class="docs-version">
		<h2><?php echo escHtml( $namespace ); ?></h2>

		<?php foreach ( $groups as $key => $routes ) : ?>
			<div class="docs-category">
				<h3><?php echo escHtml( $categories[ $key ] ?? $key ); ?></h3>

				<?php foreach ( $routes as $route ) : ?>
					<?php $this->partial( 'route', [ 'route' => $route ] ); ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</section>
<?php endforeach; ?>