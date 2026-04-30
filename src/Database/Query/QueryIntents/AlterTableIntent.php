<?php
/**
 * AlterTable Query Intent class file.
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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents an intent to alter an existing database table.
 * 
 * Collects a sequence of structural modifications to be executed on a table.
 * 
 * @since 0.2.0
 */
class AlterTableIntent {
    /**
     * @var string $table_name The name of the table to be altered.
     */
    private string $table_name;

    /**
     * The SQL builder instance.
     * 
     * @var SQLBuilder $builder
     */
    private SQLBuilder $builder;

    /**
     * The list of alteration operations.
     * 
     * @var array $operations
     */
    private array $operations = [];

    /**
     * Constructor.
     * 
     * @param string $table_name The name of the table to be altered.
     */
    private function __construct( string $table_name ) {
        $this->table_name = $table_name;
    }

    /**
     * Add a column and an optional constraint as separate operations.
     * 
     * By splitting these, the Renderer receives clean, individual DTOs
     * for 'ADD_COLUMN' and 'ADD_CONSTRAINT'.
     * 
     * @param \SmartLicenseServer\Database\Schema\Column $column
     * @param \SmartLicenseServer\Database\Schema\Constraint|null $constraint
     * @return static
     */
    public function add_column( Column $column, ?Constraint $constraint = null ) : static {
        $this->operations[] = [
            'action'  => 'ADD',
            'subject' => 'COLUMN',
            'payload' => $column, // Pure Column DTO
        ];

        if ( $constraint ) {
            // If the constraint hasn't had columns assigned, default to this column.
            if ( empty( $constraint->columns ) ) {
                $constraint->on( $column->name );
            }

            $this->add_constraint( $constraint );
        }

        return $this;
    }

    /**
     * Modify a column and optionally add/update a constraint.
     * 
     * @param \SmartLicenseServer\Database\Schema\Column $column
     * @param \SmartLicenseServer\Database\Schema\Constraint|null $constraint
     * @return static
     */
    public function modify_column( Column $column, ?Constraint $constraint = null ) : static {
        $this->operations[] = [
            'action'  => 'MODIFY',
            'subject' => 'COLUMN',
            'payload' => $column,
        ];

        if ( $constraint ) {
            if ( empty( $constraint->columns ) ) {
                $constraint->on( $column->name );
            }
            $this->add_constraint( $constraint );
        }

        return $this;
    }

    /**
     * Capture a column drop operation.
     * 
     * @param string $name The name of the column to be removed.
     * @return static
     */
    public function drop_column( string $name ) : static {
        $this->operations[] = [
            'action'  => 'DROP',
            'subject' => 'COLUMN',
            'payload' => $name,
        ];

        return $this;
    }

    /**
     * Capture a column rename operation.
     * 
     * The renderer will determine the appropriate syntax (e.g., RENAME COLUMN 
     * or CHANGE) based on the active database engine and version.
     * 
     * @param string $from The current column name.
     * @param string $to   The new column name.
     * @return static
     */
    public function rename_column( string $from, string $to ) : static {
        $this->operations[] = [
            'action'  => 'RENAME',
            'subject' => 'COLUMN',
            'payload' => [
                'from' => $from,
                'to'   => $to,
            ],
        ];

        return $this;
    }

    /**
     * Add a constraint addition operation.
     * 
     * @param Constraint $constraint
     * @return static
     */
    public function add_constraint( Constraint $constraint ) : static {
        $this->operations[] = [
            'action'  => 'ADD',
            'subject' => 'CONSTRAINT',
            'payload' => $constraint,
        ];

        return $this;
    }

    /**
     * Add a constraint drop operation.
     * 
     * @param string $constraint_name
     * @return static
     */
    public function drop_constraint( string $constraint_name ) : static {
        $this->operations[] = [
            'action'  => 'DROP',
            'subject' => 'CONSTRAINT',
            'payload' => $constraint_name,
        ];

        return $this;
    }

    /**
     * Add an index drop operation.
     * 
     * @param string $index_name
     * @return static
     */
    public function drop_index( string $index_name ) : static {
        $this->operations[] = [
            'action'  => 'DROP',
            'subject' => 'INDEX',
            'payload' => $index_name,
        ];

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
     * Retrieve all queued operations.
     * 
     * @return array
     */
    public function get_operations() : array {
        return $this->operations;
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
     * Static factory.
     * 
     * @param string     $table_name
     * @param SQLBuilder $builder
     * @return static Fluent
     */
    public static function make( string $table_name, SQLBuilder $builder ) : static {
        $static             = new static( $table_name );
        $static->builder    = $builder;

        return $static;
    }
}