<?php
/**
 * multipart/form-data request body parser (dedicated content-type class).
 *
 * @package SmartLicenseServer\Core\Parsers
 * @author Callistus Nwachukwu
 * @since 0.0.7
 */

namespace SmartLicenseServer\Core\Parsers;

use SmartLicenseServer\Exceptions\Exception;

/**
 * True streaming multipart/form-data parser: files are written to
 * temp storage as they're read, never buffered fully in memory.
 * Wraps MultipartStreamEngine and exposes the shared
 * RequestBodyParserInterface contract.
 */
class MultipartRequestParser implements RequestBodyParserInterface {

	/**
	 * Default tempnam() prefix, used when the caller doesn't supply one.
	 */
	private const DEFAULT_TMP_PREFIX = 'http_upload_';

	/**
	 * Parsed POST data.
	 *
	 * @var array
	 */
	private array $post_data = [];

	/**
	 * Parsed FILES data (native structure).
	 *
	 * @var array
	 */
	private array $files_data = [];

	/**
	 * The streaming engine instance.
	 *
	 * @var MultipartStreamEngine|null
	 */
	private ?MultipartStreamEngine $engine = null;

	/**
	 * Constructor.
	 *
	 * @param string      $content_type      Raw Content-Type header (boundary is extracted from this).
	 * @param array       $limits            Parsed php.ini limits.
	 * @param string|null $upload_tmp_prefix Optional tempnam() prefix; falls back to a generic default.
	 * @param bool        $debug             Enable debug logging.
	 * @param bool        $verbose           Whether to error_log() stream events.
	 */
	public function __construct(
		private string $content_type,
		private array $limits,
		private ?string $upload_tmp_prefix  = null,
		private bool $debug                 = false,
		private bool $verbose               = false
	) {}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function parse(): array {
		$boundary = $this->extract_boundary();
		$stream   = @fopen( 'php://input', 'rb' );

		if ( ! $stream ) {
			throw new Exception( 'stream_open_failed', 'Cannot open php://input.' );
		}

		try {
			$this->engine = new MultipartStreamEngine(
				$stream,
				$boundary,
				$this->limits,
				$this->upload_tmp_prefix ?? self::DEFAULT_TMP_PREFIX,
				$this->debug,
				$this->verbose
			);

			$this->engine->parse();

			$this->post_data  = $this->engine->get_post_data();
			$this->files_data = $this->engine->get_files_data();
		} finally {
			fclose( $stream );
		}

		return [ 'post' => $this->post_data, 'files' => $this->files_data ];
	}

	/**
	 * @inheritDoc
	 */
	public function get_bytes_read(): int {
		return $this->engine ? $this->engine->get_bytes_read() : 0;
	}

	/**
	 * @inheritDoc
	 */
	public function get_temp_files(): array {
		return $this->engine ? $this->engine->get_temp_files() : [];
	}

	/**
	 * Number of files uploaded in this request.
	 *
	 * @return int
	 */
	public function get_files_count(): int {
		return $this->engine ? $this->engine->get_files_count() : 0;
	}

	/**
	 * Debug log (only populated when debug mode is enabled).
	 *
	 * @return array
	 */
	public function get_debug_log(): array {
		return $this->engine ? $this->engine->get_debug_log() : [];
	}

	/**
	 * Extract and validate the multipart boundary from Content-Type.
	 *
	 * @return string
	 * @throws Exception
	 */
	private function extract_boundary(): string {
		if ( ! preg_match( '/boundary=(["\']?)([^"\';\s]+)\1/i', $this->content_type, $m ) ) {
			throw new Exception( 'missing_boundary', 'No boundary in Content-Type.' );
		}

		$boundary = $m[2];

		// RFC 2046: 1-70 chars, specific allowed characters.
		if ( ! preg_match( '/^[A-Za-z0-9\'\(\)\+_,\-\.\/:=\? ]{1,70}$/', $boundary ) ) {
			throw new Exception( 'invalid_boundary', 'Boundary violates RFC 2046.' );
		}

		return $boundary;
	}
}