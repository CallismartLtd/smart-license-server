<?php
/**
 * Cache statistics value object.
 *
 * Returned by {@see CacheAdapterInterface::get_stats()}.
 *
 * Extends the core {@see DTO} to benefit from array access, iteration,
 * JSON serialisation, and the allowed_keys / cast extension hooks,
 * while adding cache-specific derived metrics.
 *
 * @author Callistus Nwachukwu
 * @since 0.2.0
 * @package SmartLicenseServer\Cache
 *
 * @property int   $hits          Total cache hits since the backend started.
 * @property int   $misses        Total cache misses since the backend started.
 * @property int   $entries       Number of keys currently stored.
 * @property int   $memory_used   Bytes currently consumed by cached data.
 * @property int   $memory_total  Total bytes available to the cache backend.
 * @property int   $uptime        Seconds the backend has been running (0 if unavailable).
 * @property array $extra         Adapter-specific extras (e.g. connected_slaves for Redis).
 */

namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Core\DTO;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

final class CacheStats extends DTO {

    /*----------------------------------------------------------
     * KNOWN KEYS
     *---------------------------------------------------------*/

    public const KEY_HITS         = 'hits';
    public const KEY_MISSES       = 'misses';
    public const KEY_ENTRIES      = 'entries';
    public const KEY_MEMORY_USED  = 'memory_used';
    public const KEY_MEMORY_TOTAL = 'memory_total';
    public const KEY_UPTIME       = 'uptime';
    public const KEY_EXTRA        = 'extra';

    /*----------------------------------------------------------
     * CONSTRUCTOR
     *---------------------------------------------------------*/

    /**
     * @param int                  $hits          Total cache hits.
     * @param int                  $misses        Total cache misses.
     * @param int                  $entries       Number of currently stored keys.
     * @param int                  $memory_used   Bytes used by cached data.
     * @param int                  $memory_total  Total bytes available to the backend.
     * @param int                  $uptime        Backend uptime in seconds.
     * @param array<string, mixed> $extra         Adapter-specific extras.
     */
    public function __construct(
        int   $hits         = 0,
        int   $misses       = 0,
        int   $entries      = 0,
        int   $memory_used  = 0,
        int   $memory_total = 0,
        int   $uptime       = 0,
        array $extra        = [],
    ) {
        parent::__construct( [
            self::KEY_HITS         => $hits,
            self::KEY_MISSES       => $misses,
            self::KEY_ENTRIES      => $entries,
            self::KEY_MEMORY_USED  => $memory_used,
            self::KEY_MEMORY_TOTAL => $memory_total,
            self::KEY_UPTIME       => $uptime,
            self::KEY_EXTRA        => $extra,
        ] );
    }

    /*----------------------------------------------------------
     * DTO HOOKS
     *---------------------------------------------------------*/

    /**
     * {@inheritdoc}
     *
     * Restricts the DTO to the seven known cache-stat keys.
     * Any attempt to set an unknown key will throw.
     */
    protected function allowed_keys(): array {
        return [
            self::KEY_HITS,
            self::KEY_MISSES,
            self::KEY_ENTRIES,
            self::KEY_MEMORY_USED,
            self::KEY_MEMORY_TOTAL,
            self::KEY_UPTIME,
            self::KEY_EXTRA,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Coerces all numeric keys to int and the extra key to array,
     * regardless of what the backend hands us.
     *
     * @throws InvalidArgumentException When $extra is not an array.
     */
    protected function cast( string $key, mixed $value ): mixed {
        return match ( $key ) {
            self::KEY_HITS,
            self::KEY_MISSES,
            self::KEY_ENTRIES,
            self::KEY_MEMORY_USED,
            self::KEY_MEMORY_TOTAL,
            self::KEY_UPTIME  => (int) $value,

            self::KEY_EXTRA   => is_array( $value )
                ? $value
                : throw new InvalidArgumentException(
                    '"extra" must be an array, ' . get_debug_type( $value ) . ' given.'
                ),

            default           => $value,
        };
    }

    /*----------------------------------------------------------
     * DERIVED METRICS
     *---------------------------------------------------------*/

    /**
     * Hit rate as a float between 0.0 and 1.0.
     *
     * Returns 0.0 when there have been no requests yet.
     *
     * @return float
     */
    public function hit_rate(): float {
        $total = $this->props[ self::KEY_HITS ] + $this->props[ self::KEY_MISSES ];
        return $total > 0 ? $this->props[ self::KEY_HITS ] / $total : 0.0;
    }

    /**
     * Memory usage as a float between 0.0 and 1.0.
     *
     * Returns 0.0 when total memory is unknown (zero).
     *
     * @return float
     */
    public function memory_usage_ratio(): float {
        return $this->props[ self::KEY_MEMORY_TOTAL ] > 0
            ? $this->props[ self::KEY_MEMORY_USED ] / $this->props[ self::KEY_MEMORY_TOTAL ]
            : 0.0;
    }
}