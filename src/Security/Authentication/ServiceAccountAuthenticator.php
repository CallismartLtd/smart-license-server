<?php
/**
 * Service account authentication class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

use SensitiveParameter;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\ServiceAccount;

class ServiceAccountAuthenticator implements AuthenticatorInterface {
    /**
     * Class constructor.
     * 
     * @param string $api_key
     */
    public function __construct(
        #[SensitiveParameter] protected string $api_key
    ) {}

    /**
     * {@inheritdoc}
     */
    public function authenticate() : AuthenticationResult {
        if ( empty( $this->api_key ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_MISSING_CREDENTIALS,
                'empty_api_key',
                'API key was not provided.'
            );
        }

        try {
            $actor  = ServiceAccount::verify_api_key( $this->api_key );

            return AuthenticationResult::authenticated( $actor, 'Successfully authenticated.' );
        } catch ( Exception $e ) {
            return $this->error_to_result( $e );
        }
    }

    private function error_to_result( Exception $error ) : AuthenticationResult {
        $status   = match( $error->get_error_code() ) {
            'malformed_payload', 'key_mismatch',
            'signature_mismatch',
            'invalid_payload',
            'invalid_credentials'       => AuthenticationResult::STATUS_INVALID_CREDENTIALS,
            'service_account_disabled'  => AuthenticationResult::STATUS_UNAUTHORIZED,
            default                     => AuthenticationResult::STATUS_MISSING_CREDENTIALS

        };

        return AuthenticationResult::failure( $status, $error->get_error_code(), $error->get_error_message() );
    }
}