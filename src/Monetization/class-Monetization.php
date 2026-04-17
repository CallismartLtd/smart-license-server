<?php
/**
 * Monetization Class file
 * 
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization;

use DateTimeImmutable;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Monetization defines how an app in the repository is sold.
 *
 * An app can be monetized with one or more pricing tiers.
 * Each tier specifies its own provider, billing rules, and feature set.
 */
class Monetization {
    use SanitizeAwareTrait, DatePropertyAwareTrait;
    /**
     * The monetization ID.
     * 
     * @var int $id
     */
    protected int $id = 0;

    /**
     * App type (e.g. plugin, theme).
     * 
     * @var string $app_type
     */
    protected string $app_type = '';

    /**
     * The unique ID of the app this monetization belongs to.
     *
     * @var int
     */
    protected int $app_id = 0;

    /**
     * Whether this monetization is enabled.
     * 
     * @var bool $enabled
     */
    protected bool $enabled = false;

    /**
     * Collection of pricing tiers available for this monetization.
     *
     * @var array<int, PricingTier> $tiers Array of PricingTier objects, keyed by their ID.
     */
    protected array $tiers = [];

    /**
     * Date this monetization was created.
     * 
     * @var DateTimeImmutable|null $created_at
     */
    protected ?DateTimeImmutable $created_at = null;

    /**
     * Date this monetization was last updated.
     * 
     * @var DateTimeImmutable|null $updated_at
     */
    protected ?DateTimeImmutable $updated_at = null;

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
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the app type (e.g. plugin, theme).
     * 
     * @return string
     */
    public function get_app_type() : string {
        return $this->app_type;
    }

    /**
     * Get the app ID this monetization belongs to.
     *
     * @return int
     */
    public function get_app_id() : int {
        return $this->app_id;
    }    

    /**
     * Get the value of the enabled property.
     *
     * @return bool
     */
    public function get_enabled() : bool {
        return $this->enabled;
    }

    /**
     * Get all pricing tiers.
     *
     * @return PricingTier[]
     */
    public function get_tiers() : array {
        return $this->tiers;
    }

    /**
     * Get the creation date.
     * 
     * @return DateTimeImmutable|null
     */
    public function get_created_at() : ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Get the last update date.
     * 
     * @return DateTimeImmutable|null
     */
    public function get_updated_at() : ?DateTimeImmutable {
        return $this->updated_at;
    }

    /*
    |-----------
    | SETTERS
    |-----------
    */
    /**
     * Set the monetization ID.
     *
     * @param int $id
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = static::sanitize_int( $id );
        return $this;
    }

    /**
     * Set the ID of the app associated with this monetization.
     * 
     * @param int $app_id
     * @return static
     */
    public function set_app_id( $app_id ) : static {
        $this->app_id = static::sanitize_int( $app_id );
        return $this;
    }

    /**
     * Set the app type (e.g. plugin, theme).
     * 
     * @param string $app_type
     * @return static
     */
    public function set_app_type( $app_type ) : static {
        $this->app_type = static::sanitize_text( $app_type );
        return $this;
    }

    /**
     * Set whether this monetization is enabled.
     *
     * @param bool $enabled
     * @return static
     */
    public function set_enabled( $enabled ) : static {
        $this->enabled = (bool) $enabled;
        return $this;
    }

    /**
     * Assign multiple pricing tiers to this monetization.
     *
     * @param PricingTier[] $tiers
     * @return static
     */
    public function set_tiers( array $tiers ) : static {
        foreach ( $tiers as $tier ) {
            if ( ! ( $tier instanceof PricingTier ) ) {
                throw new \InvalidArgumentException( 'All elements of $tiers must be instances of "\SmartLicenseServer\Monetization\Monetization\PricingTier".' );
            }

            $this->add_tier( $tier );
        }

        return $this;
    }

    /**
     * Set the creation date.
     * 
     * @param string $created_at
     * @return static
     */
    public function set_created_at( $created_at )  : static {
        $this->set_date_prop( $created_at, 'created_at' );
        return $this;
    }
    /**
     * Set the last update date.
     * 
     * @param string $updated_at
     * @return static
     */
    public function set_updated_at( $updated_at ) : static {
        $this->set_date_prop( $updated_at, 'updated_at' );
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
    public function save() : bool {
        $db = smliser_db();

        $now    = new DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $data   = [
            'app_type'  => $this->app_type,
            'app_id'    => $this->app_id,
            'enabled'    => $this->enabled ? 1 : 0,
            'updated_at' => $now->format( 'Y-m-d H:i:s' ),
        ];

        if ( $this->id ) {
            // Update existing record
            $updated = $db->update( SMLISER_MONETIZATION_TABLE, $data, [ 'id' => $this->id ] );

            if ( false === $updated ) {
                return false;
            }

        } else {
            $data['created_at'] = $now->format( 'Y-m-d H:i:s' );
            $inserted           = $db->insert( SMLISER_MONETIZATION_TABLE, $data );

            if ( ! $inserted ) {
                return false;
            }

            $this->set_id( $db->get_insert_id() );
            $this->set_created_at( $now );
        }

        $this->set_updated_at( $now );

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
    public function delete() : bool {
        $db = smliser_db();

        if ( ! $this->id ) {
            return false;
        }

        // Atomic delete to prevent race conditions where tiers are deleted but monetization isn't, or vice versa.
        $monetization_table = SMLISER_MONETIZATION_TABLE;
        $pricing_tier_table = SMLISER_PRICING_TIER_TABLE;
        $sql = "DELETE m, t FROM {$monetization_table} 
            m LEFT JOIN {$pricing_tier_table} t ON m.id = t.monetization_id 
        WHERE m.id = ?";
        $deleted = $db->query( $db->prepare( $sql, [ $this->id ] ) );

        return false !== $deleted;
    }

    /**
     * Delete a specific pricing tier and keep this monetization in sync.
     *
     * @param int $tier_id
     * @return bool True if successfully deleted, false otherwise.
     */
    public function delete_tier( $tier_id ) : bool {
        $tier = $this->get_tier( $tier_id );
        if ( ! $tier ) {
            return false;
        }

        // Delete from DB.
        if ( ! $tier->delete() ) {
            return false;
        }

        // Sync in-memory tiers list.
        $this->remove_tiers( $tier_id );

        return true;
    }


    /**
     * Get a monetization record by ID.
     *
     * @param int $id Monetization ID.
     * @return Monetization|null
     */
    public static function get_by_id( $id ) : ?static {
        $db     = smliser_db();
        $table  = SMLISER_MONETIZATION_TABLE;
        $sql    = "SELECT * FROM {$table} WHERE id = ?";
        $row    = $db->get_row( $sql, [static::sanitize_int( $id )] );
        

        if ( ! $row ) {
            return null;
        }

        $static = new static();
        $static->set_id( $row['id'] ?? 0 )
            ->set_app_id( $row['app_id'] ?? '' )
            ->set_app_type( $row['app_type'] ?? '' )
            ->set_enabled( (bool) $row['enabled'] ?? false )
            ->set_created_at( $row['created_at'] ?? '' )
            ->set_updated_at( $row['updated_at'] ?? '' );

        // hydrate tiers
        $static->set_tiers( PricingTier::get_by_monetization_id( $row['id'] ?? 0 ) );

        return $static;
    }

    /**
     * Get monetization record by App type + app ID.
     *
     * @param string $app_type
     * @param int|string $app_id
     * @return Monetization|null
     */
    public static function get_by_app( $app_type, $app_id ) : ?static {
        $db     = smliser_db();
        $table  = SMLISER_MONETIZATION_TABLE;

        $sql    = "SELECT * FROM {$table} WHERE app_type = ? AND app_id = ?";
        $row    = $db->get_row( $sql, [$app_type, $app_id]);

        if ( ! $row ) {
            return null;
        }

        $static = new static();
        $static->set_id( $row['id'] ?? 0 )
            ->set_app_id( $row['app_id'] ?? 0 )
            ->set_app_type( $row['app_type'] ?? '' )
            ->set_enabled( (bool) $row['enabled'] ?? false )
            ->set_created_at( $row['created_at'] ?? '' )
            ->set_updated_at( $row['updated_at'] ?? '' );

        $static->set_tiers( PricingTier::get_by_monetization_id( $row['id'] ?? 0 ) );

        return $static;
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
    public function has_tiers() : bool {
        return ! empty( $this->tiers );
    }

    /**
     * Check if this monetization is enabled.
     *
     * @return bool
     */
    public function is_enabled() : bool {
        return $this->enabled;
    }

    /**
     * Check if this monetization is active (enabled and has tiers).
     *
     * @return bool
     */
    public function is_active() : bool {
        return $this->is_enabled() && $this->has_tiers();
    }

    /**
     * Check if this monetization exists in the database.
     * 
     * @return bool
     */
    public function exists() : bool {
        return $this->id > 0;
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
    public function to_array() : array{
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
     * @return HostedAppsInterface|null
     */
    public function get_app() : ?HostedAppsInterface {
        if ( empty( $this->app_type ) || empty( $this->app_id ) ) {
            return null;
        }

        return HostedApplicationService::get_app_by_id( $this->app_type, $this->app_id );
    }

    /**
     * Add a single pricing tier.
     *
     * @param PricingTier $tier
     * @return static
     */
    public function add_tier( PricingTier $tier ) : static {
        $t_id   = $tier->get_id();

        $this->tiers[$t_id] = $tier;
        return $this;
    }

    /**
     * Remove the specified pricing tiers from the tiers list.
     * 
     * @param int[] $tier_ids Array of tier IDs to remove.
     * @return static
     */
    public function remove_tiers( int ...$tier_ids ) : static {
        foreach ( $tier_ids as $t_id ) {
            $t_id   = static::sanitize_int( $t_id );
            unset( $this->tiers[$t_id] );
        }

        return $this;
    }

    /**
     * Find a pricing tier by its ID.
     *
     * @param int $tier_id
     * @return PricingTier|null
     */
    public function get_tier( $tier_id ) : ?PricingTier {
        $tier_id = static::sanitize_int( $tier_id );
        return $this->tiers[$tier_id] ?? null;
    }
}
