<?php
/**
 * The ServiceAccount class file
 * 
 * Represents a non-human actor for API/REST contexts.
 * 
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use DateTimeImmutable;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

use const SMLISER_SERVICE_ACCOUNTS_TABLE;
use function is_string, boolval, smliser_dbclass, gmdate, defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a service account that can act as a Principal.
 *
 * Service accounts are non-human actors used primarily for
 * API or system-to-system authentication. They are owned
 * by an Owner entity.
 */
class ServiceAccount implements PrincipalInterface {

    use SanitizeAwareTrait, CommonQueryTrait, TokenDeliveryTrait;

    /**
     * Service account active status.
     */
    public const STATUS_ACTIVE    = 'active';

    /**
     * Service account suspended status.
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Service account disabled status.
     */
    public const STATUS_DISABLED  = 'disabled';

    /**
     * Unique service account ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * ID of the owning Owner entity.
     *
     * @var int
     */
    protected int $owner_id = 0;

    /**
     * Human-readable service account name.
     *
     * @var string
     */
    protected string $display_name = '';

    /**
     * Hashed API key for authentication.
     *
     * @var string
     */
    protected string $api_key_hash = '';

    /**
     * Service account lifecycle status.
     *
     * @var string
     */
    protected string $status = self::STATUS_ACTIVE;

    /**
     * Account creation timestamp.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * Account last update timestamp.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $updated_at = null;

    /**
     * Last time this account was used.
     *
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $last_used_at = null;

    /**
     * Serialized permissions.
     *
     * @var string|null
     */
    protected ?string $permissions = null;

    /**
     * Lazy-loaded Owner instance.
     *
     * @var Owner|null
     */
    protected ?Owner $owner = null;

    /**
     * Caches the result of exists() check.
     *
     * @var bool|null
     */
    protected ?bool $exists_cache = null;

    /**
     * Constructor.
     *
     * Lightweight. Use setters or `from_array()` for hydration.
     */
    public function __construct() {}

    /*
    |-----------
    | GETTERS
    |-----------
    */
    /**
     * Get the service account ID.
     *
     * @return int The unique ID of this service account.
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the owner ID of this service account.
     *
     * @return int The owner ID that this account belongs to.
     */
    public function get_owner_id() : int {
        return $this->owner_id;
    }

    /**
     * Get the human-readable service account display name.
     *
     * @return string
     */
    public function get_display_name() : string {
        return $this->display_name;
    }

    /**
     * Get the hashed API key for this account.
     *
     * @return string Hashed API key string.
     */
    public function get_api_key_hash() : string {
        return $this->api_key_hash;
    }

    /**
     * Get the current status of this service account.
     *
     * @return string One of 'active', 'suspended', or 'disabled'.
     */
    public function get_status() : string {
        return $this->status;
    }

    /**
     * Get the creation timestamp.
     *
     * @return DateTimeImmutable|null The date the account was created.
     */
    public function get_created_at() : ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Get the last update timestamp.
     *
     * @return DateTimeImmutable|null The last time this account was updated.
     */
    public function get_updated_at() : ?DateTimeImmutable {
        return $this->updated_at;
    }

    /**
     * Get the last usage timestamp.
     *
     * @return DateTimeImmutable|null The last time this service account was used.
     */
    public function get_last_used_at() : ?DateTimeImmutable {
        return $this->last_used_at;
    }

    /**
     * Get permissions array.
     *
     * @return array
     */
    public function get_permissions() : array {
        if ( empty( $this->permissions ) ) {
            return [];
        }

        $decoded = json_decode( $this->permissions, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Lazy-load owner instance.
     *
     * @return Owner|null
     */
    public function get_owner() : ?Owner {
        if ( $this->owner ) {
            return $this->owner;
        }

        if ( $this->owner_id ) {
            $this->owner = Owner::get_by_id( $this->owner_id );
        }

        return $this->owner;
    }

    /*
    |--------------
    | SETTERS
    |--------------
    */

    /**
     * Set the service account ID.
     *
     * @param int $id The unique service account ID.
     * @return static Fluent instance.
     */
    public function set_id( $id ) : static {
        $this->id = self::sanitize_int( $id );
        return $this;
    }

    /**
     * Set the owner ID.
     *
     * @param int $owner_id The ID of the owner this account belongs to.
     * @return static Fluent instance.
     */
    public function set_owner_id( $owner_id ) : static {
        $this->owner_id = self::sanitize_int( $owner_id );
        return $this;
    }

    /**
     * Set the human-readable name.
     *
     * @param string $name Name of the service account.
     * @return static Fluent instance.
     */
    public function set_display_name( $name ) : static {
        $this->display_name = self::sanitize_text( $name );
        return $this;
    }

    /**
     * Set the hashed API key.
     *
     * @param string $hash Pre-hashed API key.
     * @return static Fluent instance.
     */
    public function set_api_key_hash( $hash ) : static {
        $this->api_key_hash = self::sanitize_text( $hash );
        return $this;
    }

    /**
     * Set the service account status.
     *
     * @param string $status One of 'active', 'suspended', or 'disabled'.
     * @return static Fluent instance.
     */
    public function set_status( $status ) : static {
        $status = self::sanitize_text( $status );
        if ( ! in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_DISABLED ], true ) ) {
            return $this;
        }
        $this->status = $status;
        return $this;
    }

    /**
     * Set creation date.
     *
     * @param string|DateTimeImmutable $date Creation date.
     * @return static Fluent instance.
     */
    public function set_created_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->created_at = $date;
        } elseif ( is_string( $date ) ) {
            try { $this->created_at = new DateTimeImmutable( $date ); } catch ( \Exception $e ) {}
        }
        return $this;
    }

    /**
     * Set last update date.
     *
     * @param string|DateTimeImmutable $date Creation date.
     * @return static Fluent instance.
     */
    public function set_updated_at( $date ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->updated_at = $date;
        } elseif ( is_string( $date ) ) {
            try { $this->updated_at = new DateTimeImmutable( $date ); } catch ( \Exception $e ) {}
        }
        return $this;
    }

    /**
     * Set the last used timestamp.
     *
     * @param \DateTimeImmutable $dt
     * @return static Fluent instance
     */
    public function set_last_used_at( \DateTimeImmutable $dt ) : static {
        $this->last_used_at = $dt;
        return $this;
    }

    /**
     * Set permissions array.
     *
     * @param array $permissions
     * @return static
     */
    public function set_permissions( array $permissions ) : static {
        $this->permissions = json_encode( $permissions );
        return $this;
    }

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Save service account to DB.
     *
     * @return bool
     */
    public function save() : bool {
        $db    = smliser_dbclass();
        $table = SMLISER_SERVICE_ACCOUNTS_TABLE;

        $data = [
            'owner_id'      => $this->get_owner_id(),
            'display_name'  => $this->get_display_name(),
            'api_key_hash'  => $this->get_api_key_hash(),
            'status'        => $this->get_status(),
            'permissions'   => $this->permissions,
            'updated_at'    => gmdate( 'Y-m-d H:i:s' )
        ];

        if ( $this->get_id() ) {
            $result = $db->update( $table, $data, [ 'id' => $this->get_id() ] );
        } else {
            $data['created_at'] = gmdate( 'Y-m-d H:i:s' );
            $result = $db->insert( $table, $data );
            $this->set_id( $db->get_insert_id() );
        }

        return $result !== false;
    }

    /**
     * Get ServiceAccount by ID.
     *
     * @param int $id
     * @return static|null
     */
    public static function get_by_id( int $id ) : ?static {
        static $accounts = [];

        if ( ! array_key_exists( $id, $accounts ) ) {
            $accounts[ $id ] = static::get_self_by_id( $id, SMLISER_SERVICE_ACCOUNTS_TABLE );
        }

        return $accounts[ $id ];
    }

    /**
     * Get ServiceAccount by API key hash.
     *
     * @param string $api_key_hash
     * @return static|null
     */
    public static function get_by_api_key( string $api_key_hash ) : ?static {
        $db     = smliser_dbclass();
        $table  = SMLISER_SERVICE_ACCOUNTS_TABLE;
        $sql    = "SELECT * FROM `{$table}` WHERE `api_key_hash` = ? LIMIT 1";

        $row = $db->get_row( $sql, [ $api_key_hash ] );
        return $row ? static::from_array( $row ) : null;
    }

    /**
     * Count total records by status
     * 
     * @param string $status
     * @return int
     */
    public static function count_status( $status ) : int {
        $status             = self::sanitize_text( $status );
        static $statuses    = [];

        if ( ! array_key_exists( $status, $statuses ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_SERVICE_ACCOUNTS_TABLE;

            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `status` = ?";

            $total  = $db->get_var( $sql, [$status] );

            $statuses[$status]  = (int) $total;
        }

        return $statuses[$status];
    }

    /*--------------------------------
    | UTILITY METHODS
    |--------------------------------*/

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
     * Convert to array
     * 
     * @return array
     */
    public function to_array() : array {
        return get_object_vars( $this );
    }

    /**
     * Determine if account can authenticate.
     *
     * @return bool
     */
    public function can_authenticate() : bool {
        return self::STATUS_ACTIVE === $this->status;
    }

    /**
     * Determine if service account exists in DB.
     *
     * @return bool
     */
    public function exists() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        if ( is_null( $this->exists_cache ) ) {
            $db     = smliser_dbclass();
            $table  = SMLISER_SERVICE_ACCOUNTS_TABLE;
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `id` = ?";

            $result = $db->get_var( $sql, [ $this->get_id() ] );
            $this->exists_cache = boolval( $result );
        }

        return $this->exists_cache;
    }

    /**
     * Get allowed statuses.
     *
     * @return array
     */
    public static function get_allowed_statuses() : array {
        return [ self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_DISABLED ];
    }

    /**
     * Generate a secure API key for this service account.
     *
     * The API key consists of:
     *  - raw key (bcrypt-protected for storage)
     *  - minimal payload hinting (sa_id, owner_id, timestamp)
     *  - HMAC signature for integrity
     *
     * @param int $length Length of random raw key in bytes (default 32)
     * @return string Base64-url encoded API key for client
     */
    public function generate_api_key( int $length = 32 ) : string {
        // Generate raw key
        $raw_key = bin2hex( random_bytes( $length ) );

        // Hash the raw key with bcrypt for storage
        $this->set_api_key_hash( password_hash( $raw_key, PASSWORD_BCRYPT ) );

        // Build minimal payload
        $payload = [
            'sa_id'     => $this->get_id(),
            'owner_id'  => $this->get_owner_id(),
            'timestamp' => time()
        ];

        $encoded_payload = \smliser_safe_json_encode( $payload );

        // Sign payload
        $secret    = self::derive_key();
        $signature = hash_hmac( 'sha256', $encoded_payload, $secret );

        // Save updated ServiceAccount to DB
        $this->save();

        // Return client API key
        $api_key = sprintf( '%s.%s.%s', $encoded_payload, $signature, $raw_key );

        return self::base64url_encode( $api_key );
    }

    /**
     * Verify a client API key and return a fully hydrated ServiceAccount.
     *
     * @param string $key Base64-url encoded API key
     * @return static Fully hydrated ServiceAccount
     * @throws \SmartLicenseServer\Exceptions\Exception If invalid or inactive
     */
    public static function verify_api_key( string $key ) : static {
        $decoded = self::base64url_decode( $key );

        // Expect format: payload.signature.raw_key
        $parts = explode( '.', $decoded, 3 );
        if ( count( $parts ) !== 3 ) {
            throw new \SmartLicenseServer\Exceptions\Exception(
                'service_account_invalid',
                'Malformed API key',
                ['status' => 400]
            );
        }

        [$encoded_payload, $signature, $raw_key] = $parts;

        // Validate HMAC signature
        $secret = self::derive_key();
        if ( ! hash_equals( hash_hmac( 'sha256', $encoded_payload, $secret ), $signature ) ) {
            throw new \SmartLicenseServer\Exceptions\Exception(
                'service_account_invalid',
                'Invalid API key signature',
                ['status' => 403]
            );
        }

        // Decode payload
        $payload = json_decode( $encoded_payload, true );
        if ( ! is_array( $payload ) || empty( $payload['sa_id'] ) ) {
            throw new \SmartLicenseServer\Exceptions\Exception(
                'service_account_invalid',
                'Invalid API key payload',
                ['status' => 400]
            );
        }

        // Hydrate ServiceAccount
        $sa = static::get_by_id( (int) $payload['sa_id'] );
        if ( ! $sa || ! $sa->can_authenticate() ) {
            throw new \SmartLicenseServer\Exceptions\Exception(
                'service_account_disabled',
                'Service account not active',
                ['status' => 403]
            );
        }

        // Verify raw key against stored bcrypt hash
        if ( ! password_verify( $raw_key, $sa->get_api_key_hash() ) ) {
            throw new \SmartLicenseServer\Exceptions\Exception(
                'service_account_invalid',
                'API key does not match',
                ['status' => 403]
            );
        }

        // Update last used timestamp
        $sa->set_last_used_at( new \DateTimeImmutable() )->save();

        return $sa;
    }
}
