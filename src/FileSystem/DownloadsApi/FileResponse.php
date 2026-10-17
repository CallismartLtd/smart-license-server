<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\HostedApps\Storage\AppStorage;

/**
 * The download response class.
 *
 * Serves one of three things:
 *  - a hosted app file (AppFile), from wherever app storage keeps it;
 *  - a file on this server, by absolute path;
 *  - a string held in memory (`is_file` => false).
 *
 * A hosted app file is served by the first of these its storage adapter
 * supports: a redirect to a time-limited direct URL, or a stream read from
 * the requested offset. Either way, memory use does not grow with the
 * file's size.
 */

class FileResponse extends Response {

	/**
	 * The file request exception.
	 * * NOTE: This property is used here to hold the specific FileRequestException 
	 * instance for child-class access, complementing the parent's generic error handling.
	 *
	 * @var FileRequestException $error
	 */
	protected $error;

	/**
	 * Seconds a redirect to a storage URL stays valid.
	 *
	 * @var int
	 */
	public const TEMPORARY_URL_TTL = 300;

	/**
	 * The filesystem, for files served by path.
	 *
	 * @var FileSystem
	 */
	protected FileSystem $fs;

	/**
	 * The repository of the app type being served, if any.
	 *
	 * @var Repository|null
	 */
	protected ?Repository $repo_class = null;

	/**
	 * The file.
	 * 
	 * @var string|AppFile|Exception $file A hosted app file, an absolute path
	 *                                     or the file string.
	 */
	protected $file;

	/**
	 * What app storage reported about a hosted app file.
	 *
	 * @var array{name: string, size: int, mtime: int, mime_type: string}|null
	 */
	protected ?array $file_info = null;

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
	 * @param string|AppFile|FileRequestException $file A hosted app file, an absolute path to the file,
	 *                                                  the file string or an instance of error.
	 * @param string|array $args An associative array of options. `is_file` is ignored for a hosted app file.
	 */
	public function __construct(
		string|AppFile|FileRequestException $file,
		protected Request $request,
		array|string $args = ''
	) {
		parent::__construct();

		if ( $file instanceof FileRequestException ) {
			// Overrides `set_exception` which handles both the parent and local error state.
			$this->set_exception( $file );
		} else {
			$this->file = $file;
		}

		$default_args = [
			'is_file'      => true, // Treated as file by default, use false if it is binary.
			'name'         => '',  // File basename will be used as default file name, `untitled` is used when the file does not exist.
			'type'         => 'binary', // The valid default values are all registered app types and `binary`.
			'content_type' => '', // The file mime type will be used by default.
		];

		$options       = \parse_args( (array) $args, $default_args );
		$download_type = ! empty( $options['type'] ) ? (string) $options['type'] : 'binary';
		$app_registery = HostedAppsRegistry::instance();
		$this->fs      = FileSystem::instance();

		try {
			if ( in_array( $download_type, $app_registery->app_types(), true ) ) {
				$this->repo_class = $app_registery->get_app_type_directory_class( $download_type );
			}
		} catch( \Throwable ) {
		}

		if ( $this->has_errors() ) {
			return;
		}

		$filename = ! empty( $options['name'] ) ? FileSystemHelper::sanitize_filename( $options['name'] ) : '';

		if ( $this->file instanceof AppFile ) {
			$this->parse_app_file( $filename, (string) $options['content_type'] );
		} elseif ( (bool) $options['is_file'] ) {
			$this->parse_file( $filename, (string) $options['content_type'] );
		} else {
			$this->parse_binary( $filename, (string) $options['content_type'] );
		}
		
	}

	/**
	 * Get the file being served.
	 * 
	 * @return string|AppFile|Exception A hosted app file, an absolute path or the file string.
	 */
	public function get_file() {
		return $this->file;
	}

	/**
	 * Get the filesystem used for files served by path.
	 * 
	 * @return FileSystem
	 */
	public function get_fs() : FileSystem {
		return $this->fs;
	}
	
	/**
	 * Checks if the response currently contains any errors.
	 * Overrides the parent to check both the parent's accumulated error state
	 * and the child's specialized FileRequestException instance.
	 * * @return bool
	 */
	public function has_errors() : bool {
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

		if ( ! $this->fs->exists( $this->file ) ) {
			$this->set_exception( new FileRequestException( 'file_not_found' ) );
			return;
		}

		if ( ! $this->fs->is_readable( $this->file ) ) {
			$this->set_exception( new FileRequestException( 'file_reading_error', 'File not readable' ) );
			return;
		}

		if ( $this->has_errors() ) {
			return;
		}

		$file_size     = (int) $this->fs->filesize( $this->file );
		$mtime         = (int) $this->fs->filemtime( $this->file );
		$last_modified = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', $mtime ) );
		$etag          = sprintf( '"%s"', md5( $file_size . $last_modified ) );
		$content_type  = $content_type ?: FileSystemHelper::get_mime_type( $this->file );

		$this->set_default_headers();

		$this->set_header( 'Last-Modified', $last_modified );
		$this->set_header( 'ETag', $etag );
		$this->set_header( 'Content-Type', $content_type );
		$this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name ) );
		$this->set_header( 'Accept-Ranges', 'bytes' );

		$this->set_range_headers( $file_size, $etag, $mtime );

	}

	/**
	 * Parse a hosted app file and set up the response.
	 *
	 * When the storage adapter gives a direct URL, the response redirects
	 * to it; otherwise the file's headers are set for streaming.
	 *
	 * @param string $file_name The file name (optional); the stored name by default.
	 * @param string $content_type The file mime content type (optional); the stored type by default.
	 * @return void
	 */
	protected function parse_app_file( string $file_name = '', string $content_type = '' ) : void {
		$storage = AppStorage::instance();

		try {
			$url = $storage->temporary_url( $this->file, static::TEMPORARY_URL_TTL );

			if ( null !== $url ) {
				$this->set_status_code( 307 );
				$this->set_header( 'Location', $url );
				$this->set_header( 'Cache-Control', 'no-store' );
				return;
			}

			$this->file_info = $storage->stat( $this->file );
		} catch ( StorageException $e ) {
			$this->set_exception( static::storage_error( $e ) );
			return;
		}

		$file_size     = $this->file_info['size'];
		$mtime         = $this->file_info['mtime'];
		$last_modified = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', $mtime ) );
		$etag          = sprintf( '"%s"', md5( $file_size . $last_modified ) );
		$content_type  = $content_type ?: $this->file_info['mime_type'];

		$this->set_default_headers();

		$this->set_header( 'Last-Modified', $last_modified );
		$this->set_header( 'ETag', $etag );
		$this->set_header( 'Content-Type', $content_type );
		$this->set_header( 'Content-Disposition', $this->get_content_disposition( $file_name ) );
		$this->set_header( 'Accept-Ranges', 'bytes' );

		$this->set_range_headers( $file_size, $etag, $mtime );
	}

	/**
	 * Turn an app storage failure into a file request error.
	 *
	 * @param StorageException $e The storage failure.
	 * @return FileRequestException
	 */
	protected static function storage_error( StorageException $e ) : FileRequestException {
		return match ( $e->get_error_code() ) {
			StorageException::NOT_FOUND, StorageException::INVALID_PATH => new FileRequestException( 'file_not_found' ),
			default => new FileRequestException( 'file_reading_error', $e->get_error_message() ),
		};
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
		$last_modified = sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s' ) );
		
		$this->set_header( 'Expires', '0' )
			->set_header( 'Cache-Control', 'private, must-revalidate, max-age=0' )
			->set_header( 'Last-Modified', $last_modified )
			->set_header( 'Date', gmdate( 'D, d M Y H:i:s \G\M\T' ) )
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
	protected function passes_if_range( string $etag, ?int $mtime ) : bool {
		$if_range = $this->request->get_header( 'if-range' );

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
		$normalize = static function( string $value ) : string {
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
		$this->range_start = 0;
		$this->range_end   = $file_size - 1;
		$range             = $this->request->get_header( 'range' );

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
			$this->set_exception( static::range_error() );
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
			$this->set_exception( static::range_error() );
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
	 * The error for a Range header that cannot be satisfied.
	 *
	 * Carries status 416 itself: "invalid_range" has no entry in the
	 * FileRequestException map, and set_exception() takes the status from
	 * the exception, so without it the 416 set before became a 500.
	 *
	 * @return FileRequestException
	 */
	protected static function range_error() : FileRequestException {
		return new FileRequestException(
			'invalid_range',
			'The requested range cannot be satisfied.',
			[ 'status' => 416, 'title' => 'Range Not Satisfiable' ]
		);
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
	public function get_content_disposition( string $fileName = '', $mimeType = '', bool $isInlineRequested = false ) : string {
		if ( $this->has_errors() ) {
			return '';
		}
		
		if ( $this->file instanceof AppFile ) {
			$stored_mime       = (string) ( $this->file_info['mime_type'] ?? '' );
			$mimeType          = $mimeType ?: $stored_mime;
			$fileName          = $fileName ?: (string) ( $this->file_info['name'] ?? $this->file->name );
			$can_render_inline = str_starts_with( $stored_mime, 'image/' );
		} else {
			$mimeType          = $mimeType ?: FileSystemHelper::get_mime_type( $this->file );
			$fileName          = $fileName ?: basename( $this->file );
			$can_render_inline = FileSystemHelper::is_image( $this->file );
		}


		$disposition     = ( $isInlineRequested && $can_render_inline ) ? 'inline' : 'attachment';
		$encodedFilename = rawurlencode( $fileName );

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
	 * Send the response.
	 *
	 * A redirect to a storage URL ends this request at the headers, so the
	 * after-serve callbacks run first: the download is handed off here.
	 *
	 * @return void
	 */
	public function send() : void {
		if ( $this->is_redirect() && ! $this->has_errors() ) {
			$this->trigger_after_serve_callbacks();
		}

		parent::send();
	}

	/**
	 * Sends the file or error to the client
	 */
	public function send_body() : void {
		$this->send_file_content();
	}

	/**
	 * Reads and sends the content of the file to the client.
	 */
	protected function send_file_content() {
		if ( ! empty( $this->get_body() ) ) {
			echo $this->get_body(); // phpcs:ignore
			$this->trigger_after_serve_callbacks();
			exit;
		}

		if ( $this->file instanceof AppFile ) {
			$this->stream_app_file();
		} elseif ( $this->is_range_request ) {
			$length = $this->range_end - $this->range_start + 1;
			$this->stream_file( $this->file, $this->range_start, $length );
		} else {
			$this->stream_file( $this->file );
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
	 * Create a new error response.
	 * 
	 * @param FileRequestException $error
	 * @param Request $request
	 * @return static
	 */
	public static function error_response( FileRequestException $error,  Request $request ) : static {
		return ( new static( $error, $request ) );
	}

	/**
	 * Tells whether the file is a valid zip file.
	 * 
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_zip_file() : bool {
		$file = $this->get_file();

		if ( $file instanceof AppFile ) {
			try {
				$path = AppStorage::instance()->local_path( $file );
			} catch ( StorageException ) {
				return false;
			}

			if ( null === $path ) {
				return $this->has_zip_signature( $file );
			}

			$file = $path;
		}

		if ( $file instanceof Exception || '' === $file ) {
			return false;
		}

		if ( null !== $this->repo_class ) {
			return $this->repo_class->is_valid_zip( $file );
		}

		return FileSystemHelper::is_archive( $file );
	}

	/**
	 * Tell whether a hosted app file not kept on this server starts like a
	 * ZIP file.
	 *
	 * Reads only the first four bytes, so the file is never downloaded to
	 * check it. This is weaker than opening the archive, which only works
	 * on a local file; packages are fully checked when they are uploaded.
	 *
	 * @param AppFile $file The file.
	 * @return bool True when it starts with a ZIP signature.
	 */
	protected function has_zip_signature( AppFile $file ) : bool {
		try {
			$stream = AppStorage::instance()->read_stream( $file, 0, 4 );
		} catch ( StorageException ) {
			return false;
		}

		$head = fread( $stream, 4 );
		fclose( $stream );

		// A local file header, or the end record of an empty archive.
		return \in_array( $head, [ "PK\x03\x04", "PK\x05\x06" ], true );
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
	public static function is_filesize_within_limit( $filesize, ?float $factor = 0.8 ) : bool {
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

		if ( 0 === $memory_limit ) {
			$memory_limit = 256 * 1024 * 1024; // 256MB reasonable fallback.
		}
		if ( 0 === $upload_limit ) {
			$upload_limit = $memory_limit;
		}
		if ( 0 === $post_limit ) {
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
	protected static function to_bytes( $val ) : int {
		if ( is_numeric( $val ) ) {
			return (int) $val;
		}

		$val = trim( (string) $val );
		if ( '' === $val ) {
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
	protected static function try_memory_allocation( int $filesize ) : bool {
		$test_size = (int) min( $filesize, 2 * 1024 * 1024 ); // max 2MB allocation
		try {
			$buffer = str_repeat( '0', $test_size );
			unset( $buffer );
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Output a file in chunks.
	 *
	 * @param string $path Absolute path.
	 * @param int $start Optional start offset.
	 * @param int $length Optional length to read.
	 * @param int $chunk_size Optional chunk size (default 1MB).
	 * @return bool True on success, false on failure.
	 */
	private function stream_file( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ) : bool {
		if ( ! $this->fs->is_file( $path ) ) {
			return false;
		}

		$stream = $this->fs->read_stream( $path );

		if ( false === $stream ) {
			return false;
		}

		if ( $start > 0 ) {
			fseek( $stream, $start );
		}

		return $this->output_stream( $stream, $length, $chunk_size );
	}

	/**
	 * Output a hosted app file, or the requested range of it, in chunks.
	 *
	 * The storage adapter opens the stream at the range start, so nothing
	 * before it is read.
	 *
	 * @param int $chunk_size Optional chunk size (default 1MB).
	 * @return bool True on success, false on failure.
	 */
	private function stream_app_file( int $chunk_size = 1048576 ) : bool {
		$start  = $this->is_range_request ? $this->range_start : 0;
		$length = $this->is_range_request ? $this->range_end - $this->range_start + 1 : null;

		try {
			$stream = AppStorage::instance()->read_stream( $this->file, $start, $length );
		} catch ( StorageException ) {
			return false;
		}

		return $this->output_stream( $stream, $length ?? 0, $chunk_size );
	}

	/**
	 * Output an open stream in chunks, then close it.
	 *
	 * @param resource $stream A readable stream, positioned at the first byte to send.
	 * @param int $length Bytes to send; 0 for everything up to the end.
	 * @param int $chunk_size Chunk size.
	 * @return bool True on success, false on failure.
	 */
	private function output_stream( $stream, int $length, int $chunk_size ) : bool {
		$remaining = $length > 0 ? $length : null;

		while ( ! feof( $stream ) ) {
			if ( null !== $remaining && $remaining <= 0 ) {
				break;
			}

			$read_length = ( null !== $remaining )
				? min( $chunk_size, $remaining )
				: $chunk_size;

			$buffer = fread( $stream, $read_length );

			if ( false === $buffer ) {
				fclose( $stream );
				return false;
			}

			echo $buffer;
			flush();

			if ( null !== $remaining ) {
				$remaining -= strlen( $buffer );
			}
		}

		fclose( $stream );

		return true;
	}
}