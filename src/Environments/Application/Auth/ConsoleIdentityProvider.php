<?php
/**
 * Console identity provider class file.
 * 
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Authentication\IdentityProviders\IdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\ServiceAccountAuthenticator;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

class ConsoleIdentityProvider implements IdentityProviderInterface {
    /**
     * {@inheritdoc}
     */
    public function authenticate(): ?Principal {
        if ( Guard::has_principal() ) {
            return Guard::get_principal();
        }

        $api_key    = $_ENV['SMLISER_CLI_API_KEY'] ?? '';

        if ( 'root' === $api_key && $this->user_owns_root_dir() ) {
            [$actor, $role] = $this->make_system_admin();
            $this->set_principal( $actor, $role );
            
            return Guard::get_principal();
        }

        $auth_result = ( new ServiceAccountAuthenticator( $api_key ) )->authenticate();

        if ( $auth_result->is_authenticated() ) {
            $this->set_principal(
                $auth_result->actor,
                $auth_result->role,
                $auth_result->owner
            );

            return Guard::get_principal();
        }

        return null;
    }

    protected function user_owns_root_dir() : bool {
        $root_dir = \SMLISER_ROOT;

        if ( ! \is_dir( $root_dir ) ) {
            return false;
        }

        if ( '\\' === \DIRECTORY_SEPARATOR ) {
            return $this->windows_user_owns_root_dir( $root_dir );
        }

        return $this->unix_user_owns_root_dir( $root_dir );
    }

    protected function unix_user_owns_root_dir( string $root_dir ) : bool {
        if ( ! \function_exists( 'posix_geteuid' ) ) {
            return false;
        }

        $owner_uid = @\fileowner( $root_dir );

        if ( false === $owner_uid ) {
            return false;
        }

        return $owner_uid === \posix_geteuid();
    }

    protected function windows_user_owns_root_dir( string $root_dir ) : bool {
        $tmp_file = @\tempnam( $root_dir, '.smliser-' );

        if ( false === $tmp_file ) {
            return false;
        }

        try {
            $root_owner = $this->windows_get_file_owner( $root_dir );
            $file_owner = $this->windows_get_file_owner( $tmp_file );

            if ( false === $root_owner || false === $file_owner ) {
                return false;
            }

            return 0 === \strcasecmp( $root_owner, $file_owner );
        } finally {
            @\unlink( $tmp_file );
        }
    }

    protected function windows_get_file_owner( string $path ) : string|false {
        $command = 'icacls ' . \escapeshellarg( $path );

        $output = [];
        $status = -1;

        @\exec( $command . ' 2>NUL', $output, $status );

        if ( 0 !== $status || empty( $output ) ) {
            return false;
        }

        foreach ( $output as $line ) {
            if ( \preg_match( '/^\s*[^:]+:\s+([^\r\n]+)$/', $line, $matches ) ) {
                $owner = \trim( $matches[1] );

                if ( '' !== $owner ) {
                    return $owner;
                }
            }
        }

        return false;
    }

    /**
     * Create a system administrator account for CLI operation.
     * 
     * @return array{0: ActorInterface, 1: Role}
     */
    protected function make_system_admin() : array {
        $default_role   = DefaultRoles::get( 'system_admin' );
        
        $role           = Role::get_by_slug( $default_role['slug'] );

        if ( ! $role ) {
            $role = new Role();
        }

        $role->set_label( $default_role['label'])
            ->set_slug( $default_role['slug'] )
            ->set_capabilities( $default_role['capabilities'] )
            ->set_is_canonical( $default_role['is_canonical'] );

        $actor  = ( new ServiceAccount() )
            ->set_display_name( 'System Admin (console)' )
            ->set_created_at( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
            ->set_id(0)
            ->set_status( ServiceAccount::STATUS_ACTIVE );

        return[ $actor, $role ];
    }

    protected function set_principal( ActorInterface $actor, Role $role, ?Owner $owner = null ) : void {
        Guard::set_principal( new Principal( $actor, $role, $owner ) );
    }
}