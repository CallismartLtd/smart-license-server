<?php
/**
 * The URL functions API.
 */

use Callismart\Http\Exceptions\HttpRequestException;
use Callismart\Http\HttpClient;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Get the web application URL.
 * 
 * @param string $path Path(optional).
 * @param array<string, string> $params Associative array of query params.
 * @return URL
 */
function url( string $path = '', array $params = [] ) : URL {
    $url = (string) ( $_ENV['SMLISER_APP_URL'] ?? '' );

    return URL::from( $url )
        ->append_path( $path )
        ->add_query_params( $params );
}

/**
 * Get the URL origin.
 *
 * @param string $url The URL to parse.
 * @return string The base website address.
 */
function smliser_url_origin( string $url ) {
    return URL::from( $url )->get_origin();
}

/**
 * Get current page URL.
 *
 * Returns an empty string if it cannot generate a URL.
 * @return \SmartLicenseServer\Core\URL
 */
function smliser_get_current_url() : URL {
    static $current_url    = null;
    
    if ( is_null( $current_url ) ) {
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $path   = parse_url( $uri, PHP_URL_PATH );
        $params = parse_url( $uri, PHP_URL_QUERY ) ?: '';
        
        parse_str( $params, $query );
        $current_url = url( $path, $query );
    }

    return $current_url;
}

/**
 * Sanitize the given URL
 * 
 * @param string $url
 */
function smliser_sanitize_url( $url ) : string {
    return URL::from( $url )->sanitize()->url();
}


/**
 * Download the given URL to a local temp file.
 *
 * @param string|URL    $url        URL to download.
 * @param int           $timeout    Timeout in seconds (default: 30).
 * @param bool          $autoclean  Whether to automatically delete the downloaded file(Default: true).
 * @return string|FileRequestException
 */
function smliser_download_url( string|URL $url, int $timeout = 30, bool $autoclean = true ) : string|FileRequestException {
    try {
        $url  = is_string( $url ) ? URL::from( $url ) : $url;
        // Validate URL.
        if ( ! $url->is_valid() ) {
            throw new FileRequestException( 'invalid_url', 'Invalid URL provided.' );
        }

        $url        = $url->url();
        $options    = [
            'timeout'   => max( 1, $timeout )
        ];

        $destination    = sprintf( '%s/%s', SMLISER_TMP_DIR, uniqid( SMLISER_UPLOAD_TMP_PREFIX ) );
        $best_client    = HttpClient::auto_client();
        $response       = ( new HttpClient( $best_client ) )->download( $url, $destination, [], $options );

        if ( $response->is_error() ) {
            $code   = match( $response->status_code ) {
                400     => 'http_file_download_bad_request',
                401     => 'http_file_download_unauthorized',
                402     => 'payment_required',
                403     => 'user_not_authorized',
                404     => 'file_not_found',
                422     => 'file_integrity_failure',
                default => 'unknown_file_error' // 500 - 599.
            };

            throw new FileRequestException( $code, null, ['status' => $response->status_code] );
        }

        if ( ! $response->is_download() ) {
            throw new FileRequestException( 'file_integrity_failure' );
        }

        if ( $autoclean ) {
            register_shutdown_function( function() use ( $response ){
                @unlink( $response->sink_path );
            });            
        }

        return $response->sink_path;

    } catch ( InvalidArgumentException|HttpRequestException $e ) {
        return new FileRequestException(
            'malformed_request',
            $e->getMessage()
        );
    } catch ( FileRequestException $e ) {
        return $e;
    }
}