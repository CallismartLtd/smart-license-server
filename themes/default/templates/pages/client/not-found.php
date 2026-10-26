<?php
/**
 * Something the page looked for is not there, or not the user's.
 *
 * @author Callistus Nwachukwu
 * @var string $message    What is missing, and what to do.
 * @var string $back_url   Where to go instead.
 * @var string $back_label The link's text.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="cell">
	<h3>Not found</h3>
	<p><?php echo escHtml( $message ); ?></p>
	<a class="btn" href="<?php echo escUrl( $back_url ); ?>"><?php echo escHtml( $back_label ); ?></a>
</div>