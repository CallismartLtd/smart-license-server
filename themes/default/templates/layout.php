<?php
/**
 * The document of every page.
 *
 * @author Callistus Nwachukwu
 * @var string $content The page's HTML.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php $this->head(); ?>
	<link rel="stylesheet" href="<?php echo escUrl( $this->asset_url( 'css/theme.css' )->url() ); ?>">
</head>
<body>
	<div class="glow-bg"></div>

	<main class="container">
		<?php $this->partial( 'navigation' ); ?>

		<?php echo $content; // phpcs:ignore -- rendered page. ?>
	</main>

	<?php $this->partial( 'footer' ); ?>

	<script src="<?php echo escUrl( $this->asset_url( 'js/theme.js' )->url() ); ?>" defer></script>
	<?php $this->footer(); ?>
</body>
</html>