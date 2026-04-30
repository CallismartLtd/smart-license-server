<?php
/**
 * Query Criteria Trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Traits
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides fluent methods for building query conditions and managing bindings.
 * 
 * @since 0.2.0
 */
trait QueryCriteriaTrait {
    /**
     * @var array $conditions Array of structured condition groups.
     */
    protected array $conditions = [];

    /**
     * @var array $bindings Positional parameter values.
     */
    protected array $bindings = [];

    /**
     * Add a basic WHERE clause.
     * 
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     * @return static
     */
    public function where( string $column, string $operator, $value, string $boolean = 'AND' ) : static {
        $this->conditions[] = [
            'type'     => 'Basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE clause.
     * 
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @return static
     */
    public function or_where( string $column, string $operator, $value ) : static {
        return $this->where( $column, $operator, $value, 'OR' );
    }

    /**
     * Add a WHERE IS NULL clause.
     * 
     * @param string $column
     * @param string $boolean
     * @param bool   $not
     * @return static
     */
    public function where_null( string $column, string $boolean = 'AND', bool $not = false ) : static {
        $this->conditions[] = [
            'type'    => 'Null',
            'column'  => $column,
            'boolean' => $boolean,
            'not'     => $not
        ];

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL clause.
     * 
     * @param string $column
     * @param string $boolean
     * @return static
     */
    public function where_not_null( string $column, string $boolean = 'AND' ) : static {
        return $this->where_null( $column, $boolean, true );
    }

    /**
     * Get tracked parameters.
     * 
     * @return array
     */
    public function get_bindings() : array {
        return $this->bindings;
    }

    /**
     * Get structured conditions.
     * 
     * @return array
     */
    public function get_conditions() : array {
        return $this->conditions;
    }
}