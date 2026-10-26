<?php
/**
 * Client app actions class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The client dashboard's app actions (<client dashboard prefix>/json/apps/…).
 *
 * The apps are the current owner's: the user, or the organization they act
 * for. An app of any other owner is not found here, even for a system
 * administrator, who manages other owners' apps in the admin dashboard.
 *
 *   POST apps/save            Create ("app_type", "app_name", "app_author", "app_zip_file")
 *                             or update ("app_type", "app_id", details, an optional new
 *                             "app_zip_file" and "app_json_file"). A new app always
 *                             belongs to the current owner; "app_owner_id" is ignored.
 *   POST apps/status          "app_type", "app_slug", "app_status".
 *   POST apps/delete          "app_type", "app_slug": an app in the trash, for good.
 *   POST apps/assets/upload   "app_type", "app_slug", "asset_type", "asset_file[]".
 *   POST apps/assets/delete   "app_type", "app_slug", "asset_name".
 *
 * The rules, capabilities included, are HostingController's, so each is
 * enforced once for the admin dashboard, the REST API, the CLI and here.
 * This class only scopes the request to the current owner and answers
 * { success, message, redirect? } for page-forms.js.
 */
final class AppActions {

	/**
	 * @param Guard             $guard      The signed-in principal.
	 * @param HostingController $hosting    The hosting rules.
	 * @param URLManager        $urlmanager Redirects.
	 */
	public function __construct(
		private Guard $guard,
		private HostingController $hosting,
		private URLManager $urlmanager
	) {}

	/**
	 * Create or update an app.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function save( Request $request ) : Response {
		$request->remove( 'app_owner_id' );

		$is_new = 0 === (int) $request->get( 'app_id', 0 );

		if ( $is_new ) {
			// A new app goes to the owner acting; without one there is no one to own it.
			if ( null === $this->owner_id() ) {
				return $this->failure( new RequestException( 'unauthorized_scope', 'Switch to an account or organization that can host apps first.', [ 'status' => 403 ] ) );
			}

			$request->remove( 'app_slug' );
		} else {
			$app = $this->owned_app( (string) $request->get( 'app_type', '' ), (int) $request->get( 'app_id', 0 ) );

			if ( $app instanceof Exception ) {
				return $this->failure( $app );
			}

			$request->set( 'app_id', $app->get_id() );
		}

		$response = $this->hosting->save_app( $request );
		$app      = $request->get( 'smliser_resource', null, false );

		return $this->relay( $response, $app instanceof AbstractHostedApp ? $this->app_url( $app ) : null );
	}

	/**
	 * Change an app's status.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function status( Request $request ) : Response {
		$app = $this->owned_app_by_slug( $request );

		if ( $app instanceof Exception ) {
			return $this->failure( $app );
		}

		return $this->relay( $this->hosting->change_app_status( $request, $app ), $this->app_url( $app ) );
	}

	/**
	 * Delete an app in the trash for good.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete( Request $request ) : Response {
		$app = $this->owned_app_by_slug( $request );

		if ( $app instanceof Exception ) {
			return $this->failure( $app );
		}

		return $this->relay(
			$this->hosting->delete_app( $request ),
			$this->urlmanager->client_dashboard_url( 'apps' )->add_query_param( 'status', AbstractHostedApp::STATUS_TRASH )->url()
		);
	}

	/**
	 * Upload assets.
	 *
	 * The controller answers with what was uploaded and what failed, file
	 * by file; any failure is the answer's message.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function upload_assets( Request $request ) : Response {
		$app = $this->owned_app_by_slug( $request );

		if ( $app instanceof Exception ) {
			return $this->failure( $app );
		}

		$response = $this->hosting->app_asset_upload( $request );

		if ( $response->has_errors() ) {
			return $this->relay( $response, null );
		}

		$body   = $response->get_body();
		$result = is_array( $body ) ? (array) ( $body['result'] ?? [] ) : [];
		$failed = [];

		foreach ( (array) ( $result['failed'] ?? [] ) as $file => $reasons ) {
			$failed[] = sprintf( '%s: %s', 'system' === $file ? 'Upload' : $file, implode( ' ', array_map( 'strval', (array) $reasons ) ) );
		}

		if ( [] !== $failed ) {
			return $this->failure( new RequestException( 'asset_upload_failed', implode( ' ', $failed ), [ 'status' => 400 ] ) );
		}

		$count = count( (array) ( $result['uploaded'] ?? [] ) );

		return $this->success( 1 === $count ? 'The image is uploaded.' : sprintf( '%d images are uploaded.', $count ), $this->app_url( $app ) );
	}

	/**
	 * Delete an asset.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete_asset( Request $request ) : Response {
		$app = $this->owned_app_by_slug( $request );

		if ( $app instanceof Exception ) {
			return $this->failure( $app );
		}

		return $this->relay( $this->hosting->app_asset_delete( $request ), $this->app_url( $app ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The ID of the owner the user acts for.
	 *
	 * @return int|null
	 */
	private function owner_id() : ?int {
		$id = (int) $this->guard->get_principal()?->get_owner()?->get_id();

		return $id > 0 ? $id : null;
	}

	/**
	 * An app of the current owner, by type and ID.
	 *
	 * @param string $type
	 * @param int    $id
	 * @return AbstractHostedApp|Exception
	 */
	private function owned_app( string $type, int $id ) : AbstractHostedApp|Exception {
		$registry = HostedAppsRegistry::instance();
		$type     = $registry->normalize_app_type( $type );
		$app      = $id > 0 && $registry->is_app_type_registered( $type ) ? HostedApplicationService::get_app_by_id( $type, $id ) : null;

		return $this->scoped( $app );
	}

	/**
	 * An app of the current owner, by the request's "app_type" and "app_slug".
	 *
	 * The request is set to the app's own type and slug, so the controller
	 * works on the app checked here.
	 *
	 * @param Request $request
	 * @return AbstractHostedApp|Exception
	 */
	private function owned_app_by_slug( Request $request ) : AbstractHostedApp|Exception {
		$registry = HostedAppsRegistry::instance();
		$type     = $registry->normalize_app_type( (string) $request->get( 'app_type', '' ) );
		$slug     = (string) $request->get( 'app_slug', '' );
		$app      = '' !== $slug && $registry->is_app_type_registered( $type ) ? HostedApplicationService::get_app_by_slug( $type, $slug ) : null;
		$app      = $this->scoped( $app );

		if ( $app instanceof AbstractHostedApp ) {
			$request->set( 'app_type', $app->get_type() );
			$request->set( 'app_slug', $app->get_slug() );
		}

		return $app;
	}

	/**
	 * The app when the current owner owns it; "not found" otherwise, so
	 * other owners' apps are not revealed.
	 *
	 * @param AbstractHostedApp|null $app
	 * @return AbstractHostedApp|Exception
	 */
	private function scoped( ?AbstractHostedApp $app ) : AbstractHostedApp|Exception {
		$owner_id = $this->owner_id();

		if ( null === $app || null === $owner_id || $app->get_owner_id() !== $owner_id ) {
			return new RequestException( 'resource_not_found', 'This app does not exist.', [ 'status' => 404 ] );
		}

		return $app;
	}

	/**
	 * The app's page.
	 *
	 * @param AbstractHostedApp $app
	 * @return string
	 */
	private function app_url( AbstractHostedApp $app ) : string {
		return $this->urlmanager->client_dashboard_url( 'apps' )
			->add_query_params( [ 'type' => $app->get_type(), 'app' => $app->get_id() ] )
			->url();
	}

	/**
	 * Turn a HostingController answer into page-forms.js's.
	 *
	 * @param Response    $response The controller's answer: { success, data: { message } } or an error.
	 * @param string|null $redirect Where to go after success; the controller's admin URL is never used.
	 * @return Response
	 */
	private function relay( Response $response, ?string $redirect ) : Response {
		if ( $response->has_errors() ) {
			$data = $response->get_error_data();
			$data = is_array( $data ) ? $data : [];

			return $this->failure( new RequestException( (string) $response->get_error_code(), (string) $response->get_error_message(), [ 'status' => (int) ( $data['status'] ?? $response->get_status_code() ) ] ) );
		}

		$body    = $response->get_body();
		$message = is_array( $body ) ? (string) ( $body['data']['message'] ?? 'Done.' ) : 'Done.';

		return $this->success( $message, $redirect );
	}

	/**
	 * @param string      $message
	 * @param string|null $redirect
	 * @return Response
	 */
	private function success( string $message, ?string $redirect ) : Response {
		return Response::json( array_filter( [ 'success' => true, 'message' => $message, 'redirect' => $redirect ], static fn ( $value ) : bool => null !== $value ), 200 );
	}

	/**
	 * @param Exception $e With a "status".
	 * @return Response
	 */
	private function failure( Exception $e ) : Response {
		$data   = $e->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 400 ) : 400;

		return Response::json( [ 'success' => false, 'message' => $e->get_error_message() ], $status >= 400 ? $status : 400 );
	}
}