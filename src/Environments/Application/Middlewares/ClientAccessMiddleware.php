<?php
/**
 * Client access middleware class file.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Lets only signed-in visitors into the client dashboard.
 *
 * A visitor is sent to the sign-in page with a redirect_url back here; a
 * script gets a JSON 401. The client dashboard counterpart of
 * AdminAccessMiddleware, which also requires the system_admin role.
 */
class ClientAccessMiddleware implements MiddlewareInterface {

	/**
	 * Constructor.
	 *
	 * @param Guard      $guard      The signed-in principal, if any.
	 * @param URLManager $urlmanager URL API.
	 */
	public function __construct( protected Guard $guard, protected URLManager $urlmanager ) {}

	/**
	 * {@inheritdoc}
	 */
	public function handle( Request $request, callable $next ) : mixed {
		if ( $this->guard->has_principal() ) {
			return $next( $request );
		}

		if ( $request->wantsJson() ) {
			return Response::json( [
				'success' => false,
				'message' => 'Unauthenticated access. Please log in to continue.',
				'code'    => 'UNAUTHENTICATED',
			], 401 );
		}

		$is_mutation = in_array( $request->method(), [ Request::POST, Request::PATCH, Request::PUT, Request::DELETE ], true );
		$return_url  = \smliser_get_current_url();

		// Form submission after signing out: return to the page the form was on.
		if ( $is_mutation && $request->referer() ) {
			$referer_url = URL::from( $request->referer() );

			if ( $referer_url->is_valid() ) {
				$return_url = $referer_url;
			}
		}

		// Prevent open redirects.
		$app_origin = $this->urlmanager->url( '/' )->get_origin();

		if ( ! $return_url->is_valid() || $return_url->get_origin() !== $app_origin ) {
			$return_url = \smliser_get_current_url();
		}

		$location = $this->urlmanager->login_url()->add_query_param( 'redirect_url', $return_url->url() );

		// 307 keeps the method and body of a form submission; 302 for GET and HEAD.
		return Response::make( '', $is_mutation ? 307 : 302 )
			->set_header( 'Location', $location->url() );
	}
}
