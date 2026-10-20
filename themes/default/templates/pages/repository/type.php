<?php
/**
 * The apps of one type.
 *
 * @author Callistus Nwachukwu
 * @var list<array{slug: string, label: string, plural: string, url: string, count: int}> $types
 * @var string      $search     The search term; empty for none.
 * @var array{slug: string, label: string, plural: string, url: string, count: int} $type
 * @var list<array> $apps       The cards of this page.
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$empty = '' === $search
	? sprintf( 'No %s are hosted here yet.', strtolower( $type['plural'] ) )
	: 'Nothing matches your search.';
?>
<div class="page-heading">
	<h1><?php echo escHtml( $view->get_title() ); ?></h1>
	<p><?php echo escHtml( $view->get_description() ); ?></p>
</div>

<?php $this->partial( 'repository.toolbar', [ 'types' => $types, 'search' => $search, 'current' => $type ] ); ?>
<?php $this->partial( 'repository.cards', [ 'apps' => $apps, 'empty' => $empty ] ); ?>
<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>