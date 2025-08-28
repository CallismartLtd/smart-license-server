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
    protected $billing_cycle = 'lifetime';

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
     *
     * @param int        $id          Unique identifier for the pricing tier.
     * @param string     $name        The name of the pricing tier.
     * @param int|string $product_id  The product ID this tier belongs to.
     * @param string     $provider_id The monetization provider ID.
     */
    public function __construct( $id, $name, $product_id, $provider_id ) {
        $this->id          = $id;
        $this->name        = $name;
        $this->product_id  = $product_id;
        $this->provider_id = $provider_id;
    }

    /** 
     *  The unique identifier for this pricing tier.
     * 
     * @return int|string 
     */
    public function get_id() { 
        return $this->id;
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
    public function get_provider_id() { return $this->provider_id; }

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
    public function get_max_sites() { return $this->max_sites; }

    /**
     * Get the features included in this pricing tier.
     * 
     * @return array
     */
    public function get_features() { return $this->features; }

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
}
