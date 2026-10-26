<?php
/**
 * Apps: the apps the current owner hosts.
 *
 * @author Callistus Nwachukwu
 * @var list<array{name: string, type: string, slug: string, status: string,
 *     status_label: string, updated: string, url: string}> $apps
 * @var list<array{label: string, url: string, current: bool}> $filters
 * @var string $status The status shown; "" for every status.
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
 * @var string $new_url The upload page; "" when the user cannot upload.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'active'    => 'success',
	'suspended' => 'failure',
	'trash'     => 'failure',
];
?>
<div class="dash-toolbar">
	<p class="dash-lead">The apps you host here, for whoever you act for.</p>
	<?php if ( '' !== $new_url ) : ?>
		<a class="btn btn-accent" href="<?php echo escUrl( $new_url ); ?>">
			<i class="ti ti-upload" aria-hidden="true"></i>
			<span>Upload an app</span>
		</a>
	<?php endif; ?>
</div>

<nav class="dash-filters" aria-label="Filter by status">
	<?php foreach ( $filters as $filter ) : ?>
		<a href="<?php echo escUrl( $filter['url'] ); ?>"<?php echo $filter['current'] ? ' aria-current="page"' : ''; ?>><?php echo escHtml( $filter['label'] ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( [] === $apps ) : ?>
	<div class="cell">
		<h3><?php echo escHtml( '' === $status ? 'No apps yet' : 'No apps here' ); ?></h3>
		<p><?php echo escHtml( '' === $status ? ( '' !== $new_url ? 'Upload a plugin, theme or software package to host it here.' : 'Apps uploaded for this account appear here.' ) : 'No app has this status.' ); ?></p>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $apps as $app ) : ?>
			<li class="record record-compact">
				<div class="record-head">
					<div>
						<h3><a href="<?php echo escUrl( $app['url'] ); ?>"><?php echo escHtml( $app['name'] ); ?></a></h3>
						<span class="muted"><?php echo escHtml( sprintf( '%s · %s · updated %s', $app['type'], $app['slug'], $app['updated'] ) ); ?></span>
					</div>
					<span class="badge" data-tone="<?php echo escAttr( $tones[ $app['status'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $app['status_label'] ); ?></span>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php endif; ?>