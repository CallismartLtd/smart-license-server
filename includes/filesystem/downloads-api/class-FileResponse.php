<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\DownloadsApi;

use SmartLicenseServer\Core\Response;
use SmartLicenseServer\FileSystem;
use SmartLicenseServer\FileSystemHelper;
use SmartLicenseServer\Exception;

defined( 'SMLISER_PATH' ) || exit;

/**
 * The download response class
 */

class FileResponse extends Response {
    /**
     * The filesystem instance
     *
     * @var \SmartLicenseServer\PluginRepository|\SmartLicenseServer\ThemeRepository|\SmartLicenseServer\SoftwareRepository|\SmartLicenseServer\Filesystem|null $repo_class The app's repository class instance.
     */
    protected $repo_class;

    /**
     * The response body
     * 
     * @var string $file_path
     */
    protected $file_path;

    /**
     * Class constructor.
     * 
     * @param string|Exception $file_path
     */
    public function __construct( $file_path, $app_type = '' ) {
        parent::__construct();

        
        if ( \is_smliser_error( $file_path ) ) {
            $this->set_exception( $file_path );
        }
            
        $this->file_path    = $file_path;

        if ( ! empty( $app_type ) ) {
            $this->repo_class   = \Smliser_Software_Collection::get_app_repository_class( $app_type );
        } else {
            $this->repo_class = FileSystem::instance();
        }
        
        $this->parse_file();
    }

    /**
     * Parse the provided file and manually sets up the parent class.
     */
    protected function parse_file() {
        if ( \is_smliser_error( $this->file_path ) ) {
            return;
        }

        if ( ! $this->repo_class ) {
            $this->error->add( 
                'unsupported_app_type',
                __( 'This application type is not supported.', 'smliser' ),
                array(
                    'status'    => 400,
                    'title'     => 'Unsupported Type',
                )
            );
        }

        if ( ! $this->repo_class->exists( $this->file_path ) ) {
            $this->error->add( 
                'file_not_found',
                __( 'The requested application\'s file was not found.', 'smliser' ),
                array(
                    'status'    => 404,
                    'title'     => 'File Not Found',
                )
            );
        }

        if ( ! $this->repo_class->is_readable( $this->file_path ) ) {
            $this->error->add( 
                'file_not_readable',
                __( 'The requested application\'s file is not readable.', 'smliser' ),
                array(
                    'status'    => 404,
                    'title'     => 'File Reading Error',
                )
            );
        }

        $file_size      = $this->repo_class->filesize( $this->file_path );
        $last_modified  = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', $this->repo_class->filemtime( $this->file_path ) ) );

        // Set default headers...
        $this->set_header( 'Expires', 0 );
        $this->set_header( 'Cache-Control', 'private, must-revalidate, max-age=0' );

        $this->set_header( 'Date', gmdate('D, d M Y H:i:s \G\M\T') );
        $this->set_header( 'Last-Modified', $last_modified );
        $this->set_header( 'ETag', sprintf( ' "%s"', md5( $file_size . $last_modified ) ) );

        $this->set_header( 'Content-Length', $file_size );
        $this->set_header( 'Content-Type', FileSystemHelper::get_mime_type( $this->file_path ) );
        $this->set_header( 'Content-Transfer-Encoding', 'Binary' );

        $this->set_header( 'X-Content-Type-Options', 'nosniff' );
        $this->set_header( 'X-Robots-Tag', 'noindex, nofollow' );
        $this->set_header( 'Content-Description', 'File Transfer' ); // Optional.
        $this->set_header( 'Pragma', 'Public' );
        $this->set_header( 'Content-Disposition', $this->get_content_disposition() );
    }

    /**
     * Determines the Content-Disposition header value based on MIME type and
     * whether inline display is explicitly requested.
     *
     * @param string $fileName The name of the file.
     * @param bool   $isInlineRequested Whether inline display was explicitly requested.
     * @return string The formatted Content-Disposition header value.
     */
    public function get_content_disposition( string $fileName = '', bool $isInlineRequested = false ): string {
        $mimeType = FileSystemHelper::get_mime_type( $this->file_path );
        if ( ! $fileName ) {
            $fileName = basename( $this->file_path );
        }

        $renderable_prefixes    = [
            'image/',          // JPG, PNG, GIF, SVG, WEBP
            'text/plain',      // TXT, LOG
            'text/html',
            'application/pdf', // PDFs (Browser support is high)
            'video/',          // MP4, WebM (Modern browser video support)
            'audio/',          // MP3, WAV (Modern browser audio support)
        ];

        $can_render_inline = false;
        foreach ( $renderable_prefixes as $prefix ) {
            if ( str_starts_with( $mimeType, $prefix ) ) {
                $can_render_inline = true;
                break;
            }
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
        $this->repo_class->readfile( $this->file_path );
        exit;
    }
}