<?php
/**
 * Create Index Intent
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\QueryIntents
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\QueryIntents;

use SmartLicenseServer\Database\Schema\Constraint;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents the intent to create a standalone index on an existing table.
 * 
 * @since 0.2.0
 */
class CreateIndexIntent {

    /**
     * The table name the index belongs to.
     *
     * @var string
     */
    protected string $table_name;

    /**
     * The index definition.
     *
     * @var Constraint
     */
    protected Constraint $index;

    /**
     * Constructor.
     *
     * @param string     $table_name Name of the target table.
     * @param Constraint $index      The constraint object representing the index.
     * 
     * @throws \Exception If the constraint type is not suitable for a standalone index.
     */
    public function __construct( string $table_name, Constraint $index ) {
        $this->table_name = $table_name;
        
        // Validation: Only INDEX or UNIQUE are typically valid for standalone CREATE INDEX
        $valid_types = [ 'INDEX', 'UNIQUE' ];
        if ( ! in_array( strtoupper( $index->type ), $valid_types, true ) ) {
            throw new \Exception( 
                sprintf( "Invalid constraint type '%s' for CreateIndexIntent. Expected INDEX or UNIQUE.", $index->type ) 
            );
        }

        $this->index = $index;
    }

    /**
     * Get the target table name.
     *
     * @return string
     */
    public function get_table_name() : string {
        return $this->table_name;
    }

    /**
     * Get the index constraint object.
     *
     * @return Constraint
     */
    public function get_index() : Constraint {
        return $this->index;
    }
}