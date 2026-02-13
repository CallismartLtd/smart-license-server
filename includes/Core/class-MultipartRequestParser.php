<?php
/**
 * RFC 2046 Compliant Streaming Multipart Parser
 *
 * Production-grade implementation with:
 * - True streaming file writes (no memory buffering)
 * - State machine architecture
 * - Full php.ini enforcement
 * - RFC 2046 compliant boundary detection
 * - Memory-safe header parsing
 * - Native $_FILES structure support
 *
 * @package SmartLicenseServer\Core
 * @author Callistus Nwachukwu
 * @since 0.0.7
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystemHelper;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Streaming multipart parser with state machine.
 */
class MultipartRequestParser {

    /**
     * Parser states.
     */
    private const STATE_SEEKING_BOUNDARY = 1;
    private const STATE_READING_HEADERS  = 2;
    private const STATE_READING_BODY     = 3;
    private const STATE_COMPLETE         = 4;

    /**
     * Supported HTTP methods.
     */
    private const SUPPORTED_METHODS = [ 'PUT', 'PATCH', 'DELETE' ];

    /**
     * Stream read chunk size (4KB for optimal I/O).
     */
    private const READ_CHUNK_SIZE = 4096;

    /**
     * Maximum header line length (8KB per RFC 2616).
     */
    private const MAX_HEADER_LINE = 8192;

    /**
     * Maximum total header size per part (64KB).
     */
    private const MAX_HEADERS_SIZE = 65536;

    /**
     * Maximum buffer size for boundary detection (must accommodate boundary + CRLF).
     */
    private const MAX_BOUNDARY_BUFFER = 512;

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
     * Temporary files created.
     *
     * @var array
     */
    private array $temp_files = [];

    /**
     * Whether parsing completed.
     *
     * @var bool
     */
    private bool $parsed = false;

    /**
     * Request method.
     *
     * @var string
     */
    private string $method;

    /**
     * Content-Type header.
     *
     * @var string
     */
    private string $content_type;

    /**
     * PHP.INI limits.
     *
     * @var array
     */
    private array $limits;

    /**
     * Total bytes read from stream.
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
     * Current parser state.
     *
     * @var int
     */
    private int $state = self::STATE_SEEKING_BOUNDARY;

    /**
     * Constructor.
     *
     * @param string|null $method       HTTP method
     * @param string|null $content_type Content-Type header
     */
    public function __construct( ?string $method = null, ?string $content_type = null ) {
        $this->method       = $method ?? ( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
        $this->content_type = $content_type ?? ( $_SERVER['CONTENT_TYPE'] ?? '' );
        $this->limits       = $this->parse_php_ini_limits();

        register_shutdown_function( [ $this, 'cleanup' ] );
    }

    /**
     * Parse php.ini limits with proper size conversion.
     *
     * @return array
     */
    private function parse_php_ini_limits(): array {
        return [
            'post_max_size'       => $this->parse_ini_size( 'post_max_size' ),
            'upload_max_filesize' => $this->parse_ini_size( 'upload_max_filesize' ),
            'max_file_uploads'    => max( 1, (int) ini_get( 'max_file_uploads' ) ),
            'memory_limit'        => $this->parse_ini_size( 'memory_limit' ),
        ];
    }

    /**
     * Parse INI size value to bytes.
     *
     * @param string $key INI directive
     * @return int Bytes (0 = unlimited)
     */
    private function parse_ini_size( string $key ): int {
        $value = trim( ini_get( $key ) );

        if ( empty( $value ) || $value === '-1' ) {
            return 0; // Unlimited
        }

        $unit  = strtolower( substr( $value, -1 ) );
        $bytes = (int) $value;

        switch ( $unit ) {
            case 'g':
                $bytes *= 1073741824; // 1024^3
                break;
            case 'm':
                $bytes *= 1048576; // 1024^2
                break;
            case 'k':
                $bytes *= 1024;
                break;
        }

        return max( 0, $bytes );
    }

    /**
     * Check if request should be parsed.
     *
     * @return bool
     */
    public function should_parse(): bool {
        if ( php_sapi_name() === 'cli' ) {
            return false;
        }

        if ( ! in_array( $this->method, self::SUPPORTED_METHODS, true ) ) {
            return false;
        }

        if ( stripos( $this->content_type, 'multipart/form-data' ) === false ) {
            return false;
        }

        return true;
    }

    /**
     * Parse multipart request.
     *
     * @return array{post: array, files: array}
     * @throws Exception
     */
    public function parse(): array {
        if ( $this->parsed ) {
            return [
                'post'  => $this->post_data,
                'files' => $this->files_data,
            ];
        }

        if ( ! $this->should_parse() ) {
            $this->parsed = true;
            return [ 'post' => [], 'files' => [] ];
        }

        try {
            $boundary = $this->extract_boundary();
            $this->validate_content_length();
            $this->stream_parse( $boundary );

            $this->parsed = true;

            return [
                'post'  => $this->post_data,
                'files' => $this->files_data,
            ];

        } catch ( Exception $e ) {
            $this->cleanup();
            throw $e;
        }
    }

    /**
     * Populate global arrays.
     *
     * @return bool
     * @throws Exception
     */
    public function populate_globals(): bool {
        if ( ! $this->should_parse() ) {
            return false;
        }

        $result = $this->parse();

        $_POST    = $this->merge_arrays( $result['post'], $_POST );
        $_FILES   = $this->merge_files_arrays( $result['files'], $_FILES );
        $_REQUEST = $this->merge_arrays( $result['post'], $_REQUEST );

        return true;
    }

    /**
     * Recursively merge arrays.
     *
     * @param array $new      New data
     * @param array $existing Existing data
     * @return array
     */
    private function merge_arrays( array $new, array $existing ): array {
        foreach ( $new as $key => $value ) {
            if ( isset( $existing[ $key ] ) && is_array( $existing[ $key ] ) && is_array( $value ) ) {
                $existing[ $key ] = $this->merge_arrays( $value, $existing[ $key ] );
            } elseif ( ! isset( $existing[ $key ] ) ) {
                $existing[ $key ] = $value;
            }
        }

        return $existing;
    }

    /**
     * Merge FILES arrays with native structure preservation.
     *
     * @param array $new      New files
     * @param array $existing Existing files
     * @return array
     */
    private function merge_files_arrays( array $new, array $existing ): array {
        foreach ( $new as $field => $data ) {
            if ( isset( $existing[ $field ] ) ) {
                $existing[ $field ] = $this->merge_file_entries( $data, $existing[ $field ] );
            } else {
                $existing[ $field ] = $data;
            }
        }

        return $existing;
    }

    /**
     * Merge individual file entries.
     *
     * @param array $new      New file data
     * @param array $existing Existing file data
     * @return array
     */
    private function merge_file_entries( array $new, array $existing ): array {
        // Check if multiple file structure
        $is_new_multiple = isset( $new['name'] ) && is_array( $new['name'] );
        $is_existing_multiple = isset( $existing['name'] ) && is_array( $existing['name'] );

        if ( $is_new_multiple && $is_existing_multiple ) {
            // Both are multiple - merge arrays
            foreach ( [ 'name', 'type', 'tmp_name', 'error', 'size' ] as $key ) {
                if ( isset( $new[ $key ] ) && isset( $existing[ $key ] ) ) {
                    $existing[ $key ] = array_merge( (array) $existing[ $key ], (array) $new[ $key ] );
                }
            }
            return $existing;
        }

        if ( ! $is_new_multiple && ! $is_existing_multiple ) {
            // Both single - convert to multiple.
            $merged = [
                'name'     => [ $existing['name'], $new['name'] ],
                'type'     => [ $existing['type'], $new['type'] ],
                'tmp_name' => [ $existing['tmp_name'], $new['tmp_name'] ],
                'error'    => [ $existing['error'], $new['error'] ],
                'size'     => [ $existing['size'], $new['size'] ],
            ];
            return $merged;
        }

        // Mixed - convert single to multiple and merge.
        if ( ! $is_existing_multiple ) {
            $existing = $this->convert_to_multiple( $existing );
        }

        if ( ! $is_new_multiple ) {
            $new = $this->convert_to_multiple( $new );
        }

        foreach ( [ 'name', 'type', 'tmp_name', 'error', 'size' ] as $key ) {
            $existing[ $key ] = array_merge( (array) $existing[ $key ], (array) $new[ $key ] );
        }

        return $existing;
    }

    /**
     * Convert single file structure to multiple.
     *
     * @param array $file Single file
     * @return array Multiple structure
     */
    private function convert_to_multiple( array $file ): array {
        return [
            'name'     => [ $file['name'] ],
            'type'     => [ $file['type'] ],
            'tmp_name' => [ $file['tmp_name'] ],
            'error'    => [ $file['error'] ],
            'size'     => [ $file['size'] ],
        ];
    }

    /**
     * Extract and validate boundary.
     *
     * @return string
     * @throws Exception
     */
    private function extract_boundary(): string {
        if ( ! preg_match( '/boundary=(["\']?)([^"\';\s]+)\1/i', $this->content_type, $m ) ) {
            throw new Exception( 'missing_boundary', 'No boundary in Content-Type.' );
        }

        $boundary = $m[2];

        // RFC 2046: 1-70 chars, specific allowed characters
        if ( ! preg_match( '/^[A-Za-z0-9\'\(\)\+_,\-\.\/:=\? ]{1,70}$/', $boundary ) ) {
            throw new Exception( 'invalid_boundary', 'Boundary violates RFC 2046.' );
        }

        return $boundary;
    }

    /**
     * Validate Content-Length.
     *
     * @throws Exception
     */
    private function validate_content_length(): void {
        $content_length = $_SERVER['CONTENT_LENGTH'] ?? null;

        if ( $content_length === null ) {
            // Chunked encoding - will enforce during streaming.
            return;
        }

        $content_length = (int) $content_length;

        if ( $content_length <= 0 ) {
            throw new Exception( 'empty_body', 'Content-Length is zero or negative.' );
        }

        $post_max = $this->limits['post_max_size'];

        if ( $post_max > 0 && $content_length > $post_max ) {
            throw new Exception(
                'post_max_size_exceeded',
                sprintf(
                    'Content-Length (%s) exceeds post_max_size (%s).',
                    FileSystemHelper::format_file_size( $content_length ),
                    FileSystemHelper::format_file_size( $post_max )
                )
            );
        }
    }

    /**
     * Stream-based parsing with state machine.
     *
     * @param string $boundary
     * @throws Exception
     */
    private function stream_parse( string $boundary ): void {
        $stream = @fopen( 'php://input', 'rb' );

        if ( ! $stream ) {
            throw new Exception( 'stream_open_failed', 'Cannot open php://input.' );
        }

        try {
            $debug = defined( 'SMLISER_DEBUG_MULTIPART' ) && constant( 'SMLISER_DEBUG_MULTIPART' );
            $parser = new MultipartStreamParser( $stream, $boundary, $this->limits, $this, $debug );
            $parser->parse();

            $this->post_data = $parser->get_post_data();
            $this->files_data = $parser->get_files_data();
            $this->temp_files = $parser->get_temp_files();
            $this->bytes_read = $parser->get_bytes_read();
            $this->files_count = $parser->get_files_count();

            // Store debug log if debugging
            if ( $debug ) {
                $this->debug_log = $parser->get_debug_log();
            }

        } finally {
            fclose( $stream );
        }
    }

    /**
     * Get debug log (if debug mode enabled).
     *
     * @return array
     */
    public function get_debug_log(): array {
        return $this->debug_log ?? [];
    }

    /**
     * Debug log storage.
     *
     * @var array
     */
    private array $debug_log = [];

    /**
     * Track file for cleanup.
     *
     * @param string $path
     */
    public function track_temp_file( string $path ): void {
        $this->temp_files[] = $path;
    }

    /**
     * Cleanup temp files safely.
     */
    public function cleanup(): void {
        $sys_tmp = realpath( sys_get_temp_dir() );

        foreach ( $this->temp_files as $path ) {
            $real_path = realpath( $path );

            // Security: only delete files in sys temp dir
            if ( $real_path && strpos( $real_path, $sys_tmp ) === 0 ) {
                if ( file_exists( $real_path ) ) {
                    @unlink( $real_path );
                }
            }
        }

        $this->temp_files = [];
    }

    /**
     * Get parsed POST data.
     *
     * @return array
     */
    public function get_post_data(): array {
        if ( ! $this->parsed ) {
            $this->parse();
        }

        return $this->post_data;
    }

    /**
     * Get parsed FILES data.
     *
     * @return array
     */
    public function get_files_data(): array {
        if ( ! $this->parsed ) {
            $this->parse();
        }

        return $this->files_data;
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public function get_stats(): array {
        return [
            'method'       => $this->method,
            'parsed'       => $this->parsed,
            'bytes_read'   => FileSystemHelper::format_file_size( $this->bytes_read ),
            'post_count'   => count( $this->post_data ),
            'files_count'  => $this->files_count,
            'temp_files'   => count( $this->temp_files ),
            'limits'       => [
                'post_max_size'       => FileSystemHelper::format_file_size( $this->limits['post_max_size'] ),
                'upload_max_filesize' => FileSystemHelper::format_file_size( $this->limits['upload_max_filesize'] ),
                'max_file_uploads'    => $this->limits['max_file_uploads'],
                'memory_limit'        => FileSystemHelper::format_file_size( $this->limits['memory_limit'] ),
            ],
        ];
    }

    /**
     * Destructor.
     */
    public function __destruct() {
        $this->cleanup();
    }
}


/**
 * Internal stream parser with state machine.
 */
class MultipartStreamParser {

    /**
     * Parser states.
     */
    private const STATE_PREAMBLE         = 1;
    private const STATE_HEADERS          = 2;
    private const STATE_BODY             = 3;
    private const STATE_COMPLETE         = 4;

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
     * Parent parser (for temp file tracking).
     *
     * @var MultipartRequestParser
     */
    private MultipartRequestParser $parent;

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
     * Temp files created.
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
     * Constructor.
     *
     * @param resource                $stream
     * @param string                  $boundary
     * @param array                   $limits
     * @param MultipartRequestParser  $parent
     * @param bool                    $debug Enable debug logging
     */
    public function __construct( $stream, string $boundary, array $limits, MultipartRequestParser $parent, bool $debug = false ) {
        $this->stream          = $stream;
        $this->boundary        = $boundary;
        $this->boundary_marker = "--{$boundary}";
        $this->boundary_close  = "--{$boundary}--";
        $this->limits          = $limits;
        $this->parent          = $parent;
        $this->debug           = $debug || defined( 'SMLISER_DEBUG_MULTIPART' );
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
            'state'       => $this->get_state_name(),
            'buffer_len'  => strlen( $this->buffer ),
            'bytes_read'  => $this->bytes_read,
            'message'     => $message,
            'context'     => $context,
        ];

        $this->debug_log[] = $entry;

        if ( defined( 'SMLISER_DEBUG_MULTIPART' ) && constant( 'SMLISER_DEBUG_MULTIPART' ) ) {
            error_log( sprintf(
                '[MultipartParser] [%s] %s (buffer: %d, read: %d)',
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
            // Read more data if buffer is low and stream has data
            if ( strlen( $this->buffer ) < 8192 && ! feof( $this->stream ) ) {
                $this->read_chunk();
            }

            // If we have no data and stream is exhausted, we're done
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

            // Safety check: if state hasn't changed and buffer is empty, read more
            $prev_state = $this->state;
            $prev_buffer_len = strlen( $this->buffer );
            
            // Detect stuck state
            static $stuck_count = 0;
            if ( $this->state === $prev_state && strlen( $this->buffer ) === $prev_buffer_len && strlen( $this->buffer ) > 0 ) {
                $stuck_count++;
                if ( $stuck_count > 3 && ! feof( $this->stream ) ) {
                    $this->read_chunk(); // Force read
                    $stuck_count = 0;
                }
            } else {
                $stuck_count = 0;
            }
        }

        // Finalize any pending part.
        if ( $this->state === self::STATE_BODY ) {
            // Process remaining buffer
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

        // Close any open file
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

        // Enforce post_max_size
        $post_max = $this->limits['post_max_size'];

        if ( $post_max > 0 && $this->bytes_read > $post_max ) {
            throw new Exception(
                'post_max_size_exceeded',
                sprintf( 'Request exceeded post_max_size (%s).', FileSystemHelper::format_file_size( $post_max ) )
            );
        }

        // Enforce memory_limit for buffer
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
        // Look for first boundary: --BOUNDARY\r\n or --BOUNDARY\n
        $pos = strpos( $this->buffer, $this->boundary_marker );

        if ( $pos === false ) {
            // Keep last bytes in buffer (boundary might span chunks)
            $keep = min( strlen( $this->buffer ), strlen( $this->boundary_marker ) + 2 );
            $this->buffer = substr( $this->buffer, -$keep );
            return;
        }

        // Found boundary - check what follows
        $after_boundary = $pos + strlen( $this->boundary_marker );

        if ( ! isset( $this->buffer[ $after_boundary ] ) ) {
            return; // Need more data
        }

        $next_chars = substr( $this->buffer, $after_boundary, 4 );

        // Check for closing boundary (--BOUNDARY--)
        if ( substr( $next_chars, 0, 2 ) === '--' ) {
            // Empty multipart - just closing boundary
            $this->state = self::STATE_COMPLETE;
            return;
        }

        // Must be followed by CRLF or LF
        if ( substr( $next_chars, 0, 2 ) === "\r\n" ) {
            $this->buffer = substr( $this->buffer, $after_boundary + 2 );
        } elseif ( substr( $next_chars, 0, 1 ) === "\n" ) {
            $this->buffer = substr( $this->buffer, $after_boundary + 1 );
        } else {
            throw new Exception( 'malformed_boundary', 'Boundary not followed by CRLF.' );
        }

        $this->state = self::STATE_HEADERS;
        $this->current_headers = [];
        $this->header_bytes = 0;
    }

    /**
     * Process headers state.
     *
     * @throws Exception
     */
    private function process_headers(): void {
        // Headers end with CRLF CRLF or LF LF
        $end_pos = strpos( $this->buffer, "\r\n\r\n" );
        $sep_len = 4;

        if ( $end_pos === false ) {
            $end_pos = strpos( $this->buffer, "\n\n" );
            $sep_len = 2;
        }

        if ( $end_pos === false ) {
            // Haven't found end of headers yet
            
            // Enforce max headers size
            if ( strlen( $this->buffer ) > 65536 ) {
                throw new Exception( 'headers_too_large', 'Headers exceed 64KB limit.' );
            }
            
            // Need more data - check if stream has ended
            if ( feof( $this->stream ) && strlen( $this->buffer ) > 0 ) {
                // Malformed - headers never ended
                throw new Exception( 'malformed_headers', 'Headers incomplete at end of stream.' );
            }
            
            return; // Need more data
        }

        $raw_headers = substr( $this->buffer, 0, $end_pos );
        $this->buffer = substr( $this->buffer, $end_pos + $sep_len );

        $this->current_headers = $this->parse_headers( $raw_headers );

        if ( ! isset( $this->current_headers['content-disposition'] ) ) {
            throw new Exception( 'missing_content_disposition', 'Part missing Content-Disposition header.' );
        }

        $this->current_disposition = $this->parse_content_disposition( $this->current_headers['content-disposition'] );

        if ( ! isset( $this->current_disposition['name'] ) ) {
            throw new Exception( 'missing_field_name', 'Content-Disposition missing name parameter.' );
        }

        // Validate field name
        $this->validate_field_name( $this->current_disposition['name'] );

        $this->state = self::STATE_BODY;
        $this->current_field_body = '';
        $this->current_file_size = 0;

        // Open file if this is a file upload
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
        // Search for next boundary with proper delimiters
        // Try CRLF first (standard)
        $crlf_boundary = "\r\n" . $this->boundary_marker;
        $lf_boundary = "\n" . $this->boundary_marker;
        
        $pos_crlf = strpos( $this->buffer, $crlf_boundary );
        $pos_lf = strpos( $this->buffer, $lf_boundary );
        
        // Determine which boundary we found
        $pos = false;
        $boundary_len = 0;
        $boundary_type = null;
        
        if ( $pos_crlf !== false && ( $pos_lf === false || $pos_crlf <= $pos_lf ) ) {
            $pos = $pos_crlf;
            $boundary_len = strlen( $crlf_boundary );
            $boundary_type = 'CRLF';
        } elseif ( $pos_lf !== false ) {
            $pos = $pos_lf;
            $boundary_len = strlen( $lf_boundary );
            $boundary_type = 'LF';
        }

        if ( $pos === false ) {
            // No boundary found yet - process available data
            $this->debug_log( 'No boundary in buffer, accumulating body data' );
            
            // Keep enough buffer to detect boundary that might span chunks
            $keep = max( strlen( $crlf_boundary ), strlen( $lf_boundary ) ) + 4;

            if ( strlen( $this->buffer ) > $keep ) {
                $to_write = substr( $this->buffer, 0, -$keep );
                $this->buffer = substr( $this->buffer, -$keep );

                if ( $this->current_file_handle ) {
                    // Stream to file
                    $this->write_to_file( $to_write );
                } else {
                    // Accumulate field body
                    $this->current_field_body .= $to_write;

                    // Enforce reasonable field size (1MB)
                    if ( strlen( $this->current_field_body ) > 1048576 ) {
                        throw new Exception( 'field_too_large', 'Form field exceeds 1MB.' );
                    }
                }
            }

            return;
        }

        // Found boundary
        $this->debug_log( 'Found boundary', [
            'type'     => $boundary_type,
            'position' => $pos,
            'body_len' => $pos,
        ] );

        // Extract body data before it
        $body_data = substr( $this->buffer, 0, $pos );

        if ( $this->current_file_handle ) {
            $this->write_to_file( $body_data );
            $this->close_current_file();
        } else {
            $this->current_field_body .= $body_data;
        }

        // Finalize current part
        $this->finalize_current_part();

        // Move buffer past the boundary delimiter
        $this->buffer = substr( $this->buffer, $pos + $boundary_len );

        // Check what follows the boundary
        // Need at least 2 chars to check for closing marker
        if ( strlen( $this->buffer ) < 2 ) {
            $this->debug_log( 'Need more data after boundary' );
            // Need more data - but mark that we're between parts
            $this->state = self::STATE_HEADERS;
            $this->current_headers = [];
            $this->header_bytes = 0;
            return;
        }

        // Check for closing boundary (--BOUNDARY--)
        if ( substr( $this->buffer, 0, 2 ) === '--' ) {
            $this->debug_log( 'Found closing boundary, parsing complete' );
            $this->state = self::STATE_COMPLETE;
            return;
        }

        // Skip CRLF or LF after boundary
        if ( substr( $this->buffer, 0, 2 ) === "\r\n" ) {
            $this->buffer = substr( $this->buffer, 2 );
        } elseif ( substr( $this->buffer, 0, 1 ) === "\n" ) {
            $this->buffer = substr( $this->buffer, 1 );
        }

        $this->debug_log( 'Starting next part' );

        // Start next part
        $this->state = self::STATE_HEADERS;
        $this->current_headers = [];
        $this->header_bytes = 0;
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
        $lines = explode( "\n", str_replace( "\r\n", "\n", $raw_headers ) );

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( empty( $line ) ) {
                continue;
            }

            // Enforce max line length
            if ( strlen( $line ) > 8192 ) {
                throw new Exception( 'header_line_too_long', 'Header line exceeds 8KB.' );
            }

            $colon = strpos( $line, ':' );

            if ( $colon === false ) {
                continue;
            }

            $key = strtolower( trim( substr( $line, 0, $colon ) ) );
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

        // name
        if ( preg_match( '/\bname=(?:"([^"]+)"|([^;\s]+))/i', $value, $m ) ) {
            $result['name'] = $m[1] ?: $m[2];
        }

        // filename
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
        // Max length
        if ( strlen( $name ) > 255 ) {
            throw new Exception( 'field_name_too_long', 'Field name exceeds 255 chars.' );
        }

        // Check for balanced brackets
        $open = substr_count( $name, '[' );
        $close = substr_count( $name, ']' );

        if ( $open !== $close ) {
            throw new Exception( 'malformed_field_name', 'Unbalanced brackets in field name.' );
        }

        // Check for valid nested structure
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

        $tmp_path = @tempnam( sys_get_temp_dir(), SMLISER_UPLOAD_TMP_PREFIX );

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
        $this->current_file_path = $tmp_path;
        $this->current_file_size = 0;
        $this->temp_files[] = $tmp_path;
        $this->parent->track_temp_file( $tmp_path );
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

        // Enforce upload_max_filesize
        $upload_max = $this->limits['upload_max_filesize'];

        if ( $upload_max > 0 && $this->current_file_size + $len > $upload_max ) {
            throw new Exception(
                'file_too_large',
                sprintf( 'File exceeds upload_max_filesize (%s).', FileSystemHelper::format_file_size( $upload_max ) )
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
            // File upload
            $filename = FileSystemHelper::sanitize_filename( $this->current_disposition['filename'] );

            $this->debug_log( 'Finalizing file upload', [
                'field_name' => $field_name,
                'filename'   => $filename,
                'size'       => $this->current_file_size,
                'has_path'   => ! empty( $this->current_file_path ),
            ] );

            if ( $this->current_file_path ) {
                // Detect MIME (never trust client)
                $mime = FileSystemHelper::get_mime_type( $this->current_file_path );

                if ( ! $mime ) {
                    $mime = $this->current_headers['content-type'] ?? 'application/octet-stream';
                }

                $this->add_file_entry( $field_name, $filename, $mime, $this->current_file_path, UPLOAD_ERR_OK, $this->current_file_size );
            } else {
                // Empty upload
                $this->add_file_entry( $field_name, $filename, '', '', UPLOAD_ERR_NO_FILE, 0 );
            }

            $this->current_file_path = null;
            $this->current_file_size = 0;

        } else {
            // Form field
            $this->debug_log( 'Finalizing form field', [
                'field_name' => $field_name,
                'value_len'  => strlen( $this->current_field_body ),
                'value'      => substr( $this->current_field_body, 0, 100 ),
            ] );

            $this->add_post_field( $field_name, $this->current_field_body );
            $this->current_field_body = '';
        }

        // Reset disposition
        $this->current_disposition = [];
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

        $base = $m[1];
        $bracket_part = $m[2] ?? '';

        if ( empty( $bracket_part ) ) {
            return [ 'is_array' => false, 'base' => $base, 'keys' => [] ];
        }

        // Parse nested keys: [key1][key2][key3]
        preg_match_all( '/\[([^\]]*)\]/', $bracket_part, $matches );

        $keys = $matches[1];

        // Empty brackets [] means append
        if ( count( $keys ) === 1 && $keys[0] === '' ) {
            return [ 'is_array' => true, 'base' => $base, 'keys' => null ];
        }

        return [ 'is_array' => true, 'base' => $base, 'keys' => $keys ];
    }

    /**
     * Set nested value.
     *
     * @param array  &$array
     * @param array  $keys
     * @param mixed  $value
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
     * @param array &$files
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