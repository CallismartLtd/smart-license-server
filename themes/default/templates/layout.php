<?php
/**
 * The document of every page.
 *
 * @author Callistus Nwachukwu
 * @var string $content The page's HTML.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

// The theme's own assets: enqueued before head(), which prints them with the page's.
$this->enqueue_style( 'style', 'css/theme.css' );
$this->enqueue_script( 'script', 'js/theme.js' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<?php $this->head(); ?>
</head>
<body>
	<?php if ( '' === $view->get_frame() ) : // A frame brings its own navigation. ?>
		<?php $this->partial( 'header' ); ?>
	<?php endif; ?>

	<main class="site-main">
		<?php echo $content; // phpcs:ignore -- rendered page. ?>
	</main>

	<?php $this->partial( 'footer' ); ?>

	<?php $this->footer(); ?>
</body>
</html>