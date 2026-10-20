<?php
/**
 * The WordPress theme information REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

namespace SmartLicenseServer\RESTAPI\Handlers;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\RepositoryVisibility;

/**
 * Handles REST API requests for hosted WordPress themes: the theme-info
 * endpoint WordPress sites call to check for updates.
 */
class WPThemes extends DataStore {

	public function __construct(
		protected AppsAnalytics $apps_analytics,
		protected RepositoryVisibility $visibility
	) {}
	/**
	 * Theme info endpoint permission callback.
	 *
	 * A theme that is not active is not found (404), except for a system
	 * admin (RepositoryVisibility).
	 * 
	 * @param Request $request The REST API request object.
	 * @return RequestException|false Error object if permission is denied, false otherwise.
	 */
	public function info_permission_callback( Request $request ) : bool|RequestException {
		/**
		 * We handle the required parameters here.
		 */
		$theme_id = (int) $request->get( 'id' );
		$slug     = (string) $request->get( 'slug' );

		if ( empty( $theme_id ) && empty( $slug ) ) {
			return new RequestException(
				'smliser_theme_info_error',
				__( 'You must provide either the theme ID or the theme slug.', 'smliser' ),
				[ 'status' => 400 ]
			);
		}

		$arg       = $theme_id ? $theme_id : $slug;
		$cache_key = static::make_cache_key( __METHOD__, [$arg, $this->visibility->audience()] );

		/** @var \SmartLicenseServer\Exceptions\RequestException|false|array $data */
		$data = static::cache_get( $cache_key );

		if ( false === $data ) {
			if ( $theme_id ) {
				$theme = HostedApplicationService::get_app_by_id( 'theme', $theme_id );
			} else {
				$theme = HostedApplicationService::get_app_by_slug( 'theme', $slug );
			}

			if ( ! $theme || ! $this->visibility->can_view( $theme ) ) {
				$message = __( 'The theme does not exist.', 'smliser' );
				$data    = new RequestException( 'theme_not_found', $message, [ 'status' => 404 ] );
			} else {
				$this->apps_analytics->log_client_access(
					$theme,
					'theme_info',
					$request->ip(),
					$request->userAgent()
				);

				$data = [
					'success' => true,
					'theme'   => $theme->get_rest_response()
				];
			}

			static::cache_set( $cache_key, $data, static::default_ttl() );

		}

		if ( $data instanceof RequestException ) {
			return $data;
		}

		$request->set( 'smliser_resource', $data );

		return true;

	}

	/**
	 * Theme info response handler.
	 * 
	 * @param Request $request The REST API request object.
	 * @return Response The REST API response object.
	 */
	public function theme_info_response( Request $request ) : Response {
		$response_data = $request->get( 'smliser_resource' );

		return Response::json(
			$response_data,
			200
		);
	}

}