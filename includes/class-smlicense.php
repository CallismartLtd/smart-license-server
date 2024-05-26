<?php
/**
 * file name smlicense.php
 * License data interaction class file
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Represents the license itself.
 */
class Smliser_license {

    /**
     * @var int $id The license ID.
     */
    private $id = 0;

    /**
     * @var int $user_id    The ID of user associated with the license.
     */
    private $user_id    = 0;

    /**
     * @var string $license_key The license key
     */
    private $license_key = '';

    /**
     * @var string $service_id  The ID of the service associated with the license.
     */
    private $service_id = '';

    /**
     * @var int $item_id    The ID of the item(maybe product) being licensed.
     */
    private $item_id    = 0;

    /**
     * @var $status The status of the license
     */
    private $status = '';

    /**
     * @var string $start_date  The license commencement date
     */
    private $start_date = '';

    /**
     * @var string  $end_date   The license termination or deactivation date.
     */
    private $end_date   = '';

    /**
     * Allowed websites.
     * 
     * @var int $allowed_sites  Number of allowed website for a license
     */
    private $allowed_sites;

    /**
     * Instance of Smliser_license
     * @var Smliser_license $instance    Instance of this class.
     */
    private static $instance = null;

    /**
     * Action being executed.
     * 
     * @var string $action
     */
    private static $action = '';

    /**
     * Class constructor
     * @param string $service_id    The service ID associated with the license.
     * @param string $license_key   The license key.
     */
    public function __construct( $service_id = '', $license_key = '' ) {
        if ( ! empty( $service_id ) && ! empty( $license_key ) ) {
            $license_data = $this->get_license_data( $service_id, $license_key );
            return $license_data;
        }
        //add_action( 'admin_post_smliser_add_license', array( $this->add_license() ) );

    }
    
    /*
    |------------
    | Setters
    |------------
    */
    
    /** 
     * Set the ID 
     * 
     * @param int $id    The ID of the license
     */
    public function set_id( ?int $id = 0 ) {
        $this->id = absint( $id );
    }

    /** 
     * Set the user_id
     * 
     * @param int $user_id  The ID of user associated with the license.
     */
    public function set_user_id( ?int $user_id = 0 ) {
        $this->user_id = intval( $user_id );
    }

    /** 
     * Set License key 
     * 
     * @param string $license_key   The license key.
     * @param string $context       The context in which the license key is set. "view" sets the
     *                              property with the provided license key, "new" generates and set the
     *                              property with a new key.
     */
    public function set_license_key( $license_key = '', $context = 'view' ) {

        if ( 'view' === $context ) {
            $this->license_key = sanitize_text_field( $license_key );
        } elseif ( 'new' === $context ) {
            $license_key = $this->generate_license_key();
            $this->license_key = sanitize_text_field( $license_key );
        }

    }

    /**
     * Set Service ID
     * 
     * @param string $service_id    The ID of the service associated with the license.
     */
    public function set_service_id( $service_id = '' ) {
        $this->service_id = sanitize_text_field( $service_id );
    }

    /**
     * Set Item ID
     * 
     * @param int $item_id  Item(maybe product) ID
     */
    public function set_item_id( $item_id = 0 ) {
        $this->item_id = absint( $item_id );
    }
    
    /**
     * Set license status
     * 
     * @param string $status The status.
     */
    public function set_status( $status ) {
        $this->status = sanitize_text_field( $status );

    }

    /**
     * Set the start date
     * 
     * @param string $start_date     The commencement date
     */
    public function set_start_date( $start_date = '' ) {
        $this->start_date = sanitize_text_field( $start_date );
    }

    /**
     * Set end date
     * 
     * @param string $end_date  The license expiration date
     */
    public function set_end_date( $end_date = '' ) {
        $this->end_date = sanitize_text_field( $end_date );
    }

    /**
     * Set number of allowed websites for a license
     * 
     * @param int $number Number of allowed websites for a license.
     */
    public function set_allowed_sites( $number ) {
        $this->allowed_sites = absint( $number );
    }

    /**
     * Set an action on class.
     * 
     * @param string $action    Action to execute.
     */
    public function set_action( $action = '' ) {
        self::$action = sanitize_text_field( $action );
    }

    /*
    |------------
    |Getters
    |------------
    */

    /**
     * Get the License ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the user ID
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * Get the service ID
     */
    public function get_service_id() {
        return $this->service_id;
    }

    /**
     * Get the license key
     */
    public function get_license_key() {
        return $this->license_key;
    }

    /**
     * Get the copyable version of the License key.
     */

    public function get_copyable_Lkey() {
        $license_key = $this->get_license_key();
        ob_start();
        ?>
        <div class="smliser-key-div">
            <!-- The container for the partially hidden key -->
            <div class="smliser-partially-hidden-license-key-container">
                <p><?php esc_html_e( substr( $license_key, 0, 16 ) . 'xxxxxxxxxxxxxxxxxxxxxxx' ); ?></p>
            </div>
            
            <!-- Container for the fully displayed and copyable license key -->
            <div class="smliser-visible-license-key" style="display: none;">
                <!-- The license key field -->
                <input type="text" class="smliser-license-key-field" value="<?php echo esc_attr( $license_key ); ?>" readonly>
                <!-- The button used to copy the text -->
                <button class="smliser-to-copy">Copy<span class="dashicons dashicons-clipboard"></span></button>
            </div>
            
            <!-- An element to toggle between password visibility -->
            <label>
                <input type="checkbox" class="smliser-show-license-key"> Show License Key
            </label>
        
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get the item ID
     */
    public function get_item_id() {
        return $this->item_id;
    }

   /**
     * Get license status
     */
    public function get_status( $context = 'view' ) {
        if ( 'edit' === $context ) {
            return $this->status;
        }
        
        $start_date = $this->start_date;
        $end_date   = $this->end_date;
        $status     = $this->status;
        $current_time = current_time( 'Y-m-d' );
        /** Manual status will take precedence */
        if ( ! empty( $status ) ) {
            return $status;
        }

        /** If no end date is found, this maybe a lifetime license */
        if ( smliser_is_empty_date( $end_date ) ) {
            $status = 'Lifetime';
        } elseif ( $start_date && smliser_is_empty_date( $end_date ) ) {
            $status = 'Lifetime';
        } elseif ( $end_date >= $current_time ) {
            $status = 'Active';
        } elseif ( $end_date < $current_time ) {
            $status = 'Expired';
        }

        return $status;
    }

    /**
     * Get the start date
     */
    public function get_start_date() {
        return $this->start_date;
    }

    /**
     * Get the end date
     */
    public function get_end_date() {
        return $this->end_date;
    }

    /**
     * Get number of allowed websites.
     */
    public function get_allowed_sites() {
        return $this->allowed_sites;
    }

    /**
     * Get an action to be executed.
     */
    public function get_action() {
        return self::$action;
    }

    /*
    |-----------------
    |   CRUD METHODS
    |-----------------
    */

    /**
     * Get a license data
     * 
     * @param string $service_id The service ID associated with the License.
     * @param string $license_key The license key.
     * @return object|null The license data or null if not found.
     */
    public function get_license_data( $service_id, $license_key ) {
        global $wpdb;

        $service_id  = sanitize_text_field( $service_id );
        $license_key = sanitize_text_field( $license_key );

        $query = $wpdb->prepare( 
            "SELECT * FROM ". SMLISER_LICENSE_TABLE ." WHERE `service_id` = %s AND `license_key` = %s", 
            $service_id,
            $license_key
        );

        // Execute the query and retrieve the results
        $result = $wpdb->get_row( $query, ARRAY_A );

        if ( $result ) {
            return self::return_db_results( $result );
        }

        return array();
    }

    /**
     * Fetch all license data from the database
     */
    public function get_licenses() {
        global $wpdb;
        $query = "SELECT * FROM " . SMLISER_LICENSE_TABLE;
        $results = $wpdb->get_results( $query, ARRAY_A );
        $all_licenses = array();

        if( ! $results ) {
            return $all_licenses; // Empty array.
        }

        foreach( $results as $result ) {
            $all_licenses[] = self::return_db_results( $result );
        }
        return $all_licenses;
    }

    /**
     * Get a license by id.
     * 
     * @param int $id The ID.
     */
    public static function get_by_id( $id ) {
        
        if ( ! is_int( $id ) ) {
            $id = absint( $id );
        }

        if ( empty( $id ) ) {
            return false;
        }

        global $wpdb;
        $query  = $wpdb->prepare( "SELECT * FROM " . SMLISER_LICENSE_TABLE . " WHERE `id` = %d ", absint( $id ) );
        $result = $wpdb->get_row( $query, ARRAY_A );
        if ( $result ) {
            return self::return_db_results( $result );
        }

        return false;
    }

    /**
     * Convert and return associative array of DB result to object of this class
     * 
     * @param array $data   Associative array containing result from database
     */
    private static function return_db_results( $data ) {
        $result = new self();
        $result->set_id( ! empty( $data['id'] ) ? $data['id'] : '' );
        $result->set_user_id( ! empty( $data['user_id'] ) ? $data['user_id'] : 0 );
        $result->set_service_id( ! empty( $data['service_id'] ) ? $data['service_id'] : '' );
        $result->set_license_key( ! empty( $data['license_key'] ) ? $data['license_key'] : '' );
        $result->set_item_id( ! empty( $data['item_id'] ) ? $data['item_id'] : 0 );
        $result->set_status( ! empty( $data['status'] ) ? $data['status'] : '' );
        $result->set_start_date( ! empty( $data['start_date'] ) ? $data['start_date'] : '' );
        $result->set_end_date( ! empty( $data['end_date'] ) ? $data['end_date'] : '' );
        $result->set_allowed_sites( ! empty( $data['allowed_sites'] ) ? $data['allowed_sites'] : '' );
        return $result;
    }

    /**
     * Save Licensed data into the database.
     */
    public function save() {
        if ( defined( __CLASS__ .'saving' ) ) {
            return;
        }
        define( __CLASS__ .'saving', true );
        global $wpdb;
        if ( empty( $this->license_key ) ) {
            return new WP_Error( 'missing_license_key', __( 'License key is required', 'smliser' ) );
        }

        // Prepare data.
        $data = array(
            'user_id'       => ! empty( $this->user_id ) ? $this->get_user_id() : '',
            'license_key'   => sanitize_text_field( $this->get_license_key() ),
            'service_id'    => ! empty( $this->service_id ) ? $this->get_service_id() : '',
            'item_id'       => ! empty( $this->item_id ) ? $this->get_item_id() : null,
            'allowed_sites' => ! empty( $this->allowed_sites ) ? $this->get_allowed_sites() : 0,
            'status'        => ! empty( $this->get_status() ) ? $this->get_status() : '',
            'start_date'    => ! empty( $this->get_start_date() ) ? $this->get_start_date() : '',
            'end_date'      => ! empty( $this->get_end_date() ) ? $this->get_end_date() : '',
        );

        // Prepare data format.
        $data_format = array(
            '%d', // User ID
            '%s', // License key
            '%s', // Service ID
            '%d', // Item ID
            '%d', // Allowed sites
            '%s', // status
            '%s', // start Date
            '%s', // End date
        );

        // phpcs:disable
        $wpdb->insert( SMLISER_LICENSE_TABLE, $data, $data_format );
        // phpcs:enable
        return $wpdb->insert_id;
    }

    /**
     * Update method
     */
    public function update() {
        if ( ! $this->id ) {
            return new WP_Error( 'invalid_data', 'The license does not exist' );
        }

        global $wpdb;
        // Prepare data.
        $data = array(
            'user_id'       => ! empty( $this->user_id ) ? intval( $this->get_user_id() ) : -1,
            'service_id'    => ! empty( $this->service_id ) ? sanitize_text_field( $this->get_service_id() ) : '',
            'item_id'       => ! empty( $this->item_id ) ? absint( $this->get_item_id() ) : null,
            'allowed_sites' => ! empty( $this->allowed_sites ) ? absint( $this->get_allowed_sites() ) : 0,
            'status'        => ! empty( $this->status ) ? sanitize_text_field( $this->get_status() ) : '',
            'start_date'    => ! empty( $this->start_date ) ? sanitize_text_field( $this->get_start_date() ) : '',
            'end_date'      => ! empty( $this->end_date ) ? sanitize_text_field( $this->get_end_date() ) : '',
        );

        // Data format.
        $data_format = array(
            '%d', // User ID.
            '%s', // Service ID.
            '%d', // Item ID.
            '%d', // Allowed Sites.
            '%s', // Status.
            '%s', // Start Date.
            '%s', // End Date.
        );

        // Where?
        $where = array(
            'id'    => absint( $this->get_id() ),
        );

        // Where format.
        $where_format = array(
            '%d',
        );

        $result = $wpdb->update( SMLISER_LICENSE_TABLE, $data, $where, $data_format, $where_format );
        if ( $result ) {
            return true;
        }

        return false;
    }

    /**
     * Handle bulk action on License table
     */
    public static function bulk_action() {
        
        if ( isset( $_POST['smliser_table_nonce'] ) &&wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_table_nonce'] ) ), 'smliser_table_nonce' ) ) {
            $action     = sanitize_text_field( $_POST['bulk_action'] );
            $licenses   = ! empty( $_POST['licenses'] ) ? array_map( 'absint', $_POST['licenses'] ) : '';

            switch ( $action ) {
                case 'delete':
                    foreach ( $licenses as $license_id ) {
                        $obj = new self();
                        $obj->delete( $license_id );
                    }

                    break;

                case 'deactivate':
                    foreach ( $licenses as $license_id ) {
                        $obj = self::get_by_id( $license_id );
                        $obj->set_action( 'deactivate' );
                        $obj->do_action();
                    }
                    break;
                    case 'revoke':
                        foreach ( $licenses as $license_id ) {
                            $obj = self::get_by_id( $license_id );
                            $obj->set_action( 'revoke' );
                            $obj->do_action();
                        }
                        break;
               case 'suspend':
                    foreach ( $licenses as $license_id ) {
                        $obj = self::get_by_id( $license_id );
                        $obj->set_action( 'suspend' );
                        $obj->do_action();
                    }
                    break;
    

                default:
                    break;
            }
        }
        wp_safe_redirect( smliser_license_page() );
        exit;
    }

    /**
     * Perform an action based on the given parameter.
     */
    public function do_action() {
        if ( ! $this->id ) {
            return;
        }

        $action     = $this->get_action();
        $new_status = '';

        switch ( $action ) {

            case 'deactivate':
                $new_status = 'Deactivated';
                break;

            case 'revoke':
                
                $new_status = 'Revoked';
                break;

            case 'suspend':
                $new_status = 'Suspended';

                break;
        }

        if ( empty( $new_status ) ) {
            return;
        }

        $data           = array( 'status' => $new_status, );
        $data_format    = array( '%s' );
        $where          = array( 'id' => absint( $this->id ), );
        $where_format   = array( '%d' );
        global $wpdb;
        $result = $wpdb->update( SMLISER_LICENSE_TABLE, $data, $where, $data_format, $where_format );
    }

    /**
     * License form controller.
     */
    public static function license_form_controller() {
        if ( isset( $_POST['smliser_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_nonce_field'] ) ), 'smliser_nonce_field' ) ) {
            // Form fields.
            $license_id     = isset( $_POST['license_id'] ) ? absint( $_POST['license_id'] ): 0;
            $user_id        = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ): -1;
            $item_id        = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ): 0;
            $allowed_sites  = isset( $_POST['allowed_sites'] ) ? absint( $_POST['allowed_sites'] ): 0;
            $service_id     = isset( $_POST['service_id'] ) ? sanitize_text_field( $_POST['service_id'] ) : '';
            $status         = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
            $start_date     = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
            $end_date       = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
            $is_editing     = isset( $_POST['smliser_license_edit'] ) ? true : false;
            $is_new         = isset( $_POST['smliser_license_new'] ) ? true : false;

            $obj = new self();
            $obj->set_id( $license_id );            
            $obj->set_user_id( $user_id );            
            $obj->set_item_id( $item_id );            
            $obj->set_allowed_sites( $allowed_sites );            
            $obj->set_service_id( $service_id );            
            $obj->set_status( $status );            
            $obj->set_start_date( $start_date );            
            $obj->set_end_date( $end_date );            
            
            if ( $is_new ) {
                $obj->set_license_key( '', 'new' );
                $license_id = $obj->save();
                set_transient( 'smliser_form_success', true, 4 );
                wp_safe_redirect( smliser_lisense_admin_action_page( 'edit', $license_id ) );
            } elseif ( $is_editing ) {
                $obj->update();
                set_transient( 'smliser_form_success', true, 4 );
                wp_safe_redirect( smliser_lisense_admin_action_page( 'edit', $license_id ) );
            }             
        }
       // wp_safe_redirect( smliser_lisense_admin_action_page( 'edit', $license_id ) );
    }

    /**
     * Delete a license
     * 
     * @param mixed $data 
     */
    public function delete( $data = '' ) {
        if ( is_int( $data ) ) {
            $data = absint( $data );
        }elseif ( empty( $data ) ) {
            $data = $this ? absint( $this->get_id() ) : 0;
        }

        if ( ! $data && defined( 'DOING_AJAX' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid Lincense' ) );

        }
        global $wpdb;
        // phpcs:disable
		$deleted = $wpdb->delete( SMLISER_LICENSE_TABLE, array( 'id' => $data ), array( '%d' ) );
		// phpcs:enable
		if ( false === $deleted ) {
			return $deleted;
		}
        return true;
    }

    /**
     * Instance of current class.
     */
    public static function instance() {

        if ( is_null( self::$instance ) ) {
            return self::$instance = new self();
        }
    }

    /**
    |-----------------
    | Utility Methods
    |-----------------
    */

    /**
     * Encode data to json.
     */
    public function encode() {
        if ( ! $this->id ) {
            return new WP_Error( 'invalid_data', 'Invalid License' );
        }
        $data = array(
            'license_key'   => $this->get_license_key(),
            'service_id'    => $this->get_service_id(),
            'item_id'       => $this->get_item_id(),
            'expiry_date'   => $this->get_end_date(),
        );
        return wp_json_encode( $data );
    }
    
    /**
     * Generate a new license key.
     *
     * @param string $prefix The prefix to be added to the license key.
     * @return string The generated license key.
     */
    public function generate_license_key( $prefix = '' ) {
        if ( empty( $prefix ) ) {
            $prefix = get_option( 'smliser_license_prefix', 'SMALISER' );
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
}
