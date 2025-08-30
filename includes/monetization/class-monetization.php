<?php
/**
 * Monetization Class file
 * 
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * Monetization defines how an item in the repository is sold.
 *
 * An item can be monetized with one or more pricing tiers.
 * Each tier specifies its own provider, billing rules, and feature set.
 */
class Monetization {
    /**
     * The monetization ID.
     * 
     * @var int $id
     */
    protected $id;

    /**
     * Item type (e.g. plugin, theme).
     * 
     * @var string $item_type
     */
    protected $item_type;

    /**
     * The unique ID of the repository item this monetization belongs to.
     *
     * @var int
     */
    protected $item_id;

    /**
     * Whether this monetization is enabled.
     * 
     * @var bool $enabled
     */
    protected $enabled = false;

    /**
     * Collection of pricing tiers available for this item.
     *
     * @var Pricing_Tier[]
     */
    protected $tiers = [];

    /**
     * Date this monetization was created.
     * 
     * @var string $created_at
     */
    protected $created_at;

    /**
     * Date this monetization was last updated.
     * 
     * @var string $updated_at
     */
    protected $updated_at;

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
     * Get the monetization ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the item type (e.g. plugin, theme).
     * 
     * @return string
     */
    public function get_item_type() {
        return $this->item_type;
    }

    /**
     * Get the item ID this monetization belongs to.
     *
     * @return int
     */
    public function get_item_id() {
        return $this->item_id;
    }    

    /**
     * Get the value of the enabled property.
     *
     * @return bool
     */
    public function get_enabled() {
        return $this->enabled;
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
     * @param int $tier_id
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

    /**
     * Get the creation date.
     * 
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get the last update date.
     * 
     * @return string
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /*-----------
    | SETTERS
    |-----------
    */
    /**
     * Set the monetization ID.
     *
     * @param int $id
     * @return self
     */
    public function set_id( $id ) {
        $this->id = absint( $id );
        return $this;
    }

    /**
     * Set the item ID this monetization belongs to.
     * 
     * @param int $item_id
     * @return self
     */
    public function set_item_id( $item_id ) {
        $this->item_id = absint( $item_id );
        return $this;
    }

    /**
     * Set the item type (e.g. plugin, theme).
     * 
     * @param string $item_type
     * @return self
     */
    public function set_item_type( $item_type ) {
        $this->item_type = sanitize_text_field( wp_unslash( $item_type ) );
        return $this;
    }

    /**
     * Set whether this monetization is enabled.
     *
     * @param bool $enabled
     * @return self
     */
    public function set_enabled( $enabled ) {
        $this->enabled = (bool) $enabled;
        return $this;
    }

    /**
     * Assign multiple pricing tiers to this monetization.
     *
     * @param Pricing_Tier[] $tiers
     * @return self
     */
    public function set_tiers( array $tiers ) {
        foreach ( $tiers as $tier ) {
            if ( ! ( $tier instanceof Pricing_Tier ) ) {
                throw new \InvalidArgumentException( 'All elements of $tiers must be instances of Pricing_Tier.' );
            }
        }

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
     * Set the creation date.
     * 
     * @param string $created_at
     * @return self
     */
    public function set_created_at( $created_at ) {
        $this->created_at = sanitize_text_field( wp_unslash( $created_at ) );
        return $this;
    }
    /**
     * Set the last update date.
     * 
     * @param string $updated_at
     * @return self
     */
    public function set_updated_at( $updated_at ) {
        $this->updated_at = sanitize_text_field( wp_unslash( $updated_at ) );
        return $this;
    }

    /*
    |---------------------------
    | CRUD METHODS
    |---------------------------
    */

    /**
     * Save or update this monetization record.
     *
     * @return bool True on success, false on failure.
     */
    public function save() {
        global $wpdb;

        $data = [
            'item_type'  => $this->item_type,
            'item_id'    => $this->item_id,
            'enabled'    => $this->enabled ? 1 : 0,
            'updated_at' => current_time( 'mysql' ),
        ];

        if ( $this->id ) {
            // Update existing record
            $updated = $wpdb->update(
                SMLISER_MONETIZATION_TABLE,
                $data,
                [ 'id' => $this->id ],
                [ '%s', '%s', '%d', '%s', '%s' ],
                [ '%d' ]
            );

            if ( false === $updated ) {
                return false;
            }
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $inserted           = $wpdb->insert(
                SMLISER_MONETIZATION_TABLE,
                $data,
                [ '%s', '%s', '%d', '%s', '%s' ]
            );

            if ( ! $inserted ) {
                return false;
            }

            $this->set_id( $wpdb->insert_id );
        }

        // Save tiers
        foreach ( $this->tiers as $tier ) {
            if ( $tier instanceof Pricing_Tier ) {
                $tier->set_monetization_id( $this->id )->save();
            }
        }

        return true;
    }

    /**
     * Delete this monetization record and its pricing tiers.
     *
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        // First delete associated pricing tiers
        $wpdb->delete(
            SMLISER_PRICING_TIER_TABLE,
            [ 'monetization_id' => $this->id ],
            [ '%d' ]
        );

        // Then delete monetization record itself
        $deleted = $wpdb->delete(
            SMLISER_MONETIZATION_TABLE,
            [ 'id' => $this->id ],
            [ '%d' ]
        );

        return false !== $deleted;
    }

    /**
     * Delete a specific pricing tier and keep this monetization in sync.
     *
     * @param int $tier_id
     * @return bool True if successfully deleted, false otherwise.
     */
    public function delete_tier( $tier_id ) {
        // Make sure tier exists in runtime collection.
        $tier = $this->get_tier( $tier_id );
        if ( ! $tier ) {
            return false;
        }

        // Delete from DB.
        if ( ! $tier->delete() ) {
            return false;
        }

        // Sync in-memory tiers list.
        $this->tiers = array_filter( $this->tiers, function( $t ) use ( $tier_id ) {
            return $t->get_id() !== $tier_id;
        });

        return true;
    }


    /**
     * Get a monetization record by ID.
     *
     * @param int $id Monetization ID.
     * @return Monetization|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . SMLISER_MONETIZATION_TABLE . " WHERE id = %d",
                absint( $id )
            )
        );

        if ( ! $row ) {
            return null;
        }

        $mon = new self();
        $mon->set_id( $row->id )
            ->set_item_id( $row->item_id )
            ->set_item_type( $row->item_type )
            ->set_enabled( (bool) $row->enabled )
            ->set_created_at( $row->created_at )
            ->set_updated_at( $row->updated_at );

        // hydrate tiers
        $mon->set_tiers( Pricing_Tier::get_by_monetization_id( $row->id ) );

        return $mon;
    }

    /**
     * Get monetization record by item type + item ID.
     *
     * @param string $item_type
     * @param int|string $item_id
     * @return Monetization|null
     */
    public static function get_by_item( $item_type, $item_id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . SMLISER_MONETIZATION_TABLE . " WHERE item_type = %s AND item_id = %s",
                sanitize_text_field( $item_type ),
                sanitize_text_field( $item_id )
            )
        );

        if ( ! $row ) {
            return null;
        }

        $mon = new self();
        $mon->set_id( $row->id )
            ->set_item_id( $row->item_id )
            ->set_item_type( $row->item_type )
            ->set_enabled( (bool) $row->enabled )
            ->set_created_at( $row->created_at )
            ->set_updated_at( $row->updated_at );

        $mon->set_tiers( Pricing_Tier::get_by_monetization_id( $row->id ) );

        return $mon;
    }

    /*
    |---------------------------
    | CONDITIONAL METHODS
    |---------------------------
    */

    /**
     * Check if this monetization has any pricing tiers.
     *
     * @return bool
     */
    public function has_tiers() {
        return ! empty( $this->tiers );
    }

    /**
     * Check if this monetization is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Check if this monetization is active (enabled and has tiers).
     *
     * @return bool
     */
    public function is_active() {
        return $this->is_enabled() && $this->has_tiers();
    }

    /**
     * Check if this monetization exists in the database.
     * 
     * @return bool
     */
    public function exists() {
        return ! empty( $this->id ) && self::get_by_id( $this->id ) !== null;
    }

    /*
    |---------------------------
    | UTILITY METHODS
    |---------------------------
    */

    /**
     * Convert this monetization to an associative array.
     *
     * @return array
     */
    public function to_array() {
        return [
            'id'         => $this->id,
            'item_type'  => $this->item_type,
            'item_id'    => $this->item_id,
            'enabled'    => $this->enabled,
            'tiers'      => array_map( function( $tier ) {
                return $tier->to_array();
            }, $this->tiers ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the Item object this monetization belongs to.
     * 
     * @return \SmartLicenseServer\HostedApps\Smliser_Hosted_Apps_Interface|null
     */
    public function get_item_object() {
        if ( empty( $this->item_type ) || empty( $this->item_id ) ) {
            return null;
        }

        $class = '\\Smliser_' . ucfirst( sanitize_text_field( wp_unslash( $this->item_type ) ) );

        if ( ! class_exists( $class ) || ! method_exists( $class, 'get_' . $this->item_type ) ) {
            return null;
        }

        $method = 'get_' . $this->item_type;

        return call_user_func( [ $class, $method ], $this->item_id );
    }
}
