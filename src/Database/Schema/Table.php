<?php
/**
 * Database Table class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a database table structure.
 * 
 * Aggregates Column and Constraint instances into a single portable entity.
 * 
 * @since 0.2.0
 */
class Table {
    /** 
     * @var string $name Resolved table name. 
     */
    protected string $name;

    /** 
     * @var Column[] $columns Array of column instances. 
     */
    protected array $columns = [];

    /** 
     * @var Constraint[] $constraints Array of constraint instances. 
     */
    protected array $constraints = [];

    /**
     * Constructor.
     * 
     * @param string $name Table name.
     */
    public function __construct( string $name ) {
        $this->name = $name;
    }

    /**
     * Static factory to start the table definition.
     * 
     * @param string $name Table name.
     * @return static
     */
    public static function make( string $name ) : static {
        return new static( $name );
    }

    /**
     * Add a column instance to the table.
     * 
     * @param Column $column
     * @return static
     */
    public function add_column( Column $column ) : static {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Add multiple columns to the table.
     * 
     * @param Column[] $columns
     * @return static
     */
    public function add_columns( array $columns ) : static {
        foreach ( $columns as $column ) {
            $this->add_column( $column );
        }

        return $this;
    }

    /**
     * Add a constraint instance to the table.
     * 
     * @param Constraint $constraint
     * @return static
     */
    public function add_constraint( Constraint $constraint ) : static {
        $this->constraints[] = $constraint;
        return $this;
    }

    /**
     * Add multiple constraints to the table.
     * 
     * @param Constraint[] $constraints
     * @return static
     */
    public function add_constraints( array $constraints ) : static {
        foreach ( $constraints as $constraint ) {
            $this->add_constraint( $constraint );
        }

        return $this;
    }

    /**
     * Get table name.
     * 
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Retrieve the raw column instances.
     * 
     * @return Column[]
     */
    public function get_columns() : array {
        return $this->columns;
    }

    /**
     * Retrieve the raw constraint instances.
     * 
     * @return Constraint[]
     */
    public function get_constraints() : array {
        return $this->constraints;
    }

    /**
     * Retrieve all constraints associated with a specific column.
     * 
     * @param string $column_name The name of the column to check.
     * @return Constraint[]
     * @since 0.2.0
     */
    public function get_constraints_for_column( string $column_name ) : array {
        return array_filter( 
            $this->constraints, 
            fn( Constraint $constraint ) => in_array( $column_name, $constraint->columns, true ) 
        );
    }

    /**
     * Check if a column has a specific type of constraint.
     * 
     * @param string $column_name
     * @param string $type primary, unique, index, etc.
     * @return bool
     * @since 0.2.0
     */
    public function column_has_constraint( string $column_name, string $type ) : bool {
        foreach ( $this->get_constraints_for_column( $column_name ) as $constraint ) {
            if ( $constraint->type === strtolower( $type ) ) {
                return true;
            }
        }

        return false;
    }
}