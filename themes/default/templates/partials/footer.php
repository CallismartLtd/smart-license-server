<?php
/**
 * The site footer.
 *
 * @author Callistus Nwachukwu
 */

?>
<footer class="site-footer">
	<div class="site-footer-inner">
		<span><?php echo escHtml( sprintf( '%s v%s', SMLISER_APP_NAME, SMLISER_VER ) ); ?></span>
		<span>&copy; <?php echo escHtml( gmdate( 'Y' ) ); ?> Callismart</span>
	</div>
</footer>