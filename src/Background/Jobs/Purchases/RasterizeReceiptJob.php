<?php
/**
 * Rasterize receipt job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Purchases
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Purchases;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Monetization\ReceiptRasterizer;

/**
 * Renders the pages of a PDF receipt to images (ReceiptRasterizer).
 *
 * Queued when a buyer submits a PDF receipt, so the buyer never waits for
 * it. When it fails or cannot run, reviewers see the PDF in the browser's
 * own viewer instead.
 */
class RasterizeReceiptJob implements JobHandlerInterface {

	public function __construct(
		protected PurchaseRequestService $requests,
		protected ReceiptRasterizer $rasterizer
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * @param array{request_id?: int} $payload
	 * @return mixed
	 */
	public function handle( array $payload = [] ) : mixed {
		$request = PurchaseRequest::get_by_id( (int) ( $payload['request_id'] ?? 0 ) );

		if ( ! $request || 'application/pdf' !== $request->get_receipt_mime() ) {
			return [ 'success' => false, 'message' => 'There is no PDF receipt for this request.' ];
		}

		$path = $this->requests->get_receipt_path( $request );

		if ( null === $path ) {
			return [ 'success' => false, 'message' => 'The receipt file is missing.' ];
		}

		$pages = $this->rasterizer->rasterize( $path );

		if ( $pages instanceof Exception ) {
			return [ 'success' => false, 'message' => $pages->get_error_message() ];
		}

		return [ 'success' => true, 'message' => sprintf( '%d page(s) rendered.', $pages ) ];
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Render PDF Receipt';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Render the pages of a PDF payment receipt to images, so reviewers see it the same in every browser.';
	}
}