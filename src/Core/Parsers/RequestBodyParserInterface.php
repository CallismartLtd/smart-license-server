<?php
/**
 * Contract for a dedicated content-type request body parser.
 *
 * @package SmartLicenseServer\Core\Parsers
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Core\Parsers;

use SmartLicenseServer\Exceptions\Exception;

/**
 * A parser dedicated to a single request body content type.
 */
interface RequestBodyParserInterface {

	/**
	 * Parse the request body.
	 *
	 * @return array{post: array, files: array}
	 * @throws Exception
	 */
	public function parse(): array;

	/**
	 * Total bytes read from the request stream.
	 *
	 * @return int
	 */
	public function get_bytes_read(): int;

	/**
	 * Temporary files created while parsing. Empty for parsers that
	 * never write to disk (json, urlencoded).
	 *
	 * @return string[]
	 */
	public function get_temp_files(): array;
}