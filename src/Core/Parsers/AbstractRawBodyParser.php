<?php
/**
 * Shared streaming raw-body reader for non-multipart content types.
 *
 * @package SmartLicenseServer\Core\Parsers
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Core\Parsers;

use SmartLicenseServer\Exceptions\Exception;

/**
 * Base class for parsers that need the whole body read into memory
 * (json, urlencoded), enforcing post_max_size/memory_limit while
 * streaming it off php://input the same way the multipart engine does.
 */
abstract class AbstractRawBodyParser implements RequestBodyParserInterface {

	/**
	 * Stream read chunk size (4KB for optimal I/O).
	 */
	private const READ_CHUNK_SIZE = 4096;

	/**
	 * Total bytes read from the request stream.
	 *
	 * @var int
	 */
	protected int $bytes_read = 0;

	/**
	 * Constructor.
	 *
	 * @param array $limits Parsed php.ini limits (post_max_size, memory_limit).
	 */
	public function __construct( protected array $limits ) {}

	/**
	 * @inheritDoc
	 */
	public function get_bytes_read(): int {
		return $this->bytes_read;
	}

	/**
	 * @inheritDoc
	 */
	public function get_temp_files(): array {
		return [];
	}

	/**
	 * Stream-read the raw request body while enforcing post_max_size
	 * and memory_limit.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function read_raw_body(): string {
		$stream = @fopen( 'php://input', 'rb' );

		if ( ! $stream ) {
			throw new Exception( 'stream_open_failed', 'Cannot open php://input.' );
		}

		$post_max  = $this->limits['post_max_size'] ?? 0;
		$mem_limit = $this->limits['memory_limit'] ?? 0;
		$body      = '';

		try {
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, self::READ_CHUNK_SIZE );

				if ( false === $chunk ) {
					throw new Exception( 'stream_read_error', 'Failed to read from stream.' );
				}

				if ( '' === $chunk ) {
					break;
				}

				$this->bytes_read += strlen( $chunk );

				if ( $post_max > 0 && $this->bytes_read > $post_max ) {
					throw new Exception(
						'post_max_size_exceeded',
						sprintf( 'Request exceeded post_max_size (%d bytes).', $post_max )
					);
				}

				$body .= $chunk;

				if ( $mem_limit > 0 && strlen( $body ) > $mem_limit / 4 ) {
					throw new Exception( 'memory_limit_exceeded', 'Body size approaching memory_limit.' );
				}
			}
		} finally {
			fclose( $stream );
		}

		return $body;
	}
}