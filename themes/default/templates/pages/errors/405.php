<?php
/**
 * The 405 page: the path exists, but not for this method.
 *
 * @author Callistus Nwachukwu
 * @var string   $method  The request method.
 * @var string[] $allowed The methods the path accepts.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'page-header',
	[
		'heading'     => 'Method Not Allowed',
		'description' => 'The requested resource does not support this HTTP method.',
	]
);
?>
<section class="content">
	<div class="message">
		<div class="card">
			<h3>Method Not Allowed</h3>
			<p><?php echo escHtml( sprintf( '%s is not supported here. Allowed: %s.', $method, implode( ', ', $allowed ) ) ); ?></p>
		</div>
	</div>
</section>