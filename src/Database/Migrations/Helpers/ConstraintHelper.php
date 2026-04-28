<?php
/**
 * Constraint, Table, and Data Helpers for Migrations
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Query\SQLBuilder;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides fluent interface for constraint operations.
 *
 * Delegates SQL generation to SQLBuilder and execution to Database.
 *
 * @since 0.2.0
 */
class ConstraintHelper {

    /**
     * Statement executor.
     *
     * @var Database
     */
    private Database $executor;

    /**
     * SQL builder.
     *
     * @var SQLBuilder
     */
    private SQLBuilder $sql_builder;

    /**
     * Table name.
     *
     * @var string
     */
    private string $table;

    /**
     * Constructor.
     *
     * @param Database   $executor    The statement executor
     * @param SQLBuilder $sql_builder The SQL builder
     * @param string     $table       The table name
     */
    public function __construct( Database $executor, SQLBuilder $sql_builder, string $table ) {
        $this->executor     = $executor;
        $this->sql_builder  = $sql_builder;
        $this->table        = $table;
    }

    /**
     * Add a primary key constraint.
     *
     * @param string|array $columns The column or columns
     *
     * @return self
     */
    public function addPrimaryKey( $columns ) : self {
        $columns = (array) $columns;

        $sql = "ALTER TABLE {$this->table} ADD PRIMARY KEY (" . implode( ', ', $columns ) . ")";
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Add a unique constraint.
     *
     * @param string       $name    Constraint name
     * @param string|array $columns Columns
     *
     * @return self
     */
    public function addUnique( string $name, $columns ) : self {
        $columns = (array) $columns;

        $sql = "ALTER TABLE {$this->table} ADD CONSTRAINT {$name} UNIQUE (" . implode( ', ', $columns ) . ")";
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Add a foreign key constraint.
     *
     * @param string $column     Local column
     * @param string $ref_table  Referenced table
     * @param string $ref_column Referenced column
     * @param string $on_action  ON DELETE/UPDATE action
     *
     * @return self
     */
    public function addForeignKey( string $column, string $ref_table, string $ref_column, string $on_action = 'CASCADE' ) : self {
        $fk_name = "fk_{$this->table}_{$column}";

        $sql = "ALTER TABLE {$this->table}
                ADD CONSTRAINT {$fk_name}
                FOREIGN KEY ({$column})
                REFERENCES {$ref_table} ({$ref_column})
                ON DELETE {$on_action}
                ON UPDATE {$on_action}";

        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Add a check constraint.
     *
     * @param string $name      Constraint name
     * @param string $condition Condition
     *
     * @return self
     */
    public function addCheck( string $name, string $condition ) : self {
        $sql = "ALTER TABLE {$this->table}
                ADD CONSTRAINT {$name}
                CHECK ({$condition})";

        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Drop a constraint.
     *
     * @param string $name Constraint name
     *
     * @return self
     */
    public function drop( string $name ) : self {
        $sql = "ALTER TABLE {$this->table} DROP CONSTRAINT {$name}";
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Drop a foreign key constraint.
     *
     * @param string $name FK name
     *
     * @return self
     */
    public function dropForeignKey( string $name ) : self {
        $engine = $this->executor->get_engine_type();

        if ( 'mysql' === $engine ) {
            $sql = "ALTER TABLE {$this->table} DROP FOREIGN KEY {$name}";
        } else {
            $sql = "ALTER TABLE {$this->table} DROP CONSTRAINT {$name}";
        }

        $this->executor->exec( $sql );

        return $this;
    }
}