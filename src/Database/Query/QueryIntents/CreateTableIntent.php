<?php
/**
 * CreateTable Query Intent class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\QueryIntents
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

use SmartLicenseServer\Database\Query\SQLBuilder;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Table;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents an intent to create a database table.
 * 
 * Encapsulates the table structure and metadata required for execution.
 * 
 * @since 0.2.0
 */
class CreateTableIntent {
    /**
     * @var Table $table The table structure to be created.
     */
    private Table $table;

    /**
     * The SQL builder instance.
     * 
     * @var SQLBuilder $builder
     */
    private SQLBuilder $builder;

    /**
     * Constructor.
     * 
     * @param string $table_name The table structure to be created.
     */
    private function __construct( string $table_name ) {
        $this->table = new Table( $table_name );
    }

    /**
    /**
     * Add a column instance to the table.
     * 
     * @param Column $column
     * @return static
     */
    public function add_column( Column $column ) : static {
        $this->table->add_column( $column );
        return $this;
    }

    /**
     * Add multiple columns to the table.
     * 
     * @param Column[] $columns
     * @return static
     */
    public function add_columns( array $columns ) : static {
        $this->table->add_columns( $columns );

        return $this;
    }

    /**
     * Add a constraint instance to the table.
     * 
     * @param Constraint $constraint
     * @return static
     */
    public function add_constraint( Constraint $constraint ) : static {
        $this->table->add_constraint( $constraint );
        return $this;
    }

    /**
     * Add multiple constraints to the table.
     * 
     * @param Constraint[] $constraints
     * @return static
     */
    public function add_constraints( array $constraints ) : static {
        $this->table->add_constraints( $constraints );
        return $this;
    }

    /**
     * Get the table name
     * 
     * @return string
     */
    public function get_table_name() : string {
        return $this->table->get_name();
    }

    /**
     * Retrieve the raw column instances.
     * 
     * @return Column[]
     */
    public function get_columns() : array {
        return $this->table->get_columns();
    }

    /**
     * Retrieve the raw constraint instances.
     * 
     * @return Constraint[]
     */
    public function get_constraints() : array {
        return $this->table->get_constraints();
    }

    /**
     * Get the query builder instance
     * 
     * @return SQLBuilder
     */
    public function query() : SQLBuilder {
        return $this->builder;
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
     * Static factory
     * 
     * @param string $table_name
     * @return static Fluent
     */
    public static function make( string $table_name, SQLBuilder $builder ) : static {
        $static             = new static( $table_name );
        $static->builder    = $builder;

        return $static;
    }
}