<?php
/**
 * Plugin download token class.
 * 
 * @author Callistus
 * @package Smliser\class
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Download Token class represents the token needed to access licensed plugin files.
 * When a plugin is licensed, the zip file becomes publicly inaccessible except with this token or through an authorized API endpoint for authenticated applications.
 */
class Smliser_Plugin_Download_Token {
    /**
     * Database table name
     * 
     * @var string $db_name
     */
    private $db_name = '';

    /**
     * ID
     * @var int $id the table ID
     */
    private $id = 0;

    /**
     * Item ID/Plugin ID
     * 
     * @var int $item_id.
     */
    private $item_id = 0;

    /**
     * License key
     * 
     * @var string $license_key
     */
    private $license_key = null;

    /**
     * The item token
     * 
     * @var string $token
     */
    private $token = '';

    /**
     * The expiry date
     * 
     * @var int $expiry Expiry date in seconds.
     */
    private $expiry = 0;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->db_name = SMLISER_DOWNLOAD_TOKEN_TABLE;
    }

    /*
    |----------------
    | GETTERS
    |----------------
    */

    /**
     * Get item id
     */
    public function get_item_id() {
        return $this->item_id;
    }

    /**
     * Convert database results to an object of Smliser_Plugin_Download_Token.
     * 
     * @param array $result An associative array of database result gotten with the ARRAY_A flag.
     * @return self This object.
     */
    public static function from_array( $result ) {
        $self               = new self();
        $self->id           = isset( $result['id'] ) ? absint( $result['id'] ) : 0;
        $self->item_id      = isset( $result['item_id'] ) ? sanitize_text_field( $result['item_id'] ) : '';
        $self->license_key  = isset( $result['license_key'] ) ? sanitize_text_field( $result['license_key'] ) : '';
        $self->token        = isset( $result['token'] ) ? sanitize_text_field( unslash( $result['token'] ) ) : '';
        $self->expiry       = isset( $result['expiry'] ) ? sanitize_text_field( $result['expiry'] ) : 0;
        return $self;
    }

    /**
     * Generate or retrieve token string
     */
    public function gen_token() {
        if ( empty( $this->token ) ) {
            $this->token  = 'smliser_' . bin2hex( random_bytes( 32 ) );
        }

        return $this->token;
    }

    /*
    |-----------------
    | CRUD METHODS
    |-----------------
    */

    /**
     * Save token to database
     */
    public function save() {
        global $wpdb;        
        $inserted = $wpdb->insert(
            $this->db_name,
            array(
                'license_key'   => $this->license_key,
                'item_id'       => $this->item_id,
                'token'         => $this->gen_token(),
                'expiry'        => time() + $this->expiry
            ),
            array( '%s','%d','%s','%s' )
        );

      
        $this->id = $wpdb->insert_id;
        return $inserted !== false;
      
    }

    /**
     * Insert helper
     * 
     * @param array $data Associative array of properties.
     * @return string|WP_Error|false Inserted token, WP_Error if there is no ID and false otherwise.
     */
    public static function insert_helper( $data ) {
        $self = new self();
        if ( ! is_array( $data ) || ! isset( $data['item_id'] ) ) {
            return new WP_Error( 'smliser_db_error', 'Check missing requirement.' );
        }

        $self->item_id = absint( $data['item_id'] );

        if ( isset( $data['license_key'] ) ) {
            $self->license_key = sanitize_text_field( $data['license_key'] );
        }

        if ( isset( $data['expiry'] ) && ! empty( $data['expiry'] ) ) {
            $self->expiry = absint( $data['expiry'] );
        } else {
            $self->expiry = 10 * DAY_IN_SECONDS;
        }

        if ( $self->save() ) {

            return $self->token;
        }

        return false;
    }

    /**
     * Get a Download token object using a token value.
     * 
     * @param string $token the token
     * @return false|self
     */
    public function get_token( $token ) {
        global $wpdb;
        
        $sql    = $wpdb->prepare( "SELECT * FROM {$this->db_name} WHERE `token` = %s", $token );
        $result = $wpdb->get_row( $sql, ARRAY_A );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( ! $result ) {
            return false;
        }

        $self = self::from_array( $result );

        if ( $self->has_expired() ) {
            $self->delete();
            return false;
        }

        if ( ! $self->item_exists() || ! $self->license_exists() ) {
            $self->delete();
            return false;
        }
        
        return $self;

    }

    /**
     * Delete a token from the database.
     * 
     * @return bool True on success, false otherwise.
     */
    public function delete() {
        if ( empty( $this->id ) ) {
            return new WP_Error( 'smliser_db_error', 'ID must be set.' );
        }
        global $wpdb;
        $deleted = $wpdb->delete( $this->db_name, array( 'id' => $this->id ), array( '%d') );

        return $deleted !== false;
    }

    /*
    |-----------------
    | UTILITY METHODS
    |-----------------
    */

    /**
     * Check if the token has expired.
     */
    public function has_expired() {
        return $this->expiry < time();
    }

    /**
     * Check if pluign/item is valid;
     */
    public function item_exists() {
        return $this->get_plugin() !== false;
    }

    /**
     * Check if license exists for this token.
     */
    public function license_exists() {
        $license =  $this->get_license();
        return $license && true === $license->can_serve_license( $this->item_id );
    }

    /**
     * Get the plugin associated with token.
     * 
     * @return Smliser_Plugin|false $plugin The plugin object, false otherwise.
     */
    public function get_plugin() {
        $plugin = new Smliser_Plugin();
        return $plugin->get_plugin( $this->item_id );
    }

    /**
     * Get the license object associated with this token
     * 
     * @return Smliser_license|false $license The License object, false otherwise.
     */
    public function get_license() {
        return Smliser_license::get_by_key( $this->license_key );
    }

    /**
     * Ajax form to generate token.
     */
    public static function ajax_token_form() {
       check_ajax_referer( 'smliser_nonce', 'security' );
       $item_id     = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
       $license_key = isset( $_GET['license_key'] ) ? sanitize_text_field( unslash( $_GET['license_key'] ) ) : '';

        if ( empty( $item_id ) || empty( $license_key ) ) {
            wp_die( 'Missing required parameters' );
        }

        $plugin         = new Smliser_Plugin();
        $plugin_name    = $plugin ? $plugin->get_plugin( $item_id )->get_name() : 'N/A';
        include_once SMLISER_PATH . 'templates/license/token-form.php';
        die();
        
    }

    /**
     * Ajax token generate
     */
    public static function get_new_token() {
        check_ajax_referer( 'smliser_nonce', 'security' );
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have the required permission to do this.') );
        }
        
        $item_id    = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : wp_send_json_error( array( 'message' => 'Item ID is required.' ) );
        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( unslash( $_POST['license_key'] ) ) : wp_send_json_error( array( 'message' => 'License key is required.' ) );
        $expiry     = isset( $_POST['expiry'] ) ? sanitize_text_field( unslash(  $_POST['expiry'] ) ): 0;

        if ( ! empty( $expiry ) ) {
            $xpiry = strtotime( $expiry );
            if ( ! $xpiry || $xpiry < time() ) {
                $expiry = 0;
            } else {
                $expiry = $xpiry - time();
            }
        }
        
        $token = smliser_generate_item_token( $item_id, $license_key, $expiry );

        if ( ! $token ) {
            wp_send_json_error( array( 'message' => 'An error occured during token creation.' ) );
        }
        
        wp_send_json_success( array( 'token' => $token ) );

    }

    /**
     * Perform periodic cleaning of expired tokens
     */
    public static function clean_expired_tokens() {
        global $wpdb;
        $query  = "SELECT * FROM " . SMLISER_DOWNLOAD_TOKEN_TABLE . " WHERE `expiry` < %d";
        $results = $wpdb->get_results( $wpdb->prepare( $query, time() ), ARRAY_A );
        $tokens = array();

        if ( ! empty( $results ) ) {
            foreach( $results as $result ) {
                $tokens[] = self::from_array( $result );
            }
        }
        
        if ( ! empty( $tokens ) ) {
            foreach( $tokens as $token ) {
                if( $token->has_expired() ) {
                    $token->delete();
                }
            }
        }
    }

    /**
     * Delete and invalidate all item tokens when the associated license is either deleted, revoked and deactivated.
     * 
     * @param string $license_key The license key that is associated with token(s).
     */
    public static function mass_invalidate( $license_key ) {

    }
}