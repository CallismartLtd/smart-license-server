<?php
/**
 * Default Database Column Value file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Utils;

/**
 * The canonical data transfer object for default value of a database
 * table column.
 */
class DefaultColumnValue {

	/**
	 * The underlying raw default value.
	 *
	 * @var mixed
	 */
	protected mixed $value;

	/**
	 * Whether the value is a raw database expression/function.
	 *
	 * @var bool
	 */
	protected bool $is_expression;

    /**
	 * Hidden constructor to force the use of clean factory methods.
	 */
	private function __construct( mixed $value, bool $is_expression = false ) {
		$this->value         = $value;
		$this->is_expression = $is_expression;
	}

	/**
	 * Universal factory for standard literal values (strings, ints, bools, nulls).
	 *
	 * @param mixed $value
	 * @return static
	 */
	public static function make( mixed $value, bool $is_expression = false ): static {
		if ( $value instanceof self ) {
			return $value;
		}
		return new self( $value, $is_expression );
	}

	/**
	 * Explicit factory for raw SQL expressions/functions.
	 *
	 * @param string $expression E.g., 'CURRENT_TIMESTAMP', 'uuid_generate_v4()'
	 * @return static
	 */
	public static function expression( string $expression ): static {
		return static::make( $expression, true );
	}

	/**
	 * Get the raw wrapped value.
	 *
	 * @return mixed
	 */
	public function value(): mixed {
		return $this->value;
	}

	/**
	 * Check if the default value is a raw system keyword/function.
	 *
	 * @return bool
	 */
	public function is_expression(): bool {
		return $this->is_expression;
	}

    public function __toString() : string {
        return (string) $this->value();
    }
}