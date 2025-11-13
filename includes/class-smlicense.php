<?php
/**
 * file name smlicense.php
 * License data interaction class file
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */
use SmartLicenseServer\Exception;


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
            <p class="smliser-partially-hidden-license-key-container"><?php echo esc_html( substr( $license_key, 0, 16 ) . 'xxxxxxxxxxxxx' ); ?></p>
            <div class="smliser-visible-license-key" style="display: none;">
                <input type="text" class="smliser-license-key-field" value="<?php echo esc_attr( $license_key ); ?>" readonly>
                <button class="smliser-to-copy">Copy<span class="dashicons dashicons-clipboard"></span></button>
            </div>
            
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
            $status = 'Active';
        } elseif ( $start_date && smliser_is_empty_date( $end_date ) ) {
            $status = 'Invalid';
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
    public function get_end_date( $context = 'view' ) {
        // Empty license end date means it's a lifetime license.
        if ( 'view' === $context && smliser_is_empty_date( $this->end_date ) ) {
            $this->end_date = '2038-01-19';
        }
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

    /**
     * Get All active licensed Websites.
     */
    public function get_active_sites( $context = 'view' ) {
        $all_sites = $this->get_meta( 'activated_on', array() );

        if ( 'view' === $context && is_array( $all_sites ) ) {
            $all_sites = array_keys( $all_sites );
            $all_sites = ! empty( $all_sites ) ? implode( ', ', $all_sites ) : 'N/A';
        }

        return $all_sites;
        
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
     * @return self|false The license data or null if not found.
     */
    public function get_license_data( $service_id, $license_key ) {
        global $wpdb;

        $service_id  = sanitize_text_field( unslash( $service_id ) );
        $license_key = sanitize_text_field( unslash( $license_key ) );

        // phpcs:disable
        $query = $wpdb->prepare( 
            "SELECT * FROM " . SMLISER_LICENSE_TABLE . " WHERE `service_id` = %s AND `license_key` = %s", 
            $service_id,
            $license_key
        );

        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable

        if ( $result ) {
            return self::from_array( $result );
        }

        return false;
    }

    /**
     * Fetch all license data from the database
     */
    public function get_licenses() {
        global $wpdb;
        // phpcs:disable
        $query = "SELECT * FROM " . SMLISER_LICENSE_TABLE;
        $results = $wpdb->get_results( $query, ARRAY_A );
        // phpcs:enable
        $all_licenses = array();

        if( ! $results ) {
            return $all_licenses; // Empty array.
        }

        foreach( $results as $result ) {
            $all_licenses[] = self::from_array( $result );
        }
        return $all_licenses;
    }

    /**
     * Get a license by id.
     * 
     * @param int $id The ID.
     * @return Smliser_license|false
     */
    public static function get_by_id( $id ) {
        
        if ( ! is_int( $id ) ) {
            $id = absint( $id );
        }

        if ( empty( $id ) ) {
            return false;
        }

        global $wpdb;
        // phpcs:disable
        $query  = $wpdb->prepare( "SELECT * FROM " . SMLISER_LICENSE_TABLE . " WHERE `id` = %d ", absint( $id ) );
        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable
        if ( $result ) {
            return self::from_array( $result );
        }

        return false;
    }

    /**
     * Get a license by license key.
     * 
     * @param string $licence_key The license key.
     */
    public static function get_by_key( $license_key ) {
        global $wpdb;
        // phpcs:disable
        $query  = $wpdb->prepare( "SELECT * FROM " . SMLISER_LICENSE_TABLE . " WHERE `license_key` = %s ", sanitize_text_field( $license_key ) );
        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable
        if ( $result ) {
            return self::from_array( $result );
        }

        return false;
    }

    /**
     * Save Licensed data into the database.
     */
    public function save() {
        global $wpdb;
        if ( empty( $this->license_key ) ) {
            return false;
        }

        if ( ! empty( $this->id ) ) {
            return $this->update();
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
            'end_date'      => ! empty( $this->end_date ) ? $this->get_end_date( 'save' ) : '0000-00-00',
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
        if ( $wpdb->insert( SMLISER_LICENSE_TABLE, $data, $data_format ) ) {
            $this->id = absint( $wpdb->insert_id );
            do_action( 'smliser_license_saved', $this->get_by_id( $wpdb->insert_id ) );
            return $wpdb->insert_id;
        }
        // phpcs:enable
        return false;
    }

    /**
     * Update method
     */
    public function update() {
        if ( ! $this->id ) {
            return false;
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
            'end_date'      => ! empty( $this->end_date ) ? sanitize_text_field( $this->end_date ) : '0000-00-00',
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

        // phpcs:disable
        $result = $wpdb->update( 
            SMLISER_LICENSE_TABLE, $data, $where, $data_format, $where_format 
        );
        // phpcs:enable
        if ( $result !== false ) {
            do_action( 'smliser_license_saved', $this );
            return true;
        }

        return false;
    }

    /**
     * Add a new metadata to a license.
     * 
     * @param mixed $key Meta Key.
     * @param mixed $value Meta value.
     * @return bool True on success, false on failure.
     */
    public function add_meta( $key, $value ) {
        if ( did_action( 'smliser_license_saved' ) > 0 ) {
            global $wpdb;

            // Sanitize inputs
            $license_id = absint( $this->id );
            $meta_key   = sanitize_text_field( $key );
            $meta_value = sanitize_text_field( is_array( $value ) ? maybe_serialize( $value ) : $value  );

            // Prepare data for insertion
            $data = array(
                'license_id'    => $license_id,
                'meta_key'      => $meta_key,
                'meta_value'    => $meta_value,
            );

            $data_format = array( '%d', '%s', '%s' );
            
            $result = $wpdb->insert( SMLISER_LICENSE_META_TABLE, $data, $data_format ); // phpcs:disable
            return $result !== false;
        }

        return false;
    }

    /**
     * Update existing metadata
     * 
     * @param mixed $key Meta key.
     * @param mixed $value New value.
     * @return bool True on success, false on failure.
     */
    public function update_meta( $key, $value ) {
        global $wpdb;

        // phpcs:disable
        $table_name = SMLISER_LICENSE_META_TABLE;
        $key        = sanitize_text_field( $key );
        $value      = sanitize_text_field( is_array( $value ) ? maybe_serialize( $value ) : $value );

        // Prepare data for insertion/updation
        $data = array(
            'license_id' => absint( $this->id ),
            'meta_key'   => $key,
            'meta_value' => $value,
        );

        $data_format = array( '%d', '%s', '%s' );

        // Check if the meta_key already exists for the given license_id
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM $table_name WHERE license_id = %d AND meta_key = %s",
            absint( $this->id ),
            $key
        ) );

        if ( ! $exists ) {
            // Insert new record if it doesn't exist
            $inserted = $wpdb->insert( $table_name, $data, $data_format );

            return $inserted !== false;
        } else {
            // Update existing record
            $updated = $wpdb->update(
                $table_name,
                array( 'meta_value' => $value ),
                array(
                    'license_id' => absint( $this->id ),
                    'meta_key'   => $key,
                ),
                array( '%s' ),
                array( '%d', '%s' )
            );

            // phpcs:enable
            return $updated !== false;
        }
    }

    /**
     * Get the value of a metadata
     * 
     * @param $meta_key The meta key.
     * @param $default_to What to return when nothing is found.
     * @return mixed|null $value The value.
     */
    public function get_meta( $meta_key, $default_to = null ) {
        global $wpdb;

        // phpcs:disable
        $query  = $wpdb->prepare( 
            "SELECT `meta_value` FROM " . SMLISER_LICENSE_META_TABLE . " WHERE `license_id` = %d AND `meta_key` = %s", 
            absint( $this->id), 
            sanitize_text_field( $meta_key ) 
        );

        $result = $wpdb->get_var( $query );
        // phpcs:enable
        if ( is_null( $result ) ) {
            return $default_to;
        }
        return is_serialized( $result ) ? unserialize( $result ) : $result;
    }

    /**
     * Get total sites license has been activated
     */
    public function get_total_active_sites() {
        return count( $this->get_meta( 'activated_on', array() ) );
    }

    /**
     * Update Activated sites.
     * 
     * @param string $site_name The name of the site to be added.
     * @param string $site_secret The secret key for the site.
     */
    public function update_active_sites( $site_name, $site_secret ) {
        $sites  = $this->get_meta( 'activated_on', array() );
        if ( ! is_array( $sites ) ) {
            $sites = array();
        }
        
        $site_name  = sanitize_url( $site_name, array( 'https', 'http' ) );
        $domain     = wp_parse_url( $site_name, PHP_URL_HOST );
        $sites[$domain] = array(
            'name'      => $site_name,
            'secret'    => $site_secret,
        );

        return $this->update_meta( 'activated_on', $sites );
    }

    /**
     * Remove Activated website
     * 
     * @param $site_name The name of the website.
     */
    public function remove_activated_website( $site_name ) {
        $sites  = $this->get_meta( 'activated_on' );

        if ( empty( $sites ) ) {
            return false;
        }
        
        $site_name  = sanitize_url( $site_name, array( 'https', 'http' ) );
        $domain     = wp_parse_url( $site_name, PHP_URL_HOST );
        foreach ( (array) $sites as $k => $v ) {
            if ( $k === $domain ) {
                unset( $sites[$k] );
            }
        }
        return $this->update_meta( 'activated_on', $sites );
    }

    /**
     * Delete a metadata from the license.
     * 
     * @param string $meta_key The meta key.
     * @return bool True on success, false on failure.
     */
    public function delete_meta( $meta_key ) {
        global $wpdb;

        $license_id = absint( $this->id );
        $meta_key   = sanitize_text_field( $meta_key );
        $where      = array(
            'license_id' => $license_id,
            'meta_key' => $meta_key
        );

        $where_format = array( '%d', '%s' );

        // Execute the delete query
        $deleted = $wpdb->delete( SMLISER_LICENSE_META_TABLE, $where, $where_format ); // phpcs:disable

        // Return true on success, false on failure
        return $deleted !== false;
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

        global $wpdb;
        // phpcs:disable
		$deleted        = $wpdb->delete( SMLISER_LICENSE_TABLE, array( 'id' => $data ), array( '%d' ) );
		$deleted_meta   = $wpdb->delete( SMLISER_LICENSE_META_TABLE, array( 'license_id' => $data ), array( '%d' ) );
		// phpcs:enable
		if ( false === $deleted && false === $deleted_meta ) {
			return $deleted;
		}
        return true;
    }

    /*
    |-----------------
    | ACTION HANDLERS
    |-----------------
    */

    /**
     * Handle bulk action on License table
     */
    public static function bulk_action() {
        
        if ( isset( $_POST['smliser_table_nonce'] ) && wp_verify_nonce( sanitize_text_field( unslash( $_POST['smliser_table_nonce'] ) ), 'smliser_table_nonce' ) ) {
            $action     = sanitize_text_field( $_POST['bulk_action'] );
            $licenses   = ! empty( $_POST['license_ids'] ) ? array_map( 'absint', $_POST['license_ids'] ) : '';

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
            wp_safe_redirect( smliser_license_page() );
            exit;
        } elseif ( isset( $_GET['smliser_nonce'] ) && wp_verify_nonce( sanitize_text_field( unslash( $_GET['smliser_nonce'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action     = isset( $_GET['real_action'] ) ? sanitize_text_field( $_GET['real_action'] ) : '';
            $license_id = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : 0;
            
            switch ( $action ) {
                case 'delete':
                    $obj = self::get_by_id( $license_id );
                    $obj->delete();
                    break;
            }

            wp_safe_redirect( smliser_license_page() );
            exit;  
        }
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

        switch ( strtolower( $action ) ) {

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
        // phpcs:disable
        $result = $wpdb->update( SMLISER_LICENSE_TABLE, $data, $where, $data_format, $where_format );
        // phpcs:enable
    }

    /**
     * License form controller.
     */
    public static function license_form_controller() {
        if ( isset( $_POST['smliser_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( unslash( $_POST['smliser_nonce_field'] ) ), 'smliser_nonce_field' ) ) {
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
                wp_safe_redirect( smliser_license_admin_action_page( 'edit', $license_id ) );
            } elseif ( $is_editing ) {
                $obj->update();
                sleep(4);
                set_transient( 'smliser_form_success', true, 4 );
                wp_safe_redirect( smliser_license_admin_action_page( 'edit', $license_id ) );
                exit;
            }             
        }
    }

    /**
     * Instance of current class.
     */
    public static function instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
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
            return new Exception( 'invalid_data', 'Invalid License' );
        }
        $data = array(
            'license_key'   => $this->get_license_key(),
            'service_id'    => $this->get_service_id(),
            'item_id'       => $this->get_item_id(),
            'expiry_date'   => $this->get_end_date(),
        );
        return smliser_safe_json_encode( $data );
    }
    
    /**
     * Generate a new license key.
     *
     * @param string $prefix The prefix to be added to the license key.
     * @return string The generated license key.
     */
    public function generate_license_key( $prefix = '' ) {
        if ( empty( $prefix ) ) {
            $prefix = get_option( 'smliser_license_prefix', 'SMLISER' );
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
     * Converts and return associative array to object of this class.
     * 
     * @param array $data   Associative array containing result from database
     */
    public static function from_array( $data ) {
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

    /*
    |---------------
    | CONDITIONALS
    |---------------
    */

    /**
     * Whether license has reached max allowed websites.
     */
    public function has_reached_max_allowed_sites() {
       return $this->get_total_active_sites() >= $this->get_allowed_sites();
         
    }

    /**
     * Check if license is associated with an item.
     */
    public function has_item() {
        return ! empty( $this->get_item_id() );
    }

    /**
     * Check if we can serve license.
     *
     * @param int    $item_id The item ID associated with the license.
     * @return true|Exception True if license can be served, otherwise Exception.
     */
    public function can_serve_license( $item_id ) {
        $error = null;

        if ( ! $this ) {
            $error = new Exception( 'license_error', 'Invalid license key or service ID.', array( 'status' => 400 ) );
        } elseif ( absint( $item_id ) !== absint( $this->get_item_id() ) ) {
            $error = new Exception( 'license_error', 'Invalid license key or service ID.', array( 'status' => 400 ) );
        } elseif ( 'Expired' === $this->get_status() ) {
            $error = new Exception( 'license_expired', 'This license has expired. Please renew it.', array( 'status' => 403 ) );
        } elseif ( 'Suspended' === $this->get_status() ) {
            $error = new Exception( 'license_suspended', 'This license has been suspended. Please contact support if you need further assistance.', array( 'status' => 403 ) );
        } elseif ( 'Revoked' === $this->get_status() ) {
            $error = new Exception( 'license_revoked', 'This license has been revoked. Please contact support if you need further assistance.', array( 'status' => 403 ) );
        } elseif ( 'Deactivated' === $this->get_status() ) {
            $error = new Exception( 'license_deactivated', 'This license has been deactivated. Please reactivate it or contact support if you need further assistance.', array( 'status' => 403 ) );
        }

        return $error ?: true;
    }

    /**
     * Checks whether the a given domain is a new domain
     * 
     * @param string $domain The name of the website.
     */
    public function is_new_domain( $domain ) {
        $domain         = sanitize_url( $domain, array( 'http', 'https' ) );
        $domain         = wp_parse_url( $domain, PHP_URL_HOST );
        $all_sites      = $this->get_active_sites( 'edit' );
        return ! isset( $all_sites[$domain] );
    }


    /**
     * The associated app
     * 
     * @return SmartLicenseServer\HostedApps\Hosted_Apps_Interface
     */
    public function get_app() {
        
    }
}
