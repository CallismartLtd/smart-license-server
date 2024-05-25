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
        ),
        'input' => array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'placeholder' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
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
        ),
        // Add more tags and attributes as needed.
    );

    // Get the output of the license_page method.
    $output = license_page();

    // Sanitize the output using wp_kses.
    $sanitized_output = wp_kses( $output, $allowed_tags );

    // Return the sanitized output.
    return $sanitized_output;
}