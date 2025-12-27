<?php
/**
 * Monetization Class file
 * 
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Monetization defines how an app in the repository is sold.
 *
 * An app can be monetized with one or more pricing tiers.
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
     * App type (e.g. plugin, theme).
     * 
     * @var string $app_type
     */
    protected $app_type;

    /**
     * The unique ID of the app this monetization belongs to.
     *
     * @var int
     */
    protected $app_id;

    /**
     * Whether this monetization is enabled.
     * 
     * @var bool $enabled
     */
    protected $enabled = false;

    /**
     * Collection of pricing tiers available for this monetization.
     *
     * @var PricingTier[]
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
     * Get the app type (e.g. plugin, theme).
     * 
     * @return string
     */
    public function get_app_type() {
        return $this->app_type;
    }

    /**
     * Get the app ID this monetization belongs to.
     *
     * @return int
     */
    public function get_app_id() {
        return $this->app_id;
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
     * @return PricingTier[]
     */
    public function get_tiers() {
        return $this->tiers;
    }

    /**
     * Find a pricing tier by its ID.
     *
     * @param int $tier_id
     * @return PricingTier|null
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
     * Set the ID of the app associated with this monetization.
     * 
     * @param int $app_id
     * @return self
     */
    public function set_app_id( $app_id ) {
        $this->app_id = absint( $app_id );
        return $this;
    }

    /**
     * Set the app type (e.g. plugin, theme).
     * 
     * @param string $app_type
     * @return self
     */
    public function set_app_type( $app_type ) {
        $this->app_type = sanitize_text_field( unslash( $app_type ) );
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
     * @param PricingTier[] $tiers
     * @return self
     */
    public function set_tiers( array $tiers ) {
        foreach ( $tiers as $tier ) {
            if ( ! ( $tier instanceof PricingTier ) ) {
                throw new \InvalidArgumentException( 'All elements of $tiers must be instances of "\SmartLicenseServer\Monetization\Monetization\PricingTier".' );
            }
        }

        $this->tiers = $tiers;
        return $this;
    }

    /**
     * Add a single pricing tier.
     *
     * @param PricingTier $tier
     * @return self
     */
    public function add_tier( PricingTier $tier ) {
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
        $this->created_at = sanitize_text_field( unslash( $created_at ) );
        return $this;
    }
    /**
     * Set the last update date.
     * 
     * @param string $updated_at
     * @return self
     */
    public function set_updated_at( $updated_at ) {
        $this->updated_at = sanitize_text_field( unslash( $updated_at ) );
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
        $db = smliser_dbclass();

        $data = [
            'app_type'  => $this->app_type,
            'app_id'    => $this->app_id,
            'enabled'    => $this->enabled ? 1 : 0,
            'updated_at' => \gmdate( 'Y-m-d H:i:s' ),
        ];

        if ( $this->id ) {
            // Update existing record
            $updated = $db->update( SMLISER_MONETIZATION_TABLE, $data, [ 'id' => $this->id ] );

            if ( false === $updated ) {
                return false;
            }
        } else {
            $data['created_at'] = \gmdate( 'Y-m-d H:i:s' );
            $inserted           = $db->insert( SMLISER_MONETIZATION_TABLE, $data );

            if ( ! $inserted ) {
                return false;
            }

            $this->set_id( $db->get_insert_id() );
        }

        // Save tiers
        foreach ( $this->tiers as $tier ) {
            if ( $tier instanceof PricingTier ) {
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
        $db = smliser_dbclass();

        if ( ! $this->id ) {
            return false;
        }

        $db->delete( SMLISER_PRICING_TIER_TABLE, [ 'monetization_id' => $this->id ] );
        $deleted = $db->delete( SMLISER_MONETIZATION_TABLE, [ 'id' => $this->id ] );

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
        $db     = smliser_dbclass();
        $table  = SMLISER_MONETIZATION_TABLE;
        $sql    = "SELECT * FROM {$table} WHERE id = ?";
        $row    = $db->get_row( $sql, [absint( $id )] );
        

        if ( ! $row ) {
            return null;
        }

        $mon = new self();
        $mon->set_id( $row['id'] ?? 0 )
            ->set_app_id( $row['app_id'] ?? '' )
            ->set_app_type( $row['app_type'] ?? '' )
            ->set_enabled( (bool) $row['enabled'] ?? false )
            ->set_created_at( $row['created_at'] ?? '' )
            ->set_updated_at( $row['updated_at'] ?? '' );

        // hydrate tiers
        $mon->set_tiers( PricingTier::get_by_monetization_id( $row['id'] ?? 0 ) );

        return $mon;
    }

    /**
     * Get monetization record by App type + app ID.
     *
     * @param string $app_type
     * @param int|string $app_id
     * @return Monetization|null
     */
    public static function get_by_app( $app_type, $app_id ) {
        $db     = smliser_dbclass();
        $table  = SMLISER_MONETIZATION_TABLE;

        $sql    = "SELECT * FROM {$table} WHERE app_type = ? AND app_id = ?";
        $row    = $db->get_row( $sql, [$app_type, $app_id]);

        if ( ! $row ) {
            return null;
        }

        $mon = new self();
        $mon->set_id( $row['id'] ?? 0 )
            ->set_app_id( $row['app_id'] ?? 0 )
            ->set_app_type( $row['app_type'] ?? '' )
            ->set_enabled( (bool) $row['enabled'] ?? false )
            ->set_created_at( $row['created_at'] ?? '' )
            ->set_updated_at( $row['updated_at'] ?? '' );

        $mon->set_tiers( PricingTier::get_by_monetization_id( $row['id'] ?? 0 ) );

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
            'app_type'  => $this->app_type,
            'app_id'    => $this->app_id,
            'enabled'    => $this->enabled,
            'tiers'      => array_map( function( $tier ) {
                return $tier->to_array();
            }, $this->tiers ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the APP this monetization belongs to.
     * 
     * @return \SmartLicenseServer\HostedApps\AbstractHostedApp|null
     */
    public function get_app() {
        if ( empty( $this->app_type ) || empty( $this->app_id ) ) {
            return null;
        }

        return HostedApplicationService::get_app_by_id( $this->app_type, $this->app_id );
    }
}
