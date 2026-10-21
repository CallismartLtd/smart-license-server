<?php
/**
 * Client preference management class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use Throwable;

/**
 * Client dashboard actions on the signed-in user's preferences
 * (<client dashboard prefix>/json/*, behind ClientAccessMiddleware).
 *
 * Response: { success, data: { message } }
 */
final class PreferenceManagement {

	/**
	 * Preferences a client may set.
	 */
	public const KEYS = [
		UserSettings::DASHBOARD_THEME_NAME,
		UserSettings::DASHBOARD_SIDEBAR_COLLAPSED_NAME,
	];

	/**
	 * Constructor.
	 *
	 * @param Guard $guard The signed-in principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * POST <client dashboard prefix>/json/preferences
	 *
	 * Fields: key (one of KEYS), value.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function save( Request $request ) : Response {
		$principal = $this->guard->get_principal();

		if ( null === $principal ) {
			return $this->answer( false, 'You must be signed in to change your preferences.', 401 );
		}

		$actor = $principal->get_actor();

		if ( ! $actor instanceof User ) {
			return $this->answer( false, 'Only user accounts have preferences.', 403 );
		}

		$key = (string) $request->get( 'key', '' );

		if ( ! in_array( $key, self::KEYS, true ) ) {
			return $this->answer( false, sprintf( 'The preference must be one of: %s.', implode( ', ', self::KEYS ) ), 400 );
		}

		try {
			$saved = UserSettings::for( $actor )->set( $key, $request->get( 'value', '' ) );
		} catch ( Throwable $e ) {
			return $this->answer( false, $e->getMessage(), 500 );
		}

		return $saved
			? $this->answer( true, 'Saved.', 200 )
			: $this->answer( false, 'The preference could not be saved.', 500 );
	}

	/**
	 * A JSON answer.
	 *
	 * @param bool   $success
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function answer( bool $success, string $message, int $status ) : Response {
		return Response::json( [ 'success' => $success, 'data' => [ 'message' => $message ] ], $status );
	}
}
