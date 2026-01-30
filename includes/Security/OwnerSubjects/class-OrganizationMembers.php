<?php
/**
 * OrganizationMembers Class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\OwnerSubjects
 */

namespace SmartLicenseServer\Security\OwnerSubjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use SmartLicenseServer\Security\Actors\OrganizationMember;

/**
 * OrganizationMembers
 *
 * Represents a collection of OrganizationMember objects within an Organization.
 *
 * Provides methods to access, filter, map, and iterate over organization members.
 */
class OrganizationMembers implements IteratorAggregate, Countable, JsonSerializable {

    /**
     * Array of OrganizationMember objects.
     *
     * @var OrganizationMember[]
     */
    protected array $members = [];

    /* -----------------------------
       Constructor
    ----------------------------- */

    /**
     * Constructor.
     *
     * @param OrganizationMember[] $members Optional initial members.
     */
    public function __construct( array $members = [] ) {
        foreach ( $members as $member ) {
            $this->add( $member );
        }
    }

    /* -----------------------------
       Collection methods
    ----------------------------- */

    /**
     * Add a member to the collection.
     *
     * @param OrganizationMember $member
     * @return void
     */
    public function add( OrganizationMember $member ): void {
        $this->members[ $member->get_id() ] = $member;
    }

    /**
     * Get a member using their ID.
     * 
     * @param OrganizationMember|int $member
     * @return OrganizationMember|null
     */
    public function get( OrganizationMember|int $member ): ?OrganizationMember {
        if ( ! $this->has( $member ) ) {
            return null;
        }
        
        $id = $member instanceof OrganizationMember ? $member->get_id() : $member;
        return $this->members[$id];
    }

    /**
     * Remove a member from the collection by OrganizationMember object or ID.
     *
     * @param OrganizationMember|int $member
     * @return void
     */
    public function remove( OrganizationMember|int $member ): void {
        $id = $member instanceof OrganizationMember ? $member->get_id() : $member;
        unset( $this->members[ $id ] );
    }

    /**
     * Determine if a member exists in the collection.
     *
     * @param OrganizationMember|int $member
     * @return bool
     */
    public function has( OrganizationMember|int $member ): bool {
        $id = $member instanceof OrganizationMember ? $member->get_id() : $member;
        return isset( $this->members[ $id ] );
    }

    /**
     * Get all members as an array.
     *
     * @return OrganizationMember[]
     */
    public function all(): array {
        return array_values( $this->members );
    }

    /**
     * Get the first member in the collection.
     *
     * @return OrganizationMember|null
     */
    public function first(): ?OrganizationMember {
        return $this->all()[0] ?? null;
    }

    /**
     * Get the last member in the collection.
     *
     * @return OrganizationMember|null
     */
    public function last(): ?OrganizationMember {
        $all = $this->all();
        return $all[ count( $all ) - 1 ] ?? null;
    }

    /**
     * Filter members using a callback.
     *
     * @param callable $callback Receives (OrganizationMember $member)
     * @return self
     */
    public function filter( callable $callback ): self {
        return new self( array_filter( $this->members, $callback ) );
    }

    /**
     * Map members into a new structure.
     *
     * @param callable $callback Receives (OrganizationMember $member)
     * @return array
     */
    public function map( callable $callback ): array {
        return array_map( $callback, $this->all() );
    }

    /* -----------------------------
       Iterator & Countable
    ----------------------------- */

    /**
     * Count members.
     *
     * @return int
     */
    public function count(): int {
        return count( $this->members );
    }

    /**
     * Iterate over members.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable {
        return new ArrayIterator( $this->members );
    }

    /* -----------------------------
       JSON & Array Serialization
    ----------------------------- */

    /**
     * Convert members to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson( int $options = 0 ): string {
        return json_encode( $this->all(), $options ) ?: '[]';
    }

    /**
     * Convert members to array.
     *
     * @return OrganizationMember[]
     */
    public function toArray(): array {
        return $this->all();
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return OrganizationMember[]
     */
    public function jsonSerialize(): mixed {
        return $this->all();
    }
}
