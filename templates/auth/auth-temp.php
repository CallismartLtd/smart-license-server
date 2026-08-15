<?php
/**
 * OAUTH Authorization Template file.
 *
 * This template can be overridden by copying it to yourtheme/smliser/auth/auth-temp.php.
 *
 * HOWEVER, on occasion Smart License Server will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @package Smliser\Templates\Auth
 * @version 0.2.0
 * @var string[] $sanitized_params
 */

defined( 'SMLISER_ROOT' ) || exit;
?>

    <?php do_action( 'smliser_auth_page_header' );?>
        
        <h2><?php 
            /* Translators: %s App name. */
            printf( escHtml( '%s would like to connect to your plugin repository' ), escHtml( ucwords( $sanitized_params['app_name'] ) ) );
        ?></h2>

        <p><?php 
            /* Translators: %1$s App name, %2$s scope. */
            printf( escHtml( 'This will give "%1$s" a "%2$s" access which will enable it to:' ), escHtml( ucwords( $sanitized_params['app_name'] ) ), escHtml( $permission ) ); ?>
        </p>

        <ul>
            <li><?php
                /** translators: %s permissions. */
                printf( escHtml( '%s plugins in the repository' ), escHtml( $verb ) ); ?>
            </li>

            <li><?php 
            /** Translators: %s permissions. */
                printf( escHtml( '%s licenses for premium plugins' ), escHtml( $verb ) ); ?>
            </li>
        </ul>
        <p>Authorizing this action will share credentials with <?php 
        
        /** Translators: %s Callback url */
            printf( escHtml( '"%s". Deny this request if you do not trust this app.' ), escHtml( $sanitized_params['callback_url'] ) );?>
            
        </p>
        <div class="smliser-auth-avater">
        <?php echo wp_kses_post( get_avatar( get_current_user_id(), 70 ) );?>

            <p><?php
                /* Translators: %s display name. */
                printf( escHtml( 'Logged in as %s' ), escHtml( wp_get_current_user()->display_name ) );
                ?>
                <a href="<?php echo escUrl( wp_logout_url( url( 'smliser-auth/v1/authorize/' )->add_query_params( $_GET ) ) ) ?>">Logout</a>
            </p>
        </div>

        <form method="post" action="<?php echo escUrl( adminUrl( 'admin-post.php' ) );?>">
            <?php wp_nonce_field( 'smliser_consent_nonce', 'smliser_consent_nonce' ); ?>
            <input type="hidden" name="action" value="smliser_authorize_app">
            <input type="hidden" name="app_name" value="<?php echo escAttr( $sanitized_params['app_name'] ); ?>">
            <input type="hidden" name="scope" value="<?php echo escAttr( $sanitized_params['scope'] ); ?>">
            <input type="hidden" name="return_url" value="<?php echo escUrl( $sanitized_params['return_url'] ); ?>">
            <input type="hidden" name="callback_url" value="<?php echo escUrl( $sanitized_params['callback_url'] ); ?>">
            <input type="hidden" name="user_id" value="<?php echo intval( get_current_user_id() ); ?>">
            <p class="smliser-auth-consent_btn-container">
                <button style="background-color: red;" type="submit" name="deny" value="true" class="smliser-auth-consent_btn"><?php echo escHtml( 'Deny' ); ?></button>
                <button style="background-color: blue;" type="submit" name="authorize" value="true" class="smliser-auth-consent_btn"><?php echo escHtml( 'Authorize' ); ?></button>
            </p>
        </form>


    <?php do_action( 'smliser_auth_page_footer' );?>
