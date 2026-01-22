<?php
/**
 * Collection utility class.
 *
 * Provides a fluent collection API inspired by modern
 * collection helpers, tailored for use within the Smart License Server.
 *
 * @package SmartLicenseServer\Core
 */

namespace SmartLicenseServer\Core;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class Collection
 *
 * A lightweight collection wrapper that provides chainable helpers for
 * filtering, mapping, grouping, sorting, and aggregating arrays of data.
 *
 * @package SmartLicenseServer\Core
 */
class Collection implements IteratorAggregate, Countable, ArrayAccess {

	/**
	 * Collection items.
	 *
	 * @var array
	 */
	protected array $items = [];

	/**
	 * Collection constructor.
	 *
	 * @param array $items Initial items.
	 */
	public function __construct( array $items = [] ) {
		$this->items = $items;
	}

	/**
	 * Create a new collection instance.
	 *
	 * @param array $items Items to wrap.
	 * @return self
	 */
	public static function make( array $items = [] ): self {
		return new self( $items );
	}

	/**
	 * Filter items using a callback.
	 *
	 * @param callable $callback Filter callback.
	 * @return self
	 */
	public function filter( callable $callback ): self {
		return new self(
			array_filter( $this->items, $callback, ARRAY_FILTER_USE_BOTH )
		);
	}

	/**
	 * Map items into a new structure.
	 *
	 * @param callable $callback Mapping callback.
	 * @return self
	 */
	public function map( callable $callback ): self {
		return new self(
			array_map( $callback, $this->items, array_keys( $this->items ) )
		);
	}

	/**
	 * Pluck a single key from each item.
	 *
	 * @param string $key Item key or property name.
	 * @return self
	 */
	public function pluck( string $key ): self {
		return $this->map(
			fn( $item ) => is_array( $item )
				? ( $item[ $key ] ?? null )
				: ( $item->$key ?? null )
		);
	}

	/**
	 * Get the first item in the collection.
	 *
	 * @param callable|null $callback Optional filter callback.
	 * @param mixed         $default  Default value if not found.
	 * @return mixed
	 */
	public function first( ?callable $callback = null, mixed $default = null ): mixed {
		if ( null === $callback ) {
			$first = reset( $this->items );
			return false === $first ? $default : $first;
		}

		foreach ( $this->items as $key => $item ) {
			if ( $callback( $item, $key ) ) {
				return $item;
			}
		}

		return $default;
	}

	/**
	 * Get the last item in the collection.
	 *
	 * @param callable|null $callback Optional filter callback.
	 * @param mixed         $default  Default value.
	 * @return mixed
	 */
	public function last( ?callable $callback = null, mixed $default = null ): mixed {
		if ( null === $callback ) {
			$value = end( $this->items );
			return false !== $value ? $value : $default;
		}

		return $this->reverse()->first( $callback, $default );
	}

	/**
	 * Determine if all items pass a truth test.
	 *
	 * @param callable $callback Test callback.
	 * @return bool
	 */
	public function every( callable $callback ): bool {
		foreach ( $this->items as $key => $item ) {
			if ( ! $callback( $item, $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine if at least one item passes a truth test.
	 *
	 * @param callable $callback Test callback.
	 * @return bool
	 */
	public function some( callable $callback ): bool {
		foreach ( $this->items as $key => $item ) {
			if ( $callback( $item, $key ) ) {
				return true;
			}
		}

		return false;
	}

    /**
     * Get only the specified keys from the collection.
     *
     * @param array $keys Keys to include.
     * @return self
     */
    public function only( array $keys ): self {
        $keys = array_flip( $keys );

        return new self(
            array_intersect_key( $this->items, $keys )
        );
    }

    /**
     * Get all items except the specified keys.
     *
     * @param array $keys Keys to exclude.
     * @return self
     */
    public function except( array $keys ): self {
        $keys = array_flip( $keys );

        return new self(
            array_diff_key( $this->items, $keys )
        );
    }


	/**
	 * Reduce the collection to a single value.
	 *
	 * @param callable $callback Reduce callback.
	 * @param mixed    $initial  Initial value.
	 * @return mixed
	 */
	public function reduce( callable $callback, mixed $initial = null ): mixed {
		return array_reduce( $this->items, $callback, $initial );
	}

	/**
	 * Determine if the collection contains a value.
	 *
	 * @param mixed $value  Value or callback.
	 * @param bool  $strict Whether to use strict comparison.
	 * @return bool
	 */
	public function contains( mixed $value, bool $strict = false ): bool {
		if ( is_callable( $value ) ) {
			return $this->some( $value );
		}

		return in_array( $value, $this->items, $strict );
	}

	/**
	 * Determine if the collection is empty.
	 *
	 * @return bool
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Determine if the collection is not empty.
	 *
	 * @return bool
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	/**
	 * Get unique items from the collection.
	 *
	 * @param string|null $key Optional key for uniqueness.
	 * @return self
	 */
	public function unique( ?string $key = null ): self {
		if ( null === $key ) {
			return new self(
				array_values( array_unique( $this->items, SORT_REGULAR ) )
			);
		}

		$exists = [];

		return $this->filter(
			function ( $item ) use ( $key, &$exists ) {
				$value = is_array( $item )
					? ( $item[ $key ] ?? null )
					: ( $item->$key ?? null );

				if ( in_array( $value, $exists, true ) ) {
					return false;
				}

				$exists[] = $value;
				return true;
			}
		)->values();
	}

	/**
	 * Sort the collection.
	 *
	 * @param callable|null $callback Optional sorting callback.
	 * @return self
	 */
	public function sort( ?callable $callback = null ): self {
		$items = $this->items;

		if ( null === $callback ) {
			sort( $items );
		} else {
			uasort( $items, $callback );
		}

		return new self( $items );
	}

	/**
	 * Sort the collection by a given key.
	 *
	 * @param string $key        Item key.
	 * @param int    $options    Sort options.
	 * @param bool   $descending Whether to sort descending.
	 * @return self
	 */
	public function sortBy(
		string $key,
		int $options = SORT_REGULAR,
		bool $descending = false
	): self {
		$items = $this->items;

		uasort(
			$items,
			function ( $a, $b ) use ( $key, $options, $descending ) {
				$a_val = is_array( $a ) ? ( $a[ $key ] ?? null ) : ( $a->$key ?? null );
				$b_val = is_array( $b ) ? ( $b[ $key ] ?? null ) : ( $b->$key ?? null );

				$result = match ( $options ) {
					SORT_STRING  => strcmp( (string) $a_val, (string) $b_val ),
					SORT_NUMERIC => $a_val <=> $b_val,
					default      => $a_val <=> $b_val,
				};

				return $descending ? -$result : $result;
			}
		);

		return new self( $items );
	}

	/**
	 * Key the collection by the given key or callback.
	 *
	 * @param string|callable $key
	 * @return self
	 */
	public function keyBy( string|callable $key ): self {
		$items = [];

		foreach ( $this->items as $item_key => $item ) {
			if ( is_callable( $key ) ) {
				$new_key = $key( $item, $item_key );
			} else {
				$new_key = is_array( $item )
					? ( $item[ $key ] ?? null )
					: ( $item->$key ?? null );
			}

			$items[ $new_key ] = $item;
		}

		return new self( $items );
	}


	/**
	 * Reverse the collection order.
	 *
	 * @return self
	 */
	public function reverse(): self {
		return new self( array_reverse( $this->items, true ) );
	}

	/**
	 * Get a slice of the collection.
	 *
	 * @param int      $offset Offset.
	 * @param int|null $length Length.
	 * @return self
	 */
	public function slice( int $offset, ?int $length = null ): self {
		return new self(
			array_slice( $this->items, $offset, $length, true )
		);
	}

	/**
	 * Take a given number of items.
	 *
	 * @param int $limit Number of items.
	 * @return self
	 */
	public function take( int $limit ): self {
		return $limit < 0
			? $this->slice( $limit, abs( $limit ) )
			: $this->slice( 0, $limit );
	}

	/**
	 * Skip a given number of items.
	 *
	 * @param int $count Number to skip.
	 * @return self
	 */
	public function skip( int $count ): self {
		return $this->slice( $count );
	}

	/**
	 * Chunk the collection.
	 *
	 * @param int $size Chunk size.
	 * @return self
	 */
	public function chunk( int $size ): self {
		$chunks = [];

		foreach ( array_chunk( $this->items, $size, true ) as $chunk ) {
			$chunks[] = new self( $chunk );
		}

		return new self( $chunks );
	}

	/**
	 * Group items by a given key.
	 *
	 * @param string $key Grouping key.
	 * @return self
	 */
	public function groupBy( string $key ): self {
		$groups = [];

		foreach ( $this->items as $item ) {
			$group_key = is_array( $item )
				? ( $item[ $key ] ?? null )
				: ( $item->$key ?? null );

			$groups[ $group_key ][] = $item;
		}

		return new self(
			array_map( fn( $group ) => new self( $group ), $groups )
		);
	}

	/**
	 * Merge items into the collection.
	 *
	 * @param array|self $items Items to merge.
	 * @return self
	 */
	public function merge( array|self $items ): self {
		$items = $items instanceof self ? $items->all() : $items;
		return new self( array_merge( $this->items, $items ) );
	}

	/**
	 * Flatten a multi-dimensional collection.
	 *
	 * @param int $depth Flatten depth.
	 * @return self
	 */
	public function flatten( int $depth = PHP_INT_MAX ): self {
		$result = [];

		foreach ( $this->items as $item ) {
			if ( ! is_array( $item ) && ! ( $item instanceof self ) ) {
				$result[] = $item;
				continue;
			}

			if ( 1 === $depth ) {
				$result = array_merge(
					$result,
					$item instanceof self ? $item->all() : $item
				);
				continue;
			}

			$flattened = ( new self(
				$item instanceof self ? $item->all() : $item
			) )->flatten( $depth - 1 )->all();

			$result = array_merge( $result, $flattened );
		}

		return new self( $result );
	}

	/**
	 * Reset keys and return values only.
	 *
	 * @return self
	 */
	public function values(): self {
		return new self( array_values( $this->items ) );
	}

	/**
	 * Get collection keys.
	 *
	 * @return self
	 */
	public function keys(): self {
		return new self( array_keys( $this->items ) );
	}

	/**
	 * Execute a callback for each item.
	 *
	 * @param callable $callback Callback.
	 * @return self
	 */
	public function each( callable $callback ): self {
		foreach ( $this->items as $key => $item ) {
			if ( false === $callback( $item, $key ) ) {
				break;
			}
		}

		return $this;
	}

	/**
	 * Tap into the collection without modifying it.
	 *
	 * @param callable $callback Callback.
	 * @return self
	 */
	public function tap( callable $callback ): self {
		$callback( clone $this );
		return $this;
	}

	/**
	 * Pipe the collection into a callback.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public function pipe( callable $callback ): mixed {
		return $callback( $this );
	}

	/**
	 * Get an item by key.
	 *
	 * @param string|int $key     Item key.
	 * @param mixed      $default Default value.
	 * @return mixed
	 */
	public function get( string|int $key, mixed $default = null ): mixed {
		return $this->items[ $key ] ?? $default;
	}

    /**
     * Find an item by key or callback.
     *
     * @param mixed $key Key or callback.
     * @param mixed $default Default value.
     * @return mixed
     */
    public function find( mixed $key, mixed $default = null ): mixed {
        if ( is_callable( $key ) ) {
            return $this->first( $key, $default );
        }

        return $this->get( $key, $default );
    }


	/**
	 * Determine if a key exists.
	 *
	 * @param string|int $key Key to check.
	 * @return bool
	 */
	public function has( string|int $key ): bool {
		return array_key_exists( $key, $this->items );
	}

	/**
	 * Get the sum of items.
	 *
	 * @param string|null $key Optional key.
	 * @return float|int
	 */
	public function sum( ?string $key = null ): float|int {
		if ( null === $key ) {
			return array_sum( $this->items );
		}

		return $this->pluck( $key )->sum();
	}

	/**
	 * Get the average value.
	 *
	 * @param string|null $key Optional key.
	 * @return float
	 */
	public function avg( ?string $key = null ): float {
		if ( $this->isEmpty() ) {
			return 0.0;
		}

		return $this->sum( $key ) / $this->count();
	}

	/**
	 * Get the minimum value.
	 *
	 * @param string|null $key Optional key.
	 * @return mixed
	 */
	public function min( ?string $key = null ): mixed {
		return null === $key
			? min( $this->items )
			: $this->pluck( $key )->min();
	}

	/**
	 * Get the maximum value.
	 *
	 * @param string|null $key Optional key.
	 * @return mixed
	 */
	public function max( ?string $key = null ): mixed {
		return null === $key
			? max( $this->items )
			: $this->pluck( $key )->max();
	}

	/**
	 * Filter items by comparison.
	 *
	 * @param string     $key      Item key.
	 * @param mixed      $operator Operator or value.
	 * @param mixed|null $value    Comparison value.
	 * @return self
	 */
	public function where( string $key, mixed $operator, mixed $value = null ): self {
		if ( 2 === func_num_args() ) {
			$value    = $operator;
			$operator = '=';
		}

		return $this->filter(
			function ( $item ) use ( $key, $operator, $value ) {
				$item_value = is_array( $item )
					? ( $item[ $key ] ?? null )
					: ( $item->$key ?? null );

				return match ( $operator ) {
					'='   => $item_value == $value,
					'===' => $item_value === $value,
					'!='  => $item_value != $value,
					'!==' => $item_value !== $value,
					'>'   => $item_value > $value,
					'>='  => $item_value >= $value,
					'<'   => $item_value < $value,
					'<='  => $item_value <= $value,
					default => false,
				};
			}
		);
	}

	/**
	 * Reject items that match a condition.
	 *
	 * @param callable $callback Callback.
	 * @return self
	 */
	public function reject( callable $callback ): self {
		return $this->filter(
			fn( $item, $key ) => ! $callback( $item, $key )
		);
	}

	/**
	 * Get random item(s).
	 *
	 * @param int $count Number of items.
	 * @return mixed
	 */
	public function random( int $count = 1 ): mixed {
		$keys = array_rand( $this->items, min( $count, $this->count() ) );

		if ( 1 === $count ) {
			return $this->items[ $keys ];
		}

		return new self(
			array_intersect_key( $this->items, array_flip( (array) $keys ) )
		);
	}

	/**
	 * Return raw array of items.
	 *
	 * @return array
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Convert collection to JSON.
	 *
	 * @param int $options JSON options.
	 * @return string
	 */
	public function toJson( int $options = 0 ): string {
		return (string) json_encode( $this->items, $options );
	}

    /**
     * Convert the collection to an array.
     *
     * @param bool $recursive Whether to recursively convert nested collections.
     * @return array
     */
    public function toArray( bool $recursive = true ): array {
        if ( ! $recursive ) {
            return $this->items;
        }

        return array_map(
            function ( $item ) {
                return $item instanceof self
                    ? $item->toArray( true )
                    : $item;
            },
            $this->items
        );
    }

    /**
     * Convert the collection to string.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->toJson();
    }


	/**
	 * Retrieve an external iterator.
	 *
	 * @return Traversable
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->items );
	}

	/**
	 * Count elements of the collection.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Determine if an offset exists.
	 *
	 * @param mixed $offset Offset.
	 * @return bool
	 */
	public function offsetExists( mixed $offset ): bool {
		return array_key_exists( $offset, $this->items );
	}

	/**
	 * Retrieve an offset value.
	 *
	 * @param mixed $offset Offset.
	 * @return mixed
	 */
	public function offsetGet( mixed $offset ): mixed {
		return $this->items[ $offset ] ?? null;
	}

	/**
	 * Set an offset value.
	 *
	 * @param mixed $offset Offset.
	 * @param mixed $value  Value.
	 * @return void
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		if ( null === $offset ) {
			$this->items[] = $value;
			return;
		}

		$this->items[ $offset ] = $value;
	}

	/**
	 * Unset an offset.
	 *
	 * @param mixed $offset Offset.
	 * @return void
	 */
	public function offsetUnset( mixed $offset ): void {
		unset( $this->items[ $offset ] );
	}
}
