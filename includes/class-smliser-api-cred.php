<?php
/**
 * File name class-smliser-api-key.php
 * Class file for API key management class.
 * 
 * @author Callistus
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_API_Cred {
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
     * API Token data
     * 
     * @var array $tokens The tokens associated with this object
     */
    private $tokens = array(
        'app_name'      => '',
        'token'         => '',
        'token_expiry'  => '',
    );

    /**
     * The API key permission.
     * 
     * @var string $permission The capability of the API user.
     */
    private $permission = null;

    /**
     * Last accessed
     * 
     * @var string $last_accessed Last interaction time.
     */
    private $last_accessed = '';

    /**
     * Date this was created.
     * 
     * @var string date The date when this object is created
     */
    private $created_at;

    /**
     * Instance of current class
     * 
     * @var Smliser_API_Cred $instance
     */
    private static $instance = null;


    /**
     * Class constructor
     */
    public function __construct() {}

    /**
     * Instantiate current class statically
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
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
                $this->api_keys['consumer_public']  = sanitize_text_field( $data['consumer_public'] );
                $this->api_keys['key_ending']       = $this->format_key_ending( $data['consumer_public'] );
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
     * Set tokens
     * 
     * @param array $data Associative array containing corresponding token indexes
     */
    public function set_tokens( $data ) {
        if ( isset( $data['token'] ) ) {
            $this->tokens['token'] = sanitize_text_field( $data['token'] );
        }

        if ( isset( $data['token_expiry'] ) ) {
            $this->tokens['token_expiry'] = sanitize_text_field( $data['token_expiry'] );
        }

        if ( isset( $data['app_name'] ) ) {
            $this->tokens['app_name'] = sanitize_text_field( $data['app_name'] );
        }
    }

    /**
     * Set individual Token using the key.
     * 
     * @param $key The token key.
     * @param $value The value to set.
     */
    public function set_token( $key, $value ) {
        if ( array_key_exists( $key, $this->tokens ) ) {
            $this->tokens[$key] = sanitize_text_field( $value );
        }
    }

    /**
     * Set permission
     * 
     * @param string $permission The API permission.
     */
    public function set_permission( $permission ) {
        $this->permission = sanitize_text_field( $permission );
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
     * Set the creation date;
     * 
     * @param string $date The date a key is created
     */
    public function set_created_at( $date ) {
        $this->created_at = sanitize_text_field( $date );
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
        return ! empty( $this->api_keys[ $key ] ) ? $this->api_keys[ $key ] : null;
    }

    /**
     * The tokens
     * 
     * @return array The tokens.
     */
    public function get_tokens() {
        return $this->tokens;
    }

    /**
     * Get individual token
     * 
     * @param string $name The token name.
     * @return string|null $value of the given token key or null if key does not exits
     */
    public function get_token( $name, $context = 'view' ) {
        if ( 'view' === $context ) {
            return ! empty( $this->tokens[ $name ] ) ? $this->tokens[ $name ] : 'N/A';
        }
        
        return ! empty( $this->tokens[ $name ] ) ? $this->tokens[ $name ] : null;
    }

    /**
     * Get permission
     * 
     * @return mixed The API permission.
     */
    public function get_permission() {
        if ( 'read_write' === $this->permission ) {
            return 'Read/Write';
        }
        return ucfirst( $this->permission );
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
     |--------------
     | CRUD METHODS
     |--------------
     */

    /**
     * Retrieve all api keys
     */
    public static function get_all() {
        $api_keys = wp_cache_get( 'Smliser_API_Creds' );
        if ( false === $api_keys ) {
            global $wpdb;
            $table_name = SMLISER_API_CRED_TABLE;
            $query      = "SELECT * FROM {$table_name}";
            $results    = $wpdb->get_results( $query, ARRAY_A ); // phpcs:disable

            $api_keys   = array();
            if ( ! empty( $results ) ) {
                foreach( $results as $result ) {
                    $api_keys[] = self::instance()->convert_db_result( $result );
                }
                wp_cache_set( 'Smliser_API_Creds', $api_keys, '', 2 * HOUR_IN_SECONDS );
            }
        }
    
        return $api_keys;
    }

    /**
     * Retrieve data for a given consumer_public and consumer_secret
     * 
     * @param string $consumer_public The public key(client_id)
     * @param string $consumer_secret The consumer_secret(client secret)
     */
    public function get_api_data( $consumer_public, $consumer_secret ) {
        global $wpdb;
        $table_name = SMLISER_API_CRED_TABLE;
        $query  = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `consumer_public` = %s", sanitize_text_field( $consumer_public ) );
        $result = $wpdb->get_row( $query, ARRAY_A );

        if ( ! empty( $result ) && password_verify( $consumer_secret, $result['consumer_secret'] ) ) {
            return $this->convert_db_result( $result );
        }

        return false;
    }

    /**
     * Retrieve data for a given consumer_public and consumer_secret
     * 
     * @param int $id The api key ID
     */
    public function get_api_key( $id ) {
        global $wpdb;
        $table_name = SMLISER_API_CRED_TABLE;
        $query  = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `id` = %d", absint( $id ) );
        $result = $wpdb->get_row( $query, ARRAY_A );

        if ( ! empty( $result ) ) {
            return $this->convert_db_result( $result );
        }

        return false;
    }

    /**
     * Generate and store new API key.
     * 
     * @return object|false $credentials Object containing the consumer keys.
     */
    private function insert_new() {
        global $wpdb;

        $credentials    = $this->create_credentials();
        $user_id        = ! empty( $this->user ) && ! empty( $this->get_user()->ID ) ? absint( $this->get_user()->ID ) : 0;
        $permission     = ! empty( $this->permission ) ? sanitize_text_field( $this->permission ) : 'read';

        // Prepare data.
        $data   = array(
            'user_id'           => $user_id,
            'permission'        => $permission,
            'consumer_secret'   => password_hash( $credentials->consumer_secret, PASSWORD_BCRYPT ),
            'consumer_public'   => $credentials->consumer_public,
            'created_at'        => current_time( 'mysql' ),
        );

        $data_format    = array(
            '%d', // User ID.
            '%s', // Permission.
            '%s', // Secret.
            '%s', // Public.
            '%s', // Created At.
        );

        if ( $wpdb->insert( SMLISER_API_CRED_TABLE, $data, $data_format ) ) {
            return $credentials;
        }

        return false;
    }

    /**
     * Delete an api key from the database
     */
    public function delete() {
        if ( empty( $this->id ) ) {
            return false; // API credentials must exist in the database.
        }

        global $wpdb;
        $deleted = $wpdb->delete( SMLISER_API_CRED_TABLE, array( 'id' => $this->id ), array( '%d' ) );

        return $deleted !== false;
    }

    /**
     * Activate an API Credential.
     * 
     * @param string $app_name The name of the client to associate with API Credentials.
     * @return string $token A session token to allow client access to protected resource.
     */
    public function activate( $app_name ) {
        global $wpdb;

        if ( empty( $this->id ) ) {
            return false; // The apicredential must exists in the database;
        }

        if ( ! is_string( $app_name ) ) {
            return false; // App Name must be string.
        }

        $this->set_token( 'app_name', $app_name );
        $this->set_token( 'token', bin2hex( random_bytes( 32 ) ) );
        $this->set_token( 'token_expiry',  wp_date( 'Y-m-d H:i:s', time() + ( 2 * WEEK_IN_SECONDS ) ) );

        // Prepare data
        $data = array(
            'app_name'      => $this->tokens['app_name'],
            'token'         => $this->tokens['token'],
            'last_accessed' => current_time( 'mysql' ),
            'token_expiry'  => $this->tokens['token_expiry'],
        );

        $data_format = array( '%s', '%s', '%s', '%s' );
        
        $updated = $wpdb->update( SMLISER_API_CRED_TABLE, $data, array( 'id' => $this->id ), $data_format, array( '%d' ) );
        
        if ( false === $updated ) {
            return false;
        }
        
        return $this->tokens;
    }

    /**
     * Regenerate access tokens.
     */
    public function reauth() {
        global $wpdb;
        
        if ( empty( $this->id ) ) {
            return false;
        }

        if ( 'Inactive' === $this->get_status() ) {
            return false;
        }

        $this->set_token( 'token', bin2hex( random_bytes( 32 ) ) );
        $this->set_token( 'token_expiry',  wp_date( 'Y-m-d H:i:s', time() + ( 2 * WEEK_IN_SECONDS ) ) );
       
        // Prepare data
        $data = array(
            'token'         => $this->tokens['token'],
            'token_expiry'  => $this->tokens['token_expiry'],
            'last_accessed' => current_time( 'mysql' ),
        );

        $data_format = array( '%s', '%s', '%s' );
        
        $updated = $wpdb->update( SMLISER_API_CRED_TABLE, $data, array( 'id' => $this->id ), $data_format, array( '%d' ) );
        
        if ( false === $updated ) {
            return false;
        }
        
        return $this->tokens;
    }

    /*
    |----------------
    | FORM AJAX HANDLER
    |----------------
    */

    /**
     * Handle form submission
     */
    public static function form_handler() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'This action failed basic security check' ) );
        }

        $user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $permission = isset( $_POST['permission'] ) ? sanitize_text_field( $_POST['permission'] ) : '';
        $description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '';

        $validation = array();
        if ( empty( $user_id ) ) {
            $validation[]   = 'Select a user';
        }

        if ( empty( $permission ) ) {
            $validation[]   = 'Select the appropriate Permission';
        }

        if ( empty( $description ) ) {
            $validation[]   = 'Add a decription for your reference';
        }

        if ( ! empty( $validation ) ) {
            wp_send_json_error( array( 'message' => 'Fill required fields' ) );
        }

        $self = new self();
        $self->set_permission( $permission );
        $self->set_user( $user_id );
        $credentials = $self->insert_new();
        if ( false === $credentials ) {
            wp_send_json_error( array( 'message' => 'Unable to generate API Key credentials' ) );
        }

        wp_send_json_success( array( 
            'description'       => $description,
            'consumer_public'   => $credentials->consumer_public,
            'consumer_secret'   => $credentials->consumer_secret 
        ) );
    }

    /**
     * Revoke a key.
     */
    public static function revoke() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'This action failed basic security check' ) );
        }

        $id = isset( $_GET['api_key_id'] ) ? absint( $_GET['api_key_id'] ) : 0;
        if ( empty( $id ) ) {
            wp_send_json_error( array( 'message' => 'No API key id specified.' ) );
        }

        $self = self::instance();
        $self->set_id( absint( $id ) );
        
        if ( false === $self->delete() ) {
            wp_send_json_error( array( 'message' => 'Unable to delete the selected key.' ) );
        }

        wp_send_json_success( array( 'message' => 'Key has been revoked' ) );
    }

    /*
    |----------------------
    | UTILITY METHODS
    |----------------------
    */

    /**
     * Convert database result Smliser_API_Cred
     * 
     * @param array $result Associative array containing database results.
     * @return self An object of current class containing with corresponding data.
     */
    private static function convert_db_result( $result ) {
        $self = new self();
        $self->set_id( $result['id'] );
        $self->set_keys( $result );
        $self->set_tokens( $result );
        $self->set_user( absint( $result['user_id'] ) );
        $self->set_created_at( $result['created_at'] );
        $self->set_permission( $result['permission'] );
        return $self;
    }

    /**
     * Return 6 characters marking the end of a consumer_public key
     * 
     * @param string $text The text to return as key ending.
     * @return string $ending The ending part of a consumer public.
     */
    private function format_key_ending( $text ) {
        if ( empty( $text ) ) {
            return $text;
        }

        if ( strlen( $text ) < 6  ) {
            return sanitize_text_field( 'xxxxxxxxxxxx' . $text );
        }

        $ending = sanitize_text_field( 'xxxxxxxxxxxx' . substr( $text, - 8 ) );

        return $ending;
    }

    /**
     * Create consumer_secret and consumer_public keys
     */
    public function create_credentials() {
        $consumer_public = 'cp_' . bin2hex( random_bytes( 16 ) );
        $consumer_secret = 'cs_' . bin2hex( random_bytes( 32 ) );

        $credentials = new stdClass();
        $credentials->consumer_public = $consumer_public;
        $credentials->consumer_secret = $consumer_secret;

        return $credentials;
    }

    /**
     * Get the status of an API key.
     */
    public function get_status() {
        if ( ! empty( $this->tokens['token'] ) && ! empty( $this->tokens['app_name'] ) ) {
            return 'Active';
        }

        return 'Inactive';
    }
}


