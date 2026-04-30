<?php
/**
 * MySQL Query Renderer
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
 * MySQL implementation of the Query Renderer.
 */
class MySQLRenderer extends AbstractQueryRenderer {

    protected string $engine = 'mysql';

    /**
     * Quote a MySQL identifier.
     */
    protected function quote_single_identifier( string $identifier ) : string {
        return "`" . str_replace( "`", "``", $identifier ) . "`";
    }

    /**
     * Render a MySQL SELECT statement.
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

        // 1. Joins
        $sql .= $this->render_joins( $intent->get_joins() );

        // 2. Where
        $conditions = $intent->get_conditions();
        if ( ! empty( $conditions ) ) {
            $sql .= " WHERE " . $this->render_where_clauses( $conditions );
        }

        // 3. Group By
        $sql .= $this->render_grouping( $intent->get_groups() );

        // 4. Order By
        $sql .= $this->render_ordering( $intent->get_orders() );

        // 5. Limit & Offset
        $sql .= $this->render_limit_offset( $intent->get_limit(), $intent->get_offset() );

        return $sql . ";";
    }

    /**
     * Render CREATE TABLE.
     */
    public function render_create_table( CreateTableIntent $intent ) : string {
        $table_name = $this->quote_identifier( $intent->get_table_name() );
        $definitions = [];

        foreach ( $intent->get_columns() as $column ) {
            $definitions[] = $this->render_column_definition( $column );
        }

        foreach ( $intent->get_constraints() as $constraint ) {
            $definitions[] = $this->render_constraint( $constraint );
        }

        return sprintf( 
            "CREATE TABLE %s (\n\t%s\n)",
            $table_name,
            implode( ",\n\t", $definitions ) 
        );
    }

    /**
     * Render a standalone CREATE INDEX.
     */
    public function render_create_index( CreateIndexIntent $intent ) : string {
        $table = $this->quote_identifier( $intent->get_table_name() );
        $index = $intent->get_index();

        return sprintf(
            "CREATE %s ON %s (%s);",
            $this->render_index_definition( $index ),
            $table,
            implode( ', ', $this->quote_identifiers( $index->columns ) )
        );
    }

    /**
     * {@inheritdoc}
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
     * Helper to render index portion for standalone or ADD INDEX queries.
     */
    protected function render_index_definition( Constraint $index ) : string {
        $prefix = ( strtolower( $index->type ) === 'unique' ) ? 'UNIQUE INDEX' : 'INDEX';
        $name   = $index->name ? $this->quote_identifier( $index->name ) : '';
        $cols   = implode( ', ', $this->quote_identifiers( $index->columns ) );

        return trim( "{$prefix} {$name} ({$cols})" );
    }

    /**
     * Render a constraint or index for a CREATE TABLE definition.
     */
    protected function render_constraint( Constraint $constraint ) : string {
        $type    = strtolower( $constraint->type );
        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );
        
        $name = $constraint->name ? ' ' . $this->quote_identifier( $constraint->name ) : '';

        return match ( $type ) {
            'primary' => "PRIMARY KEY ({$columns})",
            'unique'  => "UNIQUE INDEX{$name} ({$columns})",
            'index'   => "INDEX{$name} ({$columns})",
            'foreign' => $this->render_foreign_key( $constraint ),
            default   => throw new \Exception( "MySQL: Unsupported constraint type [{$type}]" )
        };
    }

    /**
     * Map Alter Operations to MySQL Syntax.
     */
    protected function render_alter_operation( array $op ) : string {
        $action  = strtoupper( $op['action'] );
        $subject = strtoupper( $op['subject'] );
        $payload = $op['payload'];

        return match ( "{$action}_{$subject}" ) {
            // Columns.
            'ADD_COLUMN'      => "ADD " . $this->render_column_definition( $payload ),
            'MODIFY_COLUMN'   => "MODIFY COLUMN " . $this->render_column_definition( $payload ),
            'DROP_COLUMN'     => "DROP COLUMN " . $this->quote_identifier( $payload ),
            'RENAME_COLUMN'   => sprintf(
                "RENAME COLUMN %s TO %s",
                $this->quote_identifier( $payload['from'] ),
                $this->quote_identifier( $payload['to'] )
            ),
            // Constraints.
            'ADD_CONSTRAINT'  => "ADD " . $this->render_constraint( $payload ),
            'DROP_CONSTRAINT' => $this->render_mysql_drop_constraint( $payload ),

            // Indexes
            'DROP_INDEX'      => "DROP INDEX " . $this->quote_identifier( $payload ),

            default => throw new \Exception( "MySQL: Unsupported operation {$action}_{$subject}" )
        };
    }

    /**
     * Handle MySQL specific drop logic.
     */
    private function render_mysql_drop_constraint( string $name ) : string {
        // MySQL quirk: Primary keys are dropped by type, not by name.
        if ( strtolower( $name ) === 'primary' ) {
            return "DROP PRIMARY KEY";
        }

        return "DROP CONSTRAINT " . $this->quote_identifier( $name );
    }

    /**
     * Render Column Definition.
     */
    protected function render_column_definition( Column $column ) : string {
        $parts = [
            $this->quote_identifier( $column->name ),
            $this->normalize_type( $column->type, [
                'length'    => $column->length,
                'scale'     => $column->scale,
                'precision' => $column->precision
            ])
        ];

        if ( ! $column->nullable ) $parts[] = 'NOT NULL';
        if ( $column->default !== null ) $parts[] = "DEFAULT " . $this->format_value( $column->default );
        if ( $column->auto_increment ) $parts[] = 'AUTO_INCREMENT';

        return implode( ' ', $parts );
    }

    /**
     * Specifically render a Foreign Key constraint.
     */
    protected function render_foreign_key( Constraint $constraint ) : string {
        $name    = $constraint->name ? $this->quote_identifier( $constraint->name ) : null;
        $columns = implode( ', ', $this->quote_identifiers( $constraint->columns ) );
        
        $ref_table = $this->quote_identifier( $constraint->references_table ?? '' );
        $ref_cols  = implode( ', ', $this->quote_identifiers( $constraint->references_columns ) );

        $sql = $name ? "CONSTRAINT {$name} " : "";
        $sql .= "FOREIGN KEY ({$columns}) REFERENCES {$ref_table} ({$ref_cols})";

        if ( $constraint->on_delete ) {
            $sql .= " ON DELETE " . strtoupper( $constraint->on_delete );
        }

        if ( $constraint->on_update ) {
            $sql .= " ON UPDATE " . strtoupper( $constraint->on_update );
        }

        return $sql;
    }
}
