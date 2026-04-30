<?php
/**
 * SQLite Engine Renderer
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Renderers
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\Renderers;

use SmartLicenseServer\Database\Query\QueryIntents\CreateTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\AlterTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\CreateIndexIntent;
use SmartLicenseServer\Database\Query\QueryIntents\SelectionIntent;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Column;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLite-specific SQL renderer.
 *
 * Generates SQLite-compliant SQL from normalized intent.
 * Enforces explicit exceptions for unsupported ALTER TABLE operations.
 *
 * @since 0.2.0
 */
class SQLiteRenderer extends AbstractQueryRenderer {

    /**
     * The database engine identifier.
     * 
     * @var string
     */
    protected string $engine = 'sqlite';

    /**
     * Quote a SQLite identifier using double quotes.
     * 
     * @param string $identifier
     * @return string
     */
    protected function quote_single_identifier( string $identifier ) : string {
        return '"' . str_replace( '"', '""', $identifier ) . '"';
    }

    /**
     * Render a SQLite SELECT statement.
     * 
     * @param SelectionIntent $intent
     * @return string
     */
    public function render_select( SelectionIntent $intent ) : string {
        $sql = sprintf(
            "SELECT %s FROM %s",
            $this->render_columns( $intent->get_columns() ),
            $this->quote_identifier( $intent->get_table_name() )
        );

        $sql .= $this->render_joins( $intent->get_joins() );

        $conditions = $intent->get_conditions();
        if ( ! empty( $conditions ) ) {
            $sql .= " WHERE " . $this->render_where_clauses( $conditions );
        }

        $sql .= $this->render_grouping( $intent->get_groups() );
        $sql .= $this->render_ordering( $intent->get_orders() );
        $sql .= $this->render_limit_offset( $intent->get_limit(), $intent->get_offset() );

        return $sql . ";";
    }

    /**
     * Render CREATE TABLE for SQLite.
     * 
     * @param CreateTableIntent $intent
     * @return string
     */
    public function render_create_table( CreateTableIntent $intent ) : string {
        $table_name  = $this->quote_identifier( $intent->get_table_name() );
        $definitions = [];

        foreach ( $intent->get_columns() as $column ) {
            $definitions[] = $this->render_column_definition( $column );
        }

        foreach ( $intent->get_constraints() as $constraint ) {
            // SQLite doesn't support named constraints in the same way; 
            // usually handled within column definitions or as table constraints.
            $definitions[] = $this->render_constraint( $constraint );
        }

        return sprintf( 
            "CREATE TABLE %s (\n\t%s\n);",
            $table_name,
            implode( ",\n\t", array_filter( $definitions ) ) 
        );
    }

    /**
     * Render a standalone CREATE INDEX.
     * 
     * @param CreateIndexIntent $intent
     * @return string
     */
    public function render_create_index( CreateIndexIntent $intent ) : string {
        $table = $this->quote_identifier( $intent->get_table_name() );
        $index = $intent->get_index();
        $type  = ( strtolower( $index->type ) === 'unique' ) ? 'UNIQUE INDEX' : 'INDEX';
        $name  = $index->name ? $this->quote_identifier( $index->name ) : '';
        $cols  = implode( ', ', $this->quote_identifiers( $index->columns ) );

        return trim( "CREATE {$type} {$name} ON {$table} ({$cols});" );
    }

    /**
     * Render ALTER TABLE for SQLite.
     * 
     * @param AlterTableIntent $intent
     * @throws \RuntimeException Because SQLite has extremely limited ALTER support.
     * @return string
     */
    public function render_alter_table( AlterTableIntent $intent ) : string {
        $table      = $this->quote_identifier( $intent->get_table_name() );
        $operations = $intent->get_operations();

        // SQLite only supports one ALTER action at a time.
        if ( count( $operations ) > 1 ) {
            throw new \RuntimeException( "SQLite: Multiple ALTER operations are not supported in a single statement." );
        }

        return sprintf( "ALTER TABLE %s %s;", $table, $this->render_alter_operation( $operations[0] ) );
    }

    /**
     * Render a constraint for SQLite.
     * 
     * @param Constraint $constraint
     * @return string
     */
    protected function render_constraint( Constraint $constraint ) : string {
        $type    = strtolower( $constraint->type );
        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );

        return match ( $type ) {
            'primary' => "PRIMARY KEY ({$columns})",
            'unique'  => "UNIQUE ({$columns})",
            'foreign' => $this->render_foreign_key( $constraint ),
            'index'   => '', // Handled via standalone CREATE INDEX in SQLite
            default   => throw new \RuntimeException( "SQLite: Unsupported constraint type [{$type}]" )
        };
    }

    /**
     * Map Alter Operations to SQLite Syntax.
     * 
     * @param array $op
     * @return string
     */
    protected function render_alter_operation( array $op ) : string {
        $action  = strtoupper( $op['action'] );
        $subject = strtoupper( $op['subject'] );
        $payload = $op['payload'];

        return match ( "{$action}_{$subject}" ) {
            'ADD_COLUMN'    => "ADD COLUMN " . $this->render_column_definition( $payload ),
            'RENAME_COLUMN' => sprintf(
                "RENAME COLUMN %s TO %s",
                $this->quote_identifier( $payload['from'] ),
                $this->quote_identifier( $payload['to'] )
            ),
            'RENAME_TABLE'  => "RENAME TO " . $this->quote_identifier( $payload ),
            default         => throw new \RuntimeException( "SQLite: Operation {$action}_{$subject} is not supported directly. Requires table reconstruction." )
        };
    }

    /**
     * Render Column Definition for SQLite.
     * 
     * @param Column $column
     * @return string
     */
    protected function render_column_definition( Column $column ) : string {
        $type = $this->normalize_type( $column->type );

        $parts = [ $this->quote_identifier( $column->name ), $type ];

        // SQLite primary key auto-increment is specific
        if ( $column->auto_increment ) {
            return $this->quote_identifier( $column->name ) . " INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        if ( ! $column->nullable ) $parts[] = 'NOT NULL';
        if ( $column->default !== null ) $parts[] = "DEFAULT " . $this->format_value( $column->default );

        return implode( ' ', $parts );
    }

    /**
     * Render a Foreign Key constraint.
     * 
     * @param Constraint $constraint
     * @return string
     */
    protected function render_foreign_key( Constraint $constraint ) : string {
        $columns   = implode( ', ', $this->quote_identifiers( $constraint->columns ) );
        $ref_table = $this->quote_identifier( $constraint->references_table ?? '' );
        $ref_cols  = implode( ', ', $this->quote_identifiers( $constraint->references_columns ) );

        $sql = "FOREIGN KEY ({$columns}) REFERENCES {$ref_table} ({$ref_cols})";

        if ( $constraint->on_delete ) $sql .= " ON DELETE " . strtoupper( $constraint->on_delete );
        if ( $constraint->on_update ) $sql .= " ON UPDATE " . strtoupper( $constraint->on_update );

        return $sql;
    }
}