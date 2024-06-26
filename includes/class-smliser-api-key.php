<?php
/**
 * File name class-smliser-api-key.php
 * Class file for API key management class.
 * 
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_API_Key {
    /**
     * Database id
     * 
     * @var int $id The ID of the API key data.
     */
    private $id = 0;

    /**
     * API user
     * @var WP_User $user Object of WordPress user.
     */
    private $user;

    /**
     * API Keys
     * @var array $api_keys The API key properties.
     */
    private $api_keys = array(
        'consumer_secret'  => '',
        'consumer_public'   => '',
        'key_ending'        => '',
    );

    /**
     * The API key permission.
     * 
     * @var mixed $permissions The capability of the API user.
     */
    private $permissions = null;

    /**
     * Last accessed
     * 
     * @var string $last_accessed Last interaction time.
     */
    private $last_accessed = '';

    /**
     * IP address
     * 
     * @var string $ip_address Last known client IP.
     */
    private $ip_address = '';

    /**
     * Class constructor
     * 
     * @param int $id The API key ID.
     */
    public function __construct( $id = 0 ) {
        if ( is_int( $id ) && 0 !== $id ) {
            $this->get_by_id( $id );
        }
    }

    /*
    |------------------
    | SETTERS
    |------------------
    */

    /**
     * Set id
     * 
     * @param int $id The API key ID.
     */
    public function set_id( $id ) {
        $this->id = absint( $id );
    }

    /**
     * Set the user
     * 
     * @param int|string|WP_User $user The ID, email of the user, or instance of WP_User.
     */
    public function set_user( $user ) {
        if ( is_int( $user ) ) {
            $this->user = get_user_by( 'id', $user );
        } elseif ( $user instanceof WP_User ) {
            $this->user = $user;
        } elseif ( is_string( $user ) && is_email( $user ) ) {
            $this->user = get_user_by( 'email', sanitize_email( $user ) );
        }
    }

    /**
     * Set API keys
     * 
     * @param array $data API key data.
     */
    public function set_keys( $data ) {
        if ( is_array( $data ) ) {
            if ( isset( $data['consumer_secret'] ) ) {
                $this->api_keys['consumer_secret'] = sanitize_text_field( $data['consumer_secret'] );
            }

            if ( isset( $data['consumer_public'] ) ) {
                $this->api_keys['consumer_public'] = sanitize_text_field( $data['consumer_public'] );
            }

            if ( isset( $data['key_ending'] ) ) {
                $this->api_keys['key_ending'] = sanitize_text_field( $data['key_ending'] );
            }
        }
    }

    /**
     * Set individual API key props.
     * 
     * @param string $key The API array key.
     * @param string $value The API key value.
     */
    public function set_key( $key, $value ) {
        if ( array_key_exists( $key, $this->api_keys ) ) {
            $this->api_keys[$key] = sanitize_text_field( $value );
        }
    }

    /**
     * Set permission
     * 
     * @param mixed $permissions The API permissions.
     */
    public function set_permission( $permissions ) {
        if ( is_array( $permissions ) ) {
            $this->permissions = array_map( 'sanitize_text_field', $permissions );
        } else {
            $this->permissions = sanitize_text_field( $permissions );
        }
    }

    /**
     * Set last accessed
     * 
     * @param string $value The last accessed date (WordPress local date).
     */
    public function set_last_accessed( $value ) {
        $this->last_accessed = sanitize_text_field( $value );
    }

    /**
     * Set IP
     * 
     * @param string $ip The IP address.
     */
    public function set_ip( $ip ) {
        $ip = trim( $ip );
        // Validate both IPv4 and IPv6 addresses
        $this->ip_address = filter_var( $ip, FILTER_VALIDATE_IP, array( FILTER_FLAG_NO_RES_RANGE, FILTER_FLAG_IPV4, FILTER_FLAG_IPV6 ) );
        if ( false === $this->ip_address ) {
            $this->ip_address = '';
        }
    }

    /*
    |------------------
    | GETTERS
    |------------------
    */

    /**
     * Get id
     * 
     * @return int The API key ID.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get user
     * 
     * @return WP_User The WordPress user object.
     */
    public function get_user() {
        return $this->user;
    }

    /**
     * Get API keys
     * 
     * @return array The API keys.
     */
    public function get_keys() {
        return $this->api_keys;
    }

    /**
     * Get individual API key prop
     * 
     * @param string $key The key to retrieve.
     * @return string|null The API key value or null if key does not exist.
     */
    public function get_key( $key ) {
        return isset( $this->api_keys[ $key ] ) ? $this->api_keys[ $key ] : null;
    }

    /**
     * Get permissions
     * 
     * @return mixed The API permissions.
     */
    public function get_permissions() {
        return $this->permissions;
    }

    /**
     * Get last accessed
     * 
     * @return string The last accessed date.
     */
    public function get_last_accessed() {
        return $this->last_accessed;
    }

    /**
     * Get IP address
     * 
     * @return string The IP address.
     */
    public function get_ip() {
        return $this->ip_address;
    }

    /**
     |--------------
     | CRUD METHODS
     |--------------
     */

    /**
     * Retrieve the API key data by ID
     * 
     * @param int $id The ID of the API key.
     * @return void
     */
    public function get_by_id( $id ) {


    }

}
