<?php
/**
 * Client context switch class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\OwnerSubjects\Organization;

/**
 * Switch who the signed-in user acts for (<client dashboard prefix>/json/context).
 *
 * Field "context": "individual:<user ID>" for the user themselves, or
 * "organization:<organization ID>": the values the dashboard's context
 * switcher lists (ClientDashboard\Page\Dispatcher). The rules are
 * WebIdentityProvider::switch_context()'s. The answer is
 * { success, message, redirect } for page-forms.js; the redirect opens
 * the overview, since a page of one context may not exist in another.
 */
final class ContextSwitch {

	/**
	 * @param WebIdentityProvider $identity   Switches the session's context.
	 * @param URLManager          $urlmanager For the redirect.
	 */
	public function __construct(
		private WebIdentityProvider $identity,
		private URLManager $urlmanager
	) {}

	/**
	 * POST <client dashboard prefix>/json/context
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function switch( Request $request ) : Response {
		[ $type, $id ] = array_pad( explode( ':', (string) $request->get( 'context', '' ), 2 ), 2, '' );

		$subject = match ( $type ) {
			Owner::TYPE_INDIVIDUAL   => User::get_by_id( (int) $id ),
			Owner::TYPE_ORGANIZATION => Organization::get_by_id( (int) $id ),
			default                  => null,
		};

		if ( null === $subject ) {
			return $this->answer( false, 'Choose who to act for.', 400 );
		}

		$result = $this->identity->switch_context( $subject );

		if ( $result instanceof RequestException ) {
			$data = $result->get_error_data();

			return $this->answer( false, $result->get_error_message(), (int) ( is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 ) );
		}

		return Response::json(
			[
				'success'  => true,
				'message'  => sprintf( 'You now act for %s.', $subject instanceof User ? 'yourself' : $subject->get_display_name() ),
				'redirect' => $this->urlmanager->client_dashboard_url()->url(),
			],
			200
		);
	}

	/**
	 * @param bool   $success
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function answer( bool $success, string $message, int $status ) : Response {
		return Response::json( [ 'success' => $success, 'message' => $message ], $status );
	}
}