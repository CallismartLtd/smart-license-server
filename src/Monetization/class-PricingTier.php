<?php
/**
 * Pricing Tier Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * A pricing tier represents a specific license option for an item in the repository.
 * 
 * An item in this repository can be a plugin, theme, or any other digital product.
 * 
 * Each pricing tier is tied to a monetization provider, and can define
 * billing cycles, site activation limits, and features available under that tier.
 */
class PricingTier {
    use SanitizeAwareTrait;
    /**
     * Unique identifier for the pricing tier.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * The monetization ID this tier belongs to.
     * 
     * @var int $monetization_id
     */
    protected int $monetization_id = 0;

    /**
     * The display name of the pricing tier (e.g., "Single Site", "Pro", "Lifetime").
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The product ID this tier belongs to.
     *
     * @var int|string
     */
    protected int|string $product_id = 0;

    /**
     * The monetization provider ID (e.g., WooCommerce, EDD, etc.).
     *
     * @var string
     */
    protected string $provider_id = '';

    /**
     * The billing cycle for this pricing tier.
     * Examples: "monthly", "yearly", "lifetime".
     *
     * @var string
     */
    protected string $billing_cycle = '';

    /**
     * The maximum number of sites this license tier supports.
     *
     * @var int
     */
    protected int $max_sites = 1;

    /**
     * Features included in this tier (e.g., array of strings).
     *
     * @var array
     */
    protected array $features = [];

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
     * @return int 
     */
    public function get_id() : int { 
        return $this->id;
    }

    /**
     * Get monetization ID this tier belongs to.
     * 
     * @return int
     */
    public function get_monetization_id() : int {
        return $this->monetization_id;
    }

    /** 
     * The name of this pricing tier.
     * 
     * @return string 
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * The product ID this tier belongs to.
     * 
     *  @return int|string 
     */
    public function get_product_id() : int|string {
        return $this->product_id;
    }

    /** 
     * The monetization provider ID this tier is associated with.
     * 
     * @return string 
     */
    public function get_provider_id() : string {
        return $this->provider_id;
    }

    /** 
     * The billing cycle for this pricing tier.
     * 
     * @return string
     */
    public function get_billing_cycle() : string {
        return $this->billing_cycle;
    }

    /** 
     * The maximum number of sites this tier supports.
     * 
     * @return int
     */
    public function get_max_sites() : int {
        return $this->max_sites;
    }

    /**
     * Get the features included in this pricing tier.
     * 
     * @return array
     */
    public function get_features() : array {
        return $this->features;
    }

    /*
    |-----=-----
    | SETTERS
    |-----------
    */

    /**
     * Set ID
     * 
     * @param int $id
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = static::sanitize_int( $id );
        return $this;
    }

    /**
     * Set monetization ID this tier belongs to.
     * 
     * @param int $monetization_id
     * @return static
     */
    public function set_monetization_id( $monetization_id ) : static {
        $this->monetization_id = static::sanitize_int( $monetization_id );
        return $this;
    }

    /**
     * Set the name of this pricing tier.
     *
     * @param string $name The name of the pricing tier.
     * @return static
     */
    public function set_name( $name ) : static {
        $this->name = static::sanitize_text( $name );
        return $this;
    }

    /**
     * Set the product ID this tier belongs to.
     *
     * @param int|string $product_id The product ID.
     * @return static
     */
    public function set_product_id( $product_id ) : static {
        $this->product_id = is_numeric( $product_id ) ? static::sanitize_int( $product_id ) : static::sanitize_text( $product_id );
        return $this;
    }

    /**
     * Set the monetization provider ID for this tier.
     *
     * @param string $provider_id The provider ID (e.g., 'woocommerce', 'edd').
     * @return static
     */    
    public function set_provider_id( $provider_id ) : static {
        $this->provider_id = static::sanitize_text( $provider_id );
        return $this;
    }

    /**
     * Set the billing cycle for this tier.
     *
     * @param string $billing_cycle Billing cycle (e.g., 'monthly', 'yearly', 'lifetime').
     * @return static
     */
    public function set_billing_cycle( $billing_cycle ) : static {
        $this->billing_cycle = $billing_cycle;
        return $this;
    }

    /**
     * Set the maximum number of sites this tier supports.
     *
     * @param int $max_sites
     * @return static
     */
    public function set_max_sites( $max_sites ) : static {
        $this->max_sites = (int) $max_sites;
        return $this;
    }

    /**
     * Set the features for this tier.
     *
     * @param array $features Array of feature strings.
     * @return static
     */
    public function set_features( array $features ) : static {
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
    public function save() : bool {
        $db = smliser_db();

        $data = [
            'monetization_id' => $this->monetization_id,
            'name'            => $this->name,
            'product_id'      => $this->product_id,
            'provider_id'     => $this->provider_id,
            'billing_cycle'   => $this->billing_cycle,
            'max_sites'       => $this->max_sites,
            'features'        => Format::encode( $this->features, Format::ENCODING_PHP ),
        ];

        if ( $this->id ) {
            // Update existing tier.
            $updated = $db->update( SMLISER_PRICING_TIER_TABLE, $data, [ 'id' => $this->id ] );

            return false !== $updated;
        } else {
            // Insert new tier
            $inserted = $db->insert( SMLISER_PRICING_TIER_TABLE, $data );

            if ( $inserted ) {
                $this->set_id( $db->get_insert_id() );
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
    public function delete() : bool {
        $db = smliser_db();

        if ( ! $this->id ) {
            return false;
        }

        $deleted = $db->delete( SMLISER_PRICING_TIER_TABLE, [ 'id' => $this->id ] );

        return false !== $deleted;
    }

    /**
     * Get a pricing tier by its ID.
     * 
     * @param int $id The ID of the pricing tier.
     * @return static|null The Pricing Tier object if found, null otherwise.
     */
    public static function get_by_id( $id ) : ?static {
        $db = smliser_db();
        $table  = \SMLISER_PRICING_TIER_TABLE;

        $sql    = "SELECT * FROM {$table} WHERE id = ?";
        $row    = $db->get_row( $sql, [$id] );

        if ( null === $row ) {
            return null;
        }

        $tier = ( new self() )
            ->set_monetization_id( $row['monetization_id'] ?? 0 )
            ->set_id( $row['id'] ?? 0 )
            ->set_name( $row['name'] ?? '' )
            ->set_product_id( $row['product_id'] ?? 0 )
            ->set_provider_id( $row['provider_id'] ?? '' )
            ->set_billing_cycle( $row['billing_cycle'] ?? '' )
            ->set_max_sites( $row['max_sites'] ?? 0 )
            ->set_features( Format::decode( $row['features'] ?? '' ) );

        return $tier;
    }

    /**
     * Get all pricing tiers by monetization ID.
     *
     * @param int $monetization_id The monetization ID.
     * @return static[] Array of Pricing Tier objects.
     */
    public static function get_by_monetization_id( $monetization_id ) : array {
        $db     = smliser_db();
        $table  = \SMLISER_PRICING_TIER_TABLE;

        $sql    = "SELECT * FROM {$table} WHERE monetization_id = ?";
        $rows   = $db->get_results( $sql, [$monetization_id] );

        if ( empty( $rows ) ) {
            return [];
        }

        $tiers = [];
        foreach ( $rows as $row ) {
            $tier = ( new static() )
                ->set_id( $row['id'] ?? 0 )
                ->set_monetization_id( $row['monetization_id'] ?? 0 )
                ->set_name( $row['name'] ?? '' )
                ->set_product_id( $row['product_id'] ?? 0 )
                ->set_provider_id( $row['provider_id'] ?? '' )
                ->set_billing_cycle( $row['billing_cycle'] ?? '' )
                ->set_max_sites( $row['max_sites'] ?? 0 )
                ->set_features( Format::decode( $row['features'] ?? '' ) );

            $tiers[] = $tier;
        }

        return $tiers;
    }

    /**
     * Get a pricing tier by product ID and provider ID.
     * 
     * @param int|string $product_id    The product ID.
     * @param string $provider_id       The provider ID.
     * @return static|null The Pricing Tier object if found, null otherwise.
     */
    public static function get_by_product_and_provider( int|string $product_id, string $provider_id ) : ?static {
        $db     = smliser_db();
        $table  = \SMLISER_PRICING_TIER_TABLE;

        $sql    = "SELECT * FROM {$table} WHERE product_id = ? AND provider_id = ? LIMIT 1";
        $row    = $db->get_row( $sql, [$product_id, $provider_id] );

        if ( null === $row ) {
            return null;
        }

        $tier = ( new static() )
            ->set_id( $row['id'] ?? 0 )
            ->set_monetization_id( $row['monetization_id'] ?? 0 )
            ->set_name( $row['name'] ?? '' )
            ->set_product_id( $row['product_id'] ?? 0 )
            ->set_provider_id( $row['provider_id'] ?? '' )
            ->set_billing_cycle( $row['billing_cycle'] ?? '' )
            ->set_max_sites( $row['max_sites'] ?? 0 )
            ->set_features( Format::decode( $row['features'] ?? '' ) );

        return $tier;
    }

    /*
    |---------------------
    | UTILITY METHODS
    |---------------------
    */

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

        $provider       = smliser_monetization_registry()->get_provider( $this->get_provider_id() );
        $product_data   = $provider ? $provider->get_product( $this->get_product_id() ) : [];
        $valid_product  = MonetizationRegistry::validate_product_data( $product_data );

        if ( ! is_smliser_error( $valid_product ) ) {
            $data['product'] = $valid_product;
        } else {
            $data['product_error'] = $valid_product->get_error_message();
        }

        return $data;
    }

    /**
     * Tells whether a tier exists.
     * 
     * @return bool
     */
    public function exists() : bool {
        return (bool) $this->get_id();
    }

}
