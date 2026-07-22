<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;

use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;

/**
 * The download response class
 */

class FileResponse extends Response {

    /**
     * The file request exception.
     * * NOTE: This property is used here to hold the specific FileRequestException 
     * instance for child-class access, complementing the parent's generic error handling.
     *
     * @var FileRequestException $error
     *
     */
    protected $error;

    /**
     * The filesystem instance
     *
     * @var FileSystem|Repository $repo_class The app's repository class instance.
     */
    protected FileSystem|Repository $repo_class;

    /**
     * The file.
     * 
     * @var string|Exception $file Absolute path to the file or the file string.
     */
    protected $file;

    /**
     * The resolved start byte for a ranged response.
     *
     * @var int
     */
    protected $range_start = 0;

    /**
     * The resolved end byte for a ranged response.
     *
     * @var int
     */
    protected $range_end = 0;

    /**
     * Whether the current request is a valid, honored Range request.
     *
     * @var bool
     */
    protected $is_range_request = false;

    /**
     * Class constructor.
     * 
     * @param string|FileRequestException $file Absolute path to the file, the file string or an instance of error.
     * @param string|array $args An associative array of options.
     */
    public function __construct( string|FileRequestException $file, $args = '' ) {
        parent::__construct();

        if (  $file instanceof FileRequestException ) {
            // Overrides `set_exception` which handles both the parent and local error state.
            $this->set_exception( $file ); 
        } else { 
            $this->file = $file;
        }

        $default_args = array(
            'is_file'       => true, // Treated as file by default, use false if it is binary.
            'name'          => '',  // File basename will be used as default file name, `untitled` is used when the file does not exist.
            'type'          => 'binary', // The valid default values are all registered app types and `binary`.
            'content_type'  => '', // The file mime type will be used by default.
        );

        $options        = \parse_args( (array) $args, $default_args );
        $download_type  = ! empty( $options['type'] ) ? (string) $options['type'] : 'binary';
        $app_registery  = HostedAppsRegistry::instance();

        try {
            if ( '' !== $download_type && in_array( $download_type, $app_registery->app_types() ) ) {
                $this->repo_class   = $app_registery->get_app_type_directory_class( $download_type );
            }            
        } finally {
            if ( ! isset( $this->repo_class ) ) {
                $this->repo_class = smliser_filesystem();
            }            
        }

        if ( $this->has_errors() ) {
            return; 
        }

        $filename   = ! empty( $options['name'] ) ? FileSystemHelper::sanitize_filename( $options['name'] ) : '';

        if ( (bool) $options['is_file'] ) {
            $this->parse_file( $filename, (string) $options['content_type'] );
        } else {
            $this->parse_binary( $filename, (string) $options['content_type'] );
        }
        
    }

    /**
     * Get the file path
     * 
     * @return string|Exception
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * Get the filesystem instance.
     * 
     * @return \SmartLicenseServer\FileSystem\Repository The app's repository class instance.
     */
    public function get_fs() {
        return $this->repo_class;
    }
    
    /**
     * Checks if the response currently contains any errors.
     * Overrides the parent to check both the parent's accumulated error state
     * and the child's specialized FileRequestException instance.
     * * @return bool
     */
    public function has_errors(): bool {
        if ( $this->error instanceof FileRequestException ) {
            return $this->error->has_errors();
        }

        if ( parent::has_errors() ) {
            return true;
        }

        return false;
    }

    /**
     * Parse the file property and set up other props.
     * 
     * @param string $file_name The file name (optional).
     * @param string $content_type The file mime content type (optional).
     */
    protected function parse_file( $file_name = '', $content_type = '' ) {

        if ( ! $this->repo_class ) {
            $this->set_exception( new FileRequestException( 'unsupported_repo_type' ) );
            return;
        }

        if ( ! $this->repo_class->exists( $this->file ) ) {
            $this->set_exception( new FileRequestException( 'file_not_found' ) );
            return;
        }

        if ( ! $this->repo_class->is_readable( $this->file ) ) {
            $this->set_exception( new FileRequestException( 'file_reading_error', 'File not readable' ) );
            return;
        }

        if ( $this->has_errors() ) {
            return;
        }

        $file_size      = (int) $this->repo_class->filesize( $this->file );
        $mtime          = (int) $this->repo_class->filemtime( $this->file );
        $last_modified  = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', $mtime ) );
        $etag           = sprintf( '"%s"', md5( $file_size . $last_modified ) );
        $content_type   = $content_type ?: FileSystemHelper::get_mime_type( $this->file );

        $this->set_default_headers();

        $this->set_header( 'Last-Modified', $last_modified );
        $this->set_header( 'ETag', $etag );
        $this->set_header( 'Content-Type', $content_type );
        $this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name ) );
        $this->set_header( 'Accept-Ranges', 'bytes' );

        $this->set_range_headers( $file_size, $etag, $mtime );

    }

    /**
     * Parse a raw binary file string for download.
     * 
     * @param string $file_name The name of the file.
     * @param string $content_type The file mime content type
     */
    public function parse_binary( string $file_name, string $content_type ) {
        $file_size = strlen( $this->file );

        if ( ! static::is_filesize_within_limit( $file_size ) ) {
            $this->set_exception( new FileRequestException( 'file_too_large' ) );
        }

        if ( $this->has_errors() ) {
            return;
        }

        // Content is generated/held in memory, so its "freshness" is tied to this
        // request only — an ETag is still useful for If-Range but there is no
        // meaningful Last-Modified, so date-based If-Range checks fail closed
        // (see passes_if_range()) and always fall back to a full response.
        $etag = sprintf( '"%s"', md5( $this->file ) );

        $this->set_default_headers();
        $this->set_header( 'Content-Type', $content_type );
        $this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name, $content_type ) );
        $this->set_header( 'Accept-Ranges', 'bytes' );
        $this->set_header( 'ETag', $etag );

        $this->set_range_headers( $file_size, $etag, null );

        if ( $this->has_errors() ) {
            return; // 416 already set; don't touch the body.
        }

        if ( $this->is_range_request ) {
            $length = $this->range_end - $this->range_start + 1;
            $this->set_body( substr( $this->file, $this->range_start, $length ) );
        } else {
            $this->set_body( $this->file );
        }
        
    }

    /**
     * Set default headers for file download.
     */
    public function set_default_headers() {
        // Set default headers, can be overwritten later...
        $last_modified  = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s' ) );
        
        $this->set_header( 'Expires', '0' )
        ->set_header( 'Cache-Control', 'private, must-revalidate, max-age=0' )
        ->set_header( 'Last-Modified', $last_modified )
        ->set_header( 'Date', gmdate('D, d M Y H:i:s \G\M\T') )
        ->set_header( 'Content-Transfer-Encoding', 'Binary' )
        ->set_header( 'X-Content-Type-Options', 'nosniff' )
        ->set_header( 'X-Robots-Tag', 'noindex, nofollow' )
        ->set_header( 'Content-Description', 'File Transfer' )
        ->set_header( 'Pragma', 'Public' )
        ->set_header( 'ETag', "" )
        ->set_header( 'Content-Type', 'text/plain' )
        ->set_header( 'Content-Length', '0' );

    }

    /**
     * Determine whether an incoming If-Range condition allows honoring the Range header.
     *
     * Per RFC 7233 §3.2: If-Range may carry either an ETag or an HTTP-date.
     * If the value doesn't match the resource's current validator, the Range
     * header must be ignored and the full entity served instead.
     *
     * When no If-Range header is present, the condition is trivially satisfied
     * and the Range header (if any) is honored as normal.
     *
     * @param string   $etag  The resource's current ETag, including surrounding quotes.
     * @param int|null $mtime The resource's current modification time as a Unix timestamp,
     *                        or null when no meaningful Last-Modified exists (e.g. in-memory
     *                        binaries) — a date-based If-Range then always fails closed.
     * @return bool True if Range may be honored, false if it must be ignored.
     */
    protected function passes_if_range( string $etag, ?int $mtime ): bool {
        $if_range = smliser_request()->get_header( 'if-range' );

        if ( empty( $if_range ) ) {
            return true;
        }

        $if_range = trim( $if_range );

        // HTTP-date form: only valid if it parses AND looks like an actual HTTP-date
        // (avoids treating a loosely-quoted ETag as a date via strtotime() false positives).
        if ( preg_match( '/^[A-Za-z]{3},\s\d{2}\s[A-Za-z]{3}\s\d{4}\s\d{2}:\d{2}:\d{2}\sGMT$/', $if_range ) ) {
            if ( null === $mtime ) {
                return false;
            }

            $timestamp = strtotime( $if_range );

            return false !== $timestamp && $timestamp === $mtime;
        }

        // Otherwise, treat as an ETag comparison (strong comparison — exact match required).
        $normalize = static function( string $value ): string {
            return trim( trim( $value ), '"' );
        };

        return $normalize( $if_range ) === $normalize( $etag );
    }

    /**
     * Parse the Range header (if present) against the file size and set
     * the appropriate 206/416 headers and status code.
     *
     * If an If-Range header is present and does not match the resource's
     * current ETag/Last-Modified, the Range header is ignored entirely and
     * a normal full (200) response is prepared instead.
     *
     * @param int      $file_size Total size of the file/content in bytes.
     * @param string   $etag      The resource's current ETag (quoted).
     * @param int|null $mtime     The resource's current mtime as a Unix timestamp, or null if not applicable.
     */
    protected function set_range_headers( int $file_size, string $etag = '', ?int $mtime = null ) {
        $this->range_start  = 0;
        $this->range_end    = $file_size - 1;
        $range              = smliser_request()->get_header( 'range' );

        if ( empty( $range ) ) {
            $this->set_header( 'Content-Length', (string) $file_size );
            return;
        }

        if ( ! $this->passes_if_range( $etag, $mtime ) ) {
            // Condition failed — ignore Range, serve the full entity as a normal 200.
            $this->set_header( 'Content-Length', (string) $file_size );
            return;
        }

        if ( ! preg_match( '/bytes=(\d*)-(\d*)/', $range, $matches ) ) {
            $this->set_status_code( 416 );
            $this->set_header( 'Content-Range', "bytes */{$file_size}" );
            $this->set_exception( new FileRequestException( 'invalid_range' ) );
            return;
        }

        $start = ( '' !== $matches[1] ) ? (int) $matches[1] : null;
        $end   = ( '' !== $matches[2] ) ? (int) $matches[2] : null;

        // Suffix range, e.g. "bytes=-500" (last 500 bytes).
        if ( null === $start && null !== $end ) {
            $start = (int) max( 0, $file_size - $end );
            $end   = $file_size - 1;
        } else {
            $start = $start ?? 0;
            $end   = $end ?? ( $file_size - 1 );
        }

        if ( $start > $end || $start >= $file_size || $end >= $file_size ) {
            $this->set_status_code( 416 );
            $this->set_header( 'Content-Range', "bytes */{$file_size}" );
            $this->set_exception( new FileRequestException( 'invalid_range' ) );
            return;
        }

        $this->range_start      = $start;
        $this->range_end        = $end;
        $this->is_range_request = true;

        $this->set_status_code( 206 );
        $this->set_header( 'Content-Range', "bytes {$start}-{$end}/{$file_size}" );
        $this->set_header( 'Content-Length', (string) ( $end - $start + 1 ) );
    }

    /**
     * Determines the Content-Disposition header value based on MIME type and
     * whether inline display is explicitly requested.
     *
     * @param string $fileName The name of the file.
     * @param string $mimeType  The file mime content type.
     * @param bool   $isInlineRequested Whether inline display was explicitly requested.
     * @return string The formatted Content-Disposition header value.
     */
    public function get_content_disposition( string $fileName = '', $mimeType = '', bool $isInlineRequested = false ): string {
        if ( $this->has_errors() ) {
            return '';
        }
        
        $mimeType = $mimeType ?: FileSystemHelper::get_mime_type( $this->file );
        $fileName = $fileName ?: basename( $this->file );

        $can_render_inline = false;
        if ( FileSystemHelper::is_image( $this->file ) ) {
            $can_render_inline = true;
        }
        

        $disposition        = ( $isInlineRequested && $can_render_inline ) ? 'inline' : 'attachment';
        $encodedFilename    = rawurlencode( $fileName );

        // RFC 6266 format for universal compatibility (handles both ASCII and Unicode).
        $headerValue = sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition,
            // Fallback for older clients (uses the raw filename, best effort).
            $fileName,
            $encodedFilename
        );

        return $headerValue;
    }

    /**
     * Sends the file or error to the client
     */
    public function send_body() : void {
        $this->download();
    }

    /**
     * Reads and sends the content of the file to the client.
     */
    public function download() {
        if ( ! empty( $this->get_body() ) ) {
            echo $this->get_body(); // phpcs:ignore
            $this->trigger_after_serve_callbacks();
            exit;
        }

        if ( $this->is_range_request ) {
            $length = $this->range_end - $this->range_start + 1;
            $this->repo_class->readfile( $this->file, $this->range_start, $length );
        } else {
            $this->repo_class->readfile( $this->file );
        }

        $this->trigger_after_serve_callbacks();
        exit;
    }

    /**
     * Sets the file request error/exception.
     * * Overrides parent to ensure both the parent's generic error container 
     * and the child's specialized error property are updated.
     * * @param Exception|FileRequestException $error
     */
    public function set_exception( Exception $error ) : static {
        $this->error = $error;
        
        return parent::set_exception( $this->error );
    }

    /**
     * Tells whether the file is a valid zip file.
     * 
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_zip_file() : bool {
        $file   = $this->get_file();

        if ( $file instanceof Exception || '' === $file ) {
            return false;
        }

        if ( method_exists( $this->repo_class, 'is_valid_zip' ) ) {
            return $this->repo_class->is_valid_zip( $file );
        }

        return FileSystemHelper::is_archive( $file );
    }

    /**
     * Determine if a file size is within safe limits for server processing and download.
     *
     * This check accounts for PHP memory_limit, upload_max_filesize, and post_max_size,
     * applying an optional safety margin. It also attempts lightweight memory allocation
     * to detect imminent OOM conditions.
     *
     * @param int|string $filesize Size in bytes.
     * @param float|null $factor   Optional safety factor (e.g. 0.8 = 80% of memory limit).
     *
     * @return bool True if file can be safely handled, false otherwise.
     */
    public static function is_filesize_within_limit( $filesize, ?float $factor = 0.8 ): bool {
        $filesize = (int) $filesize;

        // Reject invalid or zero-byte input.
        if ( $filesize <= 0 ) {
            return false;
        }

        // Normalize ini values to bytes.
        $memory_limit = static::to_bytes( ini_get( 'memory_limit' ) );
        $upload_limit = static::to_bytes( ini_get( 'upload_max_filesize' ) );
        $post_limit   = static::to_bytes( ini_get( 'post_max_size' ) );

        // Handle unlimited memory (-1 means no limit)
        if ( $memory_limit < 0 ) {
            $memory_limit = PHP_INT_MAX;
        }

        if ( $memory_limit === 0 ) {
            $memory_limit = 256 * 1024 * 1024; // 256MB reasonable fallback.
        }
        if ( $upload_limit === 0 ) {
            $upload_limit = $memory_limit;
        }
        if ( $post_limit === 0 ) {
            $post_limit = $memory_limit;
        }

        // Determine the most restrictive limit.
        $max_safe_size = min( $upload_limit, $post_limit, $memory_limit );

        // Apply safety margin.
        $factor = (float) $factor;
        if ( $factor > 0 && $factor < 1 ) {
            $max_safe_size = (int) ( $max_safe_size * $factor );
        }

        // Reject file if it exceeds computed threshold
        if ( $filesize > $max_safe_size ) {
            return false;
        }

        // Perform a lightweight memory allocation test
        return static::try_memory_allocation( $filesize );
    }

    /**
     * Convert shorthand memory notation like 128M, 1G, etc. to bytes.
     *
     * @param string|int $val
     * @return int
     */
    protected static function to_bytes( $val ): int {
        if ( is_numeric( $val ) ) {
            return (int) $val;
        }

        $val  = trim( (string) $val );
        if ( $val === '' ) {
            return 0;
        }

        $last = strtolower( substr( $val, -1 ) );
        $num  = (float) $val;

        switch ( $last ) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
                break;
        }

        return (int) $num;
    }

    /**
     * Attempt lightweight memory allocation to verify runtime safety.
     *
     * @param int $filesize
     * @return bool
     */
    protected static function try_memory_allocation( int $filesize ): bool {
        $test_size = (int) min( $filesize, 2 * 1024 * 1024 ); // max 2MB allocation
        try {
            $buffer = str_repeat( '0', $test_size );
            unset( $buffer );
            return true;
        } catch ( \Throwable $e ) {
            return false;
        }
    }
    
}