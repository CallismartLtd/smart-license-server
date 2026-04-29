<?php
/**
 * SQL Query Builder - Intent Layer (Engine-Agnostic)
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query;

use SmartLicenseServer\Database\Query\Renderers\AbstractQueryRenderer;
use SmartLicenseServer\Database\Query\Renderers\MySQLRenderer;
use SmartLicenseServer\Database\Query\Renderers\PostgreSQLRenderer;
use SmartLicenseServer\Database\Query\Renderers\SQLiteRenderer;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLBuilder - Intent Layer Only
 *
 * Responsibility: Collect and normalize query intent.
 * NOT: Generate engine-specific SQL, validate dialects, perform execution.
 *
 * This class stores RAW identifiers and bindings.
 * Rendering is delegated to engine-specific renderers.
 *
 * @since 0.2.0
 */
class SQLBuilder {

    /**
     * Query type constant.
     *
     * @var string
     */
    private ?string $type = null;

    /**
     * Raw table name (UNQUOTED).
     *
     * @var string
     */
    private string $table = '';

    /**
     * Query intent components.
     *
     * Stored in normalized, engine-agnostic format.
     * Rendering happens via AbstractQueryRenderer.
     *
     * @var array
     */
    private array $intent = [];

    /**
     * Query bindings for DML operations.
     *
     * @var array
     */
    private array $bindings = [];

    /**
     * Target database engine.
     *
     * @var string ('mysql', 'pgsql', 'sqlite')
     */
    private string $engine;

    /**
     * Constructor.
     *
     * @param string $engine The database engine ('mysql', 'pgsql', 'sqlite')
     */
    public function __construct( string $engine ) {
        $this->engine = strtolower( $engine );
    }

    /*
    |------------------------------------
    |QUERY TYPE BUILDERS (Intent Layer)
    |------------------------------------
    */

    /**
     * Start a SELECT query.
     *
     * @param string|array ...$columns Columns to select (empty = *)
     *
     * @return self
     */
    public function select( ...$columns ) : self {
        $this->reset_intent();
        $this->type = 'SELECT';
        $this->intent['select'] = empty( $columns ) ? ['*'] : $columns;
        return $this;
    }

    /**
     * Start an INSERT query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function insert( string $table ) : self {
        $this->reset_intent();
        $this->type     = 'INSERT';
        $this->table    = $table;
        return $this;
    }

    /**
     * Start an UPDATE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function update( string $table ) : self {
        $this->reset_intent();
        $this->type = 'UPDATE';
        $this->table = $table;
        $this->intent['set'] = [];
        return $this;
    }

    /**
     * Start a DELETE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function delete( string $table ) : self {
        $this->reset_intent();
        $this->type = 'DELETE';
        $this->table = $table;
        return $this;
    }

    /**
     * Start a CREATE TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function create_table( string $table ) : self {
        $this->reset_intent();
        $this->type = 'CREATE TABLE';
        $this->table = $table;
        $this->intent['columns'] = [];
        $this->intent['constraints'] = [];
        return $this;
    }

    /**
     * Start an ALTER TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function alter_table( string $table ) : self {
        $this->reset_intent();
        $this->type = 'ALTER TABLE';
        $this->table = $table;
        $this->intent['operations'] = [];
        return $this;
    }

    /**
     * Start a DROP TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function drop_table( string $table ) : self {
        $this->reset_intent();
        $this->type = 'DROP TABLE';
        $this->table = $table;
        return $this;
    }

    /*
    |----------------------------------
    |SELECT OPERATIONS (Intent Layer)
    |----------------------------------
    */

    /**
     * Add FROM clause.
     *
     * @param string $table Table name (raw, unquoted)
     * @param string $alias Optional alias (raw, unquoted)
     *
     * @return self
     *
     * @throws \Exception If alias is invalid
     */
    public function from( string $table, string $alias = '' ) : self {
        if ( ! empty( $alias ) ) {
            if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias ) ) {
                throw new \Exception(
                    "Invalid table alias: '{$alias}'. Must start with letter or underscore, contain only alphanumerics and underscores."
                );
            }
        }
    
        $this->table = $table;
        $this->intent['from'] = [
            'table' => $table,
            'alias' => $alias,
        ];
        return $this;
    }

    /**
     * Add JOIN clause.
     *
     * @param string $join_type The join type (INNER, LEFT, RIGHT, FULL)
     * @param string $table     Table name (raw, unquoted)
     * @param string $condition The ON condition
     * @param string $alias     Optional alias (raw, unquoted)
     *
     * @return self
     *
     * @throws \Exception If alias is invalid
     */
    public function join( string $join_type, string $table, string $condition, string $alias = '' ) : self {
        if ( ! empty( $alias ) ) {
            if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias ) ) {
                throw new \Exception(
                    "Invalid join alias: '{$alias}'. Must start with letter or underscore, contain only alphanumerics and underscores."
                );
            }
        }
    
        if ( ! isset( $this->intent['joins'] ) ) {
            $this->intent['joins'] = [];
        }
    
        $this->intent['joins'][] = [
            'type' => strtoupper( $join_type ),
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
    
        return $this;
    }

    /**
     * Shortcut: INNER JOIN.
     *
     * @param string $table     Table name
     * @param string $condition ON condition
     * @param string $alias     Optional alias
     *
     * @return self
     */
    public function inner_join( string $table, string $condition, string $alias = '' ) : self {
        return $this->join( 'INNER', $table, $condition, $alias );
    }

    /**
     * Shortcut: LEFT JOIN.
     *
     * @param string $table     Table name
     * @param string $condition ON condition
     * @param string $alias     Optional alias
     *
     * @return self
     */
    public function left_join( string $table, string $condition, string $alias = '' ) : self {
        return $this->join( 'LEFT', $table, $condition, $alias );
    }

    /**
     * Shortcut: RIGHT JOIN.
     *
     * @param string $table     Table name
     * @param string $condition ON condition
     * @param string $alias     Optional alias
     *
     * @return self
     */
    public function right_join( string $table, string $condition, string $alias = '' ) : self {
        return $this->join( 'RIGHT', $table, $condition, $alias );
    }

    /**
     * Add WHERE clause.
     *
     * @param string $condition The WHERE condition
     * @param array  $bindings  Values to bind (for DML)
     *
     * @return self
     */
    public function where( string $condition, array $bindings = [] ) : self {
        if ( ! isset( $this->intent['where'] ) ) {
            $this->intent['where'] = [];
        }

        $this->intent['where'][] = [
            'condition' => $condition,
            'bindings' => $bindings,
        ];

        $this->bindings = array_merge( $this->bindings, $bindings );

        return $this;
    }

    /**
     * Add GROUP BY clause.
     *
     * @param string|array $columns Column(s) to group by (raw, unquoted)
     *
     * @return self
     */
    public function group_by( ...$columns ) : self {
        $this->intent['group_by'] = $columns;
        return $this;
    }

    /**
     * Add HAVING clause.
     *
     * @param string $condition The HAVING condition
     * @param array  $bindings  Values to bind
     *
     * @return self
     */
    public function having( string $condition, array $bindings = [] ) : self {
        $this->intent['having'] = [
            'condition' => $condition,
            'bindings' => $bindings,
        ];

        $this->bindings = array_merge( $this->bindings, $bindings );

        return $this;
    }

    /**
     * Add ORDER BY clause.
     *
     * @param string $column    Column name (raw, unquoted)
     * @param string $direction ASC or DESC
     *
     * @return self
     */
    public function order_by( string $column, string $direction = 'ASC' ) : self {
        if ( ! isset( $this->intent['order_by'] ) ) {
            $this->intent['order_by'] = [];
        }

        $this->intent['order_by'][] = [
            'column' => $column,
            'direction' => strtoupper( $direction ),
        ];

        return $this;
    }

    /**
     * Add LIMIT clause.
     *
     * @param int $limit The limit
     *
     * @return self
     */
    public function limit( int $limit ) : self {
        $this->intent['limit'] = $limit;
        return $this;
    }

    /**
     * Add OFFSET clause.
     *
     * @param int $offset The offset
     *
     * @return self
     */
    public function offset( int $offset ) : self {
        $this->intent['offset'] = $offset;
        return $this;
    }

    /**
     * Pagination helper (LIMIT + OFFSET).
     *
     * @param int $page     Page number (1-based)
     * @param int $limit    Items per page
     *
     * @return self
     */
    public function paginate( int $page, int $limit ) : self {
        $offset = ( $page - 1 ) * $limit;
        $this->limit( $limit );
        $this->offset( $offset );
        return $this;
    }

    /*
    |----------------------------------
    |INSERT OPERATIONS (Intent Layer)
    |----------------------------------
    */

    /**
     * Set values for single-row INSERT.
     *
     * @param array $data Column => value pairs
     *
     * @return self
     */
    public function values( array $data ) : self {
        $this->intent['values'] = $data;
        $this->bindings = array_values( $data );
        return $this;
    }

    /**
     * Set multiple rows for bulk INSERT.
     *
     * Validates that all rows have identical column sets.
     *
     * @param array $rows Array of row arrays (each row: column => value)
     *
     * @return self
     *
     * @throws \Exception If rows is empty or columns don't match
     */
    public function multi_values( array $rows ) : self {
        if ( empty( $rows ) ) {
            throw new \Exception( 'multi_values requires at least one row' );
        }
    
        // Get expected column structure from first row
        $first_row = reset( $rows );
        $expected_columns = array_keys( $first_row );
        sort( $expected_columns );  // Normalize order for comparison
    
        // Validate all rows match
        foreach ( $rows as $row_index => $row ) {
            $row_columns = array_keys( $row );
            sort( $row_columns );
    
            if ( $row_columns !== $expected_columns ) {
                $missing = array_diff( $expected_columns, $row_columns );
                $extra = array_diff( $row_columns, $expected_columns );
    
                $error = "Row {$row_index} has mismatched columns.";
    
                if ( ! empty( $missing ) ) {
                    $error .= " Missing: " . implode( ', ', $missing ) . ".";
                }
                if ( ! empty( $extra ) ) {
                    $error .= " Extra: " . implode( ', ', $extra ) . ".";
                }
    
                throw new \Exception( $error );
            }
        }
    
        $this->intent['multi_values'] = $rows;
    
        // Flatten bindings in correct order
        foreach ( $rows as $row ) {
            // Ensure values are in same order as first_row for consistency
            $ordered_values = [];
            foreach ( $expected_columns as $col ) {
                $ordered_values[] = $row[ $col ];
            }
            $this->bindings = array_merge( $this->bindings, $ordered_values );
        }
    
        return $this;
    }

    /*
    |----------------------------------
    |UPDATE OPERATIONS (Intent Layer)
    |----------------------------------
    */

    /**
     * Set columns for UPDATE.
     *
     * @param array $data Column => value pairs
     *
     * @return self
     */
    public function set( array $data ) : self {
        $this->intent['set'] = $data;
        $this->bindings = array_merge( $this->bindings, array_values( $data ) );
        return $this;
    }

    /*
    |----------------------------------------
    |CREATE TABLE OPERATIONS (Intent Layer)
    |----------------------------------------
    */

    /**
     * Add column to CREATE TABLE.
     *
     * @param string $name       Column name (raw, unquoted)
     * @param string $type       Data type
     * @param string $definition Additional definition
     *
     * @return self
     */
    public function column( string $name, string $type, string $definition = '' ) : self {
        if ( ! isset( $this->intent['columns'] ) ) {
            $this->intent['columns'] = [];
        }

        $this->intent['columns'][] = [
            'name' => $name,
            'type' => $type,
            'definition' => $definition,
        ];

        return $this;
    }

    /**
     * Add PRIMARY KEY constraint.
     *
     * @param string|array $columns Column(s)
     *
     * @return self
     */
    public function primary_key( $columns ) : self {
        $this->intent['constraints'][] = [
            'type' => 'PRIMARY KEY',
            'columns' => (array) $columns,
        ];

        return $this;
    }

    /**
     * Add UNIQUE constraint.
     *
     * @param string       $name    Constraint name
     * @param string|array $columns Column(s)
     *
     * @return self
     */
    public function unique( string $name, $columns ) : self {
        $this->intent['constraints'][] = [
            'type' => 'UNIQUE',
            'name' => $name,
            'columns' => (array) $columns,
        ];

        return $this;
    }

    /**
     * Add FOREIGN KEY constraint.
     *
     * @param string $column     Local column
     * @param string $ref_table  Referenced table
     * @param string $ref_column Referenced column
     * @param string $on_delete  Delete action
     * @param string $on_update  Update action
     *
     * @return self
     */
    public function foreign_key( string $column, string $ref_table, string $ref_column, string $on_delete = '', string $on_update = '' ) : self {
        $this->intent['constraints'][] = [
            'type' => 'FOREIGN KEY',
            'column' => $column,
            'ref_table' => $ref_table,
            'ref_column' => $ref_column,
            'on_delete' => $on_delete,
            'on_update' => $on_update,
        ];

        return $this;
    }

    /**
     * Set storage engine (MySQL only - intent storage).
     *
     * @param string $engine Engine name
     *
     * @return self
     */
    public function engine( string $engine ) : self {
        $this->intent['engine'] = $engine;
        return $this;
    }

    /**
     * Set charset and collation (MySQL only - intent storage).
     *
     * @param string $charset   Charset
     * @param string $collation Collation
     *
     * @return self
     */
    public function charset( string $charset, string $collation = '' ) : self {
        $this->intent['charset'] = $charset;
        if ( $collation ) {
            $this->intent['collation'] = $collation;
        }

        return $this;
    }

    /*
    |---------------------------------------
    |ALTER TABLE OPERATIONS (Intent Layer)
    |---------------------------------------
    */

    /**
     * Add column to ALTER TABLE.
     *
     * @param string $name       Column name (raw, unquoted)
     * @param string $type       Data type
     * @param string $definition Additional definition
     * @param string $position   Position (AFTER, FIRST) - intent only
     *
     * @return self
     */
    public function add_column( string $name, string $type, string $definition = '', string $position = '' ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'ADD COLUMN',
            'name' => $name,
            'type' => $type,
            'definition' => $definition,
            'position' => $position,
        ];

        $this->validate_operation_count();

        return $this;
    }

    /**
     * Drop column in ALTER TABLE.
     *
     * @param string $name Column name (raw, unquoted)
     *
     * @return self
     */
    public function drop_column( string $name ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'DROP COLUMN',
            'name' => $name,
        ];

        return $this;
    }

    /**
     * Rename column in ALTER TABLE.
     *
     * @param string $old_name Old column name (raw, unquoted)
     * @param string $new_name New column name (raw, unquoted)
     *
     * @return self
     */
    public function rename_column( string $old_name, string $new_name ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'RENAME COLUMN',
            'old_name' => $old_name,
            'new_name' => $new_name,
        ];

        $this->validate_operation_count();
        
        return $this;
    }

    /**
     * Modify column in ALTER TABLE.
     *
     * @param string      $name       Column name (raw, unquoted)
     * @param string      $type       New type
     * @param string      $definition Additional definition (for backward compatibility)
     * @param bool|null   $nullable   Explicitly set nullable/NOT NULL (null = don't set)
     * @param string|null $default    Default value (null = don't set)
     *
     * @return self
     */
    public function modify_column( 
        string $name, 
        string $type, 
        string $definition = '', 
        ?bool $nullable = null,
        ?string $default = null
    ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }
    
        // Build operation intent with explicit constraints
        $operation = [
            'op'            => 'MODIFY COLUMN',
            'name'          => $name,
            'type'          => $type,
            'definition'    => $definition,
            'nullable'      => $nullable,
            'default'       => $default,
        ];
    
        $this->intent['operations'][] = $operation;

        $this->validate_operation_count();
    
        return $this;
    }

    /**
     * Rename table in ALTER TABLE.
     *
     * @param string $new_name New table name (raw, unquoted)
     *
     * @return self
     */
    public function rename_table( string $new_name ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'RENAME TABLE',
            'new_name' => $new_name,
        ];

        $this->validate_operation_count();
        return $this;
    }

    /**
     * Add index in ALTER TABLE.
     *
     * @param string       $name    Index name (raw, unquoted)
     * @param string|array $columns Column(s) (raw, unquoted)
     * @param string       $type    Index type (UNIQUE, FULLTEXT)
     *
     * @return self
     */
    public function add_index( string $name, $columns, string $type = '' ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'ADD INDEX',
            'name' => $name,
            'columns' => (array) $columns,
            'type' => strtoupper( $type ),
        ];

        $this->validate_operation_count();

        return $this;
    }

    /**
     * Drop index in ALTER TABLE.
     *
     * @param string $name Index name (raw, unquoted)
     *
     * @return self
     */
    public function drop_index( string $name ) : self {
        if ( ! isset( $this->intent['operations'] ) ) {
            $this->intent['operations'] = [];
        }

        $this->intent['operations'][] = [
            'op' => 'DROP INDEX',
            'name' => $name,
        ];

        $this->validate_operation_count();
        
        return $this;
    }

    /*
    |--------------------------------------
    |DROP TABLE OPERATIONS (Intent Layer)
    |--------------------------------------
    */

    /**
     * Set IF EXISTS flag for DROP TABLE.
     *
     * @return self
     */
    public function if_exists() : self {
        $this->intent['if_exists'] = true;
        return $this;
    }

    /*
    |-------------------------------------------
    |RENDERING (Delegation to Engine Renderer)
    |-------------------------------------------
    */

    /**
     * Build the SQL query.
     *
     * Validates intent and delegates to engine-specific renderer.
     * Also reconstructs bindings in correct order for DML statements.
     *
     * @return string The rendered SQL statement
     *
     * @throws \Exception If query is invalid or unsupported
     */
    public function build() : string {
        if ( ! $this->type ) {
            throw new \Exception( 'Query type not set' );
        }
    
        if ( $this->type === 'SELECT' && ! isset( $this->intent['from'] ) ) {
            throw new \Exception( 'SELECT query requires FROM clause' );
        }
    
        // Get the appropriate renderer
        $renderer = $this->get_renderer();
    
        // Render based on query type
        $sql = match ( $this->type ) {
            'SELECT'       => $renderer->render_select( $this->intent ),
            'INSERT'       => $renderer->render_insert( $this->table, $this->intent ),
            'UPDATE'       => $renderer->render_update( $this->table, $this->intent ),
            'DELETE'       => $renderer->render_delete( $this->table, $this->intent ),
            'CREATE TABLE' => $renderer->render_create_table( $this->table, $this->intent ),
            'ALTER TABLE'  => $renderer->render_alter_table( $this->table, $this->intent ),
            'DROP TABLE'   => $renderer->render_drop_table( $this->table, $this->intent ),
            default        => throw new \Exception( "Unknown query type: {$this->type}" )
        };
    
        // Reconstruct bindings for DML statements in correct order
        if ( $this->type === 'UPDATE' ) {
            $this->bindings = $renderer->reconstruct_update_bindings( $this->intent );
        } elseif ( $this->type === 'DELETE' ) {
            $this->bindings = $renderer->reconstruct_delete_bindings( $this->intent );
        } elseif ( $this->type === 'INSERT' ) {
            $this->bindings = $this->reconstruct_insert_bindings();
        }
    
        return $sql;
    }

    /**
     * Reconstruct INSERT bindings in correct order.
     * 
     * @return array
     */
    private function reconstruct_insert_bindings() : array {
        if ( ! empty( $this->intent['values'] ) ) {
            return array_values( $this->intent['values'] );
        }
    
        if ( ! empty( $this->intent['multi_values'] ) ) {
            $bindings = [];
            foreach ( $this->intent['multi_values'] as $row ) {
                $bindings = array_merge( $bindings, array_values( $row ) );
            }
            return $bindings;
        }
    
        return [];
    }

    /**
     * Get the appropriate engine renderer.
     *
     * @return AbstractQueryRenderer
     *
     * @throws \Exception If engine not supported
     */
    private function get_renderer() : AbstractQueryRenderer {
        return match ( $this->engine ) {
            'mysql'  => new MySQLRenderer(),
            'pgsql'  => new PostgreSQLRenderer(),
            'sqlite' => new SQLiteRenderer(),
            default  => throw new \Exception( "Unsupported database engine: {$this->engine}" )
        };
    }

    /*
    |-------------------------
    | RENDERING HELPERS
    |-------------------------
    */

    /**
     * Get query bindings.
     *
     * @return array
     */
    public function get_bindings() : array {
        return $this->bindings;
    }

    /**
     * Reset the builder.
     *
     * @return self
     */
    public function reset() : self {
        $this->type = null;
        $this->table = '';
        $this->intent = [];
        $this->bindings = [];
        return $this;
    }

    /**
     * Get query type.
     *
     * @return string|null
     */
    public function get_type() : ?string {
        return $this->type;
    }

    /**
     * Get raw table name.
     *
     * @return string
     */
    public function get_table() : string {
        return $this->table;
    }

    /**
     * Get raw intent (for testing/debugging).
     *
     * @return array
     */
    public function get_intent() : array {
        return $this->intent;
    }

    /**
     * Get database engine.
     *
     * @return string
     */
    public function get_engine() : string {
        return $this->engine;
    }

    /**
     * Reset intent components.
     *
     * @return void
     */
    private function reset_intent() : void {
        $this->intent = [];
        $this->bindings = [];
    }

    /**
     * Validate operation count for the current engine.
     *
     * SQLite only allows one operation per ALTER TABLE statement.
     *
     * @throws \Exception If validation fails
     *
     * @return void
     */
    private function validate_operation_count() : void {
        if ( $this->type !== 'ALTER TABLE' ) {
            return;  // Only applies to ALTER TABLE
        }
    
        $operations = $this->intent['operations'] ?? [];
    
        if ( $this->engine === 'sqlite' && count( $operations ) > 1 ) {
            throw new \Exception(
                'SQLite ALTER TABLE supports only one operation per statement. '
                . 'You have ' . count( $operations ) . ' operations. '
                . 'Separate them into multiple alter_table() calls. '
                . 'Operations: ' . implode( ', ', array_map(
                    fn( $op ) => $op['op'] ?? 'unknown',
                    $operations
                ) )
            );
        }
    }
}