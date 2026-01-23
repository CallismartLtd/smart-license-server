<?php
/**
 * The principal object class.
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use BadMethodCallException;
use InvalidArgumentException;
use function defined;
use function get_class;
use function method_exists;
use function sprintf;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical representation of the currently logged-in actor within a specific context.
 *
 * This class acts as a secure Proxy (Decorator) for the underlying identity and
 * binds it to a specific Role and Resource Owner for the duration of the request.
 * This "Strict Context" approach prevents privilege escalation and cross-owner data leaks.
 *
 * @package SmartLicenseServer\Security
 * @author  Callistus Nwachukwu
 *
 * --- PROXIED ACTOR GETTERS ---
 * @method int get_id() Get the unique identifier of the actor.
 * @method string get_display_name() Get the human-readable name of the actor.
 * @method string get_status() Get the current lifecycle status of the actor.
 * @method \DateTimeImmutable|null get_created_at() Get the date the actor was created.
 *
 * --- PROXIED ACTOR UTILITY ---
 * @method bool exists() Determine if the actor exists in storage.
 * @method array to_array() Convert the actor's data into an associative array.
 */
final class Principal {
    /**
     * The underlying actor implementation (User or Service Account).
     * @var ActorInterface
     */
    private ActorInterface $actor;

    /**
     * The validated role assigned to the actor for the current context.
     * @var Role
     */
    private Role $role;

    /**
     * The resource owner (Individual or Organization) the actor is acting upon.
     * @var Owner
     */
    private Owner $owner;

    /**
     * Principal constructor.
     *
     * Initializes the principal with a locked security context.
     * @param ActorInterface $actor The authenticated identity.
     * @param Role           $role  The resolved role for this request context.
     * @param Owner          $owner The resource owner context.
     */
    public function __construct( ActorInterface $actor, Role $role, Owner $owner ) {
        $this->actor = $actor;
        $this->role  = $role;
        $this->owner = $owner;
    }

    /**
     * Check if the principal has the permission to perform a specific action.
     *
     * This method validates the capability against the system registry. If the 
     * capability is unknown/invalid, it returns false rather than throwing an exception.
     * @param string $capability The machine name of the capability (e.g., 'license.revoke').
     * @return bool True if permitted, false otherwise.
     */
    public function can( string $capability ) : bool {
        try {
            Capability::assert_exists( $capability );
        } catch ( InvalidArgumentException $e ) {
            // Return false for unknown/invalid capabilities to fail safely.
            return false;
        }

        return $this->role->has_capability( $capability );
    }

    /**
     * Get the Resource Owner context for this principal.
     * @return Owner
     */
    public function get_owner() : Owner {
        return $this->owner;
    }

    /**
     * Get the active Role object for this principal.
     * @return Role
     */
    public function get_role() : Role {
        return $this->role;
    }

    /**
     * Get the underlying Actor object.
     * @return ActorInterface
     */
    public function get_actor() : ActorInterface {
        return $this->actor;
    }

    /**
     * Proxy method calls to the underlying actor.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     * @return mixed
     *
     * @throws BadMethodCallException If method does not exist on the actor.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( $this->actor, $method ) ) {
            return $this->actor->$method( ...$args );
        }

        throw new BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( $this->actor ), $method )
        );
    }
}