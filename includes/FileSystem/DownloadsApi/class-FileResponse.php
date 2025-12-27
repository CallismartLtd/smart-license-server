<?php
/**
 * Resource download handler file
 * * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;

use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

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
     * @var \SmartLicenseServer\FileSystem\PluginRepository|\SmartLicenseServer\FileSystem\ThemeRepository|\SmartLicenseServer\FileSystem\SoftwareRepository|\SmartLicenseServer\Filesystem|null $repo_class The app's repository class instance.
     */
    protected $repo_class;

    /**
     * The file.
     * 
     * @var string|Exception $file Absolute path to the file or the file string.
     */
    protected $file;

    /**
     * Class constructor.
     * 
     * @param string|FileRequestException $file Absolute path to the file, the file string or an instance of error.
     * @param string|array $args An associative array of options.
     */
    public function __construct( $file, $args = '' ) {
        parent::__construct();

        if ( \is_smliser_error( $file ) ) {
            // This calls the overridden set_exception which handles both the parent and local error state.
            $this->set_exception( $file ); 
        }
            
        $this->file    = $file;

        $default_args = array(
            'is_file'       => true, // Treated as file by default, use false if it is a document string.
            'name'          => '',  // File basename will be used as default file name, `untitled` is used when the file does not exist.
            'type'          => '', // The valid default values are `plugin`, `theme`, `software`, and `document`, except there is a corresponding repository class to handle it.
            'content_type'  => '', // The file mime type will be used by default.
        );

        $options = \parse_args( (array) $args, $default_args );

        if ( ! empty( $options['type'] ) ) {
            $this->repo_class   = HostedApplicationService::get_app_repository_class( (string) $options['type'] );
        } else {
            $this->repo_class = FileSystem::instance();
        }
        
        if ( $this->has_errors() ) {
            return; 
        }

        if ( (bool) $options['is_file'] ) {
            $this->parse_file( (string) $options['name'], (string) $options['content_type'] );
        } else {
            $this->parse_document( (string) $options['name'], (string) $options['content_type'] );
        }
        
    }

    /**
     * Get the file path
     * * @return string|Exception
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * Get the filesystem instance.
     * * @return \SmartLicenseServer\PluginRepository|\SmartLicenseServer\ThemeRepository|\SmartLicenseServer\SoftwareRepository|\SmartLicenseServer\Filesystem|null $repo_class The app's repository class instance.
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
        }

        if ( ! $this->repo_class->exists( $this->file ) ) {
            $this->set_exception( new FileRequestException( 'file_not_found' ) );
        }

        if ( ! $this->repo_class->is_readable( $this->file ) ) {
            $this->set_exception( new FileRequestException( 'file_reading_error' ) );
        }

        if ( $this->has_errors() ) {
            return;
        }

        $file_size      = $this->repo_class->filesize( $this->file );
        $last_modified  = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', $this->repo_class->filemtime( $this->file ) ) );
        $content_type   = $content_type ?: FileSystemHelper::get_mime_type( $this->file );

        $this->set_default_headers();
        
        $this->set_header( 'Last-Modified', $last_modified );
        $this->set_header( 'ETag', sprintf( ' "%s"', md5( $file_size . $last_modified ) ) );

        $this->set_header( 'Content-Length', $file_size );
        $this->set_header( 'Content-Type', $content_type );
        $this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name ) );

    }

    /**
     * Parse a raw document file that is not on filesystem.
     * 
     * @param string $file_name The name of the file.
     * @param string $content_type The file mime content type
     */
    public function parse_document( $file_name, $content_type ) {
        $file_size = strlen( $this->file );

        if ( ! self::is_filesize_within_limit( $file_size ) ) {
            $this->set_exception( new FileRequestException( 'file_too_large' ) );
        }

        if ( $this->has_errors() ) {
            return;
        }

        $this->set_body( $this->file );
        $this->set_default_headers();
        $this->set_header( 'Content-Length', $file_size );
        $this->set_header( 'Content-Type', $content_type );
        $this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name, $content_type ) );
        
    }

    /**
     * Set default headers for file download.
     */
    public function set_default_headers() {
        // Set default headers, can be overwritten later...
        $last_modified  = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s' ) );
        
        $this->set_header( 'Expires', 0 );
        $this->set_header( 'Cache-Control', 'private, must-revalidate, max-age=0' );
        $this->set_header( 'Last-Modified', $last_modified );
        $this->set_header( 'Date', gmdate('D, d M Y H:i:s \G\M\T') );
        $this->set_header( 'Content-Transfer-Encoding', 'Binary' );
        $this->set_header( 'X-Content-Type-Options', 'nosniff' );
        $this->set_header( 'X-Robots-Tag', 'noindex, nofollow' );
        $this->set_header( 'Content-Description', 'File Transfer' );
        $this->set_header( 'Pragma', 'Public' );
        $this->set_header( 'ETag', "" );
        $this->set_header( 'Content-Type', 'text/plain' );
        $this->set_header( 'Content-Length', 0 );

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
        $mimeType = $mimeType ?: FileSystemHelper::get_mime_type( $this->file );
        $fileName = $fileName ?: basename( $this->file );
        
        $renderable_prefixes    = [
            'image/',          // JPG, PNG, GIF, SVG, WEBP
            'text/plain',      // TXT, LOG
            'text/html',
            'application/pdf', // PDFs (Browser support is high)
            'video/',          // MP4, WebM (Modern browser video support)
            'audio/',          // MP3, WAV (Modern browser audio support)
        ];

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
    public function send_body() {
        return $this->download();
    }

    /**
     * Reads and sends the content of the file to the client.
     */
    public function download() {
        if ( ! empty( $this->get_body() ) ) {
            echo $this->get_body(); // phpcs:ignore
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
    public function set_exception( Exception $error ) : self{
        $this->error = $error;
        
        // This is crucial for parent::send() to output the correct status code and message.
        return parent::set_exception( $this->error );
    }

    /**
     * Tells whether the file is a valid zip file.
     * 
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_zip_file() : bool {
        if ( method_exists( $this->repo_class, 'is_valid_zip' ) ) {
            return $this->repo_class->is_valid_zip( $this->get_file() );
        }

        return FileSystemHelper::is_archive( $this->get_file() );
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

        // Reject invalid or zero-byte input
        if ( $filesize <= 0 ) {
            return false;
        }

        // Normalize ini values to bytes
        $memory_limit = self::to_bytes( ini_get( 'memory_limit' ) );
        $upload_limit = self::to_bytes( ini_get( 'upload_max_filesize' ) );
        $post_limit   = self::to_bytes( ini_get( 'post_max_size' ) );

        // Handle unlimited memory (-1 means no limit)
        if ( $memory_limit < 0 ) {
            $memory_limit = PHP_INT_MAX;
        }

        // Sanitize and replace zero/invalid limits with large fallback
        if ( $memory_limit === 0 ) {
            $memory_limit = 256 * 1024 * 1024; // 256MB reasonable fallback
        }
        if ( $upload_limit === 0 ) {
            $upload_limit = $memory_limit;
        }
        if ( $post_limit === 0 ) {
            $post_limit = $memory_limit;
        }

        // Determine the most restrictive limit
        $max_safe_size = min( $upload_limit, $post_limit, $memory_limit );

        // Apply safety margin
        $factor = (float) $factor;
        if ( $factor > 0 && $factor < 1 ) {
            $max_safe_size = (int) ( $max_safe_size * $factor );
        }

        // Reject file if it exceeds computed threshold
        if ( $filesize > $max_safe_size ) {
            return false;
        }

        // Perform a lightweight memory allocation test
        return self::try_memory_allocation( $filesize );
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