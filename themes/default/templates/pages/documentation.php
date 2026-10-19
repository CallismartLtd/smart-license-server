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

$docs_url = $this->url( 'documentation' );

$this->partial(
	'page-header',
	[
		'heading'     => $view->get_title(),
		'description' => $view->get_description(),
	]
);
?>
<section class="content">
	<nav class="api-breadcrumb">
		<?php if ( null === $category ) : ?>
			<span>Documentation</span>
		<?php else : ?>
			<a href="<?php echo escUrl( $docs_url->url() ); ?>">Documentation</a>
			<span class="api-breadcrumb-sep">/</span>
			<span><?php echo escHtml( $categories[ $category ] ); ?></span>
		<?php endif; ?>
	</nav>

	<?php if ( [] !== $categories ) : ?>
		<nav class="api-category-nav">
			<a href="<?php echo escUrl( $docs_url->url() ); ?>"<?php echo null === $category ? ' class="active"' : ''; ?>>All</a>
			<?php foreach ( $categories as $key => $label ) : ?>
				<a href="<?php echo escUrl( $this->url( 'documentation/' . $key . '/' )->url() ); ?>"<?php echo $key === $category ? ' class="active"' : ''; ?>><?php echo escHtml( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( [] === $versions ) : ?>
		<div class="api-empty-state">No routes are currently registered.</div>
	<?php endif; ?>

	<?php foreach ( $versions as $namespace => $groups ) : ?>
		<section class="api-version-section">
			<h2><?php echo escHtml( strtoupper( $namespace ) ); ?></h2>

			<?php foreach ( $groups as $key => $routes ) : ?>
				<div class="api-category-section">
					<h3><?php echo escHtml( $categories[ $key ] ?? $key ); ?></h3>
					<div class="grid">
						<?php foreach ( $routes as $route ) : ?>
							<?php $this->partial( 'route-card', [ 'route' => $route ] ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>
</section>