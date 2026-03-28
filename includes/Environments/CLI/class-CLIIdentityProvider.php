<?php
/**
 * CLI identity provider class file.
 *
 * Authenticates the CLI process using a service account API key stored
 * in the SMLISER_CLI_API_KEY environment variable. When successful the
 * resolved Principal is set on Guard for the lifetime of the process.
 *
 * Authentication is opportunistic — it runs at bootstrap and silently
 * returns null on any failure. Commands that require a principal check
 * Guard themselves and handle the absence in their own context.
 *
 * ## Setup
 *
 * Add to your .env file:
 *
 *   SMLISER_CLI_API_KEY=<base64url-encoded service account API key>
 *
 * The service account must exist in the database with an active status.
 * Its role determines what commands are permitted — a system_admin
 * service account has full access; a resource_owner account is scoped
 * to its own apps and licenses.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\CLI;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Authenticates the CLI process via a service account API key.
 *
 * Mirrors the authentication chain in WordPress\RESTAPI::authenticate()
 * with the only difference being the key source: environment variable
 * instead of an Authorization Bearer header.
 */
final class CLIIdentityProvider extends AbstractIdentityProvider {

    /*
    |--------------------------------------------
    | IdentityProviderInterface
    |--------------------------------------------
    */

    /**
     * Attempt to authenticate the CLI process.
     *
     * Reads SMLISER_CLI_API_KEY from the environment, verifies it
     * against the service account table, resolves the owner and role,
     * and registers the resulting Principal on Guard.
     *
     * Returns null silently on any failure — the caller never exits
     * based on this return value. Commands that require a principal
     * check Guard::get_principal() themselves.
     *
     * @return Principal|null
     */
    public function authenticate(): ?Principal {
        // Already authenticated — return existing principal.
        if ( Guard::has_principal() ) {
            return Guard::get_principal();
        }

        $api_key = $this->resolve_api_key();

        if ( $api_key === null ) {
            return null;
        }

        try {
            $service_account = ServiceAccount::verify_api_key( $api_key );

            $owner = $service_account->get_owner();

            if ( ! $owner || ! $owner->exists() ) {
                return null;
            }

            $owner_subject = ContextServiceProvider::get_owner_subject( $owner );

            $role = ContextServiceProvider::get_principal_role( $service_account, $owner_subject );

            if ( ! $role ) {
                return null;
            }

            $principal = new Principal( $service_account, $role, $owner );

            Guard::set_principal( $principal );

            return $principal;

        } catch ( Exception $e ) {
            return null;
        }
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Read the API key from the environment.
     *
     * Checks $_ENV first then falls back to getenv() for keys set
     * outside of the .env file (e.g. shell exports, Docker env vars).
     *
     * @return string|null The raw key, or null if not set or empty.
     */
    private function resolve_api_key(): ?string {
        $key = $_ENV['SMLISER_CLI_API_KEY']
            ?? ( getenv( 'SMLISER_CLI_API_KEY' ) ?: null );

        return ! empty( $key ) ? (string) $key : null;
    }
}