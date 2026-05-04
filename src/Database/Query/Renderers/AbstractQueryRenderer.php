<?php
/**
 * Abstract Query Renderer - Abstract Base Class
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Renderers
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\Renderers;

use SmartLicenseServer\Database\Query\QueryIntents\AlterTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\CreateIndexIntent;
use SmartLicenseServer\Database\Query\QueryIntents\CreateTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\DeleteIntent;
use SmartLicenseServer\Database\Query\QueryIntents\PersistenceIntent;
use SmartLicenseServer\Database\Query\QueryIntents\SelectionIntent;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides a blueprint for engine-specific SQL renderers.
 */
abstract class AbstractQueryRenderer {

    /**
     * The database engine identifier (e.g., 'mysql', 'sqlite').
     * 
     * @var string
     */
    protected string $engine = '';

    /**
     * Engine-specific quoting for a single identifier unit.
     * 
     * @param string $identifier The raw segment.
     * @return string
     */
    abstract protected function quote_single_identifier( string $identifier ) : string;

    /*
    |--------------------------------------------------------------------------
    | Schema Rendering (DDL)
    |--------------------------------------------------------------------------
    */

    /**
     * Render a CREATE TABLE SQL statement.
     * 
     * @param CreateTableIntent $intent
     * @return string
     */
    abstract public function render_create_table( CreateTableIntent $intent ) : string;

    /**
     * Render an ALTER TABLE SQL statement.
     * 
     * @param AlterTableIntent $intent
     * @return string
     */
    abstract public function render_alter_table( AlterTableIntent $intent ) : string;

    /**
     * Render a standalone CREATE INDEX SQL statement.
     * 
     * @param CreateIndexIntent $intent
     * @return string
     */
    abstract public function render_create_index( CreateIndexIntent $intent ) : string;

    /**
     * Render a table constraint or index definition for CREATE/ALTER context.
     * 
     * @param Constraint $constraint
     * @return string
     */
    abstract protected function render_constraint( Constraint $constraint ) : string;

    /**
     * Render a DROP TABLE statement.
     * 
     * @param string $table   The table name.
     * @param array  $options Configuration like ['if_exists' => true].
     * @return string
     */
    public function render_drop_table( string $table, array $options = [] ) : string {
        $table_quoted = $this->quote_identifier( $table );
        $if_exists    = ! empty( $options['if_exists'] ) ? ' IF EXISTS' : '';
        return "DROP TABLE{$if_exists} {$table_quoted};";
    }

    /*
    |--------------------------------------------------------------------------
    | Data Manipulation (DML)
    |--------------------------------------------------------------------------
    */

    /**
     * Render a SELECT statement.
     * 
     * @param SelectionIntent $intent
     * @return string
     */
    abstract public function render_select( SelectionIntent $intent ) : string;

    /**
     * Render an INSERT statement, supporting single or multiple rows.
     * 
     * @param PersistenceIntent $intent
     * @throws \RuntimeException If no data is provided.
     * @return string
     */
    public function render_insert( PersistenceIntent $intent ) : string {
        $table = $this->quote_identifier( $intent->get_table_name() );
        $data  = $intent->get_data();

        if ( empty( $data ) ) {
            throw new \RuntimeException( "Cannot render INSERT: No data provided." );
        }

        $first_row = $intent->is_multi() ? $data[0] : $data;
        $columns   = array_keys( $first_row );
        $quoted_cols = implode( ', ', array_map( [ $this, 'quote_identifier' ], $columns ) );

        if ( $intent->is_multi() ) {
            $row_placeholders = [];
            foreach ( $data as $row ) {
                $row_placeholders[] = '(' . implode( ', ', array_fill( 0, count( $row ), '?' ) ) . ')';
            }
            $values_clause = implode( ', ', $row_placeholders );
        } else {
            $values_clause = '(' . implode( ', ', array_fill( 0, count( $data ), '?' ) ) . ')';
        }

        return sprintf( "INSERT INTO %s (%s) VALUES %s;", $table, $quoted_cols, $values_clause );
    }

    /**
     * Render an UPDATE statement with conditions.
     * 
     * @param PersistenceIntent $intent
     * @return string
     */
    public function render_update( PersistenceIntent $intent ) : string {
        $table = $this->quote_identifier( $intent->get_table_name() );
        $data  = $intent->get_data();

        $set_parts = [];
        foreach ( array_keys( $data ) as $column ) {
            $set_parts[] = $this->quote_identifier( $column ) . " = ?";
        }

        $sql = sprintf( "UPDATE %s SET %s", $table, implode( ', ', $set_parts ) );
        $conditions = $intent->get_conditions();

        if ( ! empty( $conditions ) ) {
            $sql .= " WHERE " . $this->render_where_clauses( $conditions );
        }

        return $sql . ";";
    }

    /**
     * Render a DELETE statement with conditions.
     * 
     * @param DeleteIntent $intent
     * @return string
     */
    public function render_delete( DeleteIntent $intent ) : string {
        $table = $this->quote_identifier( $intent->get_table_name() );
        $sql   = "DELETE FROM {$table}";
        $conditions = $intent->get_conditions();

        if ( ! empty( $conditions ) ) {
            $sql .= " WHERE " . $this->render_where_clauses( $conditions );
        }

        return $sql . ";";
    }

    /*
    |---------------------
    | Shared Helpers
    |---------------------
    */

    /**
     * Render the column selection portion of a query.
     * 
     * @param array $columns
     * @return string
     */
    protected function render_columns( array $columns ) : string {
        return implode( ', ', array_map( [ $this, 'quote_identifier' ], $columns ) );
    }

    /**
     * Quote an array of identifiers.
     * 
     * @param array $identifiers
     * @return array
     */
    protected function quote_identifiers( array $identifiers ) : array {
        return array_map( [ $this, 'quote_identifier' ], $identifiers );
    }

    /**
     * Render WHERE clause string from structured conditions.
     * 
     * @param array $conditions
     * @throws \InvalidArgumentException If condition type is unknown.
     * @return string
     */
    protected function render_where_clauses( array $conditions ) : string {
        $parts = [];
        foreach ( $conditions as $index => $condition ) {
            $connector = ( $index === 0 ) ? '' : " {$condition['boolean']} ";
            $clause = match ( $condition['type'] ) {
                'Basic'     => sprintf( "%s %s ?", $this->quote_identifier( $condition['column'] ), $condition['operator'] ),
                
                'Null'      => sprintf( "%s IS %sNULL", $this->quote_identifier( $condition['column'] ), $condition['not'] ? 'NOT ' : '' ),
                                
                'In'        => $this->render_in_condition( $condition ),

                'Group'     => $this->render_group_condition( $condition ),

                'Between'   => sprintf(
                    "%s %sBETWEEN ? AND ?",
                    $this->quote_identifier( $condition['column'] ),
                    $condition['not'] ? 'NOT ' : ''
                ),

                'Raw' => $condition['expression'],

                default => throw new \InvalidArgumentException( "Unsupported condition type: {$condition['type']}" )
            };

            $parts[] = $connector . $clause;
        }
        return implode( '', $parts );
    }

    /**
     * Render JOIN clauses.
     * 
     * @param array $joins
     * @return string
     */
    protected function render_joins( array $joins ) : string {
        if ( empty( $joins ) ) return '';
        
        $sql = [];
        foreach ( $joins as $join ) {
            if ( $join['type'] === 'CROSS' ) {
                $sql[] = sprintf( "CROSS JOIN %s", $this->quote_identifier( $join['table'] ) );
                continue;
            }
            $sql[] = sprintf( 
                "%s JOIN %s ON %s %s %s", 
                $join['type'], 
                $this->quote_identifier( $join['table'] ), 
                $this->quote_identifier( $join['first'] ), 
                $join['operator'], 
                $this->quote_identifier( $join['second'] ) 
            );
        }
        return ' ' . implode( ' ', $sql );
    }

    /**
     * Render GROUP BY clause.
     * 
     * @param array $groups
     * @return string
     */
    protected function render_grouping( array $groups ) : string {
        return empty( $groups ) ? '' : ' GROUP BY ' . implode( ', ', $this->quote_identifiers( $groups ) );
    }

    /**
     * Render grouped WHERE conditions.
     * 
     * @param array $condition
     * @return string
     */
    protected function render_group_condition( array $condition ) : string {
        $inner = $this->render_where_clauses( $condition['conditions'] );
        return '(' . $inner . ')';
    }

    protected function render_in_condition( array $condition ) : string {
        $placeholders = implode(
            ', ',
            array_fill( 0, count( $condition['values'] ), '?' )
        );

        return sprintf(
            "%s %sIN (%s)",
            $this->quote_identifier( $condition['column'] ),
            $condition['not'] ? 'NOT ' : '',
            $placeholders
        );
    }

    /**
     * Render ORDER BY clause.
     * 
     * @param array $orders
     * @return string
     */
    protected function render_ordering( array $orders ) : string {
        if ( empty( $orders ) ) return '';
        $parts = [];
        foreach ( $orders as $order ) {
            $parts[] = $this->quote_identifier( $order['column'] ) . ' ' . $order['direction'];
        }
        return ' ORDER BY ' . implode( ', ', $parts );
    }

    /**
     * Render LIMIT and OFFSET clauses.
     * 
     * @param int|null $limit
     * @param int|null $offset
     * @return string
     */
    protected function render_limit_offset( ?int $limit, ?int $offset ) : string {
        $sql = $limit !== null ? " LIMIT {$limit}" : '';
        $sql .= $offset !== null ? " OFFSET {$offset}" : '';
        return $sql;
    }

    /**
     * Quote a database identifier, handling dot notation and aliases.
     * 
     * @param string $identifier
     * @return string
     */
    public function quote_identifier( string $identifier ) : string {
        if ( $identifier === '*' ) return '*';
        if ( str_contains( $identifier, ' ' ) ) {
            $parts = explode( ' ', $identifier );
            return $this->quote_identifier( $parts[0] ) . ' ' . $this->quote_identifier( $parts[1] );
        }
        if ( str_contains( $identifier, '.' ) ) {
            $parts = array_map( [ $this, 'quote_single_identifier' ], explode( '.', $identifier ) );
            return implode( '.', $parts );
        }
        return $this->quote_single_identifier( $identifier );
    }

    /**
     * Map abstract column type constant to engine-specific type string.
     * 
     * @param int   $type
     * @param array $args Length, scale, or precision.
     * @return string
     */
    protected function normalize_type( int $type, array $args = [] ) : string {
        return ColumnType::resolve( $type, $this->engine, $args );
    }

    /**
     * Format literal values for DDL (defaults) or raw segments.
     * 
     * @param mixed $value
     * @return string
     */
    protected function format_value( mixed $value ) : string {
        return match( true ) {
            is_bool( $value )    => $value ? '1' : '0',
            is_null( $value )    => 'NULL',
            is_numeric( $value )  => (string) $value,
            default              => "'" . str_replace( "'", "''", (string) $value ) . "'"
        };
    }
}