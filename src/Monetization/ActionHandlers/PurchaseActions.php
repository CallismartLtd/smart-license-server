<?php
/**
 * PurchaseActions class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization\ActionHandlers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The action of the purchase page (PurchasePages): a POST answering JSON.
 *
 *   POST /purchase/{tier_id}/submit  Record a purchase request with its receipt.
 *
 * The answer is { success, message } for the page-forms.js script, plus
 * "field_id" when one field is at fault.
 */
class PurchaseActions {

	/**
	 * @param PurchaseRequestService $requests
	 * @param Guard                  $guard
	 */
	public function __construct(
		private PurchaseRequestService $requests,
		private Guard $guard
	) {}

	/**
	 * POST /purchase/{tier_id}/submit
	 *
	 * Fields: buyer_name, buyer_email (guests), payment_reference, note,
	 * create_account (guests), and the file "receipt".
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function submit( Request $request ) : Response {
		$user = $this->guard->get_principal()?->get_actor();

		$result = $this->requests->submit(
			(int) $request->route_param( 'tier_id', 0 ),
			[
				'buyer_name'        => (string) $request->get( 'buyer_name', '' ),
				'buyer_email'       => (string) $request->get( 'buyer_email', '' ),
				'payment_reference' => (string) $request->get( 'payment_reference', '' ),
				'note'              => (string) $request->get( 'note', '' ),
				'create_account'    => $request->has( 'create_account' ),
			],
			$request->get_file( 'receipt' ),
			$user instanceof User ? $user : null,
			(string) $request->ip()
		);

		if ( $result instanceof Exception ) {
			$data = $result->get_error_data();

			return Response::json(
				array_filter(
					[
						'success'  => false,
						'code'     => $result->get_error_code(),
						'message'  => $result->get_error_message(),
						'field_id' => $data['field_id'] ?? null,
					],
					static fn ( $value ) : bool => null !== $value
				),
				(int) ( $data['status'] ?? 400 )
			);
		}

		return Response::json(
			[
				'success' => true,
				'message' => sprintf(
					'Thank you. Your purchase request #%d has been received and will be reviewed shortly; the license will be sent to %s once your payment is confirmed.',
					$result->get_id(),
					$result->get_buyer_email()
				),
			],
			200
		);
	}
}
