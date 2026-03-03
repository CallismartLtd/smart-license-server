<?php

declare( strict_types = 1 );

namespace SmartLicenseServer\Core;

use ArrayIterator;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Core Data Transfer Object (Dynamic DTO).
 *
 * A flexible, extendable base DTO that supports dynamic properties,
 * array access, iteration, and JSON serialization.
 *
 * Subclasses can override allowed_keys() to restrict which properties
 * may be set, or override cast() to apply type coercion on assignment.
 *
 * Example — unrestricted usage:
 *
 *   $dto = new DTO(['name' => 'Alice', 'age' => 30]);
 *   $dto->email = 'alice@example.com';
 *   echo $dto->get('name');          // Alice
 *   echo $dto['age'];                // 30
 *   echo $dto->has('missing');       // false
 *
 * Example — typed subclass:
 *
 *   class UserDTO extends DTO {
 *       protected function allowed_keys(): array {
 *           return ['name', 'email', 'role'];
 *       }
 *       protected function cast( string $key, mixed $value ): mixed {
 *           return match( $key ) {
 *               'role'  => strtolower( (string) $value ),
 *               default => $value,
 *           };
 *       }
 *   }
 *
 * @package SmartLicenseServer
 * @since   1.0.0
 */
class DTO implements \IteratorAggregate, \Countable, \ArrayAccess, \JsonSerializable {

    /**
     * Internal storage for DTO properties.
     *
     * Kept protected so subclasses can read it directly if needed,
     * but all writes should go through assign() to honour allowed_keys()
     * and cast() hooks.
     *
     * @var array<string, mixed>
     */
    protected array $props = [];

    /*----------------------------------------------------------
     * CONSTRUCTOR
     *---------------------------------------------------------*/

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Optional initial data.
     */
    public function __construct( array $data = [] ) {
        foreach ( $data as $key => $value ) {
            $this->assign( $key, $value );
        }
    }

    /*----------------------------------------------------------
     * EXTENSION HOOKS
     *
     * Override these in subclasses to add constraints or coercion
     * without rewriting the core get/set/has logic.
     *---------------------------------------------------------*/

    /**
     * Return the list of keys this DTO allows.
     *
     * When non-empty, any attempt to set an unlisted key will throw.
     * Return an empty array (default) to allow any key.
     *
     * @return string[]
     */
    protected function allowed_keys(): array {
        return [];
    }

    /**
     * Cast or transform a value before it is stored.
     *
     * Override in subclasses to enforce types, trim strings, etc.
     * The default implementation returns the value unchanged.
     *
     * @param string $key   The property name being set.
     * @param mixed  $value The raw value.
     * @return mixed The (optionally transformed) value to store.
     */
    protected function cast( string $key, mixed $value ): mixed {
        return $value;
    }

    /*----------------------------------------------------------
     * CORE INTERNAL WRITE
     *---------------------------------------------------------*/

    /**
     * Validate and store a key/value pair.
     *
     * All writes — magic setter, offsetSet, fill(), merge() — funnel
     * through here so that allowed_keys() and cast() are always respected.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     * @throws InvalidArgumentException If the key is not allowed.
     */
    protected function assign( string $key, mixed $value ): void {
        $allowed = $this->allowed_keys();

        if ( ! empty( $allowed ) && ! in_array( $key, $allowed, true ) ) {
            throw new InvalidArgumentException(
                sprintf( '"%s" is not an allowed key for %s.', $key, static::class )
            );
        }

        $this->props[ $key ] = $this->cast( $key, $value );
    }

    /*----------------------------------------------------------
     * EXPLICIT API
     *---------------------------------------------------------*/

    /**
     * Get a property value.
     *
     * @param string $key     Property name.
     * @param mixed  $default Fallback if the key does not exist.
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->props )
            ? $this->props[ $key ]
            : $default;
    }

    /**
     * Set a property value.
     *
     * @param string $key
     * @param mixed  $value
     * @return static Fluent — returns $this for chaining.
     */
    public function set( string $key, mixed $value ): static {
        $this->assign( $key, $value );
        return $this;
    }

    /**
     * Check whether a property exists (including null values).
     *
     * Uses array_key_exists rather than isset so that keys explicitly
     * set to null are still considered present.
     *
     * @param string $key
     * @return bool
     */
    public function has( string $key ): bool {
        return array_key_exists( $key, $this->props );
    }

    /**
     * Remove a property.
     *
     * @param string $key
     * @return static Fluent.
     */
    public function remove( string $key ): static {
        unset( $this->props[ $key ] );
        return $this;
    }

    /**
     * Remove all properties.
     *
     * @return static Fluent.
     */
    public function clear(): static {
        $this->props = [];
        return $this;
    }

    /**
     * Fill the DTO with the given data, replacing all existing properties.
     *
     * @param array<string, mixed> $data
     * @return static Fluent.
     */
    public function fill( array $data ): static {
        $this->props = [];
        foreach ( $data as $key => $value ) {
            $this->assign( $key, $value );
        }
        return $this;
    }

    /**
     * Merge additional data into the DTO, preserving existing properties
     * unless overridden by the incoming data.
     *
     * @param array<string, mixed> $data
     * @return static Fluent.
     */
    public function merge( array $data ): static {
        foreach ( $data as $key => $value ) {
            $this->assign( $key, $value );
        }
        return $this;
    }

    /**
     * Return only the given keys as a plain array.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function only( array $keys ): array {
        return array_intersect_key( $this->props, array_flip( $keys ) );
    }

    /**
     * Return all properties except the given keys as a plain array.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function except( array $keys ): array {
        return array_diff_key( $this->props, array_flip( $keys ) );
    }

    /**
     * Return all property keys.
     *
     * @return string[]
     */
    public function keys(): array {
        return array_keys( $this->props );
    }

    /**
     * Return all property values.
     *
     * @return mixed[]
     */
    public function values(): array {
        return array_values( $this->props );
    }

    /**
     * Return all properties as a plain associative array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return $this->props;
    }

    /**
     * Return all properties as a JSON string.
     *
     * @param int $flags json_encode() flags. Default 0.
     * @return string
     */
    public function to_json( int $flags = 0 ): string {
        return json_encode( $this->props, $flags );
    }

    /**
     * Populate the DTO from a JSON string.
     *
     * @param string $json Valid JSON object string.
     * @return static Fluent.
     * @throws InvalidArgumentException On invalid JSON or non-object JSON.
     */
    public function from_json( string $json ): static {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new InvalidArgumentException(
                'Invalid JSON: ' . json_last_error_msg()
            );
        }

        if ( ! is_array( $data ) ) {
            throw new InvalidArgumentException(
                'JSON must decode to an object/array, got a scalar.'
            );
        }

        return $this->merge( $data );
    }

    /**
     * Check whether the DTO has no properties set.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty( $this->props );
    }

    /*----------------------------------------------------------
     * MAGIC PROPERTY ACCESS
     *
     * Delegates to the explicit API so allowed_keys() and cast()
     * are always honoured, even through magic access.
     *---------------------------------------------------------*/

    /** @param string $name */
    public function __get( string $name ): mixed {
        return $this->get( $name );
    }

    /** @param string $name @param mixed $value */
    public function __set( string $name, mixed $value ): void {
        $this->assign( $name, $value );
    }

    /** @param string $name */
    public function __isset( string $name ): bool {
        return $this->has( $name );
    }

    /** @param string $name */
    public function __unset( string $name ): void {
        $this->remove( $name );
    }

    /*----------------------------------------------------------
     * ARRAY ACCESS
     *---------------------------------------------------------*/

    /** @param mixed $offset */
    public function offsetExists( mixed $offset ): bool {
        return $this->has( (string) $offset );
    }

    /** @param mixed $offset */
    public function offsetGet( mixed $offset ): mixed {
        return $this->get( (string) $offset );
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws InvalidArgumentException If offset is not a string.
     */
    public function offsetSet( mixed $offset, mixed $value ): void {
        if ( ! is_string( $offset ) ) {
            throw new InvalidArgumentException( 'DTO keys must be strings.' );
        }
        $this->assign( $offset, $value );
    }

    /** @param mixed $offset */
    public function offsetUnset( mixed $offset ): void {
        $this->remove( (string) $offset );
    }

    /*----------------------------------------------------------
     * COUNTABLE / ITERATOR / JSON
     *---------------------------------------------------------*/

    public function count(): int {
        return count( $this->props );
    }

    public function getIterator(): \Traversable {
        return new ArrayIterator( $this->props );
    }

    public function jsonSerialize(): mixed {
        return $this->props;
    }

    /*----------------------------------------------------------
     * DEBUG
     *---------------------------------------------------------*/

    /**
     * Dump the current DTO state for inspection.
     *
     * @return array<string, mixed>
     */
    public function dump(): array {
        return [
            'class'         => static::class,
            'count'         => $this->count(),
            'allowed_keys'  => $this->allowed_keys(),
            'props'         => $this->props,
        ];
    }
}