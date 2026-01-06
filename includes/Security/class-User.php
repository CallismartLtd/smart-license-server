<?php
/**
 * The User class file
 * 
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use DateTimeImmutable;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use const SMLISER_USERS_TABLE;
use function is_string, smliser_dbclass, gmdate, boolval, defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical representation of a human actor in the system.
 *
 * A User is a human identity capable of authenticating and
 * performing actions either for themselves or on behalf
 * of an Owner.
 *
 * Users do not own resources directly.
 * Ownership is always mediated through an Owner.
 */
class User implements PrincipalInterface{

    use SanitizeAwareTrait, CommonQueryTrait;

    /**
     * User active status.
     *
     * @var string
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * User suspended status.
     *
     * @var string
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * User disabled status.
     *
     * @var string
     */
    public const STATUS_DISABLED = 'disabled';

    /**
     * Unique user ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * Primary login identifier (email or username).
     *
     * @var string
     */
    protected string $identifier = '';

    /**
     * Hashed password string.
     *
     * @var string
     */
    protected string $password_hash = '';

    /**
     * User lifecycle status.
     *
     * @var string
     */
    protected string $status = self::STATUS_ACTIVE;

    /**
     * Human-readable display name.
     *
     * @var string
     */
    protected string $display_name = '';

    /**
     * Date the user was created.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * Date the user was last updated.
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
     * User constructor.
     *
     * Intentionally lightweight. Hydration is expected
     * to happen via setters or factory methods.
     */
    public function __construct() {}

    /*---------
    | GETTERS
    |-----------
    */

    /**
     * Get the user ID.
     *
     * @return int User ID.
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the primary authentication identifier.
     *
     * This may be an email address or username,
     * depending on the authentication strategy.
     *
     * @return string Identifier string.
     */
    public function get_identifier() : string {
        return $this->identifier;
    }

    /**
     * Get the hashed password.
     *
     * @return string Password hash.
     */
    public function get_password_hash() : string {
        return $this->password_hash;
    }

    /**
     * Get the user lifecycle status.
     *
     * @return string User status.
     */
    public function get_status() : string {
        return $this->status;
    }

    /**
     * Get the user display name.
     *
     * @return string Display name.
     */
    public function get_display_name() : string {
        return $this->display_name;
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

    /*---------
    | SETTERS
    |-----------
    */

    /**
     * Set the user ID.
     *
     * @param int $id User ID.
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = self::sanitize_int( $id );
        return $this;
    }

    /**
     * Set the authentication identifier.
     *
     * @param string $identifier Email or username.
     * @return static
     */
    public function set_identifier( $identifier ) : static {
        $this->identifier = self::sanitize_text( $identifier );
        return $this;
    }

    /**
     * Set the password hash.
     *
     * This method expects a *pre-hashed* password.
     * Password hashing should happen outside the entity.
     *
     * @param string $hash Hashed password.
     * @return static
     */
    public function set_password_hash( $hash ) : static {
        $this->password_hash = $hash;
        return $this;
    }

    /**
     * Set the user status.
     *
     * @param string $status User lifecycle status.
     * @return static
     */
    public function set_status( $status ) : static {
        $this->status = self::sanitize_text( $status );
        return $this;
    }

    /**
     * Set the user display name.
     *
     * @param string $name Human-readable name.
     * @return static
     */
    public function set_display_name( $name ) : static {
        $this->display_name = self::sanitize_text( $name );
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

    /*----------------
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
        static $users = [];

        if ( ! array_key_exists( $id, $users ) ) {
            $users[ $id ] = self::get_self_by_id( $id, SMLISER_USERS_TABLE );
        }

        return $users[ $id ];
    }

    /**
     * Save user.
     * 
     * @return bool True on success, false otherwise.
     */
    public function save() : bool {
        $db     = smliser_dbclass();
        $table  = SMLISER_USERS_TABLE;

        $fields = array(
            'identifier'    => $this->get_identifier(),
            'password_hash' => $this->get_password_hash(),
            'status'        => $this->get_status(),
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

    /*----------------
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
     * Determine whether the user is allowed to authenticate.
     *
     * @return bool True if the user can authenticate.
     */
    public function can_authenticate() : bool {
        return self::STATUS_ACTIVE === $this->status;
    }

    /**
     * Tells whether this user exists.
     * 
     * @return bool True when the user exists, false otherwise.
     */
    public function exists() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        if ( is_null( $this->exists_cache ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_USERS_TABLE;
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `id` = ?";

            $result = $db->get_var( $sql, [$this->get_id()] );

            $this->exists_cache = boolval( $result );
        }

        return $this->exists_cache;
    }
}
