<?php
/**
 * Organization member class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\OwnerSubjects
 */

namespace SmartLicenseServer\Security\Actors;

use BadMethodCallException;
use SmartLicenseServer\Core\URL;
use DateTimeImmutable;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Security\Permission\Role;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Classical representation of a single member of an organization.
 */
class OrganizationMember implements ActorInterface {

    /* -----------------------------
       Membership properties
    ----------------------------- */

    /**
     * Member ID
     * 
     * @var int
     */
    private int $id = 0;

    /**
     * The subject user.
     * 
     * @var User
     */
    private User $user;

    /**
     * Membership-specific fields.
     */
    private ?Role $role = null;
    private ?DateTimeImmutable $created_at = null;
    private ?DateTimeImmutable $updated_at = null;

    /* -----------------------------
       Constructor
    ----------------------------- */

    /**
     * Constructor.
     * 
     * @param User $user
     * @param Collection $extra
     */
    public function __construct( User $user, Collection $extra ) {
        $this->user = $user;

        if ( $extra->isNotEmpty() ) {
            $this->set_id( $extra->get( 'id' ) );
            $this->role = $extra->get( 'role' );
            $this->set_created_at( $extra->get( 'created_at' ) );
            $this->set_updated_at( $extra->get( 'updated_at' ) );
        }
    }

    /* -----------------------------
       Membership-specific methods
    ----------------------------- */

    /**
     * Get member role.
     * 
     * @return Role|null
     */
    public function get_role(): ?Role {
        return $this->role;
    }

    /* -----------------------------
       ActorInterface implementation
       (delegated to User where appropriate)
    ----------------------------- */

    public function set_id( $id ): static {
        $this->id = intval( $id );
        return $this;
    }

    /**
     * @throws BadMethodCallException
     */
    public function set_display_name( $name ): static {
        throw new BadMethodCallException(
            sprintf( 'Method %s::%s is marked private.', get_class( $this ), __METHOD__ )
        );
    }

    /**
     * @throws BadMethodCallException
     */
    public function set_status( $status ): static {
        throw new BadMethodCallException(
            sprintf( 'Method %s::%s is marked private.', get_class( $this ), __METHOD__ )
        );
    }

    public function set_created_at( $date ): static {

        if ( $date instanceof DateTimeImmutable ) {
            $this->created_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ) {
            return $this;
        }

        try {
            $date = new DateTimeImmutable( $date );
        } catch ( \DateMalformedStringException $e ) {
            return $this;
        }

        $this->created_at = $date;
        return $this;
    }

    public function set_updated_at( $date ): static {

        if ( $date instanceof DateTimeImmutable ) {
            $this->updated_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ) {
            return $this;
        }

        try {
            $date = new DateTimeImmutable( $date );
        } catch ( \DateMalformedStringException $e ) {
            return $this;
        }

        $this->updated_at = $date;
        return $this;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_display_name(): string {
        return $this->user->get_display_name();
    }

    public function get_status(): string {
        return $this->user->get_status();
    }

    public function get_created_at(): ?DateTimeImmutable {
        return $this->created_at;
    }

    public function get_updated_at(): ?DateTimeImmutable {
        return $this->updated_at;
    }

    public function get_type(): string {
        return $this->user->get_type();
    }

    public function get_avatar(): URL {
        return $this->user->get_avatar();
    }

    public static function get_allowed_statuses(): array {
        return User::get_allowed_statuses();
    }

    /**
     * @throws BadMethodCallException
     */
    public static function count_status( $status ): int {
        throw new BadMethodCallException(
            sprintf( 'Method %s::%s is marked private.', __CLASS__, __METHOD__ )
        );
    }

    /**
     * @throws BadMethodCallException
     */
    public static function from_array( array $data ): static {
        throw new BadMethodCallException(
            sprintf( 'Method %s::%s is marked private.', __CLASS__, __METHOD__ )
        );
    }

    /**
     * @throws BadMethodCallException
     */
    public function to_array(): array {
        return [
            'id'         => $this->id,
            'role'       => $this->role?->get_label(),
            'avatar'    => $this->get_avatar(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user'       => $this->user->to_array(),
        ];
    }

    /* -----------------------------
       Expose underlying User
    ----------------------------- */

    /**
     * Get the underlying User object.
     * 
     * @return User
     */
    public function get_user(): User {
        return $this->user;
    }

    /**
     * Check whether this member exists.
     * 
     * @return bool True when the member has an ID, false otherwise.
     */
    public function exists(): bool {
        return $this->id > 0;
    }
}
