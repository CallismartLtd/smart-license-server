<?php
/**
 * Organization member class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\OwnerSubjects
 */

namespace SmartLicenseServer\Security\Actors;

use BadMethodCallException;
use DateTimeImmutable;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;

/**
 * The classical representation of an organization member.
 * 
 * An organization member is a human user who can authenticate and perform actions onbehalf
 * of an organization.
 */
class OrganizationMember implements ActorInterface {
    use DatePropertyAwareTrait;

    /**
     * The id of this membership record.
     * 
     * @var int
     */
    protected int $id = 0;

    /**
     * The ID of user entity associated with this membership record.
     * 
     * @var int
     */
    protected int $member_id    = 0;

    /**
     * The ID of the organization this membership record belongs to.
     * 
     * @var int
     */
    protected int $organization_id  = 0;

    /**
     * The membership status.
     * 
     * @var string
     */
    protected string $status    = User::STATUS_ACTIVE;

    /**
     * The creation date.
     * 
     * @var DateTimeImmutable
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * The last update date.
     * 
     * @var DateTimeImmutable
     */
    protected ?DateTimeImmutable $updated_at = null;
    
    /**
     * The specific role assigned to this member.
     * 
     * @var Role|null
     */
    protected ?Role $role = null;

    /**
     * The user entity associated with this record(lazy loaded).
     * 
     * @var User|null
     */
    private ?User $user = null;

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Get member role.
     * 
     * @return Role|null
     */
    public function get_role(): ?Role {
        return $this->role;
    }

    /*
    |----------
    | SETTERS
    |----------
    */

    public function set_id( $id ): static {
        $this->id = intval( $id );
        return $this;
    }

    /**
     * Set the member ID
     */
    public function set_member_id( mixed $id ): static {
        $this->member_id = intval( $id );
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
        $this->status = $status;

        return $this;
    }

    /**
     * Set the resolved role for this member.
     * 
     * @param Role $role
     * @return static
     */
    public function set_role( Role $role ) : static {
        $this->role = $role;

        return $this;
    }

    public function set_created_at( $date ): static {
        return $this->set_date_prop( $date, 'created_at' );
    }

    public function set_updated_at( $date ): static {
        return $this->set_date_prop( $date, 'updated_at' );
    }

    /*
    |--------------
    | GETTERS
    |--------------
    */

    /**
     * {@inheritdoc}
     * 
     * Get the ID of this member record.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the ID of the user associated with thi memebership.
     * 
     * @return int
     */
    public function get_member_id() : int {
        return $this->member_id;
    }

    public function get_status() : string {
        return $this->status;
    }

    public function get_created_at(): ?DateTimeImmutable {
        return $this->created_at;
    }

    public function get_updated_at(): ?DateTimeImmutable {
        return $this->updated_at;
    }

    /*
    |---------------------------------------------------
    | METHODS DELEGATED TO THE UNDERLYING USER ENTITY.
    |---------------------------------------------------
    */

    public function get_type(): string {
        $this->load_user();
        return $this->user?->get_type() ?? '';
    }

    public static function get_allowed_statuses(): array {
        return User::get_allowed_statuses();
    }

    public function get_display_name(): string {
        $this->load_user();
        return $this->user?->get_display_name() ?? 'Invalid Member';
    }
    /**
     * {@inheritdoc}
     */
    public function get_unique_identifier() : string {
        $this->load_user();
        return $this->user?->get_unique_identifier() ?? '';
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
     * {@inheritdoc}
     * 
     * @param array{
     *  id: int,
     *  member_id: int,
     *  status: string,
     *  created_at?: DateTimeImmutable|string,
     *  updated_at?: DateTimeImmutable|string,
     * } $data
     */
    public static function from_array( array $data ): static {
        return ( new static() )
            ->set_id( $data['id'] ?? 0 )
            ->set_member_id( $data['member_id'] ?? 0 )
            ->set_status( $data['status'] ?? User::STATUS_ACTIVE )
            ->set_created_at( $data['created_at'] ?? '' )
            ->set_updated_at( $data['updated_at'] ?? '' );
    }

    /**
     * Get the underlying User object.
     * 
     * @return User|null
     */
    public function get_user(): ?User {
        $this->load_user();
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

    protected function load_user() : void {
        static $loaded;
        if ( isset( $loaded ) ) {
            return;
        }

        $this->user = User::get_by_id( $this->member_id );
        $loaded = true;
    }
}
