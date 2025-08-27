<?php
/**
 * Monetization Class file
 * 
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace Smart_License_Server\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * Monetization defines how an item in the repository is sold.
 *
 * An item can be monetized with one or more pricing tiers.
 * Each tier specifies its own provider, billing rules, and feature set.
 */
class Monetization {
    /**
     * The unique ID of the repository item this monetization belongs to.
     *
     * @var int|string
     */
    protected $item_id;

    /**
     * Collection of pricing tiers available for this item.
     *
     * @var Pricing_Tier[]
     */
    protected $tiers = [];

    /**
     * Constructor.
     *
     * @param int|string $item_id The repository item ID.
     */
    public function __construct( $item_id ) {
        $this->item_id = $item_id;
    }

    /**
     * Get the item ID this monetization belongs to.
     *
     * @return int|string
     */
    public function get_item_id() {
        return $this->item_id;
    }

    /**
     * Assign multiple pricing tiers to this monetization.
     *
     * @param Pricing_Tier[] $tiers
     * @return self
     */
    public function set_tiers( array $tiers ) {
        $this->tiers = $tiers;
        return $this;
    }

    /**
     * Add a single pricing tier.
     *
     * @param Pricing_Tier $tier
     * @return self
     */
    public function add_tier( Pricing_Tier $tier ) {
        $this->tiers[] = $tier;
        return $this;
    }

    /**
     * Get all pricing tiers.
     *
     * @return Pricing_Tier[]
     */
    public function get_tiers() {
        return $this->tiers;
    }

    /**
     * Find a pricing tier by its ID.
     *
     * @param string|int $tier_id
     * @return Pricing_Tier|null
     */
    public function get_tier( $tier_id ) {
        foreach ( $this->tiers as $tier ) {
            if ( $tier->get_id() === $tier_id ) {
                return $tier;
            }
        }
        return null;
    }
}
