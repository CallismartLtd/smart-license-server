<?php
/**
 * The license class file
 * 
 * @author Callistus <admin@callismart.com.ng>
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Utils\CommonQueryTrait;

defined( 'SMLISER_ABSPATH' ) || exit;
/**
 * The license class represents a licensing model for hosted applications in the repository.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */
class License {
    use CacheAwareTrait, CommonQueryTrait;
    /**
     * The license ID
     * 
     * @var int $id
     */
    protected $id = 0;

    /**
     * The ID of user associated with this license
     * 
     * @var int $user_id
     */
    protected $user_id    = 0;

    /**
     * The license key
     * 
     * @var string $license_key
     */
    protected $license_key = '';

    /**
     * The ID of the service associated with the license.
     * This property will be required for license activation.
     * 
     * @var string $service_id
     */
    protected $service_id = '';

    /**
     * The type and slug of the hosted application this license is issued to.
     * 
     * @var array $app_prop
     */
    protected $app_prop    = [
        'type'  => '',
        'slug'  => ''
    ];

    /**
     * The instance of the hosted aplication this license is issued to.
     * 
     * @var AbstractHostedApp
     */
    protected $app;

    /**
     * The status of the license.
     * 
     * @var string $status
     */
    protected $status = '';

    /**
     * The license commencement date.
     * 
     * @var string $start_date
     */
    protected $start_date = '';

    /**
     * The end date of the license.
     * 
     * @var string  $end_date
     */
    protected $end_date   = '';

    /**
     * Number of allowed domains that can request license activation.
     * 
     * @var int $max_allowed_domains
     */
    protected $max_allowed_domains;

    /**
     * The license meta data
     * 
     * @var array $meta_data
     */
    protected $meta_data = array(
        'activated_on'  => array()
    );

    public const STATUS_ACTIVE      = 'active';
    public const STATUS_EXPIRED     = 'expired';
    public const STATUS_LIFETIME    = 'lifetime';
    public const STATUS_INACTIVE    = 'inactive';
    public const STATUS_PENDING     = 'pending';
    public const STATUS_SUSPENDED   = 'suspended';
    public const STATUS_REVOKED     = 'revoked';
    public const STATUS_DEACTIVATED = 'deactivated';


    /**
     * Class constructor
     */
    public function __construct() {}

    /**
    |-------------
    | SETTERS
    |-------------
    */

    /**
     * Set the license ID.
     * 
     * @param int $id
     * @return self
     */
    public function set_id( $id ) : self {
        $this->id = max( 0, intval( $id ) );

        return $this;
    }

    /**
     * Set the the ID of the user associated with the license
     * 
     * @param int $user_id The user ID, pass 0 for guest license.
     * @return self
     */
    public function set_user_id( $user_id ) : self {
        $this->user_id = max( 0, intval( $user_id ) );

        return $this;
    }

    /**
     * Set the license key
     *
     * @param string $value
     * @return self
     */
    public function set_license_key( $value ) : self {
        $this->license_key = \sanitize_text_field( unslash( $value ) );

        return $this;
    }

    /**
     * Set the service ID for this license
     * 
     * @param string $service_id The service ID for this license.
     * @return self
     */
    public function set_service_id( $service_id ) : self {
        $this->service_id = \sanitize_text_field( \unslash( $service_id ) );

        return $this;
    }

    /**
     * Set the properties of the app associated with this license.
     * 
     * @param string $app_type  The app type
     * @param string $app_slug  The app slug.
     * @return self
     */
    public function set_app_prop( string $app_type, string $app_slug ) : self{
        $this->app_prop['type'] = $app_type;
        $this->app_prop['slug'] = $app_slug;

        return $this;
    }

    /**
     * Set the app this license is issued to.
     * 
     * @param AbstractHostedApp $app
     * @return self
     */
    public function set_app( AbstractHostedApp $app ) : self {
        $this->app = $app;

        $this->set_app_prop( $app->get_type(), $app->get_slug() );

        return $this;
    }

    /**
     * Set license status.
     * 
     * @param string $status
     * @return self
     */
    public function set_status( $status ) : self {
        $this->status = $status;

        return $this;
    }

    /**
     * Set the license start date
     * 
     * @param string $start_date
     * @return self
     */
    public function set_start_date( $start_date ) : self {
        $this->start_date = '0000-00-00' === $start_date ? '' : $start_date;

        return $this;
    }

    /**
     * Set the license end date
     * 
     * @param string $end_date
     * @return self
     */
    public function set_end_date( $end_date ) : self {
        $this->end_date = '0000-00-00' === $end_date ? '' : $end_date;

        return $this;
    }

    /**
     * Set the maximum allowed domains that can request license activation.
     * 
     * @param int $number_of_domains
     * @return self
     */
    public function set_max_allowed_domains( $number_of_domains ) : self {
        $this->max_allowed_domains = max( -1, intval( $number_of_domains ) );

        return $this;
    }

    /**
     * Set the value of the given meta data name
     * 
     * @param string $meta_name The name of the meta data to set.
     * @param mixed $meta_value The value of the meta data.
     * @return self
     */
    public function set_meta( $meta_name, $meta_value ) : self {
        $this->meta_data[\sanitize_key( $meta_name )]    = \unslash( $meta_value );

        return $this;
    }

    /**
     * Set the entire meta data of this license
     * 
     * @param array $meta_data
     * @return self
     */
    public function set_meta_data( array $meta_data ) : self {

        foreach( $meta_data as $name => $value ) {
            if ( \is_numeric( $name ) ) {
                continue;
            }

            $this->set_meta( $name, $value );
        }

        return $this;
    }

    /**
    |-------------
    | GETTERS
    |-------------
    */

    /**
     * Get the license ID.
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the the ID of the user associated with the license
     * 
     * @return int
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * Get the license key
     *
     * @return string
     */
    public function get_license_key() {
        return $this->license_key;
    }

    /**
     * Get the service ID for this license
     * 
     * @return string
     */
    public function get_service_id() {
        return $this->service_id;
    }

    /**
     * Get the properties of the app associated with this license.
     * 
     * @param string $context The context to retrieve the app prop.
     * @return array|string
     */
    public function get_app_prop( string $context = 'view' ) : array|string {
        if ( 'view' === $context ) {
            $prop       = array_filter( $this->app_prop );
            $formatted  = implode( '/', $prop );
            return $formatted;
        }

        return $this->app_prop;
    }

    /**
     * Get the app this license is issued to.
     * 
     * @return AbstractHostedApp $app
     */
    public function get_app() {
        return $this->app;
    }

    /**
     * Get license status.
     *
     * When $context is 'edit' the stored property value is returned as-is.
     * When $context is 'view' (default) – if the stored status is empty the
     * method derives the status from start/end dates. If the stored status
     * is present it will be returned (normalized).
     *
     * @param string $context Context for the status: 'view' or 'edit'. Default 'view'.
     * @return string
     */
    public function get_status( string $context = 'view' ) : string {
        // When editing we want the raw stored value (no derivation).
        if ( 'edit' === $context ) {
            return (string) $this->status;
        }

        // Normalize stored status if present.
        $stored = trim( (string) $this->status );
        if ( '' !== $stored ) {
            return strtolower( $stored );
        }

        // Derive status from dates because stored status is empty.
        $start = $this->get_start_date();
        $end   = $this->get_end_date();

        // Lifetime when there is no end date.
        if ( empty( $end ) ) {
            return self::STATUS_LIFETIME;
        }

        $now      = \time();
        $start_ts = ! empty( $start ) ? strtotime( $start ) : 0;
        $end_ts   = strtotime( $end );

        // If start defined and now is before start -> pending.
        if ( $start_ts > 0 && $now < $start_ts ) {
            return self::STATUS_PENDING;
        }

        // If now is after end -> expired.
        if ( $now > $end_ts ) {
            return self::STATUS_EXPIRED;
        }

        // Otherwise we are within the active window.
        return self::STATUS_ACTIVE;
    }


    /**
     * Get the license start date
     * 
     * @return string $start_date
     */
    public function get_start_date() {
        return $this->start_date;
    }

    /**
     * Get the license end date
     * 
     * @return string $end_date
     */
    public function get_end_date() {
        return $this->end_date;
    }

    /**
     * Get the maximum allowed domains that can request license activation.
     * 
     * @param string $context
     * @return string|int $number_of_domains
     */
    public function get_max_allowed_domains( string $context = 'view' ) : string|int {

        if ( 'view' === $context ) {
            if ( $this->max_allowed_domains < 0 ) {
                return 'Unlimited';
            }
            if ( empty( $this->max_allowed_domains ) ) {
                return 'None';
            }
        }

        if ( 'edit' === $context ) {
            if ( $this->max_allowed_domains < 0 ) {
                return '';
            }
        }

        return $this->max_allowed_domains;
    }

    /**
     * Get the value of the given meta data key
     * 
     * @param string $meta_name The name of the meta key.
     * @param mixed $default    The default value to return when the meta name is not found.
     * @return mixed
     */
    public function get_meta( $meta_name, $default = null ) {
        return $this->meta_data[$meta_name] ?? $default;
    }

    /**
     * Get the meta data
     * 
     * @return array
     */
    public function get_meta_data() {
        return $this->meta_data;
    }

    /*
    |-----------------
    |   CRUD METHODS
    |-----------------
    */

    /**
     * Get license object using service ID and the license key
     * 
     * @param string $service_id The service ID associated with the licence
     * @param string $license_key   The license key
     * @return self|null
     */
    public static function get_license( $service_id, $license_key ) : ?self {
        $key    = self::make_cache_key( __METHOD__, [$service_id, $license_key] );

        $result = self::cache_get( $key );

        if ( false === $result || ! ( $result instanceof self ) ) {
            $db     = \smliser_dbclass();
            $table  = \SMLISER_LICENSE_TABLE;
            $sql    = "SELECT * FROM {$table} WHERE `service_id` = ? AND `license_key` = ?";
            $params = [$service_id, $license_key];

            $data = $db->get_row( $sql, $params );
            
            if ( $data ) {
                $result =  self::from_array( $data );
            } else {
                $result = null;
            }

            self::cache_set( $key, $result, 30 * \MINUTE_IN_SECONDS );
        }

        return $result;

    }

    /**
     * Get license by ID
     * 
     * @param int $id
     * @return self|null
     */
    public static function get_by_id( $id ) : ?self {
        $key    = self::make_cache_key( __METHOD__, [$id] );

        $license = self::cache_get( $key );

        if ( false === $license || ! ( $license instanceof self ) ) {
            $table      = SMLISER_LICENSE_TABLE;
            $license    = self::get_self_by_id( $id, $table );

            self::cache_set( $key, $license, 30 * \MINUTE_IN_SECONDS );
        }

        return $license;
    }

    /**
     * Get all licenses from the database
     * 
     * @param int $page     The current pagination number.
     * @param int $limit    The number of license object to return.
     * @return self[]
     */
    public static function get_all( int $page = 1, int $limit = 30 ) : array {
        $key        = self::make_cache_key( __METHOD__, [$page, $limit] );
        $licenses   = self::cache_get( $key );

        if ( false === $licenses ) {
            $db         = smliser_dbclass();
            $table      = \SMLISER_LICENSE_TABLE;
            $licenses   = [];

            $offset     = $db->calculate_query_offset( $page, $limit );
            $sql        = "SELECT * FROM {$table} LIMIT {$limit} OFFSET {$offset}";

            $results    = $db->get_results( $sql );
            
            if ( ! empty( $results ) ) {
                foreach ( $results as $result ) {
                    $licenses[] = self::from_array( $result );
                }
            }
            
            self::cache_set( $key, $licenses, 30 * \MINUTE_IN_SECONDS );
        }

        return $licenses;
    }

    /**
     * Save the license.
     * 
     * @return bool True when license is save, false otherwise
     */
    public function save() : bool {
        $db         = smliser_dbclass();
        $table      = \SMLISER_LICENSE_TABLE;
        $meta_table = \SMLISER_LICENSE_META_TABLE;

        $data       = $this->to_array();
        $meta_data  = $data['meta_data'];
        $app_prop   = $this->get_app_prop( 'view' );

        unset( $data['app'], $data['meta_data'], $data['app_prop'] );

        $license_data   = array_merge( $data, compact( 'app_prop' ) );

        
        if ( $this->id ) {
            unset( $license_data['license_key'] );

            $updated        = $db->update( $table, $license_data, ['id' => $this->id] );
            $updated_meta   = 0;

            foreach ( $meta_data as $k => $v ) {
                
                $sql        = "SELECT `id` FROM {$meta_table} WHERE `meta_key` = ? AND `license_id` = ?";
                $meta_id    = $db->get_var( $sql, [$k, $this->id] );

                $metadata   = [
                    'meta_key'      => $k,
                    'meta_value'    => ! \is_scalar( $v ) ? \serialize( $v ) : $v,
                    'license_id'    => $this->id
                ];

                if ( $meta_id ) {
                    $metadata['id'] = $meta_id;
                    $done   = $db->update( $meta_table, $metadata, ['license_id' => $this->id] );
                } else {
                    $done   = $db->insert( $meta_table, $metadata );

                }

                $done && $updated_meta++;
            }

            $result = ( false !== $updated ) || ( $updated_meta > 0 );

        } else {
            $license_data['license_key'] = $this->generate_license_key();
            $inserted       = $db->insert( $table, $license_data );
            $inserted_meta  = 0;
            if ( $inserted ) {
                $this->set_id( $db->get_insert_id() );

                foreach ( $meta_data as $k => $v ) {
                    $metadata  = [
                        'meta_key'      => $k,
                        'meta_value'    => ! \is_scalar( $v ) ? \serialize( $v ) : $v,
                        'license_id'    => $this->id
                    ];
                    $done   = $db->insert( $meta_table, $metadata );

                    $done && $inserted_meta++;
                }
                
            }
            
            $result = ( false !== $inserted ) || ( $inserted_meta > 0 );
        }

        self::cache_clear();

        return false !== $result;

    }

    /**
     * Load the metadata from the database
     *
     * @return self
     */
    public function load_meta() : self {
        $db         = smliser_dbclass();
        $table      = SMLISER_LICENSE_META_TABLE;
        $sql        = "SELECT `meta_key`, `meta_value` FROM {$table} WHERE `license_id` = ?";

        $results    = $db->get_results( $sql, [$this->get_id()] );

        foreach( $results as $result ) {
            $value      = $result['meta_value'] ?? '';
            $meta_name  = $result['meta_key'] ?? '';
            $meta_value = \is_serialized( $value ) ? \unserialize( $value ) : $value;

            if ( ! empty( $meta_name ) ) {
                $this->set_meta( $meta_name, $meta_value );
            }
        }


        return $this;
    }

    /**
     * Delete license from the database
     * 
     * @return bool True on success, fase otherwise.
     */
    public function delete() {
        if ( ! $this->id ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = \SMLISER_LICENSE_TABLE;
        $meta_table = \SMLISER_LICENSE_META_TABLE;

        $deleted     = $db->delete( $table, ['id' => $this->id] );
        if ( $deleted ) {
            $db->delete( $meta_table, ['license_id' => $this->id] );
        }

        self::cache_clear();

        return false !== $deleted;
    }

    /**
    |---------------------
    | UTILITY METHODS
    |---------------------
    */

    /**
     * Get All active licensed Websites.
     */
    public function get_active_domains( $context = 'view' ) {
        $all_domains = $this->get_meta( 'activated_on', array() );

        if ( 'view' === $context && is_array( $all_domains ) ) {
            $all_hosts = array_keys( $all_domains );
            $all_domains = ! empty( $all_hosts ) ? implode( ', ', $all_hosts ) : 'N/A';
        }

        return $all_domains;
        
    }

    /**
     * Converts and return associative array to object of this class.
     * 
     * @param array $data   Associative array containing result from database
     */
    public static function from_array( $data ) : self {
        $self = new self();
        $self->set_id( $data['id'] ?? '' );
        $self->set_user_id( $data['user_id'] ?? 0 );
        $self->set_service_id( $data['service_id'] ?? '' );
        $self->set_license_key( $data['license_key'] ?? '' );
        $self->set_status( $data['status'] ?? '' );
        $self->set_start_date( $data['start_date'] ?? '' );
        $self->set_end_date( $data['end_date'] ?? '' );
        $self->set_max_allowed_domains( $data['max_allowed_domains'] ?? 0 );

        if ( ! empty( $data['app_prop'] ) && preg_match( '#^[^/]+/[^/]+$#', (string) $data['app_prop'] ) ) {
            list( $app_type, $app_slug ) = explode( '/', $data['app_prop'], 2 );
            $app_class  = HostedApplicationService::get_app_class( $app_type );
            $method     = "get_by_slug";

            if ( \class_exists( $app_class ) && \method_exists( $app_class, $method ) ) {
                /** @var AbstractHostedApp|null $app */
                $app = $app_class::$method( $app_slug );

                ( $app instanceof AbstractHostedApp ) && $self->set_app( $app );
            }
        }

        $self->load_meta();

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
     * Get total domains using this license.
     */
    public function get_total_active_domains() {
        return count( (array) $this->get_meta( 'activated_on', array() ) );
    }

    /**
     * Get the value of a domain from the active list.
     * 
     * @param string $url The domain or website name.
     * @param bool $all_data  Whether to include the values of the domain.
     * @return mixed
     */
    public function get_active_domain( $url, $all_data = true ) {
        $all_domains = $this->get_active_domains( 'edit' );

        $url    = new URL( $url );
        $host   = $url->get_host();

        if ( ! isset( $all_domains[$host] ) ) {
            return null;
        }

        $domain_data    = $all_domains[$host];

        if ( $all_data ) {
            return $domain_data;
        }
        return $domain_data['origin'];
        
    }

    /**
     * Add or update the domains where this license is currently activated.
     * 
     * `NOTE`: It is the duty of the caller to check the validity of the domain and construct a valid URL. 
     * 
     * @param string $url The url of the site to be added.
     * @param string $site_secret The secret key for the site.
     */
    public function update_active_domains( $url, $site_secret ) {
        $sites  = $this->get_meta( 'activated_on', array() );
        if ( ! is_array( $sites ) ) {
            $sites = array();
        }
        
        $url    = new URL( $url );
        $origin = $url->get_origin();
        $host   = $url->get_host();
        
        $sites[$host] = array(
            'origin'    => $origin,
            'secret'    => $site_secret,
        );

        $this->set_meta( 'activated_on', $sites );
        $this->save();
    }

    /**
     * Remove a domain from activated list.
     * 
     * @param $domain The domain
     */
    public function remove_activated_domain( $domain ) : bool {
        $sites  = $this->get_meta( 'activated_on' );

        if ( empty( $sites ) || ! is_array( $sites ) ) {
            return false;
        }
        
        $url    = new URL( $domain );

        if ( ! $url->has_scheme() ) {
            $url->set_scheme( 'https' );
            $url = new URL( $url->__toString() );
        }

        $host = $url->get_host();

        unset( $sites[$host] );
        $this->set_meta( 'activated_on', $sites );
        $this->save();
        return true;
    }

    /**
     * Whether license has reached max allowed domains.
     */
    public function has_reached_max_allowed_domains() {

        if ( -1 === $this->max_allowed_domains ) {
            return false;
        }
        
       return $this->get_total_active_domains() >= $this->max_allowed_domains;
    }

    /**
     * Checks whether the a given domain is a new domain
     * 
     * @param string $domain The name of the website.
     */
    public function is_new_domain( $domain ) {
        $url    = new URL( $domain );

        if ( ! $url->has_scheme() ) {
            $url->set_scheme( 'https' );
            $url = new URL( $url->__toString() );
        }

        $domain = $url->get_host();

        $all_sites      = $this->get_active_domains( 'edit' );
        return ! isset( $all_sites[$domain] );
    }

    /**
     * Tells whether this license is issued to an app.
     * 
     * @return bool
     */
    public function is_issued() : bool {
        return ( $this->app instanceof AbstractHostedApp );
    }

    /**
     * Tells whether the license is deactivated.
     * 
     * @return bool
     */
    public function is_deactivated() : bool {
        return strtolower( $this->get_status() === self::STATUS_DEACTIVATED );
    }

    /**
     * Tells whether we can perform actions that require license validity.
     *
     * @param int $app_id The App ID associated with the license.
     * @return true|Exception True if license can be served, otherwise Exception.
     */
    public function can_serve_license( $app_id ) {
        if ( ! $this->is_issued() ) {
            return new Exception(
                'license_error',
                'Invalid license request.',
                array( 'status' => 400 )
            );
        }

        if ( absint( $app_id ) !== $this->get_app_id() ) {
            return new Exception(
                'license_error',
                'License was not issued to this application.',
                array( 'status' => 403 ) // fixed
            );
        }

        $status = $this->get_status();

        switch ( $status ) {

            case self::STATUS_EXPIRED:
                return new Exception(
                    'license_expired',
                    'This license has expired. Please renew it.',
                    array( 'status' => 403 )
                );

            case self::STATUS_SUSPENDED:
                return new Exception(
                    'license_suspended',
                    'This license has been suspended. Please contact support.',
                    array( 'status' => 403 )
                );

            case self::STATUS_REVOKED:
                return new Exception(
                    'license_revoked',
                    'This license has been revoked. Please contact support.',
                    array( 'status' => 403 )
                );

            case self::STATUS_DEACTIVATED:
                return new Exception(
                    'license_deactivated',
                    'This license has been deactivated. Please reactivate it.',
                    array( 'status' => 409 )
                );

            default:
                return true;
        }
    }

    /**
     * Get the ID of the item associated with this license.
     * 
     * @return int
     */
    public function get_app_id() {
        return $this->is_issued() ? $this->app->get_id() : 0;
    }

    /**
     * Generate a new license key.
     *
     * @param string $prefix The prefix to be added to the license key.
     * @return string The generated license key.
     */
    public function generate_license_key( $prefix = '' ) {
        if ( empty( $prefix ) ) {
            $prefix = \smliser_settings_adapter()->get( 'smliser_license_prefix', 'SMLISER' );
        }

        $uid            = sha1( uniqid( '', true ) );
        $secure_bytes   = random_bytes( 16 );
        $random_hex     = bin2hex( $secure_bytes );
        $combined_key   = strtoupper( str_replace( '-', '', $uid ) . $random_hex );
        $license_key    = $prefix . $combined_key;

        // Insert hyphens at every 8-character interval.
        $real_license_key = '';
        for ( $i = 0; $i < strlen( $license_key ); $i += 8 ) {
            if ( $i > 0 ) {
                $real_license_key .= '-';
            }
            $real_license_key .= substr( $license_key, $i, 8 );
        }

        return $real_license_key;
    }

    /**
     * Obfuscate the license key and return a partial ending...
     * 
     * @return string
     */
    public function get_partial_key() : string {
        $license_key    = $this->get_license_key();
        $partial        = substr( $license_key, strlen( $license_key) - 12 );
        $obfuscated     = rtrim( str_repeat( '****-', 4 ), '-' );
        return sprintf( '%s-%s', $obfuscated, $partial );
    }

    /**
     * Regenerate the license key.
     *
     * This will generate a new, unique license key and optionally persist it to the database.
     * The method updates the in-memory object via set_license_key().
     *
     * @param string  $prefix  Optional prefix to pass to generate_license_key().
     * @param bool    $persist Whether to persist the new key to the database. Default true.
     * @param int     $tries   Maximum attempts to generate a unique key. Default 10.
     * @return string The newly generated license key.
     * @throws \SmartLicenseServer\Exception If a unique key cannot be generated or DB persist fails.
     */
    public function regenerate_license_key( string $prefix = '', bool $persist = true, int $tries = 10 ) : string {
        $db    = \smliser_dbclass();
        $table = \SMLISER_LICENSE_TABLE;

        $attempt = 0;
        $new_key = '';

        do {
            $attempt++;
            $new_key = $this->generate_license_key( $prefix );

            // Check uniqueness
            $sql = "SELECT COUNT(*) FROM {$table} WHERE `license_key` = ?";
            $count = (int) $db->get_var( $sql, [ $new_key ] );

            if ( 0 === $count ) {
                break;
            }
        } while ( $attempt < max( 1, absint( $tries ) ) );

        if ( $attempt >= max( 1, absint( $tries ) ) && $count > 0 ) {
            throw new Exception(
                'license_regeneration_failed',
                'Unable to generate a unique license key after multiple attempts.',
                [ 'status' => 500 ]
            );
        }

        // Update in-memory value
        $this->set_license_key( $new_key );

        // Persist if requested and we have an ID
        if ( $persist ) {
            if ( ! $this->id ) {
                throw new Exception(
                    'license_persist_failed',
                    'Cannot persist license key: license ID is not set.',
                    [ 'status' => 400 ]
                );
            }

            $updated = $db->update(
                $table,
                [ 'license_key' => $new_key ],
                [ 'id' => $this->id ]
            );

            if ( false === $updated ) {
                throw new Exception(
                    'license_persist_failed',
                    'Failed to persist regenerated license key to the database.',
                    [ 'status' => 500 ]
                );
            }
        }

        return $new_key;
    }

}