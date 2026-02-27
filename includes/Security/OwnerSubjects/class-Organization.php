<?php
/**
 * Organization class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\OwnerSubjects;

use DateMalformedStringException;
use \DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Owner;

use const SMLISER_ORGANIZATIONS_TABLE;
use function defined, is_string, smliser_dbclass, gmdate, boolval, smliser_avatar_url, md5;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical representation of an organization.
 * 
 * An Organization acts as a top-level security 
 * container, allowing for both individual (single-user) and multi-user 
 * collaborative ownership and permission management.
 */
class Organization implements OwnerSubjectInterface {
    use SanitizeAwareTrait, CommonQueryTrait;
    /**
     * Organization active status.
     *
     * @var string
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * Organization suspended status.
     *
     * @var string
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Organization disabled status.
     *
     * @var string
     */
    public const STATUS_DISABLED = 'disabled';

    /**
     * Organization ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * The org name.
     * 
     * @var string $display_name
     */
    protected string $display_name = '';

    /**
     * The org slug
     * 
     * @var string $slug
     */
    protected string $slug  = '';

    /**
     * Lifecycle status.
     *
     * @var string
     */
    protected string $status = self::STATUS_ACTIVE;

    /**
     * Date the org was created.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * Date the org was last updated.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $updated_at = null;

    /**
     * Holds the value of `exists` check
     *
     * @var boolean|null 
     */
    protected ?bool $exists_cache = null;

    /**
     * Lazy loaded members collection.
     * 
     * @var OrganizationMembers|null
     */
    protected ?OrganizationMembers $members = null;

    /**
     * Class constructor.
     *
     * Intentionally lightweight. Hydration is expected
     * to happen via setters or factory methods.
     */
    public function __construct() {}

    /*
    |----------
    | GETTERS
    |----------
    */

    /**
     * Get the org ID.
     *
     * @return int org ID.
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the org lifecycle status.
     *
     * @return string org status.
     */
    public function get_status() : string {
        return $this->status;
    }

    /**
     * Get the organization name.
     * 
     * @return string
     */
    public function get_display_name() : string {
        return $this->display_name;
    }
    
    /**
     * Get the organization slug
     * 
     * @return string
     */
    public function get_slug() : string {
        return $this->slug;
    }

    /**
     * Get the creation date.
     *
     * @return DateTimeImmutable|null Creation timestamp.
     */
    public function get_created_at() : ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Get the last update date.
     *
     * @return DateTimeImmutable|null Update timestamp.
     */
    public function get_updated_at() : ?DateTimeImmutable {
        return $this->updated_at;
    }

    /**
     * Get the members collection.
     * 
     * @return OrganizationMembers
     */
    public function get_members() : OrganizationMembers {
        if ( is_null( $this->members ) ) {
            $this->members = ContextServiceProvider::get_organization_members( $this );
        }

        return $this->members;
    }

    /*
    |---------
    | SETTERS
    |-----------
    */

    /**
     * Set ID.
     *
     * @param int $id
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = self::sanitize_int( $id );
        return $this;
    }
 
    /**
     * Set name.
     *
     * @param string $display_name
     * @return static
     */
    public function set_display_name( $display_name ) : static {
        $this->display_name = self::sanitize_text( $display_name );
        return $this;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     * @return static
     */
    public function set_slug( $slug ) : static {
        $slug = self::sanitize_text( $slug );

        if ( $this->exists() ) {
            $this->slug = $slug; // We won't change slug if org already exists.
        } else {
            $this->slug = strtolower( str_replace( [' ', '-'], ['_', '_'], $slug ) );
        }

        return $this;
    }

    /**
     * Set status.
     *
     * @param string $status
     * @return static
     */
    public function set_status( $status ) : static {
        $this->status = self::sanitize_text( $status );
        return $this;
    }

    /**
     * Set the creation timestamp.
     *
     * @param string|DateTimeImmutable $date Creation date.
     * @return static
     */
    public function set_created_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->created_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ) {
            return $this;
        }
        
        try {
            $date   = new DateTimeImmutable( $date );
        } catch( DateMalformedStringException $e ) {
            return $this;
        }

        $this->created_at = $date;
        return $this;
    }

    /**
     * Set the update timestamp.
     *
     * @param string|DateTimeImmutable $date Update date.
     * @return static
     */
    public function set_updated_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->updated_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ) {
            return $this;
        }
        
        try {
            $date   = new DateTimeImmutable( $date );
        } catch( DateMalformedStringException $e ) {
            return $this;
        }

        $this->updated_at = $date;
        return $this;
    }

    /*
    |----------------
    | CRUD METHODS
    |-----------------
    */
    /**
     * Get by id
     * 
     * @param int $id
     * @return static
     */
    public static function get_by_id( int $id ) : ?static {
        static $orgs = [];

        if ( ! array_key_exists( $id, $orgs ) ) {
            $orgs[ $id ] = self::get_self_by_id( $id, SMLISER_ORGANIZATIONS_TABLE );
        }

        return $orgs[ $id ];
    }

    /**
     * Save organization.
     * 
     * @return bool True on success, false when saving fails.
     * @throws Exception On duplicate slug entry.
     */
    public function save() : bool|Exception {
        $table          = SMLISER_ORGANIZATIONS_TABLE;
        $exists_by_slug = self::get_self_by( 'slug', $this->get_slug(), $table );

        if ( $exists_by_slug && ! $this->exists() ) {
            throw new Exception( 'org_slug_exists', 'The provided slug is not available.', ['status' => 409] );
        }

        $db     = smliser_dbclass();
        $now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
        

        $fields = array(
            'display_name'  => $this->get_display_name(),
            'status'        => $this->get_status(),
            'updated_at'    => $now->format( 'Y-m-d H:i:s' )

        );

        if ( $this->get_id() ) {
            $result = $db->update( $table, $fields, [ 'id' => $this->get_id() ] );
            $this->set_updated_at( $now );
        } else {
            $fields['slug']         = $this->get_slug();
            $fields['created_at']   = $now->format( 'Y-m-d H:i:s' );
            $result = $db->insert( $table, $fields );
            $this->set_id( $db->get_insert_id() );
            $this->set_created_at( $now );
        }

        return $result !== false;
    }

    /**
     * Get all organizations
     * 
     * @param int $page The current pagination number.
     * @param int $limit The maximum number of users to return.
     * 
     * @return self[]
     */
    public static function get_all( int $page, int $limit ) : array {
        return self::get_all_self( SMLISER_ORGANIZATIONS_TABLE, $page, $limit );
    }

    /**
     * Count total records of users by status
     * 
     * @param string $status
     * @return int
     */
    public static function count_status( $status ) : int {
        $status             = self::sanitize_text( $status );
        static $statuses    = [];

        if ( ! array_key_exists( $status, $statuses ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_ORGANIZATIONS_TABLE;

            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `status` = ?";

            $total  = $db->get_var( $sql, [$status] );

            $statuses[$status]  = (int) $total;
        }

        return $statuses[$status];
    }

    /*
    |----------------
    | UTILITY METHODS
    |-----------------
    */

    /**
     * Hydrate from array.
     *
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ) : static {
        $self = new static();

        foreach ( $data as $key => $value ) {
            $method = "set_{$key}";

            if ( is_callable( [ $self, $method ] ) ) {
                $self->$method( $value );
            }
        }

        return $self;
    }

    /**
     * Convert to array.
     * 
     * @return array
     */
    public function to_array() : array {
        $data   = get_object_vars( $this );

        if ( isset( $data['members'] ) ) {
            foreach( $data['members'] as &$member ) {
                $member = $member->get_user()->to_array();
            }
        }
        
        $data   = ['type' => $this->get_type()] + $data;

        unset( $data['exists_cache'] );
        return $data;
    }

    /**
     * Tells whether this organization exists.
     * 
     * @return bool True when the organization exists, false otherwise.
     */
    public function exists() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        if ( is_null( $this->exists_cache ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_ORGANIZATIONS_TABLE;
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `id` = ?";

            $result = $db->get_var( $sql, [$this->get_id()] );

            $this->exists_cache = boolval( $result );
        }

        return $this->exists_cache;
    }

    /**
     * Get type of the owner subject.
     * 
     * @return string
     */
    public function get_type() : string {
        return Owner::TYPE_ORGANIZATION;
    }

    /**
     * Get allowed statuses
     *
     * @return array
     */
    public static function get_allowed_statuses() : array {
        return [ self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_DISABLED ];
    }

    /**
     * Get the organization avatar
     */
    public function get_avatar() : URL {
        return new URL( smliser_avatar_url( md5( $this->get_slug() ), $this->get_type() ) );
    }

    /**
     * Tells whether the given member is a member of this organization.
     * 
     * @param OrganizationMember|string|int $member
     * @return bool True when the member is a member, false otherwise.
     */
    public function is_member( OrganizationMember|string|int $member ) : bool {
        return $this->get_members()->has( $member );
    }

}