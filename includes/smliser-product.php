<?php
/**
 * The Smart License Product Class file.
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

Class Smliser_Product {

    /**
     * Product ID.
     * @var int $product_id
     */
    private $product_id = 0;

    /**
     * Product Name
     * 
     * @var string $name
     */
    private $name = '';

    /**
     * Product price
     * 
     * @var float $price
     */
    private $price = 0;

    /**
     * Fee 
     * 
     * @var float $fee
     */
    private $fee = 0;

    /**
     * Description.
     * @var string $description
     */
    private $description = '';

    /**
     * License Key
     * 
     * @var array $license_key
     */
    private $license_key = '';

    /**
     * plugin basename.
     * 
     * @var string $plugin_basename
     */
    private $plugin_basename = '';

    /**
     * Creation time
     * 
     * @var string $created_at
     */
    private $created_at = '';

    /**
     * Update time
     * 
     * @var $updated_at
     */
    private $updated_at = '';

    /**
     * Class constructor
     */
    public function __construct( $data ) {
        if ( is_int( $data ) ) {
            return $this->get_product( $data );
        } elseif ( $data instanceof Smliser_Product ) {
            return $data;
        }
    }

    /*
    |---------------
    | Setters
    |---------------
    */

    /**
     * Set product ID
     * 
     * @param int $id The product ID
     */
    public function set_id( $id ) {
        $this->product_id = absint( $id );
    }

    /**
     * Set product name
     * 
     * @param string $name The product name
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( $name );
    }

    /**
     * Set product price
     * 
     * @param float $price The product price
     */
    public function set_price( $price ) {
        $this->price = floatval( $price );
    }

    /**
     * Set the fee.
     * 
     * @param float $fee
     */
    public function set_fee( $fee ) {
        $this->fee = floatval( $fee );
    }

    /**
     * Set description.
     * 
     * @param string $description The description.
     */
    public function set_description( $description) {
        $this->description = wp_kses_post( $description );
    }

    /**
     * Set the license key
     * 
     * @param $license_key The license key
     * @param string $context The context in which the key is set.
     */
    public function set_license_key( $license_key, $context = 'view' ) {
        if ( 'view' === $context) {
            $this->license_key = sanitize_text_field( $license_key );
        } elseif ( 'edit' === $context ) {
            $license            = new Smliser_license();
            $license_key        = $license->generate_license_key();
            $this->license_key  = sanitize_text_field( $license_key );
        }
    }

    /**
     * Set Repository basename for plugin.
     * 
     * @param string $basename The plugin base name
     */
    public function set_baseame( $basename ) {
        $this->plugin_basename = sanitize_text_field( $basename );
    }

    /**
     * Set creation time.
     */
    private function set_created_at() {
        $this->created_at = current_time( 'mysql' );
    }

    /**
     * Set update time.
     */
    private function set_updated_at() {
        $this->updated_at = current_time( 'mysql' );
    }

    /*
    |---------------
    | Getters
    |---------------
    */

    /**
     * Set product ID
    */
    public function get_id() {
        return $this->product_id;
    }

    /**
     * Get product name
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get product price
     */
    public function get_price() {
        return $this->price;
    }

    /**
     * Get the fee.
     */
    public function get_fee() {
        return $this->fee;
    }

    /**
     * Get description.
     * 
     * @param string $description The description.
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get the license key.
     * 
     * @param string $context The context in which the license should be rendered.
     */
    public function get_license_key( $context = 'view' ) {
        if ( 'view' === $context) {
            $license = Smliser_license::get_by_key( $this->get_license_key() );
            return $license->get_copyable_Lkey() ?? null;
        } elseif ( 'edit' === $context ) {
            return $this->license_key;
        }
    }

    /**
     * Get Repository basename for plugin.
     */
    public function get_basename() {
        return $this->plugin_basename;
    }

    /**
     * Get creation time.
     */
    private function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get update time.
     */
    private function get_updated_at() {
        return $this->updated_at;
    }

    /*
    |------------------
    | Crud methods
    |------------------
    */

    /**
     * Save product to the database
     */
    public function save() {
        global $wpdb;

        $data = array(
            'name'              => isset( $this->name ) ? sanitize_text_field( $this->get_name() ) : '',
            'price'             => isset( $this->price ) ? floatval( $this->get_price() ) : 0,
            'fee'               => isset( $this->fee ) ? floatval( $this->get_fee() ) : 0,
            'license_key'       => isset( $this->license_key ) ? sanitize_text_field( $this->get_license_key() ) : '',
            'plugin_basename'   => isset( $this->plugin_basename ) ? $this->get_basename() : '',
            'created_at'        => current_time( 'mysql' ),
        );

        $data_format = array(
            '%s', // Name.
            '%f', // Price.
            '%f', // Fee.
            '%s', // License key.
            '%s', // plugin basename.
            '%s', // created at.
        );

        // phpcs:disable
        $result = $wpdb->insert( SMLISER_PRODUCT_TABLE, $data, $data_format );
        // phpcs:enable
        if ( $result ) {
            return $wpdb->insert_id;
        }
        return false;
    }
}