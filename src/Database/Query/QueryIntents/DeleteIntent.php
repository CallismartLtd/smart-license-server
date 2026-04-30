<?php
/**
 * Delete Query Intent class file.
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
 * Represents an intent to delete data from the database.
 * 
 * This class utilizes the QueryCriteriaTrait to manage the WHERE clauses
 * that define the scope of the deletion.
 * 
 * @since 0.2.0
 */
class DeleteIntent {
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
     * Constructor.
     * 
     * @param string $table_name
     */
    private function __construct( string $table_name ) {
        $this->table_name = $table_name;
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
     * Build query.
     * 
     * Delegates the final SQL generation to the builder's renderer.
     * 
     * @return string
     */
    public function build() : string {
        return $this->builder->build();
    }

    /**
     * Get bindings.
     * 
     * For a DELETE operation, the bindings consist solely of the 
     * criteria parameters tracked by the QueryCriteriaTrait.
     * 
     * @return array
     */
    public function get_bindings() : array {
        return $this->bindings;
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
}