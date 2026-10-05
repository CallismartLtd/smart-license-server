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
     * @param Guard           $guard     The security guard.
     * @param FolderOwnership $ownership Folder ownership check.
     */
    public function __construct(
        protected Guard $guard,
        protected FolderOwnership $ownership
    ) {}

    /**
     * {@inheritdoc}
     */
    public function authenticate(): ?Principal {
        if ( $this->guard->has_principal() ) {
            return $this->guard->get_principal();
        }

        $api_key    = $_ENV['SMLISER_CLI_API_KEY'] ?? '';

        if ( 'root' === $api_key && $this->user_owns_root_dir() ) {
            [$actor, $role] = $this->make_system_admin();
            $this->set_principal( $actor, $role );
            
            return $this->guard->get_principal();
        }

        $auth_result = ( new ServiceAccountAuthenticator( $api_key ) )->authenticate();

        if ( $auth_result->is_authenticated() ) {
            $this->set_principal(
                $auth_result->actor,
                $auth_result->role,
                $auth_result->owner
            );

            return $this->guard->get_principal();
        }

        return null;
    }

    /**
     * Whether the user running this process owns the application folder.
     *
     * @return bool
     */
    protected function user_owns_root_dir() : bool {
        return $this->ownership->is_owner( \SMLISER_ROOT );
    }

    /**
     * Create a system administrator account for CLI operation.
     * 
     * @return array{0: ActorInterface, 1: Role}
     */
    protected function make_system_admin() : array {
        $default_role   = DefaultRoles::get( 'system_admin' );
        
        // The lookup goes through the cache and the database, neither of
        // which exists before installation. Every field but the ID is set
        // from the defaults below, so a new Role is an equivalent fallback.
        try {
            $role = Role::get_by_slug( $default_role['slug'] ) ?: new Role();
        } catch ( \Throwable ) {
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
        $this->guard->set_principal( new Principal( $actor, $role, $owner ) );
    }
}