<?php
/**
 * Plugin download token class.
 * 
 * @author Callistus
 * @package Smliser\class
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

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

    /*
    |-------------------------------
    | CONVERT DB RESULTS TO PROPS
    |-------------------------------
    */
    private function convert_db_result( $result ) {
        $self = new self();
        $self->id           = isset( $result['id'] ) ? absint( $result['id'] ) : 0;
        $self->item_id      = isset( $result['item_id'] ) ? sanitize_text_field( $result['item_id'] ) : '';
        $self->license_key  = isset( $result['license_key'] ) ? sanitize_text_field( $result['license_key'] ) : '';
        $self->token        = isset( $result['token'] ) ? sanitize_text_field( smliser_safe_base64_decode( $result['token'] ) ) : '';
        $self->expiry       = isset( $result['expiry'] ) ? sanitize_text_field( $result['expiry'] ) : 0;
        return $self;
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
                'token'         => ! empty( $this->token ) ? base64_encode( sanitize_text_field( $this->token ) ) : base64_encode( 'smliser_' . bin2hex( random_bytes( 32 ) ) ),
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

        if ( isset( $data['item_id'] ) ) {
            $self->item_id = sanitize_text_field( $data['item_id'] );
        }

        if ( isset( $data['license_key'] ) ) {
            $self->license_key = sanitize_text_field( $data['license_key'] );
        }

        if ( isset( $data['expiry'] ) && ! empty( $data['expiry'] ) ) {
            $self->expiry = absint( $data['expiry'] );
        } else {
            $self->expiry = 10 * DAY_IN_SECONDS;
        }

        if ( isset( $data['token'] ) ) {
            $self->token = sanitize_text_field( $data['token'] );
        } else {
            $self->token = 'smliser_' . bin2hex( random_bytes( 32 ) );
        }

        if ( $self->save() ) {

            return $self->token;
        }

    }

    /**
     * Get a token by token value.
     * 
     * @param string $token the token
     */
    public function get_token( $token ) {
        global $wpdb;
        $sql    = $wpdb->prepare( "SELECT * FROM {$this->db_name} WHERE `token` = %s", $token );
        $result = $wpdb->get_row( $sql, ARRAY_A );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( ! $result ) {
            return false;
        }

        $self = $this->convert_db_result( $result );

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
     * Delete a token from the database
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
        if ( $this->expiry < time() ) {
            return true;
        }

        return false;
    }

    /**
     * Check if item is valid;
     */
    public function item_exists() {
        $plugin = new Smliser_Plugin();
        return $plugin->get_plugin( $this->item_id ) !== false;
    }

    /**
     * Check if license exists for this token.
     */
    public function license_exists() {
        $license = Smliser_license::get_by_key( $this->license_key );
        return $license && true === $license->can_serve_license( $this->item_id );
    }

    /**
     * Ajax form to generate token.
     */
    public static function ajax_token_form() {
       check_ajax_referer( 'smliser_nonce', 'security' );
       $item_id     = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
       $license_key = isset( $_GET['license_key'] ) ? sanitize_text_field( wp_unslash( $_GET['license_key'] ) ) : '';

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
        
        if ( isset( $_POST['expiry'] ) && ! empty( $_POST['expiry'] ) ) {
            $converted_date     = strtotime( sanitize_text_field( wp_unslash( $_POST['expiry'] ) ) );
            $duration           = $converted_date ? max( 0, $converted_date - time() ): 0;
            $_POST['expiry']    = $duration;

            error_log( 'modified date ' . $_POST['expiry'] );
        }
        $token = self::insert_helper( $_POST );

        if ( is_wp_error( $token ) ) {
            wp_send_json_error( array( 'message' => $token->get_error_message() ) );
        }

        if ( ! $token ) {
            wp_send_json_error( array( 'message' => 'An error occured during token creation.' ) );
        }

        wp_send_json_success( array( 'token' => base64_encode( $token ) ) );

    }
}