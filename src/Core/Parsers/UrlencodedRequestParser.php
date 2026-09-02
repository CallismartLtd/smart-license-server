<?php
/**
 * application/x-www-form-urlencoded request body parser.
 *
 * @package SmartLicenseServer\Core\Parsers
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Core\Parsers;

/**
 * Decodes an application/x-www-form-urlencoded request body into the
 * post/files shape shared by every RequestBodyParserInterface implementation.
 */
class UrlencodedRequestParser extends AbstractRawBodyParser {

	/**
	 * @inheritDoc
	 */
	public function parse(): array {
		$raw = $this->read_raw_body();

		$data = [];
		parse_str( $raw, $data );

		return [ 'post' => $data, 'files' => [] ];
	}
}