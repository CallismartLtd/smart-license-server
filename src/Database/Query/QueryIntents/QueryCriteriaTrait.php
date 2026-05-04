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
     * Add a WHERE IN / NOT IN clause.
     * 
     * @param string $column   The target column.
     * @param array  $values   The set of values for comparison.
     * @param string $boolean  Logical connector (AND / OR).
     * @param bool   $not      Whether to negate the condition (NOT IN).
     * @throws \InvalidArgumentException If values array is empty.
     * @return static
     */
    public function where_in( string $column, array $values, string $boolean = 'AND', bool $not = false ) : static {
        if ( empty( $values ) ) {
            throw new \InvalidArgumentException( 'where_in values cannot be empty.' );
        }

        $this->conditions[] = [
            'type'    => 'In',
            'column'  => $column,
            'values'  => $values,
            'boolean' => $boolean,
            'not'     => $not,
        ];

        foreach ( $values as $value ) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add a WHERE NOT IN clause.
     * 
     * @param string $column The target column.
     * @param array  $values The set of values to exclude.
     * @return static
     */
    public function where_not_in( string $column, array $values ) : static {
        return $this->where_in( $column, $values, 'AND', true );
    }

    /**
     * Add a WHERE BETWEEN / NOT BETWEEN clause.
     * 
     * @param string $column
     * @param mixed  $from
     * @param mixed  $to
     * @param string $boolean
     * @param bool   $not
     * @return static
     */
    public function where_between( string $column, $from, $to, string $boolean = 'AND', bool $not = false ) : static {
        $this->conditions[] = [
            'type'    => 'Between',
            'column'  => $column,
            'values'  => [ $from, $to ],
            'boolean' => $boolean,
            'not'     => $not,
        ];

        $this->bindings[] = $from;
        $this->bindings[] = $to;

        return $this;
    }

    /**
     * Add a WHERE NOT BETWEEN clause.
     * 
     * @param string $column
     * @param mixed  $from
     * @param mixed  $to
     * @return static
     */
    public function where_not_between( string $column, $from, $to ) : static {
        return $this->where_between( $column, $from, $to, 'AND', true );
    }

    /**
     * Add a raw WHERE clause segment.
     * 
     * @param string $expression Raw SQL expression (must be safe).
     * @param array  $bindings   Optional bindings for placeholders.
     * @param string $boolean
     * @return static
     */
    public function where_raw( string $expression, array $bindings = [], string $boolean = 'AND' ) : static {
        $this->conditions[] = [
            'type'       => 'Raw',
            'expression' => $expression,
            'boolean'    => $boolean,
        ];

        foreach ( $bindings as $binding ) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add a grouped WHERE clause using a nested condition set.
     * 
     * @param callable $callback Receives a new query instance for grouping.
     * @param string   $boolean  Logical connector (AND / OR).
     * @return static
     */
    public function where_group( callable $callback, string $boolean = 'AND' ) : static {

        // Create a fresh instance to isolate grouped conditions
        /** @disregard */
        $group = $this->new_instance();

        $callback( $group );

        $this->conditions[] = [
            'type'       => 'Group',
            'conditions' => $group->get_conditions(),
            'boolean'    => $boolean,
        ];

        // Merge bindings in order
        foreach ( $group->get_bindings() as $binding ) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add an OR grouped WHERE clause.
     * 
     * @param callable $callback
     * @return static
     */
    public function or_where_group( callable $callback ) : static {
        return $this->where_group( $callback, 'OR' );
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