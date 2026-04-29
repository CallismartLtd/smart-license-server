<?php
/**
 * Absdtract Query Renderer - Abstract Base Class
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Renderers
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\Renderers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Abstract base for engine-specific SQL renderers.
 *
 * Responsibility: Convert normalized query intent into engine-correct SQL.
 *
 * Subclasses MUST implement:
 * - quote_identifier()
 * - render_select()
 * - render_insert()
 * - render_update()
 * - render_delete()
 * - render_create_table()
 * - render_alter_table()
 * - render_drop_table()
 *
 * @since 0.2.0
 */
abstract class AbstractQueryRenderer {

    /**
     * Engine name for error reporting.
     *
     * @var string
     */
    protected string $engine = '';

    /**
     * Quote an identifier (table, column, index, etc.)
     *
     * @param string $identifier Raw identifier
     *
     * @return string Quoted identifier
     */
    abstract protected function quote_identifier( string $identifier ) : string;

    /**
     * Normalize a data type for this engine.
     *
     * @param string $type Raw type
     *
     * @return string Normalized type
     */
    abstract protected function normalize_type( string $type ) : string;

    /**
     * Render a SELECT query.
     *
     * @param array $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_select( array $intent ) : string {
        if ( ! isset( $intent['from'] ) ) {
            throw new \Exception( 'SELECT requires FROM clause' );
        }
    
        $columns = $intent['select'] ?? ['*'];
    
        // Handle SELECT columns with wildcard support
        if ( in_array( '*', $columns, true ) ) {
            $select = '*';
        } else {
            $select_parts = [];
            
            foreach ( $columns as $col ) {
                // Detect table.* wildcard pattern
                if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*)\.\*$/', $col, $matches ) ) {
                    // Validate table alias, don't quote wildcard
                    $this->validate_identifier( $matches[1] );
                    $select_parts[] = $this->quote_identifier( $matches[1] ) . '.*';
                } elseif ( $col === '*' ) {
                    // Bare wildcard
                    $select_parts[] = '*';
                } else {
                    // Regular column identifier
                    $select_parts[] = $this->quote_identifier( $col );
                }
            }
            
            $select = implode( ', ', $select_parts );
        }
    
        $sql = 'SELECT ' . $select;
    
        $from = $intent['from'];
        $sql .= ' FROM ' . $this->quote_identifier( $from['table'] );
    
        if ( ! empty( $from['alias'] ) ) {
            $this->validate_identifier( $from['alias'] );
            $sql .= ' AS ' . $this->quote_identifier( $from['alias'] );
        }
    
        if ( ! empty( $intent['joins'] ) ) {
            $sql .= $this->render_joins( $intent['joins'] );
        }
    
        if ( ! empty( $intent['where'] ) ) {
            $sql .= $this->render_where( $intent['where'] );
        }
    
        if ( ! empty( $intent['group_by'] ) ) {
            $sql .= $this->render_group_by( $intent['group_by'] );
        }
    
        if ( ! empty( $intent['having'] ) ) {
            $sql .= $this->render_having( $intent['having'] );
        }
    
        if ( ! empty( $intent['order_by'] ) ) {
            $sql .= $this->render_order_by( $intent['order_by'] );
        }
    
        if ( isset( $intent['limit'] ) ) {
            $sql .= $this->render_limit( $intent['limit'] );
        }
    
        if ( isset( $intent['offset'] ) ) {
            $sql .= $this->render_offset( $intent['offset'] );
        }
    
        return $sql;
    }

    /**
     * Render an INSERT query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_insert( string $table, array $intent ) : string {
        $table = $this->quote_identifier( $table );

        if ( ! empty( $intent['values'] ) ) {
            $data = $intent['values'];

            $this->validate_bindings_not_empty( $data, 'INSERT' );

            $columns = array_map( [ $this, 'quote_identifier' ], array_keys( $data ) );
            $placeholders = array_fill( 0, count( $data ), '?' );

            return 'INSERT INTO ' . $table .
                ' (' . implode( ', ', $columns ) . ') ' .
                'VALUES (' . implode( ', ', $placeholders ) . ')';
        }

        if ( ! empty( $intent['multi_values'] ) ) {
            $rows = $intent['multi_values'];

            $this->validate_not_empty( $rows, 'Multi-value INSERT' );

            $first_row = reset( $rows );
            $columns = array_map( [ $this, 'quote_identifier' ], array_keys( $first_row ) );

            $groups = [];

            foreach ( $rows as $row ) {
                $groups[] = '(' . implode( ', ', array_fill( 0, count( $row ), '?' ) ) . ')';
            }

            return 'INSERT INTO ' . $table .
                ' (' . implode( ', ', $columns ) . ') ' .
                'VALUES ' . implode( ', ', $groups );
        }

        throw new \Exception( 'INSERT requires values() or multi_values()' );
    }

    /**
     * Render a table constraint (engine-specific differences allowed).
     *
     * @param array $constraint
     *
     * @return string
     */
    abstract protected function render_constraint( array $constraint ) : string;

    /**
     * Render a single ALTER TABLE operation.
     *
     * @param string $table     Quoted table name.
     * @param array  $operation Operation intent.
     *
     * @return string|array
     */
    abstract protected function render_alter_operation( string $table, array $operation ) : string|array;

    /**
     * Render an UPDATE query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_update( string $table, array $intent ) : string {
        $table = $this->quote_identifier( $table );
    
        if ( empty( $intent['set'] ) ) {
            throw new \Exception( 'UPDATE requires set()' );
        }
    
        $sets = [];
    
        foreach ( array_keys( $intent['set'] ) as $column ) {
            $sets[] = $this->quote_identifier( $column ) . ' = ?';
        }
    
        $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $sets );
    
        if ( ! empty( $intent['where'] ) ) {
            $sql .= $this->render_where( $intent['where'] );
        }
    
        return $sql;
    }

    /**
     * Render a DELETE query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_delete( string $table, array $intent ) : string {
        $table = $this->quote_identifier( $table );

        $sql = 'DELETE FROM ' . $table;

        if ( ! empty( $intent['where'] ) ) {
            $sql .= $this->render_where( $intent['where'] );
        }

        return $sql;
    }

    /**
     * Render a CREATE TABLE query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_create_table( string $table, array $intent ) : string {
        $table = $this->quote_identifier( $table );

        if ( empty( $intent['columns'] ) ) {
            throw new \Exception( 'CREATE TABLE requires at least one column' );
        }

        $definitions = [];

        foreach ( $intent['columns'] as $col ) {
            $col_def = $this->quote_identifier( $col['name'] ) . ' ' . $this->normalize_type( $col['type'] );

            if ( ! empty( $col['definition'] ) ) {
                $col_def .= ' ' . $col['definition'];
            }

            $definitions[] = $col_def;
        }

        if ( ! empty( $intent['constraints'] ) ) {
            foreach ( $intent['constraints'] as $constraint ) {
                $definitions[] = $this->render_constraint( $constraint );
            }
        }

        $sql = 'CREATE TABLE ' . $table . ' (' . implode( ', ', $definitions ) . ')';

        $suffix = $this->render_create_table_suffix( $intent );

        if ( $suffix !== '' ) {
            $sql .= ' ' . $suffix;
        }

        return $sql;
    }

    /**
     * Render an ALTER TABLE query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    abstract public function render_alter_table( string $table, array $intent ) : string;

    /**
     * Render a DROP TABLE query.
     *
     * @param string $table  Raw table name
     * @param array  $intent Query intent
     *
     * @return string SQL statement
     */
    public function render_drop_table( string $table, array $intent ) : string {
        $table = $this->quote_identifier( $table );

        $sql = 'DROP TABLE';

        if ( ! empty( $intent['if_exists'] ) ) {
            $sql .= ' IF EXISTS';
        }

        return $sql . ' ' . $table;
    }

    /**
     * Quote multiple identifiers.
     *
     * @param string|array $identifiers
     *
     * @return string|array
     */
    protected function quote_identifiers( $identifiers ) {
        if ( is_array( $identifiers ) ) {
            return array_map( [ $this, 'quote_identifier' ], $identifiers );
        }

        return $this->quote_identifier( $identifiers );
    }

    /**
     * Render WHERE clauses from intent.
     *
     * @param array $where_intent
     *
     * @return string
     */
    protected function render_where( array $where_intent ) : string {
        if ( empty( $where_intent ) ) {
            return '';
        }

        $conditions = [];

        foreach ( $where_intent as $item ) {
            $conditions[] = $item['condition'];
        }

        return ' WHERE ' . implode( ' AND ', $conditions );
    }

    /**
     * Render JOIN clauses from intent.
     *
     * @param array $joins_intent
     *
     * @return string
     */
    protected function render_joins( array $joins_intent ) : string {
        if ( empty( $joins_intent ) ) {
            return '';
        }
    
        $joins = [];
    
        foreach ( $joins_intent as $join ) {
            $join_sql = $join['type'] . ' JOIN ' . $this->quote_identifier( $join['table'] );
    
            if ( ! empty( $join['alias'] ) ) {
                $this->validate_identifier( $join['alias'] );
                $join_sql .= ' AS ' . $this->quote_identifier( $join['alias'] );
            }
    
            $join_sql .= ' ON ' . $join['condition'];
    
            $joins[] = $join_sql;
        }
    
        return ' ' . implode( ' ', $joins );
    }

    /**
     * Render GROUP BY clause from intent.
     *
     * @param array $group_by_intent
     *
     * @return string
     */
    protected function render_group_by( array $group_by_intent ) : string {
        if ( empty( $group_by_intent ) ) {
            return '';
        }

        $columns = array_map( [ $this, 'quote_identifier' ], $group_by_intent );

        return ' GROUP BY ' . implode( ', ', $columns );
    }

    /**
     * Render HAVING clause from intent.
     *
     * @param array|null $having_intent
     *
     * @return string
     */
    protected function render_having( ?array $having_intent ) : string {
        if ( ! $having_intent ) {
            return '';
        }

        return ' HAVING ' . $having_intent['condition'];
    }

    /**
     * Render ORDER BY clause from intent.
     *
     * @param array $order_by_intent
     *
     * @return string
     */
    protected function render_order_by( array $order_by_intent ) : string {
        if ( empty( $order_by_intent ) ) {
            return '';
        }

        $orders = [];

        foreach ( $order_by_intent as $item ) {
            $orders[] = $this->quote_identifier( $item['column'] ) . ' ' . $item['direction'];
        }

        return ' ORDER BY ' . implode( ', ', $orders );
    }

    /**
     * Render LIMIT clause from intent.
     *
     * @param int|null $limit
     *
     * @return string
     */
    protected function render_limit( ?int $limit ) : string {
        if ( ! $limit ) {
            return '';
        }

        return ' LIMIT ' . $limit;
    }

    /**
     * Render OFFSET clause from intent.
     *
     * @param int|null $offset
     *
     * @return string
     */
    protected function render_offset( ?int $offset ) : string {
        if ( ! $offset ) {
            return '';
        }

        return ' OFFSET ' . $offset;
    }

    /**
     * Validate that columns list is not empty.
     *
     * @param array  $columns
     * @param string $context
     *
     * @return void
     */
    protected function validate_not_empty( array $columns, string $context ) : void {
        if ( empty( $columns ) ) {
            throw new \Exception( "{$context} requires at least one column" );
        }
    }

    /**
     * Validate that bindings is not empty.
     *
     * @param array  $bindings
     * @param string $context
     *
     * @return void
     */
    protected function validate_bindings_not_empty( array $bindings, string $context ) : void {
        if ( empty( $bindings ) ) {
            throw new \Exception( "{$context} requires data bindings" );
        }
    }

    /**
     * Reconstruct bindings for UPDATE in correct SQL order.
     * 
     * Call this AFTER rendering SQL to ensure binding order matches placeholder order.
     * 
     * @param array $intent Query intent with 'set' and optional 'where'
     * 
     * @return array Bindings in correct order for prepared statement
     */
    public function reconstruct_update_bindings( array $intent ) : array {
        $bindings = [];
    
        // SET values come first in the SQL
        if ( ! empty( $intent['set'] ) ) {
            $bindings = array_merge( $bindings, array_values( $intent['set'] ) );
        }
    
        // WHERE conditions come second
        if ( ! empty( $intent['where'] ) ) {
            foreach ( $intent['where'] as $item ) {
                $bindings = array_merge( $bindings, $item['bindings'] ?? [] );
            }
        }
    
        return $bindings;
    }
    
    /**
     * Reconstruct bindings for DELETE in correct SQL order.
     * 
     * Call this AFTER rendering SQL to ensure binding order matches placeholder order.
     * 
     * @param array $intent Query intent with optional 'where'
     * 
     * @return array Bindings in correct order for prepared statement
     */
    public function reconstruct_delete_bindings( array $intent ) : array {
        $bindings = [];
    
        // DELETE only has WHERE conditions
        if ( ! empty( $intent['where'] ) ) {
            foreach ( $intent['where'] as $item ) {
                $bindings = array_merge( $bindings, $item['bindings'] ?? [] );
            }
        }
    
        return $bindings;
    }

    /**
     * Validate that a string is a valid SQL identifier.
     *
     * Valid identifiers: alphanumeric + underscores, must start with letter or underscore.
     *
     * @param string $identifier The identifier to validate
     *
     * @return void
     *
     * @throws \Exception If identifier is invalid
     */
    protected function validate_identifier( string $identifier ) : void {
        if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier ) ) {
            throw new \Exception(
                "Invalid identifier: '{$identifier}'. Must start with letter or underscore, contain only alphanumerics and underscores."
            );
        }
    }
    
    /**
     * Validate multiple identifiers.
     *
     * @param array $identifiers Array of identifier strings
     *
     * @return void
     *
     * @throws \Exception If any identifier is invalid
     */
    protected function validate_identifiers( array $identifiers ) : void {
        foreach ( $identifiers as $identifier ) {
            $this->validate_identifier( $identifier );
        }
    }

    /*
    |--------------------------------------
    | ENGINE HOOKS (optional overrides)
    |--------------------------------------
    */

    /**
     * Engine-specific CREATE TABLE suffix (e.g. ENGINE, CHARSET).
     *
     * @param array $intent
     *
     * @return string
     */
    protected function render_create_table_suffix( array $intent ) : string {
        return '';
    }
}