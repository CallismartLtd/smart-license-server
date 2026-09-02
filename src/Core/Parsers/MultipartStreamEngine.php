<?php
/**
 * RFC 2046 compliant streaming multipart state machine.
 *
 * @package SmartLicenseServer\Core\Parsers
 * @author Callistus Nwachukwu
 * @since 0.0.7
 */

namespace SmartLicenseServer\Core\Parsers;

use SmartLicenseServer\Exceptions\Exception;

/**
 * Internal multipart stream parser with state machine. True streaming
 * file writes, no memory buffering of upload bodies.
 *
 * Deliberately has no reference back to its caller: it only needs a
 * stream, a boundary, php.ini limits, and a tempnam() prefix. Owning
 * code reads its state back out via the public getters once parse()
 * returns (or, if a fatal error aborts mid-parse, straight off this
 * object's still-live properties from a shutdown handler).
 */
class MultipartStreamEngine {

	/**
	 * Parser states.
	 */
	private const STATE_PREAMBLE = 1;
	private const STATE_HEADERS  = 2;
	private const STATE_BODY     = 3;
	private const STATE_COMPLETE = 4;

	/**
	 * Input stream.
	 *
	 * @var resource
	 */
	private $stream;

	/**
	 * Boundary string.
	 *
	 * @var string
	 */
	private string $boundary;

	/**
	 * Boundary with leading dashes.
	 *
	 * @var string
	 */
	private string $boundary_marker;

	/**
	 * Closing boundary marker.
	 *
	 * @var string
	 */
	private string $boundary_close;

	/**
	 * Limits from php.ini.
	 *
	 * @var array
	 */
	private array $limits;

	/**
	 * tempnam() prefix for uploaded file temp storage.
	 *
	 * @var string
	 */
	private string $tmp_prefix;

	/**
	 * Current state.
	 *
	 * @var int
	 */
	private int $state = self::STATE_PREAMBLE;

	/**
	 * Read buffer.
	 *
	 * @var string
	 */
	private string $buffer = '';

	/**
	 * Current part headers.
	 *
	 * @var array
	 */
	private array $current_headers = [];

	/**
	 * Current part disposition.
	 *
	 * @var array
	 */
	private array $current_disposition = [];

	/**
	 * Current file handle (for streaming writes).
	 *
	 * @var resource|null
	 */
	private $current_file_handle = null;

	/**
	 * Current file path.
	 *
	 * @var string|null
	 */
	private ?string $current_file_path = null;

	/**
	 * Current file bytes written.
	 *
	 * @var int
	 */
	private int $current_file_size = 0;

	/**
	 * Current field body (for form fields).
	 *
	 * @var string
	 */
	private string $current_field_body = '';

	/**
	 * POST data accumulator.
	 *
	 * @var array
	 */
	private array $post_data = [];

	/**
	 * FILES data accumulator.
	 *
	 * @var array
	 */
	private array $files_data = [];

	/**
	 * Temp files created. Populated as files are opened, not just at
	 * the end, so a fatal error mid-parse still leaves an accurate
	 * list for cleanup.
	 *
	 * @var array
	 */
	private array $temp_files = [];

	/**
	 * Total bytes read.
	 *
	 * @var int
	 */
	private int $bytes_read = 0;

	/**
	 * Files uploaded counter.
	 *
	 * @var int
	 */
	private int $files_count = 0;

	/**
	 * Header bytes accumulated.
	 *
	 * @var int
	 */
	private int $header_bytes = 0;

	/**
	 * Debug mode flag.
	 *
	 * @var bool
	 */
	private bool $debug = false;

	/**
	 * Debug log.
	 *
	 * @var array
	 */
	private array $debug_log = [];

	/**
	 * Verbosity flag.
	 *
	 * @var bool
	 */
	private bool $verbose = false;

	/**
	 * Constructor.
	 *
	 * @param resource $stream     Input stream (php://input).
	 * @param string   $boundary   Multipart boundary (without leading dashes).
	 * @param array    $limits     Parsed php.ini limits.
	 * @param string   $tmp_prefix tempnam() prefix for uploaded file temp storage.
	 * @param bool     $debug      Enable debug logging.
	 * @param bool     $verbose    Whether to error_log() stream events.
	 */
	public function __construct( $stream, string $boundary, array $limits, string $tmp_prefix, bool $debug = false, bool $verbose = false ) {
		$this->stream          = $stream;
		$this->boundary        = $boundary;
		$this->boundary_marker = "--{$this->boundary}";
		$this->boundary_close  = "--{$this->boundary}--";
		$this->limits          = $limits;
		$this->tmp_prefix      = $tmp_prefix;
		$this->debug           = $debug;
		$this->verbose         = $verbose;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	private function debug_log( string $message, array $context = [] ): void {
		if ( ! $this->debug ) {
			return;
		}

		$entry = [
			'state'      => $this->get_state_name(),
			'buffer_len' => strlen( $this->buffer ),
			'bytes_read' => $this->bytes_read,
			'message'    => $message,
			'context'    => $context,
		];

		$this->debug_log[] = $entry;

		if ( $this->verbose ) {
			error_log( sprintf(
				'[MultipartStreamEngine] [%s] %s (buffer: %d, read: %d)',
				$this->get_state_name(),
				$message,
				strlen( $this->buffer ),
				$this->bytes_read
			) );
		}
	}

	/**
	 * Get state name for debugging.
	 *
	 * @return string
	 */
	private function get_state_name(): string {
		return match ( $this->state ) {
			self::STATE_PREAMBLE => 'PREAMBLE',
			self::STATE_HEADERS  => 'HEADERS',
			self::STATE_BODY     => 'BODY',
			self::STATE_COMPLETE => 'COMPLETE',
			default              => 'UNKNOWN',
		};
	}

	/**
	 * Get debug log.
	 *
	 * @return array
	 */
	public function get_debug_log(): array {
		return $this->debug_log;
	}

	/**
	 * Main parsing loop.
	 *
	 * @throws Exception
	 */
	public function parse(): void {
		while ( $this->state !== self::STATE_COMPLETE ) {
			if ( strlen( $this->buffer ) < 8192 && ! feof( $this->stream ) ) {
				$this->read_chunk();
			}

			if ( strlen( $this->buffer ) === 0 && feof( $this->stream ) ) {
				break;
			}

			switch ( $this->state ) {
				case self::STATE_PREAMBLE:
					$this->process_preamble();
					break;

				case self::STATE_HEADERS:
					$this->process_headers();
					break;

				case self::STATE_BODY:
					$this->process_body();
					break;
			}

			$prev_state      = $this->state;
			$prev_buffer_len = strlen( $this->buffer );

			static $stuck_count = 0;
			if ( $this->state === $prev_state && strlen( $this->buffer ) === $prev_buffer_len && strlen( $this->buffer ) > 0 ) {
				$stuck_count++;
				if ( $stuck_count > 3 && ! feof( $this->stream ) ) {
					$this->read_chunk();
					$stuck_count = 0;
				}
			} else {
				$stuck_count = 0;
			}
		}

		if ( $this->state === self::STATE_BODY ) {
			if ( strlen( $this->buffer ) > 0 ) {
				if ( $this->current_file_handle ) {
					$this->write_to_file( $this->buffer );
				} else {
					$this->current_field_body .= $this->buffer;
				}
				$this->buffer = '';
			}
			$this->finalize_current_part();
		}

		$this->close_current_file();
	}

	/**
	 * Read chunk from stream.
	 *
	 * @throws Exception
	 */
	private function read_chunk(): void {
		if ( feof( $this->stream ) ) {
			return;
		}

		$chunk = fread( $this->stream, 4096 );

		if ( $chunk === false ) {
			throw new Exception( 'stream_read_error', 'Failed to read from stream.' );
		}

		$chunk_len = strlen( $chunk );

		if ( $chunk_len === 0 ) {
			return;
		}

		$this->bytes_read += $chunk_len;

		$post_max = $this->limits['post_max_size'];

		if ( $post_max > 0 && $this->bytes_read > $post_max ) {
			throw new Exception(
				'post_max_size_exceeded',
				sprintf( 'Request exceeded post_max_size (%d bytes).', $post_max )
			);
		}

		$mem_limit = $this->limits['memory_limit'];

		if ( $mem_limit > 0 && strlen( $this->buffer ) + $chunk_len > $mem_limit / 4 ) {
			throw new Exception( 'memory_limit_exceeded', 'Buffer size approaching memory_limit.' );
		}

		$this->buffer .= $chunk;
	}

	/**
	 * Process preamble state (before first boundary).
	 *
	 * @throws Exception
	 */
	private function process_preamble(): void {
		$pos = strpos( $this->buffer, $this->boundary_marker );

		if ( $pos === false ) {
			$keep         = min( strlen( $this->buffer ), strlen( $this->boundary_marker ) + 2 );
			$this->buffer = substr( $this->buffer, -$keep );
			return;
		}

		$after_boundary = $pos + strlen( $this->boundary_marker );

		if ( ! isset( $this->buffer[ $after_boundary ] ) ) {
			return;
		}

		$next_chars = substr( $this->buffer, $after_boundary, 4 );

		if ( substr( $next_chars, 0, 2 ) === '--' ) {
			$this->state = self::STATE_COMPLETE;
			return;
		}

		if ( substr( $next_chars, 0, 2 ) === "\r\n" ) {
			$this->buffer = substr( $this->buffer, $after_boundary + 2 );
		} elseif ( substr( $next_chars, 0, 1 ) === "\n" ) {
			$this->buffer = substr( $this->buffer, $after_boundary + 1 );
		} else {
			throw new Exception( 'malformed_boundary', 'Boundary not followed by CRLF.' );
		}

		$this->state           = self::STATE_HEADERS;
		$this->current_headers = [];
		$this->header_bytes    = 0;
	}

	/**
	 * Process headers state.
	 *
	 * @throws Exception
	 */
	private function process_headers(): void {
		$end_pos = strpos( $this->buffer, "\r\n\r\n" );
		$sep_len = 4;

		if ( $end_pos === false ) {
			$end_pos = strpos( $this->buffer, "\n\n" );
			$sep_len = 2;
		}

		if ( $end_pos === false ) {
			if ( strlen( $this->buffer ) > 65536 ) {
				throw new Exception( 'headers_too_large', 'Headers exceed 64KB limit.' );
			}

			if ( feof( $this->stream ) && strlen( $this->buffer ) > 0 ) {
				throw new Exception( 'malformed_headers', 'Headers incomplete at end of stream.' );
			}

			return;
		}

		$raw_headers  = substr( $this->buffer, 0, $end_pos );
		$this->buffer = substr( $this->buffer, $end_pos + $sep_len );

		$this->current_headers = $this->parse_headers( $raw_headers );

		if ( ! isset( $this->current_headers['content-disposition'] ) ) {
			throw new Exception( 'missing_content_disposition', 'Part missing Content-Disposition header.' );
		}

		$this->current_disposition = $this->parse_content_disposition( $this->current_headers['content-disposition'] );

		if ( ! isset( $this->current_disposition['name'] ) ) {
			throw new Exception( 'missing_field_name', 'Content-Disposition missing name parameter.' );
		}

		$this->validate_field_name( $this->current_disposition['name'] );

		$this->state              = self::STATE_BODY;
		$this->current_field_body = '';
		$this->current_file_size  = 0;

		if ( isset( $this->current_disposition['filename'] ) ) {
			$this->open_file_for_streaming();
		}
	}

	/**
	 * Process body state (streaming).
	 *
	 * @throws Exception
	 */
	private function process_body(): void {
		$crlf_boundary = "\r\n" . $this->boundary_marker;
		$lf_boundary   = "\n" . $this->boundary_marker;

		$pos_crlf = strpos( $this->buffer, $crlf_boundary );
		$pos_lf   = strpos( $this->buffer, $lf_boundary );

		$pos           = false;
		$boundary_len  = 0;
		$boundary_type = null;

		if ( $pos_crlf !== false && ( $pos_lf === false || $pos_crlf <= $pos_lf ) ) {
			$pos           = $pos_crlf;
			$boundary_len  = strlen( $crlf_boundary );
			$boundary_type = 'CRLF';
		} elseif ( $pos_lf !== false ) {
			$pos           = $pos_lf;
			$boundary_len  = strlen( $lf_boundary );
			$boundary_type = 'LF';
		}

		if ( $pos === false ) {
			$this->debug_log( 'No boundary in buffer, accumulating body data' );

			$keep = max( strlen( $crlf_boundary ), strlen( $lf_boundary ) ) + 4;

			if ( strlen( $this->buffer ) > $keep ) {
				$to_write     = substr( $this->buffer, 0, -$keep );
				$this->buffer = substr( $this->buffer, -$keep );

				if ( $this->current_file_handle ) {
					$this->write_to_file( $to_write );
				} else {
					$this->current_field_body .= $to_write;

					if ( strlen( $this->current_field_body ) > 1048576 ) {
						throw new Exception( 'field_too_large', 'Form field exceeds 1MB.' );
					}
				}
			}

			return;
		}

		$this->debug_log( 'Found boundary', [
			'type'     => $boundary_type,
			'position' => $pos,
			'body_len' => $pos,
		] );

		$body_data = substr( $this->buffer, 0, $pos );

		if ( $this->current_file_handle ) {
			$this->write_to_file( $body_data );
			$this->close_current_file();
		} else {
			$this->current_field_body .= $body_data;
		}

		$this->finalize_current_part();

		$this->buffer = substr( $this->buffer, $pos + $boundary_len );

		if ( strlen( $this->buffer ) < 2 ) {
			$this->debug_log( 'Need more data after boundary' );
			$this->state            = self::STATE_HEADERS;
			$this->current_headers  = [];
			$this->header_bytes     = 0;
			return;
		}

		if ( substr( $this->buffer, 0, 2 ) === '--' ) {
			$this->debug_log( 'Found closing boundary, parsing complete' );
			$this->state = self::STATE_COMPLETE;
			return;
		}

		if ( substr( $this->buffer, 0, 2 ) === "\r\n" ) {
			$this->buffer = substr( $this->buffer, 2 );
		} elseif ( substr( $this->buffer, 0, 1 ) === "\n" ) {
			$this->buffer = substr( $this->buffer, 1 );
		}

		$this->debug_log( 'Starting next part' );

		$this->state            = self::STATE_HEADERS;
		$this->current_headers  = [];
		$this->header_bytes     = 0;
	}

	/**
	 * Parse headers.
	 *
	 * @param string $raw_headers
	 * @return array
	 * @throws Exception
	 */
	private function parse_headers( string $raw_headers ): array {
		$headers = [];
		$lines   = explode( "\n", str_replace( "\r\n", "\n", $raw_headers ) );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			if ( strlen( $line ) > 8192 ) {
				throw new Exception( 'header_line_too_long', 'Header line exceeds 8KB.' );
			}

			$colon = strpos( $line, ':' );

			if ( $colon === false ) {
				continue;
			}

			$key   = strtolower( trim( substr( $line, 0, $colon ) ) );
			$value = trim( substr( $line, $colon + 1 ) );

			$headers[ $key ] = $value;
		}

		return $headers;
	}

	/**
	 * Parse Content-Disposition.
	 *
	 * @param string $value
	 * @return array
	 */
	private function parse_content_disposition( string $value ): array {
		$result = [];

		if ( preg_match( '/\bname=(?:"([^"]+)"|([^;\s]+))/i', $value, $m ) ) {
			$result['name'] = $m[1] ?: $m[2];
		}

		if ( preg_match( '/\bfilename=(?:"([^"]*)"|([^;\s]+))/i', $value, $m ) ) {
			$result['filename'] = $m[1] ?: $m[2];
		}

		return $result;
	}

	/**
	 * Validate field name for injection attempts.
	 *
	 * @param string $name
	 * @throws Exception
	 */
	private function validate_field_name( string $name ): void {
		if ( strlen( $name ) > 255 ) {
			throw new Exception( 'field_name_too_long', 'Field name exceeds 255 chars.' );
		}

		$open  = substr_count( $name, '[' );
		$close = substr_count( $name, ']' );

		if ( $open !== $close ) {
			throw new Exception( 'malformed_field_name', 'Unbalanced brackets in field name.' );
		}

		if ( $open > 10 ) {
			throw new Exception( 'excessive_nesting', 'Field name nesting exceeds 10 levels.' );
		}
	}

	/**
	 * Open file for streaming writes.
	 *
	 * @throws Exception
	 */
	private function open_file_for_streaming(): void {
		$this->files_count++;

		if ( $this->files_count > $this->limits['max_file_uploads'] ) {
			throw new Exception(
				'max_file_uploads_exceeded',
				sprintf( 'Exceeded max_file_uploads (%d).', $this->limits['max_file_uploads'] )
			);
		}

		$tmp_path = @tempnam( sys_get_temp_dir(), $this->tmp_prefix );

		if ( ! $tmp_path ) {
			throw new Exception( 'tempfile_creation_failed', 'Cannot create temp file.' );
		}

		$handle = @fopen( $tmp_path, 'wb' );

		if ( ! $handle ) {
			@unlink( $tmp_path );
			throw new Exception( 'tempfile_open_failed', 'Cannot open temp file for writing.' );
		}

		@chmod( $tmp_path, 0600 );

		$this->current_file_handle = $handle;
		$this->current_file_path   = $tmp_path;
		$this->current_file_size   = 0;
		$this->temp_files[]        = $tmp_path;
	}

	/**
	 * Write data to current file (streaming).
	 *
	 * @param string $data
	 * @throws Exception
	 */
	private function write_to_file( string $data ): void {
		if ( ! $this->current_file_handle ) {
			return;
		}

		$len = strlen( $data );

		if ( $len === 0 ) {
			return;
		}

		$upload_max = $this->limits['upload_max_filesize'];

		if ( $upload_max > 0 && $this->current_file_size + $len > $upload_max ) {
			throw new Exception(
				'file_too_large',
				sprintf( 'File exceeds upload_max_filesize (%d bytes).', $upload_max )
			);
		}

		$written = fwrite( $this->current_file_handle, $data );

		if ( $written === false || $written !== $len ) {
			throw new Exception( 'file_write_error', 'Failed to write to temp file.' );
		}

		$this->current_file_size += $written;
	}

	/**
	 * Close current file.
	 */
	private function close_current_file(): void {
		if ( $this->current_file_handle ) {
			fclose( $this->current_file_handle );
			$this->current_file_handle = null;
		}
	}

	/**
	 * Finalize current part.
	 */
	private function finalize_current_part(): void {
		if ( ! isset( $this->current_disposition['name'] ) ) {
			$this->debug_log( 'Skipping part without name' );
			return;
		}

		$field_name = $this->current_disposition['name'];

		if ( isset( $this->current_disposition['filename'] ) ) {
			$filename = $this->sanitize_filename( $this->current_disposition['filename'] );

			$this->debug_log( 'Finalizing file upload', [
				'field_name' => $field_name,
				'filename'   => $filename,
				'size'       => $this->current_file_size,
				'has_path'   => ! empty( $this->current_file_path ),
			] );

			if ( $this->current_file_path ) {
				// Client-supplied MIME type, exactly as PHP's native $_FILES
				// population does — it is untrusted input. Verify it
				// server-side (e.g. via finfo against the file content)
				// before relying on it for anything security-sensitive.
				$mime = $this->current_headers['content-type'] ?? 'application/octet-stream';

				$this->add_file_entry( $field_name, $filename, $mime, $this->current_file_path, UPLOAD_ERR_OK, $this->current_file_size );
			} else {
				$this->add_file_entry( $field_name, $filename, '', '', UPLOAD_ERR_NO_FILE, 0 );
			}

			$this->current_file_path = null;
			$this->current_file_size = 0;

		} else {
			$this->debug_log( 'Finalizing form field', [
				'field_name' => $field_name,
				'value_len'  => strlen( $this->current_field_body ),
				'value'      => substr( $this->current_field_body, 0, 100 ),
			] );

			$this->add_post_field( $field_name, $this->current_field_body );
			$this->current_field_body = '';
		}

		$this->current_disposition = [];
	}

	/**
	 * Sanitize an uploaded filename: strip directory components, null
	 * bytes, and control characters. Deliberately minimal — this only
	 * guards the filename string itself; the file's content/type is
	 * still unverified (see finalize_current_part()).
	 *
	 * @param string $filename
	 * @return string
	 */
	private function sanitize_filename( string $filename ): string {
		$filename = str_replace( '\\', '/', $filename );
		$filename = basename( $filename );
		$filename = preg_replace( '/[\x00-\x1F\x7F]/', '', $filename );
		$filename = trim( ltrim( $filename, '.' ) );

		return '' === $filename ? 'unnamed' : $filename;
	}

	/**
	 * Add file entry.
	 *
	 * @param string $field_name
	 * @param string $filename
	 * @param string $mime
	 * @param string $tmp_path
	 * @param int    $error
	 * @param int    $size
	 */
	private function add_file_entry( string $field_name, string $filename, string $mime, string $tmp_path, int $error, int $size ): void {
		$parsed = $this->parse_field_name( $field_name );

		$file_data = [
			'name'     => $filename,
			'type'     => $mime,
			'tmp_name' => $tmp_path,
			'error'    => $error,
			'size'     => $size,
		];

		if ( $parsed['is_array'] ) {
			$base = $parsed['base'];

			if ( ! isset( $this->files_data[ $base ] ) ) {
				$this->files_data[ $base ] = [
					'name'     => [],
					'type'     => [],
					'tmp_name' => [],
					'error'    => [],
					'size'     => [],
				];
			}

			if ( $parsed['keys'] ) {
				$this->set_nested_file_value( $this->files_data[ $base ], $parsed['keys'], $file_data );
			} else {
				foreach ( $file_data as $k => $v ) {
					$this->files_data[ $base ][ $k ][] = $v;
				}
			}
		} else {
			$this->files_data[ $field_name ] = $file_data;
		}
	}

	/**
	 * Add POST field.
	 *
	 * @param string $field_name
	 * @param string $value
	 */
	private function add_post_field( string $field_name, string $value ): void {
		$parsed = $this->parse_field_name( $field_name );

		if ( $parsed['is_array'] ) {
			$base = $parsed['base'];

			if ( ! isset( $this->post_data[ $base ] ) ) {
				$this->post_data[ $base ] = [];
			}

			if ( $parsed['keys'] ) {
				$this->set_nested_value( $this->post_data[ $base ], $parsed['keys'], $value );
			} else {
				$this->post_data[ $base ][] = $value;
			}
		} else {
			$this->post_data[ $field_name ] = $value;
		}
	}

	/**
	 * Parse field name.
	 *
	 * @param string $name
	 * @return array
	 */
	private function parse_field_name( string $name ): array {
		if ( ! preg_match( '/^([^\[]+)(\[.*\])?$/', $name, $m ) ) {
			return [ 'is_array' => false, 'base' => $name, 'keys' => [] ];
		}

		$base         = $m[1];
		$bracket_part = $m[2] ?? '';

		if ( empty( $bracket_part ) ) {
			return [ 'is_array' => false, 'base' => $base, 'keys' => [] ];
		}

		preg_match_all( '/\[([^\]]*)\]/', $bracket_part, $matches );

		$keys = $matches[1];

		if ( count( $keys ) === 1 && $keys[0] === '' ) {
			return [ 'is_array' => true, 'base' => $base, 'keys' => null ];
		}

		return [ 'is_array' => true, 'base' => $base, 'keys' => $keys ];
	}

	/**
	 * Set nested value.
	 *
	 * @param array $array
	 * @param array $keys
	 * @param mixed $value
	 */
	private function set_nested_value( array &$array, array $keys, $value ): void {
		$current = &$array;

		foreach ( $keys as $i => $key ) {
			if ( $i === count( $keys ) - 1 ) {
				$current[ $key ] = $value;
			} else {
				if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
					$current[ $key ] = [];
				}
				$current = &$current[ $key ];
			}
		}
	}

	/**
	 * Set nested file value.
	 *
	 * @param array $files
	 * @param array $keys
	 * @param array $file_data
	 */
	private function set_nested_file_value( array &$files, array $keys, array $file_data ): void {
		foreach ( [ 'name', 'type', 'tmp_name', 'error', 'size' ] as $field ) {
			$current = &$files[ $field ];

			foreach ( $keys as $i => $key ) {
				if ( $i === count( $keys ) - 1 ) {
					$current[ $key ] = $file_data[ $field ];
				} else {
					if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
						$current[ $key ] = [];
					}
					$current = &$current[ $key ];
				}
			}
		}
	}

	/**
	 * Get POST data.
	 *
	 * @return array
	 */
	public function get_post_data(): array {
		return $this->post_data;
	}

	/**
	 * Get FILES data.
	 *
	 * @return array
	 */
	public function get_files_data(): array {
		return $this->files_data;
	}

	/**
	 * Get temp files.
	 *
	 * @return array
	 */
	public function get_temp_files(): array {
		return $this->temp_files;
	}

	/**
	 * Get bytes read.
	 *
	 * @return int
	 */
	public function get_bytes_read(): int {
		return $this->bytes_read;
	}

	/**
	 * Get files count.
	 *
	 * @return int
	 */
	public function get_files_count(): int {
		return $this->files_count;
	}
}