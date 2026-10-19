<?php
/**
 * The 404 page: no route matches the path.
 *
 * @author Callistus Nwachukwu
 * @var string $path The requested path.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'page-header',
	[
		'heading'     => 'Page Not Found',
		'description' => 'The resource you requested does not exist.',
	]
);
?>
<section class="content">
	<div class="message">
		<div class="card">
			<h3>Page Not Found</h3>
			<p>The requested resource could not be found. Check the URL and try again.</p>
			<div class="code-block"><code><?php echo escHtml( $path ); ?></code></div>
		</div>
	</div>
</section>