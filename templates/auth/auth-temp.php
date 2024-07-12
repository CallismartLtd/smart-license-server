<?php
/**
 * OAUTH Authorization Template file.
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

defined( 'ABSPATH' ) || exit;

// Define the default values for the required parameters.
$default_params = array(
    'scope'         => '',
    'app_name'      => '',
    'return_url'    => '',
    'callback_url'  => '',
    'user_id'       => ''
);

// Filter $_GET to include only expected keys.
$filtered_get = array_intersect_key( $_GET, $default_params );

// Merge the default values with the filtered $_GET parameters.
$merged_params = array_merge( $default_params, $filtered_get );

// Sanitize all parameters.
$sanitized_params = array_map( function( $value ) {
    return sanitize_text_field( wp_unslash( $value ) );
}, $merged_params );

// Check for missing required parameters and use wp_die() to show a message.
foreach ( $default_params as $key => $value ) {
    if ( empty( $sanitized_params[$key] ) ) {
        wp_die( sprintf( 'Missing required parameter: %s', esc_html( $key ) ) );
    }
}

$permission = 'Read';
$verb       = 'View';
if ( 'read_write' === $sanitized_params['scope'] ) {
    $permission = 'Read/Write';
    $verb       = 'View and manage';
} elseif( 'write' === $sanitized_params['scope'] ) {
    $permission = 'Write';
    $verb       = 'Create';
} elseif ( 'read' !== $sanitized_params['scope'] && 'read_write' !== $sanitized_params['scope'] && 'write' !== $sanitized_params['scope'] ) {
    wp_die( 'Invalid scope: "' . esc_html( $sanitized_params['scope'] ) . '".' );
}
?>

    <?php do_action( 'smliser_auth_page_header' );?>
        
        <h2><?php 
            /* Translators: %s App name. */
            printf( esc_html__( '%s would like to connect to your plugin repository', 'smliser' ), esc_html( ucwords( $sanitized_params['app_name'] ) ) );
        ?></h2>

        <p><?php 
            /* Translators: %1$s App name, %2$s scope. */
            printf( esc_html__( 'This will give "%1$s" a "%2$s" access which will enable it to:', 'smliser' ), esc_html( ucwords( $sanitized_params['app_name'] ) ), esc_html( $permission ) ); ?>
        </p>

        <ul>
            <li><?php
                /** translators: %s permissions. */
                printf( esc_html__( '%s plugins in the repository', 'smliser' ), esc_html( $verb ) ); ?>
            </li>

            <li><?php 
            /** Translators: %s permissions. */
                printf( esc_html__( '%s licenses for premium plugins', 'smliser' ), esc_html( $verb ) ); ?>
            </li>
        </ul>
        <p>Authorizing this action will share credentials with <?php 
        
        /** Translators: %s Return url */
            printf( esc_html__( '"%s". Deny this request if you do not trust this app.', 'smliser' ), esc_html( $sanitized_params['return_url'] ) );?>
            
        </p>
        <div class="smliser-auth-avater">
        <?php echo wp_kses_post( get_avatar( get_current_user_id(), 70 ) );?>

            <p><?php
                /* Translators: %s display name. */
                printf( esc_html__( 'Logged in as %s', 'smliser' ), esc_html( wp_get_current_user()->display_name ) );
                ?>
                <a href="<?php echo esc_url( wp_logout_url( site_url( 'smliser-auth/v1/authorize/?' . http_build_query( $_GET ) ) ) ) ?>">Logout</a>
            </p>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) );?>">
            <?php wp_nonce_field( 'smliser_consent_nonce', 'smliser_consent_nonce' ); ?>
            <input type="hidden" name="action" value="smliser_authorize_app">
            <input type="hidden" name="app_name" value="<?php echo esc_attr( $sanitized_params['app_name'] ); ?>">
            <input type="hidden" name="scope" value="<?php echo esc_attr( $sanitized_params['scope'] ); ?>">
            <input type="hidden" name="return_url" value="<?php echo esc_url( $sanitized_params['return_url'] ); ?>">
            <input type="hidden" name="callback_url" value="<?php echo esc_url( $sanitized_params['callback_url'] ); ?>">
            <input type="hidden" name="user_id" value="<?php echo absint( get_current_user_id() ); ?>">
            <p class="smliser-auth-consent_btn-container">
                <button style="background-color: red;" type="submit" name="deny" value="true" class="smliser-auth-consent_btn"><?php esc_html_e( 'Deny', 'smliser' ); ?></button>
                <button style="background-color: blue;" type="submit" name="authorize" value="true" class="smliser-auth-consent_btn"><?php esc_html_e( 'Authorize', 'smliser' ); ?></button>
            </p>
        </form>


    <?php do_action( 'smliser_auth_page_footer' );?>
