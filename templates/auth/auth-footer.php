<?php
/**
 * Auth footer
 *
 * This template can be overridden by copying it to yourtheme/smliser/auth/auth-footer.php.
 *
 * HOWEVER, on occasion Smart License Server will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @package Smliser\Templates\Auth
 * @version 1.0.0
 */

defined( 'SMLISER_ABSPATH' ) || exit;
?>

        </div> <!-- .smliser-auth-content -->
        <footer class="smliser-auth-footer">
            <p>&copy;<?php echo esc_html( get_bloginfo( 'name' ) );?> <?php echo esc_html( date( 'Y' ) ); ?> powered by <?php esc_html_e( 'Smart License Server', 'smliser' ); ?></p>
        </footer>
    </body>
</html>
