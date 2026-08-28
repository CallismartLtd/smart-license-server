<?php
/**
 * Client settings controller class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */
declare( strict_types = 1 );
namespace SmartLicenseServer\ClientDashboard\Handlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Exceptions\SecurityException;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use Throwable;

/**
 * Client dashboard settings request controller.
 * 
 * Handles all settings submission by the user.
 */
class ClientSettingsController {
    public function __construct( protected Guard $guard ) {}

    public function set_user_preference( Request $request ) : array {
        try {
            $this->is_authenticated();

            $key            = $request->get( 'key', '' );

            if ( empty( $key ) ) {
                throw new RequestException( 'required_param', 'Dashboard preference key must is required.' );
            }

            if ( ! in_array( $key, [ 'theme', 'sidebar_collapsed'], true ) ) {
                throw new RequestException( 'invalid_request', 'Preference key is not allowed.' );
            }

            $value          = $request->get( 'value', '' );

            $principal      = $this->guard->get_principal();
            $user_settings  = UserSettings::for( $principal->get_actor() );

            $saved  = $user_settings->set( $key, $value );

            return static::ensure_response( $saved, ( $saved ? 'Saved' : 'Something went wrong' ) );            
        } catch ( SecurityException $e ) {
            return static::ensure_response( false, $e->get_error_message() );
        } catch ( Throwable $e ) {
            return static::ensure_response( false, $e->getMessage() );
        }

    }

    /**
     * Checks whether the current user is authenticated
     * 
     * @return void
     * @throws SecurityException
     */
    private function is_authenticated() : void {
        if ( ! $this->guard->has_principal() ) {
            throw new SecurityException(
                'authentication_required',
                'You must be logged in to perform this action.'
            );
        }
    }

    /**
     * Ensure response is as expected from ClientDashboardRouter.
     * 
     * @param bool $success Whether the response is successful.
     * @param string $message The response message.
     * @return array{success: bool, message: string}
     */
    private static function ensure_response( bool $success, string $message ) : array {
        return [ 'success' => $success, 'message' => $message ];
    }
}