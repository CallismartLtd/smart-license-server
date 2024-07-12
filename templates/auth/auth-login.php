<?php
/**
 * Auth Footer
 *
 * This template can be overridden by copying it to yourtheme/smliser/auth/auth-login.php.
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

defined( 'ABSPATH' ) || exit;
?>

<?php do_action( 'smliser_auth_page_header' );?>

    <h2><?php 
        /* Translators: %s App name. */
        printf( esc_html__( '%s would like to connect to your plugin repository', 'smliser' ), esc_html( ucwords( $sanitized_params['app_name'] ) ) );
    ?></h2>

    <p><?php 
        /* Translators: %1$s App name, %2$s scope. */
        printf( esc_html__( 'You must be logged in to approved "%1s" for the "%2s" permission requested.', 'smliser' ), esc_html( ucwords( $sanitized_params['app_name'] ) ), esc_html( $permission ) ); ?>
    </p>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="smliser-oauth-login-form">
    <?php if ( $message = get_transient( 'smliser_form_validation_message' ) ) :?>
        <?php echo wp_kses_post( smliser_form_message( $message ) ) ;?>
    <?php delete_transient( 'smliser_form_validation_message' ); endif;?>

    <input type="hidden" name="action" value="smliser_oauth_login"/>
    <input type="hidden" name="redirect_args" value="<?php echo esc_html( http_build_query( $_GET ) ); ?>"/>
    <p class="smliser-form-row">
            <label for="user-login" class="smliser-oauth-form-label">Username/Email *</label>
            <input type="text" name="user_login" id="user-login" class="smliser-login-input"/>
        </p>

        <p class="smliser-form-row">
            <label for="password" class="smliser-oauth-form-label">Password *</label>
            <input type="password" name="password" id="password" class="smliser-login-input"/>
        </p>
        <div class="smliser-submit-btn-container">
            <button type="submit" name="smliser_login" class"smliser-login-button">Login</button>
        </div>
    </form>

<?php do_action( 'smliser_auth_page_footer' );?>