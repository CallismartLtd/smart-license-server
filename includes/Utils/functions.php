<?php
/**
 * File name smliser-functions.php
 * Utility functions file
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\functions
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;

use SmartLicenseServer\Utils\MDParser;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Check whether debug mode is enabled.
 *
 * @return bool
 */
function smliser_debug_enabled() : bool {
    if ( defined( 'DEBUG_MODE' ) && constant( 'DEBUG_MODE' )  ) {
        return true;
    } 

    if ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) ) {
        return true;
    }

    return false;
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
 * The repository page url function
 */
function smliser_repo_page() {

    if ( is_admin() ) {
        $url = add_query_arg( array(
            'page' => 'repository',
        ), admin_url( 'admin.php' ) );
        return $url;
    }

    return site_url( \smliser_settings_adapter()->get( 'smliser_repo_base_perma', 'plugins' ) );
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
        <p><?php echo wp_kses_post( $text ) ?> </p>
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
            'tab'        => $action,
            'license_id'    => $license_id,
        ), smliser_license_page() );
    } else {
        $url = add_query_arg( array(
            'tab'    => $action,
        ), smliser_license_page() );
    }
    return $url;
}

/**
 * Action url constructor for admin repository tabs.
 * 
 * @param string $tab The tab.
 * @param array $args An associative array the will be passed to add_query_args. 
 */
function smliser_admin_repo_tab( $tab = 'add-new', $args = array() ) {

    if ( ! is_array( $args ) ) {
        if ( is_int( $args ) ) {
            $args = array( 'app_id' => $args );
        } else if ( is_string( $args ) ) {
            $args = array( 'type' => $args );
        }
    }
    
    $args['tab'] = $tab;

    $url = add_query_arg($args, smliser_repo_page()  );

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
            $ip_list = explode( ',', sanitize_text_field( unslash( $_SERVER[ $key ] ) ) );
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
 * Parses a user agent string and returns a short description.
 *
 * @param string $user_agent_string The user agent string to parse.
 * @return string Description in the format: "Browser Version on OS (Device)".
 */
function smliser_parse_user_agent( $user_agent_string ) {
    $info = array(
        'browser' => 'Unknown Browser',
        'version' => '',
        'os'      => '',
        'device'  => 'Desktop',
    );

    // ---------------------------------------------
    // Detect OS
    // ---------------------------------------------
    if ( preg_match( '/Windows NT ([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $os_version = $matches[1];
        $info['os'] = match ( $os_version ) {
            '10.0' => 'Windows 10',
            '6.3'  => 'Windows 8.1',
            '6.2'  => 'Windows 8',
            '6.1'  => 'Windows 7',
            '6.0'  => 'Windows Vista',
            '5.1'  => 'Windows XP',
            default => 'Windows ' . $os_version
        };
    } elseif ( preg_match( '/Mac OS X ([0-9_.]+)/i', $user_agent_string, $matches ) ) {
        $info['os'] = 'macOS ' . str_replace( '_', '.', $matches[1] );
    } elseif ( preg_match( '/Linux/i', $user_agent_string ) && ! preg_match( '/Android/i', $user_agent_string ) ) {
        $info['os'] = 'Linux';
    } elseif ( preg_match( '/Android ?([0-9.]*)/i', $user_agent_string, $matches ) ) {
        $info['os']     = trim( 'Android ' . ( $matches[1] ?? '' ) );
        $info['device'] = 'Mobile';
    } elseif ( preg_match( '/iPhone|iPad|iPod/i', $user_agent_string, $matches ) ) {
        $info['os']     = 'iOS';
        $info['device'] = ( 'iPad' === $matches[0] ) ? 'Tablet' : 'Mobile';
    }

    // ---------------------------------------------
    // Detect device
    // ---------------------------------------------
    if ( preg_match( '/BlackBerry|Mobile Safari|Opera Mini|Opera Mobi|Firefox Mobile|webOS|NokiaBrowser|Series40|NintendoBrowser/i', $user_agent_string ) ) {
        $info['device'] = 'Mobile';
    } elseif ( preg_match( '/Tablet|iPad|Nexus 7|Nexus 10|GT-P|SM-T/i', $user_agent_string ) ) {
        $info['device'] = 'Tablet';
    }

    // ---------------------------------------------
    // Detect browser & version (Standard browsers)
    // ---------------------------------------------
    if ( preg_match( '/Edg\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Edge';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/Edge\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Edge';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/(OPR|Opera)\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Opera';
        $info['version'] = $matches[2];
    } elseif ( preg_match( '/CriOS\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Chrome iOS';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/Chrome\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Chrome';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/Firefox\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Firefox';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/Safari\/([0-9.]+)/i', $user_agent_string, $matches ) && ! preg_match( '/Chrome|Edg/i', $user_agent_string ) ) {
        $info['browser'] = 'Safari';

        if ( preg_match( '/Version\/([0-9.]+)/i', $user_agent_string, $version_matches ) ) {
            $info['version'] = $version_matches[1];
        } else {
            $info['version'] = $matches[1];
        }
    } elseif ( preg_match( '/MSIE ([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Internet Explorer';
        $info['version'] = $matches[1];
    } elseif ( preg_match( '/Trident\/([0-9.]+)/i', $user_agent_string, $matches ) ) {
        $info['browser'] = 'Internet Explorer';
        $info['version'] = ( '7.0' === $matches[1] ) ? '11.0' : 'Unknown IE';
    }

    // ---------------------------------------------
    // NEW: Generic fallback for custom UAs
    // Detect first "Something/1.2.3"
    // ---------------------------------------------
    if ( 'Unknown Browser' === $info['browser'] ) {
        if ( preg_match( '/([A-Za-z0-9._-]+)\/([0-9.]+)/', $user_agent_string, $m ) ) {
            $info['browser'] = $m[1];
            $info['version'] = $m[2];
        }
    }

    // ---------------------------------------------
    // NEW: Secondary OS/device hints for custom UAs
    // e.g. "(Linux; x86_64)" or "(Android; Phone)"
    // ---------------------------------------------
    if ( 'Unknown OS' === $info['os'] ) {
        if ( preg_match( '/\(([^)]+)\)/', $user_agent_string, $m ) ) {

            $raw = $m[1];

            if ( preg_match( '/linux/i', $raw ) ) {
                $info['os'] = 'Linux';
            } elseif ( preg_match( '/android/i', $raw ) ) {
                $info['os']     = 'Android';
                $info['device'] = 'Mobile';
            } elseif ( preg_match( '/iphone|ipod/i', $raw ) ) {
                $info['os']     = 'iOS';
                $info['device'] = 'Mobile';
            } elseif ( preg_match( '/ipad/i', $raw ) ) {
                $info['os']     = 'iOS';
                $info['device'] = 'Tablet';
            } elseif ( preg_match( '/mac|darwin/i', $raw ) ) {
                $info['os'] = 'macOS';
            } elseif ( preg_match( '/win/i', $raw ) ) {
                $info['os'] = 'Windows';
            }
        }
    }

    // ---------------------------------------------
    // Ensure fallback device type
    // ---------------------------------------------
    if ( 'Desktop' === $info['device'] ) {
        if ( preg_match( '/mobile|phone/i', $user_agent_string ) ) {
            $info['device'] = 'Mobile';
        } elseif ( preg_match( '/tablet|ipad/i', $user_agent_string ) ) {
            $info['device'] = 'Tablet';
        }
    }

    // ---------------------------------------------
    // Output
    // ---------------------------------------------
    return trim( sprintf(
        '%s%s on %s (%s)',
        $info['browser'],
        $info['version'] ? ' ' . $info['version'] : '',
        $info['os'],
        $info['device']
    ) );
}


/**
 * Get user agent agent.
 * 
 * @param bool $raw Whether to get the raw user agent string.
 * @return string
 */
function smliser_get_user_agent( bool $raw = false ) {
    $user_agent_string = smliser_get_param( 'HTTP_USER_AGENT', '', $_SERVER );
    if ( $raw ) {
        return $user_agent_string;
    }

    return smliser_parse_user_agent( $user_agent_string );
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
    
    if ( isset( $headers['authorization'] ) ) {
        $auth_header = $headers['authorization'][0];
        
        // Extract the token using a regex match for Bearer token.
        if ( preg_match( '/Bearer\s(\S+)/', $auth_header, $matches ) ) {
            return $matches[1]; // Return the token.
        } else {
            $auth_header;
        }
    }
    
    return null;
}

/**
 * Generate a download token for the given licensed item.
 * 
 * @param License $license  The License object associated with the item.
 * @param int     $expiry   Expiry duration in seconds (optional).
 * @return string|false     The signed, base64url-encoded token or false on failure.
 */
function smliser_generate_item_token( License $license, int $expiry = 864000 ) {
    try {
        // Create a new download token
        $token = DownloadToken::create_token( $license, $expiry );
        return $token;
    } catch ( Exception $e ) {
        // You can log $e->getMessage() here if needed
        return false;
    }
}

/**
 * Verify a licensed plugin download token.
 *
 * @param string                  $client_token     The base64url-encoded token received from the client.
 * @param AbstractHostedApp    $app             app context to validate against.
 * @return DownloadToken|Exception                  Returns the DownloadToken object on success, false on failure.
 */
function smliser_verify_item_token( string $client_token, AbstractHostedApp $app ) : DownloadToken|Exception {
    if ( empty( $client_token ) ) {
        return new Exception( 'empty_token', 'Download token cannot be empty' );
    }

    try {
        // Verify token within the app context
        $download_token = DownloadToken::verify_token_for_app( $client_token, $app );
        return $download_token;
    } catch ( Exception $e ) {
        // Invalid or expired token
        return $e;
    }
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
 * Safely encodes data to JSON, emulating WordPress' wp_json_encode().
 *
 * Ensures consistent encoding across environments, handling
 * non-UTF8 characters and partial encoding failures gracefully.
 *
 * @param mixed $data  Data to encode.
 * @param int   $flags Optional. Bitmask of JSON encode options. Default 0.
 * @param int   $depth Optional. Set the maximum depth. Default 512.
 * 
 * @return string|false The JSON encoded string, or false on failure.
 */
function smliser_safe_json_encode( mixed $data, int $flags = 0, int $depth = 512 ) {
	if ( function_exists( 'wp_json_encode' ) ) {
		return wp_json_encode( $data, $flags, $depth );
	}

	// Attempt normal JSON encoding first.
	$json = json_encode( $data, $flags, $depth );

	if ( false !== $json && JSON_ERROR_NONE === json_last_error() ) {
		return $json;
	}

	// If encoding fails, try to clean invalid UTF-8 recursively.
	$clean_data = smliser_utf8ize( $data );

	$json = json_encode( $clean_data, $flags, $depth );

	if ( false !== $json && JSON_ERROR_NONE === json_last_error() ) {
		return $json;
	}

	return false;
}

/**
 * Send a json response
 * 
 * @param mixed $data Data to encode and send.
 */
function smliser_send_json( $data, $status_code = 200, $flags = 0 ) {
    if ( function_exists( 'wp_send_json' ) ) {
        wp_send_json( $data, $status_code, $flags );
    }

    if ( ! headers_sent() ) {
        status_header( $status_code );
        header( 'Content-Type: application/json; charset=' . smliser_settings_adapter()->get( 'charset', 'UTF-8', false ) );
    }

    echo smliser_safe_json_encode( $data, $flags ); // phpcs:ignore
    exit;
}

/**
 * Send json error response
 * 
 * @param mixed $data Data to encode and send.
 * @param int $status_code HTTP status code.
 * @param int $flags JSON encode flags.
 */
function smliser_send_json_error( $data = null, $status_code = 400, $flags = 0 ) {
    if ( function_exists( 'wp_send_json_error' ) && ( ! $data instanceof Exception ) ) {
        wp_send_json_error( $data, $status_code, $flags );
    }

    $response = array( 'success' => false );

    if ( isset( $data ) ) {
        if ( is_smliser_error( $data ) ) {
            /**
             * @var SmartLicenseServer\Exception $data
             */
            $error_data = $data->to_array();
            if ( smliser_debug_enabled() ) {
                unset( $error_data['trace'] );
            } 

            $response['data'] = $error_data;

            if ( isset( $data->get_error_data()['status'] ) ) {
                $status_code = $data->get_error_data()['status'];
            }
        } else {
            $response['data'] = $data;
        }
    }

    smliser_send_json( $response, $status_code, $flags );
}

/**
 * Send json success response
 * 
 * @param mixed $data Data to encode and send.
 * @param int $status_code HTTP status code.
 * @param int $flags JSON encode flags.
 */
function smliser_send_json_success( $data = null, $status_code = 200, $flags = 0 ) {
    if ( function_exists( 'wp_send_json_success' ) ) {
        wp_send_json_success( $data, $status_code, $flags );
    }

    $response = array( 'success' => true );

    if ( isset( $data ) ) {
        $response['data'] = $data;
    }

    smliser_send_json( $response, $status_code, $flags );
}

/**
 * Recursively clean strings to ensure valid UTF-8, similar to WordPress' `smliser_safe_json_encode()` handling.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed UTF-8 cleaned data.
 */
function smliser_utf8ize( mixed $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			unset( $data[ $key ] );
			$data[ smliser_utf8ize( $key ) ] = smliser_utf8ize( $value );
		}
	} elseif ( is_string( $data ) ) {
		return mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
	} elseif ( is_object( $data ) ) {
		$vars = get_object_vars( $data );
		foreach ( $vars as $key => $value ) {
			$data->$key = smliser_utf8ize( $value );
		}
	}

	return $data;
}

/**
 * Sanitize and normalize a file path to prevent directory traversal attacks.
 *
 * @param string $path The input path.
 * @return string|SmartLicenseServer\Exception The sanitized and normalized path, or Exception on failure.
 */
function smliser_sanitize_path( $path ) {
    return FileSystemHelper::sanitize_path( $path );
}

/**
 * Get the base website address from a given URL, handling localhost and other environments.
 *
 * @param string $url The URL to parse.
 * @return string The base website address.
 */
function smliser_get_base_url( string $url ) {
    $url    = new URL( $url );
    return $url->get_origin();
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
    return apply_filters( 'smliser_download_slug', 'downloads' );
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
        return unslash( $headers['authorization'] );
    }

    // Fallback to Apache-specific headers
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        return unslash( $_SERVER['HTTP_AUTHORIZATION'] );
    }

    // Fallback to other possible server variables
    if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        return unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
    }

    return null;
}

/**
 * Get the value of a URL query parameter.
 * @param string $param The name of the query parameter.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the query parameter.
 */
function smliser_get_query_param( $param, $default = '' ) {
    return smliser_get_param( $param, $default, $_GET );
}

/**
 * Get the value of a POST parameter.
 * @param string $param The name of the POST parameter.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the POST parameter.
 */
function smliser_get_post_param( $param, $default = '' ) {
    return smliser_get_param( $param, $default, $_POST );
}

/**
 * Get the value of a ROEQUEST parameter.
 * @param string $param The name of the REQUEST parameter.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the REQUEST parameter.
 */
function smliser_get_request_param( $param, $default = '' ) {
    return smliser_get_param( $param, $default, $_REQUEST );
}


/**
 * Retrieve and sanitize a parameter from a given source array, with automatic type detection.
 *
 * This function supports automatic type detection and sanitization for common data types:
 * - Arrays: sanitized recursively with `sanitize_text_field()`.
 * - Numeric strings: cast to `int` or `float`.
 * - Boolean-like values: evaluated with `FILTER_VALIDATE_BOOLEAN` (supports "true", "false", "1", "0", "yes", "no").
 * - Email addresses: validated and sanitized with `sanitize_email()`.
 * - URLs: validated with `FILTER_VALIDATE_URL` and sanitized with `esc_url_raw()`.
 * - All other strings: sanitized with `sanitize_text_field()`.
 *
 * @param string $key     The key to retrieve from the source array.
 * @param mixed  $default Optional. The default value to return if the key is not set. Default ''.
 * @param array  $source  Optional. The source array to read from (e.g., $_GET or $_POST). Default empty array.
 *
 * @return mixed The sanitized value if found, or the default value if the key is not present.
 */
function smliser_get_param( $key, $default = '', $source = array() ) {
    if ( ! array_key_exists( $key, $source ) ) {
        return $default;
    }

    $value = unslash( $source[ $key ] );

    if ( is_array( $value ) ) {
        return array_map( 'sanitize_text_field', $value );
    }

    if ( is_numeric( $value ) ) {
        return ( strpos( $value, '.' ) !== false ) ? floatval( $value ) : intval( $value );
    }

    $lower = strtolower( $value );
    if ( in_array( $lower, [ 'true', 'false', '1', '0', 'yes', 'no' ], true ) ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    if ( is_email( $value ) ) {
        return sanitize_email( $value );
    }

    if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
        return esc_url_raw( $value );
    }

    return sanitize_text_field( $value );
}

/**
 * Check whether a key is set in the query param
 * 
 * @param string $key The ky to check
 * @return bool True when found, false otherwise.
 */
function smliser_has_query_param( $key ) {
    return isset( $_GET[$key] );
}

/**
 * Renders a reusable, prefixed toggle switch component.
 *
 * @param array $attrs Associative array of attributes for the input element.
 *                     Supported: id, name, value, class, and any custom data-* or aria-* attributes.
 *                     Example:
 *                     [
 *                        'id' => 'autosave_toggle',
 *                        'name' => 'autosave',
 *                        'value' => 1, // 1 for checked, 0 for unchecked
 *                        'data-group' => 'editor',
 *                        'aria-label' => 'Enable autosave',
 *                     ]
 *
 * @return void
 */
function smliser_render_toggle_switch( $attrs = array() ) {
    $defaults = array(
        'id'    => uniqid( 'smliser_toggle_' ),
        'name'  => 'toggle_switch',
        'value' => 0,
        'class' => 'smliser_toggle-switch-input',
    );

    $attrs = array_merge( $defaults, $attrs );

    // Extract value to determine checked state
    $value = (int) $attrs['value'];
    unset( $attrs['value'] );

    // Build attribute string
    $attr_str = '';
    foreach ( $attrs as $key => $val ) {
        if ( is_bool( $val ) ) {
            $attr_str .= $val ? sprintf( ' %s', esc_attr( $key ) ) : '';
        } else {
            $attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $val ) );
        }
    }

    printf(
        '<div class="smliser_toggle-switch-container">
            <input type="checkbox"%1$s value="1" %2$s />
            <label for="%3$s" class="smliser_toggle-switch-label">
                <span class="smliser_toggle-switch-slider"></span>
            </label>
        </div>',
        $attr_str,
        checked( $value, 1, false ),
        esc_attr( $attrs['id'] )
    );
}

/**
 * Render form input field
 *
 * @param array $args Arguments to render the field.
 */
function smliser_render_input_field( $args = array() ) {
    $default_args = array(
        'label' => '',
        'input' => array(
            'type'      => 'text',
            'name'      => '',
            'value'     => '',
            'class'     => 'smliser-form-label-row',
            'options'   => array(), // For select/radio
            'attr'      => array(),
        ),
    );

    $parsed_args = parse_args_recursive( $args, $default_args );
    $input       = $parsed_args['input'];
    $type        = $input['type'];
    
    // Build attributes string.
    $attr_str = '';
    if ( ! empty( $input['attr'] ) && is_array( $input['attr'] ) ) {
        foreach ( $input['attr'] as $key => $val ) {
            $attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $val ) );
        }
    }
    
    $id = ! empty( $input['attr']['id'] ) ? $input['attr']['id'] : $input['name'];

    // Hidden fields don't need labels
    if ( 'hidden' === $type ) {
        printf( '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s>',
            esc_attr( $type ),
            esc_attr( $input['name'] ),
            esc_attr( $id ),
            esc_attr( $input['value'] ),
            $attr_str
        );
        return;
    }

    printf( '<label for="%1$s" class="%2$s"><span>%3$s</span>', esc_attr( $id ), esc_html( $input['class'] ), esc_html( $parsed_args['label'] ) );

    // Handle different tag types
    switch ( $type ) {
        case 'textarea':
            printf(
                '<textarea name="%1$s" id="%2$s"%3$s>%4$s</textarea>',
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                $attr_str,
                esc_textarea( $input['value'] )
            );
            break;

        case 'select':
            printf( '<select name="%1$s" id="%2$s"%3$s>', esc_attr( $input['name'] ), esc_attr( $id ), $attr_str );
            foreach ( $input['options'] as $val => $label ) {
                printf( 
                    '<option value="%1$s" %2$s>%3$s</option>', 
                    esc_attr( $val ), 
                    selected( $input['value'], $val, false ), 
                    esc_html( $label ) 
                );
            }
            echo '</select>';
            break;

        case 'checkbox':
        case 'radio':
            printf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" %5$s %6$s>',
                esc_attr( $type ),
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                checked( $input['value'], true, false ),
                $attr_str
            );
            break;

        case 'password':
            // Password field with toggle visibility
            echo '<div class="smliser-password-field-wrapper">';
            printf(
                '<input type="password" name="%1$s" id="%2$s" value="%3$s" class="smliser-password-input"%4$s>',
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                $attr_str
            );
            printf(
                '<button type="button" class="smliser-password-toggle" data-target="%1$s" aria-label="Toggle password visibility">
                    <svg class="smliser-eye-icon smliser-eye-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="smliser-eye-icon smliser-eye-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>',
                esc_attr( $id )
            );
            echo '</div>';
            break;

        default:
            printf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s>',
                esc_attr( $type ),
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                $attr_str
            );
            break;
    }

    echo '</label>';
}

/**
 * Get the URL for a given app asset.
 *
 * @param string $type     App type ('plugin' or 'theme').
 * @param string $slug     The app slug.
 * @param string $filename The asset file name (e.g. screenshot-1.png).
 * @return string
 */
function smliser_get_app_asset_url( $type, $slug, $filename ) {
    return smliser_get_repo_url(
        sprintf( '%s/%s/assets/%s', $type, $slug, ltrim( $filename, '/' ) )
    );
}

/**
 * Get the repository base URL or a path within it.
 *
 * @param string $path Optional relative path within the repo.
 * @return string
 */
function smliser_get_repo_url( $path = '' ) {
    $repo_base = \smliser_settings_adapter()->get( 'smliser_repo_base_perma', 'repository' );
    $base_url  = site_url( $repo_base );

    if ( $path !== '' ) {
        // Avoid double slashes but do not enforce trailing slash
        return $base_url . '/' . ltrim( $path, '/' );
    }

    return $base_url;
}

/**
 * Parse a given argument with default arguments.
 * Similar to wp_parse_args(), but strips out undefined default keys.
 *
 * @param array|object|null $args     The arguments to parse.
 * @param array             $defaults The default arguments.
 * @return array
 */
function parse_args( $args, $defaults ) {
    $args     = (array) $args;
    $defaults = (array) $defaults;

    return array_intersect_key( array_merge( $defaults, $args ), $defaults );
}

/**
 * Parse arguments recursively, respecting default keys only.
 *
 * @param array|object|null $args
 * @param array             $defaults
 * @return array
 */
function parse_args_recursive( $args, $defaults ) {
    $args     = is_array( $args ) || is_object( $args ) ? (array) $args : array();
    $defaults = is_array( $defaults ) || is_object( $defaults ) ? (array) $defaults : array();

    foreach ( $defaults as $key => $default ) {

        if ( array_key_exists( $key, $args ) ) {
            $value = $args[ $key ];

            if ( is_array( $default ) ) {

                // Empty default array means "accept anything".
                if ( empty( $default ) ) {
                    $value = (array) $value;
                } elseif ( is_array( $value ) ) {
                    $value = parse_args_recursive( $value, $default );
                }
            }

        } else {
            $value = $default;
        }

        $defaults[ $key ] = $value;
    }

    return $defaults;
}

/**
 * Kills appliaction execution and displays HTML page with an error message.
 *
 * This function complements the `die()` PHP function. The difference is that
 * HTML will be displayed to the user. It is recommended to use this function
 * only when the execution should not continue any further. It is not recommended
 * to call this function very often, and try to handle as many errors as possible
 * silently or more gracefully.
 *
 * As a shorthand, the desired HTTP response code may be passed as an integer to
 * the `$title` parameter (the default title would apply) or the `$args` parameter.
 *
 *
 * @param string|SmartLicenseServer\Exception  $message Optional. Error message. If this is an error object,
 *                                  and not an Ajax or XML-RPC request, the error's messages are used.
 *                                  Default empty string.
 * @param string|int       $title   Optional. Error title. If `$message` is a `SmartLicenseServer\Exceptions\Exception;` object,
 *                                  error data with the key 'title' may be used to specify the title.
 *                                  If `$title` is an integer, then it is treated as the response code.
 *                                  Default empty string.
 * @param string|array|int $args {
 *     Optional. Arguments to control behavior. If `$args` is an integer, then it is treated
 *     as the response code. Default empty array.
 *
 *     @type int    $response       The HTTP response code. Default 200 for Ajax requests, 500 otherwise.
 *     @type string $link_url       A URL to include a link to. Only works in combination with $link_text.
 *                                  Default empty string.
 *     @type string $link_text      A label for the link to include. Only works in combination with $link_url.
 *                                  Default empty string.
 *     @type bool   $back_link      Whether to include a link to go back. Default false.
 *                                  Default is the value of is_rtl().
 *     @type string $charset        Character set of the HTML output. Default 'utf-8'.
 *     @type string $code           Error code to use. Default is 'smliser_error', or the main error code if $message
 *                                  is a WP_Error.
 *     @type bool   $exit           Whether to exit the process after completion. Default true.
 * }
 */
function smliser_abort_request( $message = '', $title = '', $args = [] ) {
    $defaults = [
        'response'  => 500, // Default HTTP status for a fatal error
        'link_url'  => '',
        'link_text' => '',
        'back_link' => false,
        'charset'   => 'utf-8',
        'code'      => 'smliser_error',
        'exit'      => true,
    ];

    // Handle HTTP response code passed as an integer shorthand.
    if ( is_int( $title ) ) {
        $args = [ 'response' => $title ];
        $title = '';
    } elseif ( is_int( $args ) ) {
        $args = [ 'response' => $args ];
    }
    
    // Resolve final configuration array
    $r = parse_args( $args, $defaults );

    $error_object = null;
    if ( is_smliser_error( $message ) ) {
        $error_object = $message;

        // Fetch primary data from the error object's internal structure
        $error_data = $error_object->get_error_data();

        // Overwrite defaults with structured error data
        $r['response'] = $error_data['status'] ?? $r['response'];
        $r['code']     = $error_object->get_error_code() ?: $r['code'];
        $title         = $title ?: ($error_data['title'] ?? 'Application Error');
        

        $message = smliser_debug_enabled() ? sprintf( '<pre>%s</pre>', $error_object->__toString() ) : $error_object->get_error_message();
    }
    
    // Fallback if initial message was empty string
    $message = $message ?: 'An unknown fatal error occurred.';
    $title   = $title ?: 'Fatal Error';

    if ( function_exists( 'wp_die' ) && $error_object === null ) {
        // If we only have string inputs, use the native WP handler
        // Note: $error_object is not passed here as it complicates wp_die's native flow.
        wp_die( $message, $title, $r );
    }

    $http_response_code = (int) $r['response'];
    if ( ! headers_sent() ) {
        http_response_code( $http_response_code );
        header( "Content-Type: text/html; charset={$r['charset']}" );
    }

    // --- 5. Prepare HTML Content ---
    $link_html = '';

    if ( ! empty( $r['link_url'] ) && ! empty( $r['link_text'] ) ) {
        $link_html .= '<p><a href="' . esc_url( $r['link_url'] ) . '">' . esc_html( $r['link_text'] ) . '</a></p>';
    }

    if ( $r['back_link'] ) {
        $link_html .= '<p><a href="javascript:history.back()">Go Back</a></p>';
    }

    $safe_message = $message;
    $safe_title   = $title;

    // --- 6. Output and Exit ---
    die( '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="' . esc_attr( $r['charset'] ) . '">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $safe_title . '</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 50px; }
            .error-container { max-width: 80%; margin: auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1); overflow-wrap: anywhere }
            h1 { color: #e74c3c; margin-top: 0; font-size: 24px; }
            p { font-size: 16px; color: #333; }
            a { color: #3498db; text-decoration: none; }
            a:hover { text-decoration: underline; }
            pre div { max-width: 100%; background-color: #f1f1f1; overflow-x: auto; padding: 10px; scrollbar-width: thin; }
        </style>
    </head>
    <body>
        <div class="error-container">
            ' . $safe_message
            . '<p>' . $link_html . '</p>
        </div>
    </body>
    </html>' );

    // The die() call above handles the exit, but this line is left for explicit clarity.
    if ( $r['exit'] ) {
        exit;
    }
}

/**
 * Unified download function.
 *
 * Attempts WordPress download_url, Laravel HTTP client, cURL, or PHP fopen/file_get_contents.
 *
 * @param string $url     URL to download.
 * @param int    $timeout Timeout in seconds (default: 30).
 * @return string|FileRequestException Local temporary file path on success, FileRequestException on failure.
 */
function smliser_download_url( $url, $timeout = 30 ) {

    $url  = new URL( $url );
    // Validate URL.
    if ( ! $url->is_valid( true ) ) {
        return new FileRequestException( 'invalid_url', 'Invalid URL provided.' );
    }

    $url    = $url->__toString();
    // Validate timeout
    $timeout = max( 1, (int) $timeout );

    // Try WordPress native function first (most reliable in WP context)
    if ( function_exists( 'download_url' ) ) {
        $tmp_file = download_url( $url, $timeout );
        
        if ( is_smliser_error( $tmp_file ) ) {
            return new FileRequestException( $tmp_file->get_error_code(), $tmp_file->get_error_message() );
        }

        return $tmp_file;
    }

    // Try cURL (most common and reliable)
    if ( function_exists( 'curl_init' ) ) {
        return smliser_download_with_curl( $url, $timeout );
    }

    // Try Laravel HTTP client (if available)
    if ( class_exists( 'Illuminate\Support\Facades\Http' ) ) {
        return smliser_download_with_laravel( $url, $timeout );
    }

    // Try PHP native file_get_contents (last resort).
    if ( ini_get( 'allow_url_fopen' ) ) {
        return smliser_download_with_fopen( $url, $timeout );
    }

    return new FileRequestException(
        'no_download_method',
        'No suitable download method available (WordPress, cURL, Laravel HTTP, or fopen).'
    );
}

/**
 * Download file using cURL.
 *
 * @param string $url     URL to download.
 * @param int    $timeout Timeout in seconds.
 * @return string|FileRequestException Temporary file path or error.
 */
function smliser_download_with_curl( $url, $timeout ) {
    $tmp_file = tempnam( sys_get_temp_dir(), 'smliser_' );
    
    if ( false === $tmp_file ) {
        return new FileRequestException( 'temp_file_failed', 'Failed to create temporary file.' );
    }

    $fp = @fopen( $tmp_file, 'w+' );
    
    if ( false === $fp ) {
        @unlink( $tmp_file );
        return new FileRequestException( 'temp_file_open_failed', 'Failed to open temporary file for writing.' );
    }

    $ch = curl_init( $url );
    
    if ( false === $ch ) {
        fclose( $fp );
        @unlink( $tmp_file );
        return new FileRequestException( 'curl_init_failed', 'Failed to initialize cURL.' );
    }

    curl_setopt_array( $ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min( 10, $timeout ),
        CURLOPT_USERAGENT      => 'SmliserDownload/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXREDIRS      => 5,
    ] );

    $success   = curl_exec( $ch );
    $error     = curl_error( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    
    // curl_close( $ch ); deprecated php 8.0 +
    fclose( $fp );

    // Check for success
    if ( $success && $http_code >= 200 && $http_code < 300 ) {
        // Verify file was actually downloaded.
        if ( filesize( $tmp_file ) > 0 ) {
            return $tmp_file;
        }
        @unlink( $tmp_file );
        return new FileRequestException( 'file_integrity_failure' ); // Error message is known.
    }

    // Clean up and return error
    @unlink( $tmp_file );
    
    $error_message = sprintf(
        'cURL failed with HTTP code %d. Error: %s',
        $http_code,
        $error ?: 'Unknown error'
    );

    return new FileRequestException( 'curl_download_failed', $error_message );
}

/**
 * Download file using Laravel HTTP client.
 *
 * @param string $url     URL to download.
 * @param int    $timeout Timeout in seconds.
 * @return string|FileRequestException Temporary file path or error.
 */
function smliser_download_with_laravel( $url, $timeout ) {
    try {
        $tmp_file = tempnam( sys_get_temp_dir(), 'smliser_' );
        
        if ( false === $tmp_file ) {
            return new FileRequestException( 'temp_file_failed', 'Failed to create temporary file.' );
        }
    
        // @phpstan-ignore-next-line
        $response = \Illuminate\Support\Facades\Http::timeout( $timeout )
            ->connectTimeout( min( 10, $timeout ) )
            ->withOptions( [
                'verify' => true,
                'sink'   => $tmp_file, 
            ] )
            ->get( $url );

        if ( $response->successful() ) {
            // Verify file was downloaded
            if ( file_exists( $tmp_file ) && filesize( $tmp_file ) > 0 ) {
                return $tmp_file;
            }
            @unlink( $tmp_file );
            return new FileRequestException( 'file_integrity_failure' ); // Error message is known.
        }

        @unlink( $tmp_file );
        
        return new FileRequestException(
            'laravel_download_failed',
            sprintf( 'Laravel HTTP client failed with status %d.', $response->status() )
        );
    } catch ( \Illuminate\Http\Client\ConnectionException $e ) {
        if ( isset( $tmp_file ) ) {
            @unlink( $tmp_file );
        }
        return new FileRequestException( 'connection_failed', 'Connection failed: ' . $e->getMessage() );
    } catch ( \Throwable $e ) {
        if ( isset( $tmp_file ) ) {
            @unlink( $tmp_file );
        }
        return new FileRequestException( 'laravel_exception', 'Laravel client exception: ' . $e->getMessage() );
    }
}

/**
 * Download file using PHP file_get_contents.
 *
 * @param string $url     URL to download.
 * @param int    $timeout Timeout in seconds.
 * @return string|FileRequestException Temporary file path or error.
 */
function smliser_download_with_fopen( $url, $timeout ) {
    $tmp_file = tempnam( sys_get_temp_dir(), 'smliser_' );
    
    if ( false === $tmp_file ) {
        return new FileRequestException( 'temp_file_failed', 'Failed to create temporary file.' );
    }

    $context = stream_context_create( [
        'http' => [
            'timeout'        => $timeout,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'header'         => "User-Agent: SmliserDownload/1.0\r\n",
            'ignore_errors'  => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ] );

    // Suppress warnings and capture errors
    $error_reporting = error_reporting( 0 );
    $data = @file_get_contents( $url, false, $context );
    error_reporting( $error_reporting );

    if ( false === $data ) {
        @unlink( $tmp_file );
        $error = error_get_last();
        return new FileRequestException(
            'fopen_download_failed',
            'PHP file_get_contents failed: ' . ( $error['message'] ?? 'Unknown error' )
        );
    }

    if ( empty( $data ) ) {
        @unlink( $tmp_file );
        return new FileRequestException( 'file_integrity_failure' ); // Error message is known.
    }

    // Write data to temp file
    $written = @file_put_contents( $tmp_file, $data );
    
    if ( false === $written || $written === 0 ) {
        @unlink( $tmp_file );
        return new FileRequestException( 'file_write_failed', 'Failed to write downloaded data to temporary file.' );
    }

    return $tmp_file;
}

/**
 * Get the database class singleton instance.
 *
 * @return \SmartLicenseServer\Database\Database Singleton instance of the Database class.
 */
function smliser_dbclass() : \SmartLicenseServer\Database\Database {
    return \SmartLicenseServer\Database\Database::instance();
}

/**
 * Get the settings API singleton class.
 * 
 * @return SmartLicenseServer\SettingsAPI\Settings
 */
function smliser_settings_adapter() : SmartLicenseServer\SettingsAPI\Settings {
    return SmartLicenseServer\SettingsAPI\Settings::instance();
}

/**
 * Get the cache singleton instance.
 *
 * @return \SmartLicenseServer\Cache\Cache Singleton instance of the Cache class.
 */
function smliser_cache() : \SmartLicenseServer\Cache\Cache {
    return \SmartLicenseServer\Cache\Cache::instance();
}

/**
 * Get placeholder icon.
 *
 * @param string $type
 * @return string
 */
function smliser_get_placeholder_icon( string $type = '' ) : string {

    static $cache = array();

    $base_url = rtrim( SMLISER_URL, '/' );

    $type = strtolower( trim( $type ) );

    if ( isset( $cache[ $type ] ) ) {
        return $cache[ $type ];
    }

    $relative_path = match ( $type ) {
        'plugin', 'plugins'         => 'assets/images/plugins-placeholder.svg',
        'license', 'licenses'       => 'assets/images/license-placeholder.svg',
        'theme', 'themes'           => 'assets/images/themes-placeholder.svg',
        'app', 'apps' , 'all'       => 'assets/images/apps-placeholder.svg',
        'software', 'softwares'     => 'assets/images/software-placeholder.svg',
        'download', 'downloads'     => 'assets/images/downloads-icon.svg',
        'avatar', 'default-avatar'  => 'assets/images/default-avatar.svg',
        default                     => 'assets/images/software-placeholder.svg',
    };

    return $cache[ $type ] = sprintf(
        '%s/%s',
        $base_url,
        $relative_path
    );
}

/**
 * Get our uploads url.
 * 
 * @param $path
 * @return string
 */
function smliser_uploads_url( string $path  = '' ) : string {
    $uploads_rel_path   = str_replace( SMLISER_ABSPATH, '', SMLISER_UPLOADS_DIR );
    $path               = FileSystemHelper::join_path( $uploads_rel_path, $path );
    $uploads_url        = site_url( $path );
    return $uploads_url;
}

/**
 * Get avatar URL.
 * 
 * @param $filename_hash    The MD5 hash name of the avatar.
 * @param $type             The avatar type.
 * @return string
 */
function smliser_avatar_url( string $filename_hash, string $type ) : string {
    $type       = smliser_pluralize( str_replace( '_', '-', $type ) );
    $path       = FileSystemHelper::join_path( 'avatars', $type );

    if ( is_smliser_error( $path ) ) {
        return '';
    }

    $path       = FileSystemHelper::join_path( $path, $filename_hash );
    $avatar_url = smliser_uploads_url( $path );
    return $avatar_url;
}



/**
 * Returns the singleton instance of the parser.
 *
 * @return MDParser
 */
function smliser_md_parser() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new MDParser();
	}
	return $instance;
}

/**
 * Build default WordPress app.json manifest data
 * 
 * @param \SmartLicenseServer\HostedApps\AbstractHostedApp $app The application instance.
 * @param array $metadata The app metadata.
 */
function smliser_build_wp_manifest( AbstractHostedApp $app, array $metadata ) {
    $min_ver        = $metadata['requires_at_least'] ?? '';
    $max_ver        = $metadata['tested_up_to'] ?? '';
    $type           = strtolower( $app->get_type() );
    $name_key       = sprintf( '%s_name', $type );
    $version_key    = ( $app instanceof Plugin ) ? 'stable_tag' : 'version';
    return array(
        'name'          => $metadata[$name_key] ?? '',
        'slug'          => $app->get_slug(),
        'version'       => $metadata[$version_key ] ?? $app->get_meta( 'version' ),
        'type'          => \sprintf( 'wordpress-%s', $type ),
        'platforms'     => ['WordPress'],
        'tech_stack'    => ['PHP', 'JavaScript'],
        'tested'        => $max_ver,
        'requires'  => array(
            'Wordpress' => \sprintf( '%s +', $min_ver ),
            'PHP'       => \sprintf( ' %s +', $metadata['requires_php'] ?? '7.4' ),
        ),
        
    );
}

/**
 * Get current page URL.
 *
 * Returns an empty string if it cannot generate a URL.
 * @return \SmartLicenseServer\Core\URL
 */
function smliser_get_current_url() : URL {
	$uri = $_SERVER['REQUEST_URI'] ?? '';

    $site_url   = site_url( $uri );
	return ( new URL( $site_url ) )->sanitize();
}

/**
 * Simple English Pluralizer
 * 
 * Converts singular English words to their plural forms.
 * Handles regular patterns, irregular plurals, and uncountable nouns.
 * 
 * @param string $string The singular word (e.g., "license", "category").
 * @return string The pluralized word.
 */
function smliser_pluralize( string $string ) : string {
    $string = trim( $string );
    
    if ( empty( $string ) ) {
        return '';
    }

    $lower = strtolower( $string );

    // Irregular plurals (common cases)
    $irregulars = [
        'child'     => 'children',
        'person'    => 'people',
        'man'       => 'men',
        'woman'     => 'women',
        'tooth'     => 'teeth',
        'foot'      => 'feet',
        'mouse'     => 'mice',
        'goose'     => 'geese',
        'ox'        => 'oxen',
        'quiz'      => 'quizzes',
        'axis'      => 'axes',
        'analysis'  => 'analyses',
        'basis'     => 'bases',
        'crisis'    => 'crises',
        'thesis'    => 'theses',
        'phenomenon' => 'phenomena',
        'criterion' => 'criteria',
        'datum'     => 'data',
    ];

    if ( isset( $irregulars[ $lower ] ) ) {
        return smliser_preserve_case( $string, $irregulars[ $lower ] );
    }

    // Uncountable nouns (return as-is)
    $uncountable = [
        'information', 'equipment', 'rice', 'money', 'species', 'series',
        'fish', 'sheep', 'deer', 'moose', 'aircraft', 'software', 'hardware',
        'data', 'news', 'advice', 'furniture', 'luggage', 'evidence', 'users'
    ];

    if ( in_array( $lower, $uncountable, true ) ) {
        return $string;
    }

    // Regular pluralization rules (order matters!)
    $rules = [
        // Words ending in s, x, z, ch, sh -> add 'es'
        '/(s|ss|x|z|ch|sh)$/i' => '$1es',
        
        // Words ending in consonant + y -> ies
        '/([^aeiouy])y$/i' => '$1ies',
        
        // Words ending in fe -> ves
        '/([^f])fe$/i' => '$1ves',
        
        // Words ending in f -> ves (leaf, half, etc.)
        '/([lr])f$/i' => '$1ves',
        
        // Words ending in consonant + o -> oes (hero, potato, tomato)
        // BUT: Exclude common exceptions (photo, piano, halo)
        '/(?<!photo)(?<!piano)(?<!halo)([^aeiou])o$/i' => '$1oes',
        
        // Words ending in is -> es (analysis -> analyses)
        '/is$/i' => 'es',
        
        // Words ending in us -> i (cactus -> cacti)
        '/us$/i' => 'i',
        
        // Words ending in on -> a (criterion -> criteria)
        '/on$/i' => 'a',
    ];

    foreach ( $rules as $pattern => $replacement ) {
        if ( preg_match( $pattern, $string ) ) {
            return preg_replace( $pattern, $replacement, $string );
        }
    }

    // Default: just add 's'
    return $string . 's';
}

/**
 * Preserve the original case pattern when replacing a word.
 * 
 * @param string $original The original word (with case).
 * @param string $replacement The replacement word (lowercase).
 * @return string The replacement with original case pattern applied.
 */
function smliser_preserve_case( string $original, string $replacement ) : string {
    // All uppercase
    if ( $original === strtoupper( $original ) ) {
        return strtoupper( $replacement );
    }
    
    // First letter uppercase (Title Case)
    if ( $original[0] === strtoupper( $original[0] ) ) {
        return ucfirst( $replacement );
    }
    
    // All lowercase
    return $replacement;
}

/**
 * Attemps to convert plural word back to singular.
 * 
 * @param string $string The plural word.
 * @return string The singularized word.
 */
function smliser_singularize( string $string ) : string {
    $string = trim( $string );
    
    if ( empty( $string ) ) {
        return '';
    }

    $lower = strtolower( $string );

    // Irregular reverse mapping
    $irregulars = [
        'children'   => 'child',
        'people'     => 'person',
        'men'        => 'man',
        'women'      => 'woman',
        'teeth'      => 'tooth',
        'feet'       => 'foot',
        'mice'       => 'mouse',
        'geese'      => 'goose',
        'oxen'       => 'ox',
        'quizzes'    => 'quiz',
        'axes'       => 'axis',
        'analyses'   => 'analysis',
        'bases'      => 'basis',
        'crises'     => 'crisis',
        'theses'     => 'thesis',
        'phenomena'  => 'phenomenon',
        'criteria'   => 'criterion',
    ];

    if ( isset( $irregulars[ $lower ] ) ) {
        return smliser_preserve_case( $string, $irregulars[ $lower ] );
    }

    // Uncountable
    $uncountable = [
        'information', 'equipment', 'rice', 'money', 'species', 'series',
        'fish', 'sheep', 'deer', 'moose', 'aircraft', 'software', 'hardware',
        'data', 'news', 'advice', 'furniture', 'luggage', 'evidence'
    ];

    if ( in_array( $lower, $uncountable, true ) ) {
        return $string;
    }

    // Singularization rules
    $rules = [
        '/ies$/i' => 'y',       // categories -> category
        '/ves$/i' => 'f',       // leaves -> leaf
        '/oes$/i' => 'o',       // heroes -> hero
        '/(ss|x|ch|sh)es$/i' => '$1', // boxes -> box
        '/ses$/i' => 's',       // buses -> bus
        '/s$/i' => '',          // cats -> cat
    ];

    foreach ( $rules as $pattern => $replacement ) {
        if ( preg_match( $pattern, $string ) ) {
            return preg_replace( $pattern, $replacement, $string );
        }
    }

    return $string;
}

/**
 * Prints an indepth analysis of the given URL.
 * 
 * @param string $url
 */
function smliser_dump_url( $url ) : void {
    $dump   = ( new URL( $url ) )->dump();
    // Pretty print for debugging
    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.6; overflow-x: auto;">';
    echo '<strong style="color: #4ec9b0; font-size: 16px;">🔍 URL DEBUG DUMP</strong>' . "\n";
    echo str_repeat('─', 80) . "\n\n";
    
    // Full URL
    echo '<span style="color: #569cd6;">📌 FULL URL:</span> ' . "\n";
    echo '   <span style="color: #ce9178;">' . htmlspecialchars( $dump['url']['full_url'] ) . '</span>' . "\n\n";
    
    // Origin
    echo '<span style="color: #569cd6;">🌐 ORIGIN:</span> ' . "\n";
    echo '   <span style="color: #ce9178;">' . htmlspecialchars( $dump['url']['origin'] ?? 'N/A' ) . '</span>' . "\n\n";
    
    // Components
    echo '<span style="color: #569cd6;">🧩 COMPONENTS:</span>' . "\n";
    foreach ( $dump['components'] as $key => $value ) {
        $color = $value !== null ? '#b5cea8' : '#808080';
        $display = $value ?? '<span style="color: #808080; font-style: italic;">not set</span>';
        echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: %s;">%s</span>' . "\n", 
            str_pad( $key, 10 ), 
            $color, 
            $display 
        );
    }
    echo "\n";
    
    // Query Parameters
    echo '<span style="color: #569cd6;">🔗 QUERY PARAMETERS:</span>' . "\n";
    if ( empty( $dump['query_params'] ) ) {
        echo '   <span style="color: #808080; font-style: italic;">No query parameters</span>' . "\n";
    } else {
        foreach ( $dump['query_params'] as $key => $value ) {
            if ( is_array( $value ) ) {
                echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: #ce9178;">[%s]</span>' . "\n",
                    htmlspecialchars( $key ),
                    htmlspecialchars( implode( ', ', $value ) )
                );
            } else {
                echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: #ce9178;">%s</span>' . "\n",
                    htmlspecialchars( $key ),
                    htmlspecialchars( (string) $value )
                );
            }
        }
    }
    echo "\n";
    
    // Validation States
    echo '<span style="color: #569cd6;">✅ VALIDATION:</span>' . "\n";
    foreach ( $dump['validation'] as $key => $value ) {
        $icon = $value ? '✓' : '✗';
        $color = $value ? '#4ec9b0' : '#f48771';
        echo sprintf( '   <span style="color: %s;">%s</span> <span style="color: #9cdcfe;">%s</span>' . "\n",
            $color,
            $icon,
            str_replace( '_', ' ', $key )
        );
    }
    echo "\n";
    
    // Presence Checks
    echo '<span style="color: #569cd6;">🔎 PRESENCE CHECKS:</span>' . "\n";
    foreach ( $dump['has'] as $key => $value ) {
        $icon = $value ? '●' : '○';
        $color = $value ? '#4ec9b0' : '#808080';
        echo sprintf( '   <span style="color: %s;">%s</span> <span style="color: #9cdcfe;">has_%s</span>' . "\n",
            $color,
            $icon,
            $key
        );
    }
    echo "\n";
    
    // Raw Components
    echo '<span style="color: #569cd6;">⚙️  RAW COMPONENTS ARRAY:</span>' . "\n";
    echo '<span style="color: #808080;">';
    print_r( $dump['raw_components'] );
    echo '</span>';
    
    echo str_repeat('─', 80) . "\n";
    echo '</pre>';
}

/**
 * Normalize a value to an array when possible.
 *
 * - Calls toArray() or to_array() on objects when available
 * - Optionally falls back to get_object_vars()
 * - Returns arrays as-is
 * - Returns empty array for unsupported values
 *
 * @param mixed $value The value to normalize.
 * @param bool  $use_object_vars Whether to fallback to get_object_vars().
 * @return array
 */
function smliser_value_to_array( mixed $value, bool $use_object_vars = false ): array {
	if ( is_object( $value ) ) {
		if ( method_exists( $value, 'toArray' ) ) {
			return $value->toArray();
		}

		if ( method_exists( $value, 'to_array' ) ) {
			return $value->to_array();
		}

		if ( $use_object_vars ) {
			return get_object_vars( $value );
		}

		return [];
	}

	if ( is_array( $value ) ) {
		return $value;
	}

	return [];
}
