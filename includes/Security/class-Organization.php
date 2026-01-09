<?php
/**
 * Organization class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use \DateTimeImmutable;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Exceptions\Exception;

use const SMLISER_ORGANIZATIONS_TABLE;
use function defined, is_string, smliser_dbclass, gmdate, boolval;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical representation of an organization.
 * 
 * An Organization acts as a top-level security 
 * container, allowing for both individual (single-user) and multi-user 
 * collaborative ownership and permission management.
 */
class Organization {
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
     * @var string $name
     */
    protected string $name = '';

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
     * Role ID.
     * 
     * @var int $role_id
     */
    protected int $role_id = 0;

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
     * Organization members
     * 
     * @var
     */

    /**
     * Holds the value of `exists` check
     *
     * @var boolean|null 
     */
    protected ?bool $exists_cache = null;

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
     * Get the organization name
     * 
     * @return string
     */
    public function get_name() : string {
        return $this->name;
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
     * Get role id
     * 
     * @return int
     */
    public function get_role_id() : int {
        return $this->role_id;
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
     * @param string $name
     * @return static
     */
    public function set_name( $name ) : static {
        $this->name = self::sanitize_text( $name );
        return $this;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     * @return static
     */
    public function set_slug( $slug ) : static {
        $this->slug = self::sanitize_text( $slug );
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
     * Set role_id.
     *
     * @param int $role_id
     * @return static
     */
    public function set_role_id( $role_id ) : static {
        $this->role_id = self::sanitize_int( $role_id );
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
        } catch( \DateMalformedStringException $e ) {
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
        } catch( \DateMalformedStringException $e ) {
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
     * @return bool|Exception True on success, false when saving fails and exception object on duplicate slug entry.
     */
    public function save() : bool|Exception {
        $table          = SMLISER_ORGANIZATIONS_TABLE;
        $exists_by_slug = self::get_self_by( 'slug', $this->get_slug(), $table );

        if ( $exists_by_slug && ! $this->exists() ) {
            return new Exception( 'org_slug_exists', 'This slug has already been taken', ['status' => 409] );
        }
        $db     = smliser_dbclass();
        

        $fields = array(
            'name'          => $this->get_name(),
            'slug'          => $this->get_slug(),
            'status'        => $this->get_status(),
            'role_id'       => $this->get_role_id(),
            'updated_at'    => gmdate( 'Y-m-d H:i:s' )

        );

        if ( $this->get_id() ) {
            $result = $db->update( $table, $fields, [ 'id' => $this->get_id() ] );
        } else {
            $fields['created_at']   = gmdate( 'Y-m-d H:i:s' );
            $result = $db->insert( $table, $fields );
            $this->set_id( $db->get_insert_id() );
        }

        return $result !== false;
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



}