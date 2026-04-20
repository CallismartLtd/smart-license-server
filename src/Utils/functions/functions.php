<?php
/**
 * File name smliser-functions.php
 * Utility functions file
 * 
 * @author Callistus
 * @since 0.2.0
 * @package Smliser\functions
 */

use SmartLicenseServer\Environment;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Http\HttpClient;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Security\Context\IdentityProviderInterface;
use SmartLicenseServer\Utils\MDParser;
use SmartLicenseServer\Utils\Sanitizer;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Check whether debug mode is enabled.
 *
 * @return bool
 */
function smliser_debug_enabled() : bool {
    return ( defined( 'APP_DEBUG' ) && APP_DEBUG ) || false;
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
 * @return string
 */
function smliser_get_client_ip() : string {
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
        if ( empty( $_SERVER[ $key ] ) ) {
            continue;
        }

        // In case of multiple IPs, we take the first one (usually the client IP).
        $ip_list = explode( ',', Sanitizer::sanitize_text( unslash( $_SERVER[ $key ] ) ) );
        foreach ( $ip_list as $ip ) {
            $ip = trim( $ip );
            // Validate both IPv4 and IPv6 addresses.
            if ( filter_var( $ip, FILTER_VALIDATE_IP, array( FILTER_FLAG_NO_RES_RANGE, FILTER_FLAG_IPV4, FILTER_FLAG_IPV6 ) ) ) {
                return $ip;
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
 * Sanitize and normalize a file path to prevent directory traversal attacks.
 *
 * @param string $path The input path.
 * @return string|SmartLicenseServer\Exception The sanitized and normalized path, or Exception on failure.
 */
function smliser_sanitize_path( $path ) {
    return FileSystemHelper::sanitize_path( $path );
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
 * 
 * @param string $param The name of the POST parameter.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the POST parameter.
 */
function smliser_get_post_param( $param, $default = '' ) {
    return smliser_get_param( $param, $default, $_POST );
}

/**
 * Get the value of a $_REQUEST parameter.
 * @param string $param The name of the $_REQUEST parameter.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the $_REQUEST parameter.
 */
function smliser_get_request_param( $param, $default = '' ) {
    return smliser_get_param( $param, $default, $_REQUEST );
}

/**
 * Get the value of a $_FILES key.
 * 
 * @param string $key The key of the $_FILES to look for.
 * @param mixed $default The default value to return.
 * @return mixed The sanitized value of the POST parameter.
 */
function smliser_get_files_param( $key, $default = null ) {
    return $_FILES[$key] ?? null;
}


/**
 * Retrieve and sanitize a parameter from a given source array, with automatic type detection.
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
        return array_map( [Sanitizer::class, 'sanitize_text'], $value );
    }

    if ( is_numeric( $value ) ) {
        return ( strpos( $value, '.' ) !== false ) ? floatval( $value ) : intval( $value );
    }

    $lower = strtolower( $value );
    if ( in_array( $lower, [ 'true', 'false', '1', '0', 'yes', 'no' ], true ) ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    if ( is_email( $value ) ) {
        return Sanitizer::sanitize_email( $value );
    }

    if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
        return smliser_sanitize_url( $value );
    }

    return Sanitizer::sanitize_text( $value );
}

/**
 * Check whether a key is set in the query param
 * 
 * @param string $key The ky to check
 * @return bool True when found, false otherwise.
 */
function smliser_has_query_param( $key ) {
    return array_key_exists( $key, $_GET );
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
 * Unified download function.
 *
 * Attempts WordPress download_url, Laravel HTTP client, cURL, or PHP fopen/file_get_contents.
 *
 * @param string|URL    $url        URL to download.
 * @param int           $timeout    Timeout in seconds (default: 30).
 * @param bool          $autoclean  Whether to automatically delete the downloaded file(Default: true).
 * @return string|FileRequestException
 */
function smliser_download_url( string|URL $url, $timeout = 30, bool $autoclean = true ) : string|FileRequestException {
    try {
        $url  = is_string( $url ) ? new URL( $url ) : $url;
        // Validate URL.
        if ( ! $url->is_valid() ) {
            throw new FileRequestException( 'invalid_url', 'Invalid URL provided.' );
        }

        $url        = $url->url();
        $options    = [
            'timeout'   => max( 1, (int) $timeout )
        ];

        $destination    = sprintf( '%s/%s', SMLISER_TMP_DIR, uniqid( SMLISER_UPLOAD_TMP_PREFIX ) );

        $response    = smliser_http_client()->download( $url, $destination, [], $options );

        if ( $response->is_error() ) {
            throw new FileRequestException(
                'http_download_error',
                $response->reason_phrase,
                ['status' => $response->status_code]
            );
        }

        if ( ! $response->is_download() ) {
            throw new FileRequestException(
                'http_download_missing_file',
                'Downloaded file was corrupted',
                ['status' => 400]
            );
        }

        if ( $autoclean ) {
            register_shutdown_function( function() use ( $response ){
                @unlink( $response->sink_path );
            });            
        }

        return $response->sink_path;

    } catch ( InvalidArgumentException $e ) {
        return new FileRequestException(
            'malformed_request',
            $e->getMessage()
        );
    } catch ( FileRequestException $e ) {
        return $e;
    }
}

/**
 * Get the environment provider instance.
 * 
 * @return \SmartLicenseServer\Environment
 */
function smliser_envProvider() : Environment {
    return Environment::envProvider();
}

/**
 * Get the database API instance.
 *
 * @return \SmartLicenseServer\Database\Database Singleton instance of the Database class.
 */
function smliser_db() : \SmartLicenseServer\Database\Database {
    return smliser_envProvider()->database();
}

/**
 * Get the filesystem abstraction class
 * 
 * @return FileSystem
 */
function smliser_filesystem() : FileSystem {
    return smliser_envProvider()->filesystem();
}

/**
 * Return the global job queue manager instance.
 *
 * Usage:
 *   smliser_job_queue()->dispatch( JobDTO::make( ... ) );
 *   smliser_job_queue()->find_job( $id );
 *
 * @return \SmartLicenseServer\Background\Queue\JobQueue
 */
function smliser_job_queue(): \SmartLicenseServer\Background\Queue\JobQueue {
    return smliser_envProvider()->job_queue();
}
 
/**
 * Return the global queue worker instance.
 *
 * Usage:
 *   smliser_queue_worker()->process_next_job();
 *   smliser_queue_worker()->start_processing();
 *
 * @return \SmartLicenseServer\Background\Workers\QueueWorker
 */
function smliser_queue_worker(): \SmartLicenseServer\Background\Workers\QueueWorker {
    return smliser_envProvider()->queue_worker();
}

/**
 * Get the settings API singleton class.
 * 
 * @return SmartLicenseServer\SettingsAPI\Settings
 */
function smliser_settings() : SmartLicenseServer\SettingsAPI\Settings {
    return smliser_envProvider()->settings();
}

/**
 * Get the cache singleton instance.
 *
 * @return \SmartLicenseServer\Cache\Cache Singleton instance of the Cache class.
 */
function smliser_cache() : \SmartLicenseServer\Cache\Cache {
    return smliser_envProvider()->cache();
}

/**
 * Get the mailer API
 * 
 * @return \SmartLicenseServer\Email\Mailer
 */
function smliser_mailer() : Mailer {
    return smliser_envProvider()->mailer();
}

/**
 * Return the global Scheduler instance.
 *
 * Lazy-loaded — the scheduler and all its tasks are only instantiated
 * when this function is first called. Has zero cost on requests that
 * never touch the scheduler.
 *
 * Usage:
 *   smliser_scheduler()->run_due_tasks();
 *   smliser_scheduler()->call( $fn )->daily_at( '02:00' );
 *   smliser_scheduler()->get_tasks_with_state();
 *
 * @return \SmartLicenseServer\Background\Schedule\Scheduler
 */
function smliser_scheduler(): \SmartLicenseServer\Background\Schedule\Scheduler {
    return smliser_envProvider()->scheduler();
}

/**
 * Get the http client instance.
 * 
 * @return \SmartLicenseServer\Http\HttpClient
 */
function smliser_http_client() : HttpClient {
    return smliser_envProvider()->httpClient();
}

/**
 * Get the monetization registry instance.
 */
function smliser_monetization_registry() : MonetizationRegistry {
    return smliser_envProvider()->monetizationRegistry();
}

/**
 * Email providers registry instance.
 */
function smliser_emailProvidersRegistry() : EmailProvidersRegistry {
    return smliser_envProvider()->emailProviders();
}

/**
 * Get the current request object.
 */
function smliser_request() : Request {
    return smliser_envProvider()->request();
}

/**
 * Get the identity provider.
 */
function identityProvider() : IdentityProviderInterface {
    return smliser_envProvider()->identityProvider();
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
        'api-key', 'apikey', 'api'  => 'assets/images/api-key.svg',
        'org', 'organization'       => 'assets/images/organization.svg',
        default                     => 'assets/images/software-placeholder.svg',
    };

    return $cache[ $type ] = sprintf(
        '%s/%s',
        $base_url,
        $relative_path
    );
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
        'child'         => 'children',
        'person'        => 'people',
        'man'           => 'men',
        'woman'         => 'women',
        'tooth'         => 'teeth',
        'foot'          => 'feet',
        'mouse'         => 'mice',
        'goose'         => 'geese',
        'ox'            => 'oxen',
        'quiz'          => 'quizzes',
        'axis'          => 'axes',
        'analysis'      => 'analyses',
        'basis'         => 'bases',
        'crisis'        => 'crises',
        'thesis'        => 'theses',
        'phenomenon'    => 'phenomena',
        'criterion'     => 'criteria',
        'datum'         => 'data',
        'organization'  => 'organizations',
        'organizations' => 'organizations',
    ];

    if ( isset( $irregulars[ $lower ] ) ) {
        return smliser_preserve_case( $string, $irregulars[ $lower ] );
    }

    // Uncountable nouns (return as-is)
    $uncountable = [
        'information', 'equipment', 'rice', 'money', 'species', 'series',
        'fish', 'sheep', 'deer', 'moose', 'aircraft', 'software', 'hardware',
        'data', 'news', 'advice', 'furniture', 'luggage', 'evidence', 'users',
        'houses'
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

/**
 * Helper: Check if array contains only scalar values
 */
function smliser_is_scalar_array( $array ) {
    if ( empty( $array ) || ! is_array( $array ) ) {
        return false;
    }
    return count( array_filter( $array, 'is_array' ) ) === 0;
}

/**
 * Global translation helper.
 */
function smliser__( string $text, string $domain = 'default' ): string {
    return $text; //@TODO: Build a translation engine.
}