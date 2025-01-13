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
 * Generate ISO8601 compatible Period Time in Seconds for incoming task requests
 *
 * @return string ISO8601 duration string.
 */
function smliser_wait_period() {
    // Initialize the object and get all scheduled tasks
    $obj            = new Smliser_Server();
    $all_tasks      = $obj->scheduled_tasks();
    $default_wait   = 600; // 10 minutes
    $max_wait       = 3600; // 1 hour

    // Default wait period if no tasks are pending
    if ( empty( $all_tasks ) ) {
        $wait_duration = 'PT' . $default_wait . 'S'; 
        delete_transient( 'smliser_task_wait_period' ); // Reset wait period when no tasks are pending
        return $wait_duration;
    }

    $extend_wait = get_transient( 'smliser_task_wait_period' );
    if ( false === $extend_wait ) {
        $extend_wait = $default_wait / 2; // Start with half the default wait time
    }

    // Adjust wait period based on the number of pending tasks
    if ( count( $all_tasks ) <= 2 ) {
        $extend_wait += $default_wait / 2;
        set_transient( 'smliser_task_wait_period', $extend_wait, 10 * MINUTE_IN_SECONDS );
    } elseif ( count( $all_tasks ) <= 5 ) {
        $extend_wait += $default_wait;
        set_transient( 'smliser_task_wait_period', $extend_wait, 20 * MINUTE_IN_SECONDS );
    } else {
        $extend_wait += $default_wait + 300; // Add 5 minutes extra
        set_transient( 'smliser_task_wait_period', $extend_wait, 30 * MINUTE_IN_SECONDS );
    }

    // Ensure the wait period does not exceed the maximum limit
    $total_wait_time = min( $extend_wait, $max_wait );

    // Format the total wait time into ISO 8601 duration format
    $wait_duration = 'PT' . $total_wait_time . 'S';

    return $wait_duration;
}


/**
 * The License page url function
 * can be useful to get the url to the License page in all scenerio
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
 * The Product page url function
 * can be useful to get the url to the product page in all scenerio
 */
function smliser_repo_page() {

    if ( is_admin() ) {
        $url = add_query_arg( array(
            'page' => 'repository',
        ), admin_url( 'admin.php' ) );
        return $url;
    }

    return site_url( get_option( 'smliser_repo_base_perma', 'plugins' ) );
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
        <p><?php echo esc_html( $text ) ?> </p>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * Submenu navigation button tab function
 *
 * @param array  $tabs         An associative array of tabs (tab_slug => tab title).
 * @param string $title        The title of the current submenu page.
 * @param string $page_slug    The admin menu/submenu slug.
 * @param string $current_tab  The current tab parameter for the submenu page.
 * @param string $query_var    The query variable.
 */
function smliser_sub_menu_nav( $tabs, $title, $page_slug, $current_tab, $query_var ) {
	$output  = '<div class="wrap">';
    $dashicon= ( 'Settings' === $title ) ? '<span class="dashicons dashicons-admin-generic"></span>' : '';
	$output .= '<h1 class="wp-heading-inline">' . esc_html( $title ) . ' '. $dashicon . '</h1>';
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

/**
 * Action url constructor for admin license page
 * 
 * @param string $action Action query variable for the page.
 * @param int $license_id   The ID of the license. 
 */
function smliser_license_admin_action_page( $action = 'add-new', $license_id = '' ) {
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
 * Action url constructor for admin product page
 * 
 * @param string $action Action query variable for the page.
 * @param int $license_id   The ID of the license. 
 */
function smliser_repository_admin_action_page( $action = 'add-new', $item_id = '' ) {
    if ( 'edit' === $action || 'view' === $action ) {
        $url = add_query_arg( array(
            'action'        => $action,
            'item_id'       => $item_id,
        ), smliser_repo_page() );
    } else {
        $url = add_query_arg( array(
            'action'    => $action,
        ), smliser_repo_page() );
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

/**
 * Get the client's IP address.
 *
 * @return string|false The client's IP address or false if not found.
 */
function smliser_get_client_ip() {
    $ip_keys = array(
        'HTTP_X_REAL_IP',
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
            // In case of multiple IPs, we take the first one (usually the client IP)
            $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
            foreach ( $ip_list as $ip ) {
                $ip = trim( $ip ); // Remove any extra spaces
                // Validate both IPv4 and IPv6 addresses
                if ( filter_var( $ip, FILTER_VALIDATE_IP, array( FILTER_FLAG_NO_RES_RANGE, FILTER_FLAG_IPV4, FILTER_FLAG_IPV6 ) ) ) {
                    return $ip;
                }
            }
        }
    }

    return 'unresoved_ip';
}



/**
 * Extract the token from the authorization header.
 * 
 * @param WP_REST_Request $request The current request object.
 * @return string|null The extracted token or null if not found.
 */
function smliser_get_auth_token( WP_REST_Request $request ) {
    // Get the authorization header.
    $headers = $request->get_headers();
    
    // Check if the authorization header is set
    if ( isset( $headers['authorization'] ) ) {
        $auth_header = $headers['authorization'][0];
        
        // Extract the token using a regex match for Bearer token.
        if ( preg_match( '/Bearer\s(\S+)/', $auth_header, $matches ) ) {
            return $matches[1]; // Return the token.
        }
    }
    
    // Return null if no valid token is found.
    return null;
}

/**
 * Generate Api key for license interaction.
 * 
 * @param int $item_id    The item ID associated with the license.
 * @param string $license_key License Key associated with the item.
 * @param int $expiry The expiry date for the token.
 */
function smliser_generate_item_token( $item_id = 0, $license_key = '', $expiry = 0 ) {
    $key_props  = array(
        'item_id'       => absint( $item_id ),
        'license_key'   => sanitize_text_field( wp_unslash( $license_key ) ),
        'expiry'        => ! empty( $expiry ) ? absint( $expiry ) : 10 * DAY_IN_SECONDS,
    );

    $token = Smliser_Plugin_Download_Token::insert_helper( $key_props );

    if ( is_wp_error( $token ) || false === $token ) {
        return $token;
    }

    return base64_encode( $token );
}

/**
 * Verify a licensed plugin download token.
 * 
 * @param string $token     The Download token.
 * @param int    $item_id   The ID of the liensed plugin.
 * @return bool True if item ID associated with token matches with provided item ID, false otherwise.
 */
function smliser_verify_item_token( $token, $item_id ) {
    $t_obj  = new Smliser_Plugin_Download_Token();
    $t_data =  $t_obj->get_token( $token );

    if ( ! $t_data ) {
        return false;
    }

    $key_item_id = absint( $t_data->get_item_id() );

    if ( ! empty( $key_item_id ) ) {
        return $key_item_id === absint( $item_id );
    }
    
    return false;
}

/**
 * Validate and decode Base64-encoded token prefixed with "smliser_".
 * 
 * @param string $encoded_token The Base64-encoded token.
 * @return string|null The decoded token if valid, null otherwise.
 */
function smliser_safe_base64_decode( $encoded_token ) {
    // Check the length of the string.
    if ( strlen( $encoded_token ) % 4 !== 0 ) {
        return null;
    }

    // Check the character set.
    if ( preg_match( '/^[A-Za-z0-9+\/=]*$/', $encoded_token ) !== 1 ) {
        return null;
    }

    // Base64 decode the token.
    $decoded_token = base64_decode( $encoded_token, true );
    if ( $decoded_token === false ) {
        return null;
    }

    // Further validate the content.
    if ( ! preg_match( '/^smliser_[a-f0-9]+$/', $decoded_token ) ) {  
        return null;
    }
    

    return $decoded_token;
}

/**
 * Sanitize and normalize a file path to prevent directory traversal attacks.
 *
 * @param string $path The input path.
 * @return string|WP_Error The sanitized and normalized path, or WP_Error on failure.
 */
function sanitize_and_normalize_path( $path ) {
    // Remove any null bytes.
    $path = str_replace( "\0", '', $path );

    // Normalize to forward slashes.
    $path = str_replace( '\\', '/', $path );

    // Split the path into segments.
    $segments = explode( '/', $path );
    $sanitized_segments = array();

    foreach ( $segments as $segment ) {
        // Remove any empty segments or current directory references.
        if ( $segment === '' || $segment === '.' ) {
            continue;
        }

        // Remove any parent directory references.
        if ( $segment === '..' ) {
            array_pop( $sanitized_segments );
        }

        // Sanitize each segment.
        $sanitized_segment = sanitize_file_name( $segment );
        $sanitized_segments[] = $sanitized_segment;
        
    }

    // Rejoin the sanitized segments.
    $sanitized_path = implode( '/', $sanitized_segments );

    // Return the sanitized and normalized path.
    return $sanitized_path;
}

/**
 * Get the base website address from a given URL, handling localhost and other environments.
 *
 * @param string $url The URL to parse.
 * @return string The base website address.
 */
function smliser_get_base_address( $url ) {
    $parts = parse_url( $url );

    if ( ! isset( $parts['scheme'], $parts['host'] ) ) {
        return $parts['path'];
    }

    $scheme = $parts['scheme'];
    $host   = $parts['host'];
    $path   = isset( $parts['path'] ) ? $parts['path'] : '';

    // Check for localhost or local IP addresses.
    if ( $host === 'localhost' || preg_match( '/^127\.0\.0\.1|::1$/', $host ) ) {
        // Split the path by slashes and take the first part after the host.
        $path_parts = explode( '/', trim( $path, '/' ) );
        $base_path  = isset( $path_parts[0] ) ? '/' . $path_parts[0] : '';
        return $scheme . '://' . $host . $base_path;
    }

    // Handle custom local domains (e.g., mysite.local).
    if ( preg_match( '/^(.*)\.local$/', $host ) ) {
        return $scheme . '://' . $host;
    }

    // For non-localhost addresses, return the scheme and host.
    return $scheme . '://' . $host;
}

/**
 * Load app authentication page header
 */
function smliser_load_auth_header() {
    $theme_template_dir = get_template_directory() . '/smliser/auth/auth-header.php';
    include_once file_exists( $theme_template_dir ) ? $theme_template_dir : SMLISER_PATH . 'templates/auth/auth-header.php';
}

/**
 * Load app authentication page footer
 */
function smliser_load_auth_footer() {
    $theme_template_dir = get_template_directory() . '/smliser/auth/auth-footer.php';
    include_once file_exists( $theme_template_dir ) ? $theme_template_dir : SMLISER_PATH . 'templates/auth/auth-footer.php';
}

/**
 * Get the slug for file downloads
 */
function smliser_get_download_slug() {
    return apply_filters( 'smliser_download_slug', 'smliser-download' );
}


/**
 * Retrieve the Authorization header from the client request.
 *
 * @return string|null The Authorization header value or null if not found.
 */
function smliser_get_authorization_header() {
    $headers = [];

    // Use getallheaders() if it exists
    if ( function_exists( 'getallheaders' ) ) {
        $headers = getallheaders();
        // Normalize header keys to lowercase
        $headers = array_change_key_case( $headers, CASE_LOWER );
    }

    // Check the Authorization header in getallheaders result
    if ( isset( $headers['authorization'] ) ) {
        return wp_unslash( $headers['authorization'] );
    }

    // Fallback to Apache-specific headers
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        return wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
    }

    // Fallback to other possible server variables
    if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        return wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
    }

    return null;
}
