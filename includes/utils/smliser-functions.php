<?php
/**
 * File name smliser-functions.php
 * Utility functions file
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\functions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate ISO8601 compatible Period Time in Seconds
 */
function smliser_wait_period() {
    
    $random_seconds = wp_rand( 60, 600 );

    // Format the random seconds into ISO 8601 duration format
    $wait_duration = 'PT' . $random_seconds . 'S';

    return $wait_duration;
}

/**
 * The License page url function
 * can be useful to get the url to the Liense page in all scenerio
 */
function smliser_license_page() {

    if ( is_admin() ) {
        $url = add_query_arg( array(
            'page' => 'licenses',
        ), admin_url( 'admin.php' ) );
        return $url;
    }
}

/**
 * Not found container
 * 
 * @param string $text Message to show
 */
function smliser_not_found_container( $text ) {
    ob_start();
    ?>
    <div class="smliser-not-found-container">
        <p><?php esc_html_e( $text ) ?> </p>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * Submenu navigation button tab function
 *
 * @param array  $tabs         An associative array of tabs (tab_slug => tab_title).
 * @param string $title        The title of the current submenu page.
 * @param string $page_slug    The admin menu/submenu slug.
 * @param string $current_tab  The current tab parameter for the submenu page.
 * @param string $query_var    The query variable.
 */
function smliser_sub_menu_nav( $tabs, $title, $page_slug, $current_tab, $query_var ) {
	$output  = '<div class="wrap">';
	$output .= '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
	$output .= '<nav class="nav-tab-wrapper">';

	foreach ( $tabs as $tab_slug => $tab_title ) {
		$active_class = ( $current_tab === $tab_slug ) ? 'nav-tab-active' : '';

		if ( '' === $tab_slug ) {
			$output      .= "<a href='" . esc_url( admin_url( 'admin.php?page=' . $page_slug ) ) . "' class='nav-tab $active_class'>$tab_title</a>";

		} else {
			$output      .= "<a href='" . esc_url( add_query_arg( $query_var, $tab_slug, admin_url( 'admin.php?page=' . $page_slug ) ) ) . "' class='nav-tab $active_class'>$tab_title</a>";

		}
	}

	$output .= '</nav>';
	$output .= '</div>';

	return $output;
}


function smliser_is_empty_date( $date_string ) {
    // Trim the date string to remove any surrounding whitespace
    $date_string = trim( $date_string );

    // Check if the date string is empty or equals '0000-00-00'
    return empty( $date_string ) || $date_string === '0000-00-00';
}

function smliser_allowed_html() {
    // Define the allowed HTML tags and attributes.
    $allowed_tags = array(
        'div' => array(
            'class' => array(),
            'style' => array(),
        ),
        'table' => array(
            'class' => array(),
            'style' => array(),
        ),
        'thead' => array(),
        'tbody' => array(),
        'tr' => array(),
        'th' => array(
            'class' => array(),
            'style' => array(),
        ),
        'td' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h1' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h2' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h3' => array(
            'class' => array(),
            'style' => array(),
        ),
        'p' => array(
            'class' => array(),
            'style' => array(),
        ),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'span' => array(
            'class' => array(),
            'style' => array(),
        ),
        'form' => array(
            'action' => array(),
            'method' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
        ),
        'input' => array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'placeholder' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'required' => array(),
            'readonly' => array(),

        ),
        'button' => array(
            'type' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
        ),
        'select' => array(
            'name' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'required' => array(),
            'readonly' => array(),
        ),
        'option' => array(
            'value' => array(),
            'selected' => array(),
        ),
        'textarea' => array(
            'name' => array(),
            'rows' => array(),
            'cols' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'required' => array(),
            'readonly' => array(),
        ),
        'span' => array(
            'class' => array(),
            'title' => array(),
        ),
        'label' => array(
            'name' => array(),
            'id' => array(),
            'class' => array(),
            'title' => array(),
            'data-title' => array(),
        ),    
    
    );

    return $allowed_tags;
}

/**
 * Action url constructor for admin license page
 * 
 * @param string $action Action query variable for the page.
 * @param int $license_id   The ID of the license. 
 */
function smliser_lisense_admin_action_page( $action = 'add-new', $license_id = '' ) {
    if ( 'edit' === $action || 'view' === $action ) {
        $url = add_query_arg( array(
            'action'        => $action,
            'license_id'    => $license_id,
        ), smliser_license_page() );
    } else {
        $url = add_query_arg( array(
            'action'    => $action,
        ), smliser_license_page() );
    }


    return $url;
}

/**
 * Form validation message intepreter.
 * 
 * @param mixed     $text   The message to show.
 * @return string   $notice Formatted Notice to show 
 */
function smliser_form_message( $texts ) {
    $notice = '<div class="smliser-form-notice-container">';

    if ( is_array( $texts ) ) {
        $count = 1;
        foreach ( $texts as $text ) {
            $notice .= '<p>' . $count . ' ' . $text . '</p>';
            $count++;
        }
    } else {
        $notice .= '<p>' . $texts . '</p>';

    }
    $notice .= '</div>';

    return $notice;

}

function get_client_ip() {
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );

    foreach ( $ip_keys as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            // In case of multiple IPs, take the first one (usually the client IP)
            $ip_list = explode( ',', $_SERVER[ $key ] );
            foreach ( $ip_list as $ip ) {
                $ip = trim( $ip ); // Remove any extra spaces
                if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
    }
    return false; // Return false if no valid IP is found
}
