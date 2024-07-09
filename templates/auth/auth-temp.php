<?php
/**
 * Authorization Template file.
 *
 * This template can be overridden by copying it to yourtheme/smliser/auth/auth-header.php.
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

defined( 'ABSPATH' ) || exit; global $wp;
?>

<?php do_action( 'smliser_auth_page_header' );?>

<?php var_dump( $_GET ); ?>
<?php do_action( 'smliser_auth_page_footer' );?>
