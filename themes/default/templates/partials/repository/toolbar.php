<?php
/**
 * The repository's search form and app type navigation.
 *
 * Searches the current type's page, or the whole repository.
 *
 * @author Callistus Nwachukwu
 * @var list<array{slug: string, label: string, plural: string, url: string, count: int}> $types
 * @var string     $search  The search term.
 * @var array|null $current The current type's summary; null on the repository page.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$all    = $this->urls()->repository_url()->url();
$action = null === $current ? $all : $current['url'];
$label  = null === $current ? 'Search the repository' : sprintf( 'Search %s', $current['plural'] );
?>
<div class="repo-toolbar">
	<form class="repo-search" role="search" method="get" action="<?php echo escUrl( $action ); ?>">
		<label class="screen-reader-text" for="repo-search"><?php echo escHtml( $label ); ?></label>
		<input id="repo-search" type="search" name="search" value="<?php echo escAttr( $search ); ?>" placeholder="<?php echo escAttr( $label ); ?>">
		<button class="btn" type="submit">Search</button>
	</form>

	<nav class="pills" aria-label="App types">
		<a href="<?php echo escUrl( $all ); ?>"<?php echo null === $current ? ' aria-current="page"' : ''; ?>>All</a>
		<?php foreach ( $types as $type ) : ?>
			<a href="<?php echo escUrl( $type['url'] ); ?>"<?php echo null !== $current && $type['slug'] === $current['slug'] ? ' aria-current="page"' : ''; ?>>
				<?php echo escHtml( $type['plural'] ); ?> <span class="repo-count"><?php echo escHtml( (string) $type['count'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
</div>