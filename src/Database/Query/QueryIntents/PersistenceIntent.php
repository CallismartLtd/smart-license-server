<?php
/**
 * Persistence Query Intent class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\QueryIntents
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

use SmartLicenseServer\Database\Query\SQLBuilder;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents an intent to persist or modify data (INSERT/UPDATE).
 * 
 * Encapsulates the dataset and column mappings for DML operations.
 * 
 * @since 0.2.0
 */
class PersistenceIntent implements QueryItentInterface {
    use QueryCriteriaTrait;
    /**
     * @var string $table_name The target table name.
     */
    private string $table_name;

    /**
     * The SQL builder instance.
     * 
     * @var SQLBuilder $builder
     */
    private SQLBuilder $builder;

    /**
     * The data payload for the operation.
     * 
     * @var array $data
     */
    private array $data = [];

    /**
     * Whether this is a multi-row operation.
     * 
     * @var bool $is_multi
     */
    private bool $is_multi = false;

    /**
     * Constructor.
     * 
     * @param string $table_name
     */
    private function __construct( string $table_name ) {
        $this->table_name = $table_name;
    }

    /**
     * Set data for a single row operation.
     * 
     * @param array $data Column => Value pairs.
     * @return static
     */
    public function values( array $data ) : static {
        $this->is_multi = false;
        $this->data     = $data;

        return $this;
    }

    /**
     * Set data for multiple rows (Bulk Insert).
     * 
     * @param array $rows Array of column => value arrays.
     * @return static
     * @throws InvalidArgumentException
     */
    public function multi_values( array $rows ) : static {
        if ( empty( $rows ) ) {
            throw new InvalidArgumentException( 'Multi-values intent requires at least one row.' );
        }

        $this->is_multi = true;
        $this->data     = $rows;

        return $this;
    }

    /**
     * Retrieve the table name.
     * 
     * @return string
     */
    public function get_table_name() : string {
        return $this->table_name;
    }

    /**
     * Retrieve the dataset.
     * 
     * @return array
     */
    public function get_data() : array {
        return $this->data;
    }

    /**
     * Check if the operation is multi-row.
     * 
     * @return bool
     */
    public function is_multi() : bool {
        return $this->is_multi;
    }

    /**
     * Build query.
     * 
     * @return string
     */
    public function build() : string {
        return $this->builder->build();
    }

    /**
     * Get bindings for the operation.
     * 
     * Flattens the internal data into a one-dimensional array of values.
     * 
     * @return array
     */
    public function get_bindings() : array {
        // 1. Get the values being SET (inherited from your previous logic)
        $set_bindings = $this->is_multi ? $this->flatten_multi_data() : array_values( $this->data );

        // 2. Get the WHERE criteria bindings from the trait
        // Note: We use the trait's property directly because the 
        // trait's get_bindings() would likely be shadowed.
        $where_bindings = $this->bindings; 

        return array_merge( $set_bindings, $where_bindings );
    }

    private function flatten_multi_data() : array {
        $flat = [];
        foreach ( $this->data as $row ) {
            foreach ( $row as $val ) $flat[] = $val;
        }
        return $flat;
    }

    /**
     * Static factory.
     * 
     * @param string     $table_name
     * @param SQLBuilder $builder
     * @return static Fluent
     */
    public static function make( string $table_name, SQLBuilder $builder ) : static {
        $static          = new static( $table_name );
        $static->builder = $builder;

        return $static;
    }

    public function new_instance() : static {
        return $this->make( $this->table_name, $this->builder );
    }
}