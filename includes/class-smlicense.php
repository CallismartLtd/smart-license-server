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
    
    /**
     * Generate a new license key with a prefix and hyphens at every 8-character interval.
     *
     * @param string $prefix The prefix to be added to the license key.
     * @return string The generated license key.
     */
    public function generate_license_key( $prefix = '' ) {
        $prefix = get_option( 'smart_license_prefix', 'SMALISER' );
        // Generate UUID for uniqueness.
        $uuid = '';

        // Check if the uuid_create function is available.
        if ( function_exists( 'uuid_create' ) ) {
            $uuid = uuid_create( UUID_TYPE_RANDOM );
        } else {
            // Fallback to md5 and uniqid if uuid_create is not available.
            $uuid = sha1( uniqid( '', true ) );
        }

        // Generate cryptographically secure random bytes.
        $secure_bytes = random_bytes( 16 );

        // Convert random bytes to a hexadecimal string.
        $random_hex = bin2hex( $secure_bytes );

        // Combine UUID and random hexadecimal string, and format the result as uppercase.
        $combined_key = strtoupper( str_replace( '-', '', $uuid ) . $random_hex );

        // Add prefix to the combined key.
        $license_key = $prefix . $combined_key;

        // Insert hyphens at every 8-character interval.
        $formatted_license_key = '';
        for ( $i = 0; $i < strlen( $license_key ); $i += 8 ) {
            if ( $i > 0 ) {
                $formatted_license_key .= '-';
            }
            $formatted_license_key .= substr( $license_key, $i, 8 );
        }

        return $formatted_license_key;
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
     * @param int $user_id  The IS of user associated with the license.
     */
    public function set_user_id( ?int $user_id = 0 ) {
        $this->user_id = absint( $user_id );
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
    public function get_status() {
        return $this->status;
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
        if ( empty( $this->get_license_key() ) || empty( $this->get_service_id() ) || empty( $this->get_item_id() ) ) {
            return new WP_Error( 'required_field_missing', __( 'Service ID, Item ID and License key are required', 'smliser' ) );
        }

        // Prepare data.
        $data = array(
            'user_id'       => ! empty( $this->get_user_id() ) ? $this->get_user_id() : '',
            'license_key'   => $this->get_license_key(),
            'service_id'    => $this->get_service_id(),
            'item_id'       => $this->get_item_id(),
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
            '%s', // status
            '%s', // start Date
            '%s', // End date
        );

        // phpcs:disable
        $wpdb->insert( SMLISER_LICENSE_TABLE, $data, $data_format );
        return $wpdb->insert_id;
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
                        $obj = new self();
                        $obj->deactivate( $license_id );
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
    
}
