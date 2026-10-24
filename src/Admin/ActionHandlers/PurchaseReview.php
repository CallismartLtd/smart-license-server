<?php
/**
 * Purchase request review actions class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Admin\ActionHandlers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;

/**
 * The actions of the purchase request review page: POST routes answering JSON.
 *
 *   POST <admin>/admin/json/purchase-request-note     Add a note (field "note"; "notify_buyer" to email it).
 *   POST <admin>/admin/json/purchase-request-approve  Issue the license (optional field "message").
 *   POST <admin>/admin/json/purchase-request-reject   Reject it (field "reason").
 *
 * Every action takes "request_id" and needs monetization.license.issue:
 * the reviewers of purchase requests are those who may issue licenses. The
 * answer is { success, message, redirect } for page-forms.js; the redirect
 * reloads the review page.
 */
class PurchaseReview {
	use SecurityAwareTrait;

	/**
	 * @param PurchaseRequestService $requests
	 * @param URLManager             $urlmanager
	 * @param Guard                  $guard
	 */
	public function __construct(
		protected PurchaseRequestService $requests,
		protected URLManager $urlmanager,
		Guard $guard
	) {
		$this->guard = $guard;
	}

	/**
	 * Add a note to a request.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_note( Request $request ) : Response {
		return $this->run( $request, function ( PurchaseRequest $purchase, int $reviewer ) use ( $request ) {
			$notify = $request->has( 'notify_buyer' );
			$note   = $this->requests->add_note( $purchase, $reviewer, (string) $request->get( 'note', '' ), $notify );

			return $note instanceof Exception ? $note : ( $notify ? 'Note saved and emailed to the buyer.' : 'Note saved.' );
		} );
	}

	/**
	 * Approve a request: issue its license and email it.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_approve( Request $request ) : Response {
		return $this->run( $request, function ( PurchaseRequest $purchase, int $reviewer ) use ( $request ) {
			$license = $this->requests->approve( $purchase, $reviewer, (string) $request->get( 'message', '' ) );

			return $license instanceof Exception
				? $license
				: sprintf( 'Approved. License #%d was issued and is being emailed to %s.', $license->get_id(), $purchase->get_buyer_email() );
		} );
	}

	/**
	 * Reject a request with a reason.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_reject( Request $request ) : Response {
		return $this->run( $request, function ( PurchaseRequest $purchase, int $reviewer ) use ( $request ) {
			$result = $this->requests->reject( $purchase, $reviewer, (string) $request->get( 'reason', '' ) );

			return $result instanceof Exception ? $result : sprintf( 'Rejected. The reason is being emailed to %s.', $purchase->get_buyer_email() );
		} );
	}

	/**
	 * Check the reviewer, find the request, run the action and answer.
	 *
	 * @param Request                                        $request
	 * @param callable( PurchaseRequest, int ): (string|Exception) $action Returns the success message, or the failure.
	 * @return Response
	 */
	protected function run( Request $request, callable $action ) : Response {
		try {
			$this->check_permissions( 'monetization.license.issue' );
		} catch ( RequestException $e ) {
			return Response::error( $e )->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}

		$purchase = PurchaseRequest::get_by_id( (int) $request->get( 'request_id', 0 ) );

		if ( ! $purchase ) {
			return $this->failure( new Exception( 'request_not_found', 'This purchase request does not exist.', [ 'status' => 404 ] ) );
		}

		$result = $action( $purchase, (int) $this->guard->get_principal()?->get_actor()->get_id() );

		if ( $result instanceof Exception ) {
			return $this->failure( $result );
		}

		return Response::json(
			[
				'success'  => true,
				'message'  => $result,
				'redirect' => $this->urlmanager->admin_license_page_url( 'purchase-request', [ 'id' => $purchase->get_id() ] )->url(),
			],
			200
		);
	}

	/**
	 * A failure as JSON.
	 *
	 * @param Exception $e With a "status" and, when it applies, a "field_id".
	 * @return Response
	 */
	protected function failure( Exception $e ) : Response {
		$data = $e->get_error_data();

		return Response::json(
			array_filter(
				[
					'success'  => false,
					'code'     => $e->get_error_code(),
					'message'  => $e->get_error_message(),
					'field_id' => $data['field_id'] ?? null,
				],
				static fn ( $value ) : bool => null !== $value
			),
			(int) ( $data['status'] ?? 400 )
		);
	}
}