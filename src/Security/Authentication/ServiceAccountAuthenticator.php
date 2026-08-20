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
use SmartLicenseServer\Security\Context\ContextServiceProvider;

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

            $owner = $actor->get_owner();

            if ( ! $owner || ! $owner->exists() ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, the service account owner does not exist.',
                    [ 'status' => 403 ]
                );
            }

            $owner_subject = ContextServiceProvider::get_owner_subject( $owner );

            if ( ! $owner_subject ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, you can no longer act for this resource owner.',
                    [ 'status' => 403 ]
                );
            }

            $role = ContextServiceProvider::get_principal_role( $actor, $owner_subject );

            if ( ! $role ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, you do not have a valid role.',
                    [ 'status' => 403 ]
                );
            }

            return AuthenticationResult::authenticated(
                actor: $actor,
                owner: $owner,
                role: $role,
                message: 'Successfully authenticated.'
            );

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