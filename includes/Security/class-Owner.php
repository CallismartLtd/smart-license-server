<?php
/**
 * Resource owner class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use DateTimeImmutable;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use DateMalformedStringException;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Exceptions\Exception;

use const SMLISER_OWNERS_TABLE;
use function defined, smliser_dbclass, array_key_exists, is_string, is_null, is_callable,
sprintf;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical representation of a resource owner.
 *
 * A resource owner is the legal or logical entity that has ultimate
 * (and delegable) authority over resources in the system.
 *
 * Resources include hosted apps, licenses, monetization assets,
 * messaging privileges, and analytics data.
 *
 * An Owner can be:
 * - an individual (personal ownership),
 * - an organization (company / team),
 * - or the platform itself (system-level ownership).
 *
 * Owners do not authenticate or perform actions directly.
 * Actions are performed by actors (Users or Service Accounts)
 * acting on behalf of an Owner.
 */

class Owner {
    use SanitizeAwareTrait, CommonQueryTrait, CacheAwareTrait;
    public const TYPE_INDIVIDUAL    = 'individual';
    public const TYPE_ORGANIZATION  = 'organization';
    public const TYPE_PLATFORM      = 'platform';

    public const STATUS_ACTIVE      = 'active';
    public const STATUS_SUSPENDED   = 'suspended';
    public const STATUS_DISABLED    = 'disabled';
    /**
     * The unique ID
     * 
     * @var int $id
     */
    protected int $id = 0;

    /**
     * The principal ID.
     * 
     * @var int $principal_id The ID of the principal entity.
     */
    protected int $principal_id = 0;

    /**
     * The owner type.
     * - an individual (personal ownership),
     * - an organization (company / team),
     * - or the platform itself (system-level ownership).
     * 
     * @var string $type
     */
    protected string $type = self::TYPE_PLATFORM;

    /**
     * Owner lifecycle status.
     *
     * Examples:
     * - active
     * - suspended
     * - disabled
     *
     * @var string
     */
    protected string $status = self::STATUS_ACTIVE;

    /**
     * Human-readable display name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Creation date
     * 
     * @var DateTimeImmutable $created_at
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * Last updated
     * 
     * @var DateTimeImmutable $updated_at
     */
    protected ?DateTimeImmutable $updated_at = null;

    /**
     * Owned apps
     * 
     * @var AbstractHostedApp[] $apps
     */
    protected ?array $apps = null;

    /** 
     * The roles avaliable to this owner.
     * @var Role|Role[]|null Single role instance for individual users, an array
     * `                     of role objects for organization 
     */
    protected Role|array|null $roles = null;

    /**
     * Holds the value of `exists` check
     *
     * @var boolean|null 
     */
    protected ?bool $exists_cache = null;

    /**
     * Class constructor
     */
    public function __construct() {}

    /*
    |-----------
    | SETTERS
    |-----------
    */

    /**
     * Set ID
     * @param int $id
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = static::sanitize_int( $id );

        return $this;
    }

    /**
     * Set the id of the of the principal
     * 
     * @param int $id
     * @return static
     */
    public function set_principal_id( $id ) : static {
        $this->principal_id = static::sanitize_int( $id );

        return $this;
    }

    /**
     * Set type
     * 
     * @param string $type
     * @return static
     */
    public function set_type( $type ) : static {
        $type = static::sanitize_text( $type );
        if ( ! in_array( $type, static::get_allowed_owner_types(), true ) ) {
            return $this;
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Set ownership status
     * 
     * @param string $status
     * @return static
     */
    public function set_status( $status ) : static {
        $status = static::sanitize_text( $status );

        if ( ! in_array( $status, static::get_allowed_statuses(), true ) ) {
            return $this;
        }

        $this->status = $status;
        return $this;
    }

    /**
     * Set owner name
     * 
     * @param string $name
     * @return static
     */
    public function set_name( $name ) : static {
        $this->name = static::sanitize_text( $name );
        
        return $this;
    }

    /**
     * Set date created.
     * 
     * @param string|DateTimeImmutable $date
     * @return static
     */
    public function set_created_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->created_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ){
            return $this;
        }

        try {
            $date   = new DateTimeImmutable( $date );
        } catch ( DateMalformedStringException $e ) {
            return $this;
        }

        $this->created_at = $date;

        return $this;
    }

    /**
     * Set update date.
     * 
     * @param string|DateTimeImmutable $date
     * @return static
     */
    public function set_updated_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->updated_at = $date;
            return $this;
        }

        if ( ! is_string( $date ) ){
            return $this;
        }

        try {
            $date   = new DateTimeImmutable( $date );
        } catch ( DateMalformedStringException $e ) {
            return $this;
        }

        $this->updated_at = $date;

        return $this;
    }

    /*
    |-----------
    | GETTERS
    |-----------
    */

    /**
     * Get owner ID.
     * 
     * @return int
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the principal ID
     * 
     * @return int
     */
    public function get_principal_id() : int {
        return $this->principal_id;
    }

    /**
     * Get type
     * 
     * @return string
     */
    public function get_type() : string {
        return $this->type;
    }

    /**
     * Get the ownership status
     * 
     * @return string
     */
    public function get_status() : string {
        return $this->status;
    }

    /**
     * Get owner name.
     * 
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get date created
     * 
     * @return DateTimeImmutable
     */
    public function get_created_at() : ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Get date created
     * 
     * @return DateTimeImmutable
     */
    public function get_updated_at() : ?DateTimeImmutable {
        return $this->updated_at;
    }

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Get hosted apps.
     * 
     * @return AbstractHostedApp[]
     */
    public function get_apps() : array {
        if ( ! $this->id ) {
            return [];
        }
        // Lazy loaded.
        if ( ! is_null( $this->apps ) ) {
            return $this->apps;
        }

        $this->apps = HostedApplicationService::get_all_by_owner( $this->get_id() );

        return $this->apps;
    }

    /**
     * Save app to the database.
     * 
     * @return bool
     * @throws \SmartLicenseServer\Exceptions\Exception
     */
    public function save() : bool {
        $db     = smliser_dbclass();
        $table  = SMLISER_OWNERS_TABLE;

        $data   = [
            'principal_id'  => $this->get_principal_id(),
            'name'          => $this->get_name(),
            'type'          => $this->get_type(),
            'status'        => $this->get_status(),
            'updated_at'    => gmdate( 'Y-m-d H:i:s' )
        ];

        if ( $this->get_id() ) {
            unset( $data['principal_id'], $data['type'] );
            $result = $db->update( $table, $data, ['id' => $this->get_id()] );
        } else {
            $principal_exists = static::get_by_principal_context( $data['principal_id'], $data['type'] );

            if ( $principal_exists ) {
                throw new Exception( 'duplicate_owner', sprintf( 'This %s is already a resource owner.', $data['type'] ) );
            }
            $data['created_at'] = gmdate( 'Y-m-d H:i:s' );
            $result = $db->insert( $table, $data );

            $this->set_id( $db->get_insert_id() );
        }

        return false !== $result;
    }

    /**
     * Get by id
     * 
     * @param int $id
     * @return static
     */
    public static function get_by_id( int $id ) : ?static {
        static $owners = [];

        if ( ! array_key_exists( $id, $owners ) ) {
            $owners[ $id ] = static::get_self_by_id( $id, SMLISER_OWNERS_TABLE );
        }

        return $owners[ $id ];
    }

    /**
     * Get a resource Owner object in the context of it's pricipal
     * 
     * @param int $principal_id The principal ID.
     * @param string $owner_type    The owner type.
     * @return static
     */
    public static function get_by_principal_context( int $principal_id, string $type ) : ?static {
        static $owners = [];

        $key    = sprintf( '%s:%s', $principal_id, $type );
        if ( ! array_key_exists( $key, $owners ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_OWNERS_TABLE;
            $sql    = "SELECT * FROM `{$table}` WHERE `principal_id` = ? AND `type` = ?";

            $result = $db->get_row( $sql, [$principal_id, $type] );

            $owner  = $result ? static::from_array( $result ) : null;
            $owners[ $key ] = $owner;
        }

        return $owners[ $key ];
    }

    /**
     * Get all owners
     * 
     * @param int $page The current pagination number.
     * @param int $limit The number of results per page.
     * @return self[]
     */
    public static function get_all( int $page = 1, $limit = 25 ) : array {
        return self::get_all_self( SMLISER_OWNERS_TABLE, $page, $limit );

    }

    /**
     * Lazy load the roles.
     *
     * @return Role|Role[]|null
     */
    public function get_roles(): Role|array|null {
        if ( is_null( $this->roles ) ) {
            $this->roles = Role::get_all_by_owner( $this->get_id(), $this->get_type() );
        }
        return $this->roles;
    }

    /**
     * Count total records of owners by status
     * 
     * @param string $status
     * @return int
     */
    public static function count_status( $status ) : int {
        $status             = self::sanitize_text( $status );
        static $statuses    = [];

        if ( ! array_key_exists( $status, $statuses ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_OWNERS_TABLE;

            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `status` = ?";

            $total  = $db->get_var( $sql, [$status] );

            $statuses[$status]  = (int) $total;
        }

        return $statuses[$status];
    }

    /*
    |-------------------
    | UTITLITY METHODS
    |-------------------
    */

    /**
     * Hydrate from array
     * 
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ) : static {
        $self = new static();
        foreach ( $data as $key => $value ) {
            $method = "set_{$key}";

            if ( is_callable( [$self, $method] ) ) {
                $self->$method( $value );
            }
        }

        
        return $self;
    }

    /**
     * Get allowed owner types
     * 
     * @return array
     */
    public static function get_allowed_owner_types() : array {
        return [self::TYPE_INDIVIDUAL, self::TYPE_ORGANIZATION, self::TYPE_PLATFORM];
    }

    /**
     * Get allowed owner statuses
     *
     * @return array
     */
    public static function get_allowed_statuses() : array {
        return [ self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_DISABLED ];
    }

    /*
    |--------------------
    | CONDITIONAL METHODS
    |--------------------
    */

    /**
     * Tells whether this owner exists.
     * 
     * @return bool True when the owner exists, false otherwise.
     */
    public function exists() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        if ( is_null( $this->exists_cache ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_OWNERS_TABLE;
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `id` = ?";

            $result = $db->get_var( $sql, [$this->get_id()] );

            $this->exists_cache = boolval( $result );
        }

        return $this->exists_cache;
    }

    /**
     * Tells whether owner is individual.
     * 
     * @return bool
     */
    public function is_individual() : bool {
        return $this->get_type() === static::TYPE_INDIVIDUAL;
    }

    /**
     * Tells whether owner is organization.
     * 
     * @return bool
     */
    public function is_organization() : bool {
        return $this->get_type() === static::TYPE_ORGANIZATION;
    }

    /**
     * Tells whether owner is platform.
     * 
     * @return bool
     */
    public function is_platform() : bool {
        return $this->get_type() === static::TYPE_PLATFORM;
    }

    /**
     * Tells whether this app is owned by this owner.
     * 
     * @param AbstractHostedApp
     * @return bool
     */
    public function owns_app( AbstractHostedApp $app ) : bool {
        return $app->get_owner_id() === $this->get_id();
    }
}