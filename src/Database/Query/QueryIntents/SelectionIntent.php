<?php
/**
 * Selection Query Intent class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\QueryIntents
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

use SmartLicenseServer\Database\Query\SQLBuilder;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents an intent to select data from the database.
 * 
 * This class orchestrates the components of a SELECT query, including
 * columns, tables, joins, filtering, grouping, and ordering.
 * 
 * @since 0.2.0
 */
class SelectionIntent implements QueryItentInterface{
    use QueryCriteriaTrait;

    /**
     * @var array $columns Columns to be selected.
     */
    protected array $columns = [];

    /**
     * @var string $table_name The primary table for the FROM clause.
     */
    protected string $table_name = '';

    /**
     * @var array $joins Structured join definitions.
     */
    protected array $joins = [];

    /**
     * @var array $groups Grouping columns for the GROUP BY clause.
     */
    protected array $groups = [];

    /**
     * @var array $orders Ordering definitions (column and direction).
     */
    protected array $orders = [];

    /**
     * @var int|null $limit Maximum number of rows to return.
     */
    protected ?int $limit = null;

    /**
     * @var int|null $offset Number of rows to skip.
     */
    protected ?int $offset = null;

    /**
     * @var SQLBuilder $builder The builder instance for the final hand-off.
     */
    private SQLBuilder $builder;

    /**
     * Private constructor to enforce static factory usage.
     */
    private function __construct( SQLBuilder $builder ) {
        $this->builder  = $builder;
    }

    /**
     * Static factory to initialize a selection intent with specific columns.
     * 
     * @param string ...$columns Variadic list of column names.
     * @return static
     */
    public function select( string ...$columns ) : static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Set the source table for the query.
     * 
     * @param string $table The raw table name.
     * @return $this
     */
    public function from( string $table ) : static {
        $this->table_name = $table;
        return $this;
    }

    /**
     * Add an INNER JOIN clause.
     * 
     * @param string $table    Table to join.
     * @param string $first    First column in condition.
     * @param string $operator Comparison operator.
     * @param string $second   Second column in condition.
     * @return $this
     */
    public function join( string $table, string $first, string $operator, string $second ) : static {
        return $this->add_join_entry( $table, $first, $operator, $second, 'INNER' );
    }

    /**
     * Add a LEFT JOIN clause.
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function left_join( string $table, string $first, string $operator, string $second ) : static {
        return $this->add_join_entry( $table, $first, $operator, $second, 'LEFT' );
    }

    /**
     * Add a RIGHT JOIN clause.
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function right_join( string $table, string $first, string $operator, string $second ) : static {
        return $this->add_join_entry( $table, $first, $operator, $second, 'RIGHT' );
    }

    /**
     * Add a CROSS JOIN clause.
     * 
     * @param string $table
     * @return $this
     */
    public function cross_join( string $table ) : static {
        return $this->add_join_entry( $table, '', '', '', 'CROSS' );
    }

    /**
     * Internal helper to standardize join data structures.
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return $this
     */
    protected function add_join_entry( string $table, string $first, string $operator, string $second, string $type ) : static {
        $this->joins[] = compact( 'table', 'first', 'operator', 'second', 'type' );
        return $this;
    }

    /**
     * Add columns to the GROUP BY clause.
     * 
     * @param string ...$columns Variadic list of columns.
     * @return $this
     */
    public function group_by( string ...$columns ) : static {
        $this->groups = array_merge( $this->groups, $columns );
        return $this;
    }

    /**
     * Add a column to the ORDER BY clause.
     * 
     * @param string $column
     * @param string $direction Sort direction (ASC or DESC).
     * @return $this
     */
    public function order_by( string $column, string $direction = 'ASC' ) : static {
        $this->orders[] = [
            'column'    => $column,
            'direction' => strtoupper( trim( $direction ) )
        ];
        return $this;
    }

    /**
     * Set the LIMIT clause.
     * 
     * @param int $limit
     * @return $this
     */
    public function limit( int $limit ) : static {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the OFFSET clause.
     * 
     * @param int $offset
     * @return $this
     */
    public function offset( int $offset ) : static {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Trigger the build process via the injected builder.
     * 
     * @return string
     */
    public function build() : string {
        return $this->builder->build();
    }

    /**
     * Retrieve parameter bindings for the query criteria.
     * 
     * @return array
     */
    public function get_bindings() : array {
        return $this->bindings;
    }

    /**
     * Get the columns to be selected. Returns ['*'] if none specified.
     * 
     * @return array
     */
    public function get_columns() : array {
        return empty( $this->columns ) ? ['*'] : $this->columns;
    }

    /**
     * Get the primary table name.
     * 
     * @return string
     */
    public function get_table_name() : string {
        return $this->table_name;
    }

    /**
     * Get all defined joins.
     * 
     * @return array
     */
    public function get_joins() : array {
        return $this->joins;
    }

    /**
     * Get grouping columns.
     * 
     * @return array
     */
    public function get_groups() : array {
        return $this->groups;
    }

    /**
     * Get ordering definitions.
     * 
     * @return array
     */
    public function get_orders() : array {
        return $this->orders;
    }

    /**
     * Get the row limit.
     * 
     * @return int|null
     */
    public function get_limit() : ?int {
        return $this->limit;
    }

    /**
     * Get the row offset.
     * 
     * @return int|null
     */
    public function get_offset() : ?int {
        return $this->offset;
    }

    /**
     * Static factory
     * 
     * @param SQLBuilder $builder
     * @return static Fluent
     */
    public static function make( SQLBuilder $builder ) : static {
        return new static( $builder );
    }

    public function new_instance() : static {
        return new static( $this->builder );
    }
}