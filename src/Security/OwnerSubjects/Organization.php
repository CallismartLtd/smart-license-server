<?php
/**
 * Organization class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\OwnerSubjects;

use Callismart\DBPrism\Database;
use DateMalformedStringException;
use \DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Owner;

use const SMLISER_ORGANIZATIONS_TABLE;

/**
 * Canonical representation of an organization.
 * 
 * An Organization acts as a top-level security 
 * container, allowing for both individual (single-user) and multi-user 
 * collaborative ownership and permission management.
 */
class Organization extends DataStore implements OwnerSubjectInterface {
    use SanitizeAwareTrait;
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
            $data           = static::fetch_by( 'id', $id, SMLISER_ORGANIZATIONS_TABLE );
            $orgs[ $id ]    = $data ? static::from_array( $data ) : null;
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
        return static::$DB->transactional( function( Database $db ) {
            $table          = SMLISER_ORGANIZATIONS_TABLE;
            $exists_by_slug = self::fetch_by( 'slug', $this->get_slug(), $table );

            if ( $exists_by_slug && ! $this->exists() ) {
                throw new Exception( 'org_slug_exists', 'The provided slug is not available.', ['status' => 409] );
            }

            $now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
            

            $fields = array(
                'display_name'  => $this->get_display_name(),
                'status'        => $this->get_status(),
                'updated_at'    => $now->format( 'Y-m-d H:i:s' )

            );

            $lock_sql   = static::query()
                ->select( 'id' )->from( $table )
                ->where( 'id', '=', $this->get_id() )
                ->limit(1)->lock_for_update();
            $id = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );

            if ( $id ) {
                $result = $db->update( $table, $fields, [ 'id' => $id ] );
                $result && $this->set_updated_at( $now );
            } else {
                $fields['slug']         = $this->get_slug();
                $fields['created_at']   = $now->format( 'Y-m-d H:i:s' );
                $result = $db->insert( $table, $fields );
                
                if ( $result ) {
                    $this->set_id( $db->get_insert_id() );
                    $this->set_created_at( $now );
                    $this->set_updated_at( $now );
                } 
            }

            return $result !== false;
        });
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
        return array_map(
            [static::class, 'from_array'],
            self::fetch( SMLISER_ORGANIZATIONS_TABLE, $page, $limit )
        );
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
            $table  = SMLISER_ORGANIZATIONS_TABLE;

            $sql    = static::query()
                ->select( 'COUNT(*)' )->from( $table )
                ->where( 'status', '=', $status );

            $total  = static::$DB->get_var( $sql->build(), $sql->get_bindings() );

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
        return static::from_array_helper( SMLISER_ORGANIZATIONS_TABLE, $data );
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
        return $this->id > 0;
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
     * Tells whether the given member is a member of this organization.
     * 
     * @param OrganizationMember|string|int $member
     * @return bool True when the member is a member, false otherwise.
     */
    public function is_member( OrganizationMember|string|int $member ) : bool {
        return $this->get_members()->has( $member );
    }

}