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
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Cache request controller class handles all HTTP requests for cache
 * configuration in the admin UI.
 */
class CacheRequestController {
    use SanitizeAwareTrait, SecurityAwareTrait;

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

            $ttl    = (int) max( 0, $request->get( 'default_cache_ttl', 0 ) );
            \smliser_settings_adapter()->set( 'default_cache_ttl', $ttl, true );
            CacheAdapterCollection::update_adapter_settings( $adapter_id, $saved_settings );

            $wants_reset = $is_default && $request->isEmpty( 'set_as_default' );

            // Optionally promote this adapter to the system default.
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
     * Returns a 200 JSON response on success and a structured error
     * response on validation failure or a failed connection test.
     *
     * @param Request $request
     * @return Response
     */
    public static function test_cache_adapter_settings( Request $request ): Response {
        try {
            static::is_system_admin();

            // validate_settings_fields() handles schema validation, required-field
            // checks, password masking, and clones + applies settings to the adapter.
            // $cloned is the adapter with the submitted settings pre-applied;
            // $valid_settings is the sanitized settings array ready to pass to test().
            [$cloned, $valid_settings] = self::validate_settings_fields( $request );

            // Delegate the live connection test to the adapter.
            // Each adapter's test() performs a write → read → delete round-trip
            // using $valid_settings in isolation — nothing is persisted.
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

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Helper method to validate the cache settings submitted in a request.
     *
     * Reads all fields defined in the adapter's settings schema, enforces
     * required-field presence, skips masked password placeholders, sanitizes
     * by field type, then clones the adapter and applies the validated
     * settings so adapter-specific rules are checked before anything is
     * written to storage.
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
            throw new RequestException(
                'required_param',
                'Adapter ID is required.'
            );
        }

        $adapter = $collection->get_adapter( $adapter_id );

        if ( $adapter === null ) {
            throw new RequestException(
                'validation_failed',
                'Invalid cache adapter.'
            );
        }

        $schema         = $adapter->get_settings_schema();
        $saved_settings = [];
        $missing_fields = [];

        foreach ( $schema as $key => $field ) {
            $raw_value = $request->get( $key, null );

            // Field was not submitted at all.
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

            // Sanitize by field type.
            $saved_settings[ $key ] = match ( $field['type'] ?? 'text' ) {
                'password' => $raw_value,                            // Passwords are stored as-is.
                'number'   => static::sanitize_int( $raw_value ),
                'select'   => static::sanitize_text( $raw_value ),
                default    => static::sanitize_text( $raw_value ),
            };

            // Validate required fields are non-empty after sanitization.
            if ( ! empty( $field['required'] ) && $saved_settings[ $key ] === '' ) {
                $missing_fields[] = $field['label'] ?? $key;
            }
        }

        if ( ! empty( $missing_fields ) ) {
            throw new RequestException(
                'required_param',
                sprintf(
                    'The following fields are required: %s.',
                    implode( ', ', $missing_fields )
                )
            );
        }

        // Clone the adapter and apply settings so adapter-specific validation
        // rules run (e.g. path constraints in SQLiteCacheAdapter::set_settings())
        // before anything is written to persistent storage.
        try {
            $cloned = clone $adapter;
            $cloned->set_settings( $saved_settings );
        } catch ( \InvalidArgumentException | \LogicException $e ) {
            throw new RequestException(
                'validation_failed',
                $e->getMessage()
            );
        }

        return [ $cloned, $saved_settings ];
    }
}