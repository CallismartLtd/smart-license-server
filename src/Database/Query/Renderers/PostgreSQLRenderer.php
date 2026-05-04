<?php
/**
 * PostgreSQL Engine Renderer
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
 * PostgreSQL-specific SQL renderer.
 *
 * Generates PostgreSQL-compliant SQL from normalized intent.
 * Handles PostgreSQL-specific double-quote identifiers and ALTER syntax.
 *
 * @since 0.2.0
 */
class PostgreSQLRenderer extends AbstractQueryRenderer {

    /**
     * The database engine identifier.
     * 
     * @var string
     */
    protected string $engine = 'pgsql';

    /**
     * Quote a PostgreSQL identifier using double quotes.
     * 
     * @param string $identifier
     * @return string
     */
    protected function quote_single_identifier( string $identifier ) : string {
        return '"' . str_replace( '"', '""', $identifier ) . '"';
    }

    /**
     * Render a PostgreSQL SELECT statement.
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
     * Render CREATE TABLE for PostgreSQL.
     * 
     * @param CreateTableIntent $intent
     * @return string
     */
    public function render_create_table( CreateTableIntent $intent ) : string {
        $table_name  = $this->quote_identifier( $intent->get_table_name() );
        $definitions = [];
        $index_sql   = [];

        // Columns
        foreach ( $intent->get_columns() as $column ) {
            $definitions[] = $this->render_column_definition( $column );
        }

        // Constraints
        foreach ( $intent->get_constraints() as $constraint ) {

            // 🚨 Extract INDEX constraints into separate statements
            if ( strtolower( $constraint->type ) === 'index' ) {
                $index_sql[] = $this->render_inline_index_as_create_index(
                    $intent->get_table_name(),
                    $constraint
                );
                continue;
            }

            $definitions[] = $this->render_constraint( $constraint );
        }

        // CREATE TABLE
        $table_sql = sprintf(
            "CREATE TABLE %s (\n\t%s\n);",
            $table_name,
            implode( ",\n\t", $definitions )
        );

        // Append indexes (separate statements)
        if ( ! empty( $index_sql ) ) {
            $table_sql .= "\n\n" . implode( "\n", $index_sql );
        }

        return $table_sql;
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

    protected function render_inline_index_as_create_index( string $table, Constraint $constraint ) : string {
        $table = $this->quote_identifier( $table );

        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );

        $name = $constraint->name
            ? $this->quote_identifier( $constraint->name )
            : $this->quote_identifier(
                $table . '_' . implode( '_', $constraint->columns ) . '_idx'
            );

        return sprintf(
            "CREATE INDEX %s ON %s (%s);",
            $name,
            $table,
            $columns
        );
    }

    /**
     * Render ALTER TABLE statement for PostgreSQL.
     * 
     * @param AlterTableIntent $intent
     * @return string
     */
    public function render_alter_table( AlterTableIntent $intent ) : string {
        $table      = $this->quote_identifier( $intent->get_table_name() );
        $operations = [];

        foreach ( $intent->get_operations() as $op ) {
            $operations[] = $this->render_alter_operation( $op );
        }

        return sprintf( "ALTER TABLE %s %s;", $table, implode( ', ', $operations ) );
    }

    /**
     * Render a constraint or index for a CREATE TABLE definition.
     * 
     * @param Constraint $constraint
     * @return string
     */
    protected function render_constraint( Constraint $constraint ) : string {
        $type    = strtolower( $constraint->type );
        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );
        $name    = $constraint->name ? ' CONSTRAINT ' . $this->quote_identifier( $constraint->name ) : '';

        return match ( $type ) {
            'primary' => "{$name} PRIMARY KEY ({$columns})",
            'unique'  => "{$name} UNIQUE ({$columns})",
            'foreign' => $this->render_foreign_key( $constraint ),
            default   => throw new \RuntimeException( "PostgreSQL: Unsupported constraint type [{$type}]" )
        };
    }

    /**
     * Map Alter Operations to PostgreSQL Syntax.
     * 
     * @param array $op
     * @return string
     */
    protected function render_alter_operation( array $op ) : string {
        $action  = strtoupper( $op['action'] );
        $subject = strtoupper( $op['subject'] );
        $payload = $op['payload'];

        return match ( "{$action}_{$subject}" ) {
            'ADD_COLUMN'      => "ADD COLUMN " . $this->render_column_definition( $payload ),
            'MODIFY_COLUMN'   => $this->render_pgsql_modify_column( $payload ),
            'DROP_COLUMN'     => "DROP COLUMN " . $this->quote_identifier( $payload ),
            'RENAME_COLUMN'   => sprintf(
                "RENAME COLUMN %s TO %s",
                $this->quote_identifier( $payload['from'] ),
                $this->quote_identifier( $payload['to'] )
            ),
            'ADD_CONSTRAINT'  => "ADD " . $this->render_constraint( $payload ),
            'DROP_CONSTRAINT' => "DROP CONSTRAINT " . $this->quote_identifier( $payload ),
            'DROP_INDEX'      => "DROP INDEX " . $this->quote_identifier( $payload ),
            default           => throw new \RuntimeException( "PostgreSQL: Unsupported operation {$action}_{$subject}" )
        };
    }

    /**
     * PostgreSQL specific column modification.
     * Unlike MySQL, PG requires separate ALTER ACTIONS for type, nullability, etc.
     * 
     * @param Column $column
     * @return string
     */
    private function render_pgsql_modify_column( Column $column ) : string {
        $name = $this->quote_identifier( $column->name );
        $type = $this->normalize_type( $column->type, [
            'length' => $column->length,
            'precision' => $column->precision,
            'scale' => $column->scale
        ]);

        $actions = [ "ALTER COLUMN {$name} TYPE {$type}" ];
        $actions[] = "ALTER COLUMN {$name} " . ( $column->nullable ? "DROP NOT NULL" : "SET NOT NULL" );

        if ( $column->default !== null ) {
            $actions[] = "ALTER COLUMN {$name} SET DEFAULT " . $this->format_value( $column->default );
        }

        return implode( ', ', $actions );
    }

    /**
     * Render Column Definition.
     * 
     * @param Column $column
     * @return string
     */
    protected function render_column_definition( Column $column ) : string {
        $type = $this->normalize_type( $column->type, [
            'length'    => $column->length,
            'scale'     => $column->scale,
            'precision' => $column->precision
        ]);

        // Handle PostgreSQL Serial (Auto Increment)
        if ( $column->auto_increment ) {
            $type = ( strtolower( $type ) === 'bigint' ) ? 'BIGSERIAL' : 'SERIAL';
        }

        $parts = [ $this->quote_identifier( $column->name ), $type ];

        if ( ! $column->nullable ) $parts[] = 'NOT NULL';
        
        // If not serial, handle standard defaults
        if ( ! $column->auto_increment && $column->default !== null ) {
            $parts[] = "DEFAULT " . $this->format_value( $column->default );
        }

        return implode( ' ', $parts );
    }

    /**
     * Render a Foreign Key constraint.
     * 
     * @param Constraint $constraint
     * @return string
     */
    protected function render_foreign_key( Constraint $constraint ) : string {
        $name    = $constraint->name ? 'CONSTRAINT ' . $this->quote_identifier( $constraint->name ) . ' ' : '';
        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );
        $ref_table = $this->quote_identifier( $constraint->references_table ?? '' );
        $ref_cols  = implode( ', ', $this->quote_identifiers( $constraint->references_columns ) );

        $sql = "{$name}FOREIGN KEY ({$columns}) REFERENCES {$ref_table} ({$ref_cols})";

        if ( $constraint->on_delete ) $sql .= " ON DELETE " . strtoupper( $constraint->on_delete );
        if ( $constraint->on_update ) $sql .= " ON UPDATE " . strtoupper( $constraint->on_update );

        return $sql;
    }
}