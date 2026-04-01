<?php
/**
 * Cache Adapter Request Controller class file.
 * 
 * @author Callistus Nwachukwu
 * @since 0.2.0
 */
declare( strict_types = 1 );
namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\Exceptions\CacheTestException;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Cache request controller class handles all HTTP requests for cache
 * configuration and management in the admin UI.
 */
class CacheRequestController {
    use SanitizeAwareTrait, SecurityAwareTrait;

    /*
    |--------------------------------------------------------------------------
    | SETTINGS
    |--------------------------------------------------------------------------
    */

    /**
     * Handle a request to save settings for a specific cache adapter.
     *
     * Reads all fields defined in the adapter's settings schema from the
     * request, validates required fields, skips masked password placeholders
     * so saved credentials are never overwritten with '********', then
     * persists the full settings batch via CacheAdapterCollection.
     *
     * Optionally sets the adapter as the system default if the
     * 'set_as_default' flag is present in the request.
     *
     * @param Request $request
     * @return Response
     */
    public static function save_adapter_settings( Request $request ): Response {
        try {
            static::is_system_admin();

            $adapter_id         = static::sanitize_key( $request->get( 'adapter_id' ) );
            $default_adapter_id = CacheAdapterCollection::get_default_adapter_id();
            $is_default         = $adapter_id === $default_adapter_id;

            [$adapter, $saved_settings] = self::validate_settings_fields( $request );

            $ttl = (int) max( 0, $request->get( 'default_cache_ttl', 0 ) );
            \smliser_settings()->set( 'default_cache_ttl', $ttl, true );
            CacheAdapterCollection::update_adapter_settings( $adapter_id, $saved_settings );

            $wants_reset = $is_default && $request->isEmpty( 'set_as_default' );

            if ( (bool) $request->get( 'set_as_default', false ) ) {
                CacheAdapterCollection::set_default_adapter( $adapter_id );
            } elseif ( $wants_reset ) {
                CacheAdapterCollection::set_default_adapter( 'runtime' );
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message'    => sprintf( '%s settings saved successfully.', $adapter->get_name() ),
                    'is_default' => CacheAdapterCollection::get_default_adapter_id() === $adapter_id,
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

/**
     * Handle a request to test settings for a specific cache adapter.
     *
     * Validates the submitted fields through the same pipeline as
     * save_adapter_settings(), but instead of persisting anything it
     * passes the validated settings to the adapter's test() method.
     *
     * test() is responsible for attempting a live round-trip (connect,
     * write, read, delete) without touching the persisted configuration,
     * so this endpoint is safe to call before the user saves.
     *
     * @param Request $request
     * @return Response
     */
    public static function test_cache_adapter_settings( Request $request ): Response {
        try {
            static::is_system_admin();

            [$cloned, $valid_settings] = self::validate_settings_fields( $request );

            $passed = $cloned->test( $valid_settings );

            if ( ! $passed ) {
                throw new RequestException(
                    'test_failed',
                    sprintf(
                        'Could not establish a connection to %s. Please check your settings and try again.',
                        $cloned->get_name()
                    )
                );
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message'    => sprintf( '%s connection test passed successfully.', $cloned->get_name() ),
                    'is_default' => CacheAdapterCollection::get_default_adapter_id() === $cloned->get_id(),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( CacheTestException $e ) {
            return ( new Response( 200, [], [
                'success' => false,
                'data'    => [
                    'message' => $e->getMessage(),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATS DASHBOARD
    |--------------------------------------------------------------------------
    */

    /**
     * Return live cache statistics as a JSON payload.
     *
     * Called by the JS refresh cycle after management actions so the stat
     * cards update in-place without a full page reload. Returns the raw
     * stats data — the caller's JS layer is responsible for re-rendering
     * the cards from this payload.
     *
     * @param Request $request
     * @return Response
     */
    public static function get_cache_stats( Request $request ): Response {
        try {
            static::is_system_admin();

            $cache = \smliser_cache();
            $stats = $cache->get_stats();

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'adapter_id'   => $cache->get_id(),
                    'adapter_name' => $cache->get_name(),
                    'stats'        => $stats->to_array(),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE MANAGEMENT ACTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Clear all entries from the active cache adapter.
     *
     * @param Request $request
     * @return Response
     */
    public static function clear_all_cache( Request $request ): Response {
        try {
            static::is_system_admin();

            $cache   = \smliser_cache();
            $cleared = $cache->clear();

            if ( ! $cleared ) {
                throw new RequestException(
                    'action_failed',
                    sprintf(
                        'Failed to clear %s cache. The adapter may not support a full flush.',
                        $cache->get_name()
                    )
                );
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message' => sprintf( '%s cache cleared successfully.', $cache->get_name() ),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Delete all cache keys that begin with the supplied prefix.
     *
     * Delegates to Cache::delete_by_prefix() which proxies to the active
     * adapter. Adapters that cannot enumerate keys should return 0.
     *
     * @param Request $request  Expects: { "value": "prefix_string", "security": "nonce" }
     * @return Response
     */
    public static function delete_cache_by_prefix( Request $request ): Response {
        try {
            static::is_system_admin();

            $prefix = static::sanitize_text( $request->get( 'value', '' ) );

            if ( $prefix === '' ) {
                throw new RequestException( 'required_param', 'A key prefix is required.' );
            }

            $cache   = \smliser_cache();
            $deleted = $cache->delete_by_prefix( $prefix );

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message' => sprintf(
                        'Deleted %d key%s matching prefix "%s".',
                        $deleted,
                        $deleted !== 1 ? 's' : '',
                        $prefix
                    ),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Delete all cache keys matching the supplied regex pattern.
     *
     * The pattern must be a valid PHP regex including delimiters, e.g.
     * /^smliser_user_\d+/. Validated server-side before reaching the adapter.
     *
     * @param Request $request  Expects: { "value": "/pattern/", "security": "nonce" }
     * @return Response
     */
    public static function delete_cache_by_pattern( Request $request ): Response {
        try {
            static::is_system_admin();

            $pattern = static::sanitize_text( $request->get( 'value', '' ) );

            if ( $pattern === '' ) {
                throw new RequestException( 'required_param', 'A regex pattern is required.' );
            }

            if ( @preg_match( $pattern, '' ) === false ) {
                throw new RequestException(
                    'validation_failed',
                    sprintf(
                        '"%s" is not a valid regex pattern. Include delimiters, e.g. /^your_key_\d+/.',
                        $pattern
                    )
                );
            }

            $cache   = \smliser_cache();
            $deleted = $cache->delete_by_pattern( $pattern );

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message' => sprintf(
                        'Deleted %d key%s matching pattern "%s".',
                        $deleted,
                        $deleted !== 1 ? 's' : '',
                        $pattern
                    ),
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Flush expired (TTL-elapsed) entries from the active cache adapter.
     *
     * Meaningful only for adapters that accumulate expired rows without
     * immediate eviction (SQLite, Runtime). Others return 0 without error.
     *
     * @param Request $request
     * @return Response
     */
    public static function flush_expired_cache( Request $request ): Response {
        try {
            static::is_system_admin();

            $cache  = \smliser_cache();

            if ( 'sqlitecache' !== $cache->get_id() ) {
                throw new RequestException(
                    'validation_failed',
                    'Manual cache expiry flush is not supported by this driver.'
                );
            }
            $pruned = $cache->prune_expired();

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message' => $pruned > 0
                        ? sprintf( 'Flushed %d expired entr%s.', $pruned, $pruned !== 1 ? 'ies' : 'y' )
                        : 'No expired entries found.',
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Return the top N APCu cache keys sorted by hit count descending.
     *
     * Only available when the active adapter is APCu. Reads directly from
     * apcu_cache_info() since per-key hit data is not part of CacheStats.
     *
     * Response data shape per key:
     *   { key: string, hits: int, ttl: int|null, size: int }
     *
     * ttl is null when the entry has no expiry (APCu ttl=0), otherwise
     * the remaining seconds until expiry.
     *
     * @param Request $request  Expects query param: limit (int, default 10, max 100).
     * @return Response
     */
    public static function get_top_cache_keys( Request $request ): Response {
        try {
            static::is_system_admin();

            $cache = \smliser_cache();

            if ( $cache->get_id() !== 'apcu' ) {
                throw new RequestException(
                    'not_supported',
                    sprintf(
                        'Key-level browsing is only available for APCu. The active adapter is %s.',
                        $cache->get_name()
                    )
                );
            }

            if ( ! \function_exists( 'apcu_cache_info' ) ) {
                throw new RequestException( 'not_supported', 'The APCu extension is not available.' );
            }

            $limit = min( 100, max( 1, (int) $request->get( 'limit', 10 ) ) );
            $info  = \apcu_cache_info( false );
            $raw   = $info['cache_list'] ?? [];

            \usort( $raw, static fn( $a, $b ) => ( $b['num_hits'] ?? 0 ) <=> ( $a['num_hits'] ?? 0 ) );

            $now  = \time();
            $keys = [];

            foreach ( \array_slice( $raw, 0, $limit ) as $entry ) {
                $creation_time = (int) ( $entry['creation_time'] ?? 0 );
                $ttl_raw       = (int) ( $entry['ttl']           ?? 0 );

                $ttl_remaining = $ttl_raw === 0
                    ? null
                    : max( 0, ( $creation_time + $ttl_raw ) - $now );

                $keys[] = [
                    'key'  => (string) ( $entry['info']    ?? '' ),
                    'hits' => (int)    ( $entry['num_hits'] ?? 0 ),
                    'ttl'  => $ttl_remaining,
                    'size' => (int)    ( $entry['mem_size'] ?? 0 ),
                ];
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [ 'keys' => $keys ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Validate cache settings submitted in a request.
     *
     * Reads all fields defined in the adapter's settings schema, enforces
     * required-field presence, skips masked password placeholders, sanitizes
     * by field type, then clones the adapter and applies the validated
     * settings so adapter-specific rules are checked before anything is
     * written to persistent storage.
     *
     * @param  Request $request
     * @throws RequestException On missing adapter, missing required fields,
     *                          or adapter-level validation failure.
     * @return array{0: CacheAdapterInterface, 1: array<string, mixed>}
     *         Tuple of [cloned adapter with settings applied, validated settings array].
     */
    private static function validate_settings_fields( Request $request ): array {
        $collection = CacheAdapterCollection::instance();
        $adapter_id = static::sanitize_key( $request->get( 'adapter_id' ) );

        if ( ! $adapter_id ) {
            throw new RequestException( 'required_param', 'Adapter ID is required.' );
        }

        $adapter = $collection->get_adapter( $adapter_id );

        if ( $adapter === null ) {
            throw new RequestException( 'validation_failed', 'Invalid cache adapter.' );
        }

        $schema         = $adapter->get_settings_schema();
        $saved_settings = [];
        $missing_fields = [];

        foreach ( $schema as $key => $field ) {
            $raw_value = $request->get( $key, null );

            if ( $raw_value === null ) {
                if ( ! empty( $field['required'] ) ) {
                    $missing_fields[] = $field['label'] ?? $key;
                }
                continue;
            }

            // Password field submitted with the masked placeholder —
            // preserve the previously saved value rather than overwriting
            // with the display mask.
            if ( ( $field['type'] ?? '' ) === 'password' && $raw_value === '********' ) {
                $saved_settings[ $key ] = CacheAdapterCollection::get_option( $adapter_id, $key );
                continue;
            }

            $saved_settings[ $key ] = match ( $field['type'] ?? 'text' ) {
                'password' => $raw_value,
                'number'   => static::sanitize_int( $raw_value ),
                'select'   => static::sanitize_text( $raw_value ),
                default    => static::sanitize_text( $raw_value ),
            };

            if ( ! empty( $field['required'] ) && $saved_settings[ $key ] === '' ) {
                $missing_fields[] = $field['label'] ?? $key;
            }
        }

        if ( ! empty( $missing_fields ) ) {
            throw new RequestException(
                'required_param',
                sprintf( 'The following fields are required: %s.', implode( ', ', $missing_fields ) )
            );
        }

        try {
            $cloned = clone $adapter;
            $cloned->set_settings( $saved_settings );
        } catch ( \InvalidArgumentException | \LogicException $e ) {
            throw new RequestException( 'validation_failed', $e->getMessage() );
        }

        return [ $cloned, $saved_settings ];
    }
}