<?php
/**
 * The home page.
 *
 * @author Callistus Nwachukwu
 * @var string $app_version The running app version.
 * @var string $php_version The running PHP version.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'page-header',
	[
		'badge'       => 'Smart License Server Engine Active',
		'heading'     => SMLISER_APP_NAME,
		'description' => $view->get_description(),
	]
);
?>
<section class="content">
	<div class="grid">
		<div class="card">
			<h3>CLI Control Engine</h3>
			<p>Manage applications, licenses, and environment setup directly from your terminal using the global runner.</p>
			<div class="code-block"><code>smliser status</code></div>
		</div>

		<div class="card">
			<h3>DBPrism Abstraction</h3>
			<p>Multi-adapter database abstraction layer supporting MySQL, MariaDB, PostgreSQL, and SQLite seamlessly.</p>
			<div class="code-block"><code>callismart/dbprism</code></div>
		</div>

		<div class="card">
			<h3>Runtime Environment</h3>
			<p>Strict PHP 8.4+ execution engine utilizing Callismart HTTP/DTO and CommonMark Markdown parsing.</p>
			<div class="code-block"><code>PHP v<?php echo escHtml( $php_version ); ?> · Smart License Server v<?php echo escHtml( $app_version ); ?></code></div>
		</div>
	</div>
</section>