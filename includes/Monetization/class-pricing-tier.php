<?php
/**
 * Pricing Tier Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * A pricing tier represents a specific license option for an item in the repository.
 * 
 * An item in this repository can be a plugin, theme, or any other digital product.
 * 
 * Each pricing tier is tied to a monetization provider, and can define
 * billing cycles, site activation limits, and features available under that tier.
 */
class Pricing_Tier {
    /**
     * Unique identifier for the pricing tier.
     *
     * @var int
     */
    protected $id;

    /**
     * The monetization ID this tier belongs to.
     * 
     * @var int $monetization_id
     */
    protected $monetization_id;

    /**
     * The display name of the pricing tier (e.g., "Single Site", "Pro", "Lifetime").
     *
     * @var string
     */
    protected $name;

    /**
     * The product ID this tier belongs to.
     *
     * @var int|string
     */
    protected $product_id;

    /**
     * The monetization provider ID (e.g., WooCommerce, EDD, etc.).
     *
     * @var string
     */
    protected $provider_id;

    /**
     * The billing cycle for this pricing tier.
     * Examples: "monthly", "yearly", "lifetime".
     *
     * @var string
     */
    protected $billing_cycle = '';

    /**
     * The maximum number of sites this license tier supports.
     *
     * @var int
     */
    protected $max_sites = 1;

    /**
     * Features included in this tier (e.g., array of strings).
     *
     * @var array
     */
    protected $features = [];

    /**
     * Constructor.
     */
    public function __construct() {}

    /*
    |-----------
    | GETTERS
    |-----------
    */

    /** 
     *  The unique identifier for this pricing tier.
     * 
     * @return int|string 
     */
    public function get_id() { 
        return $this->id;
    }

    /**
     * Get monetization ID this tier belongs to.
     * 
     * @return int
     */
    public function get_monetization_id() {
        return $this->monetization_id;
    }

    /** 
     * The name of this pricing tier.
     * 
     * @return string 
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * The product ID this tier belongs to.
     * 
     *  @return int|string 
     */
    public function get_product_id() {
        return $this->product_id;
    }

    /** 
     * The monetization provider ID this tier is associated with.
     * 
     * @return string 
     */
    public function get_provider_id() {
        return $this->provider_id;
    }

    /** 
     * The billing cycle for this pricing tier.
     * 
     * @return string
     */
    public function get_billing_cycle() {
        return $this->billing_cycle;
    }

    /** 
     * The maximum number of sites this tier supports.
     * 
     * @return int
     */
    public function get_max_sites() {
        return $this->max_sites;
    }

    /**
     * Get the features included in this pricing tier.
     * 
     * @return array
     */
    public function get_features() {
        return $this->features;
    }

    /*-----------
    | SETTERS
    |-----------
    */

    /**
     * Set ID
     * 
     * @param int $id
     * @return self
     */
    public function set_id( $id ) {
        $this->id = absint( $id );
        return $this;
    }

    /**
     * Set monetization ID this tier belongs to.
     * 
     * @param int $monetization_id
     * @return self
     */
    public function set_monetization_id( $monetization_id ) {
        $this->monetization_id = absint( $monetization_id );
        return $this;
    }

    /**
     * Set the name of this pricing tier.
     *
     * @param string $name The name of the pricing tier.
     * @return self
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( unslash( $name ) );
        return $this;
    }

    /**
     * Set the product ID this tier belongs to.
     *
     * @param int|string $product_id The product ID.
     * @return self
     */
    public function set_product_id( $product_id ) {
        $this->product_id = is_numeric( $product_id ) ? absint( $product_id ) : sanitize_text_field( unslash( $product_id ) );
        return $this;
    }

    /**
     * Set the monetization provider ID for this tier.
     *
     * @param string $provider_id The provider ID (e.g., 'woocommerce', 'edd').
     * @return self
     */    
    public function set_provider_id( $provider_id ) {
        $this->provider_id = sanitize_text_field( unslash( $provider_id ) );
        return $this;
    }

    /**
     * Set the billing cycle for this tier.
     *
     * @param string $billing_cycle Billing cycle (e.g., 'monthly', 'yearly', 'lifetime').
     * @return self
     */
    public function set_billing_cycle( $billing_cycle ) {
        $this->billing_cycle = $billing_cycle;
        return $this;
    }

    /**
     * Set the maximum number of sites this tier supports.
     *
     * @param int $max_sites
     * @return self
     */
    public function set_max_sites( $max_sites ) {
        $this->max_sites = (int) $max_sites;
        return $this;
    }

    /**
     * Set the features for this tier.
     *
     * @param array $features Array of feature strings.
     * @return self
     */
    public function set_features( array $features ) {
        $this->features = $features;
        return $this;
    }

    /*
    |---------------------------
    | CRUD METHODS
    |---------------------------
    */

    /**
     * Save or update this pricing tier in the database.
     * 
     * @return bool True on success, false on failure.
     */
    public function save() {
        global $wpdb;

        $data = [
            'monetization_id' => $this->monetization_id,
            'name'            => $this->name,
            'product_id'      => $this->product_id,
            'provider_id'     => $this->provider_id,
            'billing_cycle'   => $this->billing_cycle,
            'max_sites'       => $this->max_sites,
            'features'        => maybe_serialize( $this->features ),
        ];

        if ( $this->id ) {
            // Update existing tier
            $updated = $wpdb->update(
                SMLISER_PRICING_TIER_TABLE,
                $data,
                [ 'id' => $this->id ],
                [ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ],
                [ '%d' ]
            );

            return false !== $updated;
        } else {
            // Insert new tier
            $inserted = $wpdb->insert(
                SMLISER_PRICING_TIER_TABLE,
                $data,
                [ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
            );

            if ( $inserted ) {
                $this->set_id( $wpdb->insert_id );
                return true;
            }

            return false;
        }
    }

    /**
     * Delete this pricing tier from the database.
     * 
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        $deleted = $wpdb->delete(
            SMLISER_PRICING_TIER_TABLE,
            [ 'id' => $this->id ],
            [ '%d' ]
        );

        return false === $deleted ? false : true;
    }

    /**
     * Get a pricing tier by its ID.
     * 
     * @param int $id The ID of the pricing tier.
     * @return Pricing_Tier|null The Pricing_Tier object if found, null otherwise.
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SMLISER_PRICING_TIER_TABLE . " WHERE id = %d", absint( $id ) ) );

        if ( null === $row ) {
            return null;
        }

        $tier = new self();
        $tier->set_monetization_id( $row->monetization_id )
            ->set_id( $row->id )
            ->set_name( $row->name )
            ->set_product_id( $row->product_id )
            ->set_provider_id( $row->provider_id )
            ->set_billing_cycle( $row->billing_cycle )
            ->set_max_sites( $row->max_sites )
            ->set_features( maybe_unserialize( $row->features ) );

        return $tier;
    }

    /**
     * Get all pricing tiers by monetization ID.
     *
     * @param int $monetization_id The monetization ID.
     * @return Pricing_Tier[] Array of Pricing_Tier objects.
     */
    public static function get_by_monetization_id( $monetization_id ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . SMLISER_PRICING_TIER_TABLE . " WHERE monetization_id = %d",
                absint( $monetization_id )
            )
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $tiers = [];
        foreach ( $rows as $row ) {
            $tier = new self();
            $tier->set_id( $row->id )
                ->set_monetization_id( $row->monetization_id )
                ->set_name( $row->name )
                ->set_product_id( $row->product_id )
                ->set_provider_id( $row->provider_id )
                ->set_billing_cycle( $row->billing_cycle )
                ->set_max_sites( $row->max_sites )
                ->set_features( maybe_unserialize( $row->features ) );

            $tiers[] = $tier;
        }

        return $tiers;
    }

    /**
     * Format pricing tier to array
     * 
     * @return array
     */
    public function to_array() {
        $data = array(
            'id'            => $this->get_id(),
            'name'          => $this->get_name(),
            'product_id'    => $this->get_product_id(),
            'provider_id'   => $this->get_provider_id(),
            'billing_cycle' => $this->get_billing_cycle(),
            'max_sites'     => $this->get_max_sites(),
            'features'      => $this->get_features(),
            'product'       => [],

        );

        $provider       = Provider_Collection::instance()->get_provider( $this->get_provider_id() );
        $product_data   = $provider ? $provider->get_product( $this->get_product_id() ) : [];
        $valid_product  = Provider_Collection::validate_product_data( $product_data );

        if ( ! is_smliser_error( $valid_product ) ) {
            $data['product'] = $valid_product;
        } else {
            $data['product_error'] = $valid_product->get_error_message();
        }

        return $data;
    }

}
