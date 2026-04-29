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
    public string $name;

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
}