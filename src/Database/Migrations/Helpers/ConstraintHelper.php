<?php
/**
 * Constraint Helper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

use Exception;
use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Query\SQLBuilder;
use SmartLicenseServer\Database\Utils\Constraint;

/**
 * Provides fluent interface for constraint operations.
 *
 * Delegates SQL generation to SQLBuilder and execution to Database abstraction layer.
 * 
 * Also performs engine specific migrations for table constraints.
 *
 * @since 0.2.0
 */
class ConstraintHelper {

	/**
	 * Constructor.
	 *
	 * @param Database   $dbal          Database abstraction layer.
	 * @param SQLBuilder $queryBuilder  The SQL builder.
	 * @param string $table             The table name.
	 */
	public function __construct( 
		private Database $dbal, 
		private SQLBuilder $queryBuilder,
		private string $table 
	) {
        $this->assert_table_exists();
    }

	/**
	 * Add a primary key constraint.
	 *
	 * @param string ...$columns The column or columns
	 *
	 * @return static
	 */
	public function add_primary_key( string ...$columns ) : static {
		$constraint = Constraint::primary();
		$constraint->columns    = $columns;

		return $this->add_constraint( $constraint );
	}

	/**
	 * Add a unique constraint.
	 *
	 * @param string    $name    Constraint name
	 * @param string ...$columns Columns
	 *
	 * @return static
	 */
	public function add_unique( ?string $name, string ...$columns ) : static {

		if ( empty( $columns ) ) {
			throw new \Exception( 'ConstraintHelper: At least one column is required for this operation.' );
		}
		
		$constraint             = Constraint::unique( $name );
		$constraint->columns    = $columns;

		return $this->add_constraint( $constraint );
	}

	/**
	 * Add a foreign key constraint.
	 *
	 * @param string $fk_name       Foreign key name.
	 * @param string $ref_table     Referenced table
	 * @param array{
	 *      on_columns: string[], // The names of the columns on current table.
	 *      ref_columns: string[], // The name of the columns on the reference table.
	 *      on_delete_action?: string,
	 *      on_update_action?: string
	 * 
	 * } $definitions
	 *
	 * @return static
	 */
	public function add_foreign_key( string $fk_name, string $ref_table, array $definitions = [] ) : static {
		$columns        = (array) ( $definitions['on_columns'] ?? [] );
		$ref_columns    = (array) ( $definitions['ref_columns'] ?? [] );

		if ( empty( $columns ) ) {
			throw new \Exception( 'ConstraintHelper: No local column name provided.' );
		}

		if ( count( $columns ) !== count( $ref_columns ) ) {
			throw new Exception(
				sprintf(
					"ConstraintHelper: Column count mismatch for '%s'. Local has %d column(s), but reference table '%s' has %d.",
					$this->table,
					count($columns),
					$ref_table,
					count($ref_columns)
				)
			);
		}

		if ( '' === $fk_name ) {
			$col_names  = implode( $columns );
			$fk_name = "fk_{$this->table}_{$col_names}";
		}

		$constraint = Constraint::foreign_key( $fk_name )
			->on( ...$columns )
			->references( $ref_table, ...$ref_columns )
			->on_delete( $definitions['on_delete_action'] ?? 'CASCADE' )
			->on_update( $definitions['on_update_action'] ?? 'CASCADE' );
		
		return $this->add_constraint( $constraint );
	}

	/**
	 * Drop a primary key constraint.
	 *
	 * @param string $name Optional. Primary key constraint name (usually auto-generated).
	 *
	 * @return static
	 */
	public function drop_primary_key( ?string $name = null ) : static {
		// Fallback to generic drop if name not provided
		$constraint_name = $name ?? 'PRIMARY';
		
		return $this->drop( $constraint_name );
	}

	/**
	 * Drop a unique constraint.
	 *
	 * @param string $name Constraint name
	 *
	 * @return static
	 */
	public function drop_unique( string $name ) : static {
		return $this->drop( $name );
	}

	/**
	 * Drop a constraint.
	 *
	 * @param string $name Constraint name
	 *
	 * @return static
	 */
	public function drop( string $name ) : static {
		$engine = $this->dbal->get_driver();

		if ( 'sqlite' === $engine ) {
			$this->sqlite_drop_constraint( $name );
		} else {
			$sql = $this->queryBuilder
				->alter_table( $this->table )
				->drop_constraint( $name )
				->build();
			
			$this->dbal->exec( $sql );
		}

		return $this;
	}

	/**
	 * Drop a foreign key constraint.
	 *
	 * @param string $name FK name
	 * 
	 * NOTE: Droping foreign key in SQLite is not reliable due to
	 * table rebuild quirks. A walk around is
	 * to specify the `fk_{localtable}_{reference_column}`
	 * @return static
	 */
	public function drop_foreign_key( string $name ) : static {
		$engine = $this->dbal->get_driver();

		switch( $engine ) {
			case 'pgsql':
				$this->drop( $name );
				break;
			case 'mysql':
				$sql = "ALTER TABLE {$this->table} DROP FOREIGN KEY {$name}";
				$this->dbal->exec( $sql );
				break;
			case 'sqlite':
				$this->sqlite_drop_foreign_key( $name );
				break;
		}

		return $this;
	}

	/**
	 * Add constraint to a table
	 * 
	 * @param Constraint $constraint
	 * @return static Fluent.
	 */
	public function add_constraint( Constraint $constraint ) : static {
		$engine = $this->dbal->get_driver();

		if ( 'sqlite' === $engine ) {
			$this->sqlite_add_constraint( $constraint );
		} else {
			$sql = $this->queryBuilder
				->alter_table( $this->table )
				->add_constraint( $constraint )
				->build();
				
			$this->dbal->exec( $sql );
		}

		return $this;
	}

    private function assert_table_exists() {
        if ( $this->dbal->table_exists( $this->table ) ) return;

        throw new Exception( "ConstraintHelper: The table '{$this->table}' does not exist." );
    }

	/**
	 * ========================================
	 * SQLite-Specific Table Recreation Methods
	 * ========================================
	 * 
	 * SQLite does not support ALTER TABLE ADD/DROP CONSTRAINT.
	 * Instead, we must:
	 * 1. Save existing data
	 * 2. Drop the old table
	 * 3. Recreate with the new constraint
	 * 4. Restore data
	 */

	/**
	 * Add a constraint to a SQLite table via table recreation.
	 *
	 * @param Constraint $constraint The constraint to add.
	 * @return void
	 * @throws Exception
	 */
	private function sqlite_add_constraint( Constraint $constraint ) : void {
		try {
			$this->dbal->begin_transaction();

			// Get current table definition
			$columns = $this->sqlite_get_columns_with_definitions();
			$constraints = $this->sqlite_get_existing_constraints();

			// Add the new constraint to the collection
			$constraints[] = $constraint->to_array();

			// Backup and recreate table
			$this->sqlite_recreate_table( $columns, $constraints );

			$this->dbal->commit();
		} catch ( Exception $e ) {
			$this->dbal->rollback();
			throw $e;
		}
	}

	/**
	 * Drop a foreign key constraint from a SQLite table via table recreation.
	 *
	 * @param string $fk_name The foreign key name to drop.
	 * @return void
	 * @throws Exception
	 */
	private function sqlite_drop_foreign_key( string $fk_name ) : void {
		try {
			$this->dbal->begin_transaction();

			// Get current table definition
			$columns		= $this->sqlite_get_columns_with_definitions();
			$constraints	= $this->sqlite_get_existing_constraints();

			// Remove the specified foreign key constraint
			$constraints = array_filter(
				$constraints,
				fn( $c ) => ! ( 'foreign' === $c['type'] && $c['name'] === $fk_name )
			);

			// Reindex array keys
			$constraints = array_values( $constraints );

			// Backup and recreate table
			$this->sqlite_recreate_table( $columns, $constraints );

			$this->dbal->commit();
		} catch ( Exception $e ) {
			$this->dbal->rollback();
			throw $e;
		}
	}

	/**
	 * Drop a constraint (any type) from a SQLite table via table recreation.
	 *
	 * Supports: unique constraints, primary keys, foreign keys, and other constraint types.
	 *
	 * @param string $name The constraint name to drop.
	 * @return void
	 * @throws Exception
	 */
	private function sqlite_drop_constraint( string $name ) : void {
		try {
			$this->dbal->begin_transaction();

			// Get current table definition
			$columns = $this->sqlite_get_columns_with_definitions();
			$constraints = $this->sqlite_get_existing_constraints();

			// Remove the constraint by name (works for any type)
			$constraints = array_filter(
				$constraints,
				fn( $c ) => ( $c['name'] ?? null ) !== $name
			);

			// Reindex array keys
			$constraints = array_values( $constraints );

			// Backup and recreate table
			$this->sqlite_recreate_table( $columns, $constraints );

			$this->dbal->commit();
		} catch ( Exception $e ) {
			$this->dbal->rollback();
			throw $e;
		}
	}

	/**
	 * Retrieve all column definitions for a table from SQLite schema.
	 *
	 * Returns raw column definitions suitable for CREATE TABLE recreation.
	 *
	 * @return array Array of column definition strings, keyed by column name.
	 * @throws Exception
	 */
	private function sqlite_get_columns_with_definitions() : array {
		$sql = "PRAGMA table_info({$this->table})";
		$result = $this->dbal->get_results( $sql );

		if ( empty( $result ) ) {
			throw new Exception( "Cannot introspect table '{$this->table}'." );
		}

		$columns = [];
		foreach ( $result as $col ) {
			// PRAGMA table_info returns: cid, name, type, notnull, dflt_value, pk
			$def = $col['name'] . ' ' . $col['type'];

			if ( (bool) $col['notnull'] ) {
				$def .= ' NOT NULL';
			}

			if ( null !== $col['dflt_value'] ) {
				$def .= ' DEFAULT ' . $col['dflt_value'];
			}

			if ( (bool) $col['pk'] ) {
				$def .= ' PRIMARY KEY';
			}

			$columns[ $col['name'] ] = $def;
		}

		return $columns;
	}

	/**
	 * Retrieve all existing constraints for a table from SQLite schema.
	 *
	 * Returns constraints in a normalized array format compatible with Constraint::to_array().
	 *
	 * @return array Array of constraint definitions.
	 * @throws Exception
	 */
	private function sqlite_get_existing_constraints() : array {
		$constraints = [];

		// Get foreign keys
		$fk_sql = "PRAGMA foreign_key_list({$this->table})";
		$fk_results = $this->dbal->get_results( $fk_sql );

		foreach ( $fk_results as $fk ) {
			// PRAGMA foreign_key_list returns: id, seq, table, from, to, on_update, on_delete, match
			$key = 'fk_' . $this->table . '_' . $fk['from'];

			// Accumulate columns for this FK id
			if ( ! isset( $constraints[ $key ] ) ) {
				$constraints[ $key ] = [
					'type'               => 'foreign',
					'name'               => $key,
					'columns'            => [],
					'references_table'   => $fk['table'],
					'references_columns' => [],
					'on_delete'          => $fk['on_delete'] ?? 'CASCADE',
					'on_update'          => $fk['on_update'] ?? 'CASCADE',
				];
			}

			$constraints[ $key ]['columns'][] = $fk['from'];
			$constraints[ $key ]['references_columns'][] = $fk['to'];
		}

		// Get unique constraints via index introspection
		$idx_sql = "SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='{$this->table}' AND sql IS NOT NULL";
		$idx_results = $this->dbal->get_results( $idx_sql );

		foreach ( $idx_results as $idx ) {
			// Parse unique constraint from index definition
			if ( stripos( $idx['sql'], 'UNIQUE' ) !== false ) {
				// Extract columns from the SQL definition
				if ( preg_match( '/\(([^)]+)\)/', $idx['sql'], $matches ) ) {
					$cols = array_map( 'trim', explode( ',', $matches[1] ) );
					$constraints[ $idx['name'] ] = [
						'type'    => 'unique',
						'name'    => $idx['name'],
						'columns' => $cols,
					];
				}
			}
		}

		return array_values( $constraints );
	}

	/**
	 * Recreate a SQLite table with new column definitions and constraints.
	 *
	 * This is the core operation for constraint modifications in SQLite:
	 * 1. Create a temporary table with the same data
	 * 2. Drop the original table
	 * 3. Recreate the original table with new schema
	 * 4. Copy data back from temporary table
	 * 5. Drop temporary table
	 *
	 * @param array $columns     Column definitions keyed by column name.
	 * @param array $constraints Constraint definitions.
	 * @return void
	 * @throws Exception
	 */
	private function sqlite_recreate_table( array $columns, array $constraints ) : void {
		$temp_table = $this->table . '_tmp_' . uniqid();
		$col_names = array_keys( $columns );
		$col_list = implode( ', ', $col_names );

		try {
			// Step 1: Backup data to temporary table
			$backup_sql = "CREATE TABLE {$temp_table} AS SELECT {$col_list} FROM {$this->table}";
			$this->dbal->exec( $backup_sql );

			// Step 2: Drop original table
			$drop_sql = "DROP TABLE {$this->table}";
			$this->dbal->exec( $drop_sql );

			// Step 3: Recreate table with new constraints
			$create_sql = $this->sqlite_build_create_table_sql( $columns, $constraints );
			$this->dbal->exec( $create_sql );

			// Step 4: Restore data
			$restore_sql = "INSERT INTO {$this->table} ({$col_list}) SELECT {$col_list} FROM {$temp_table}";
			$this->dbal->exec( $restore_sql );

			// Step 5: Clean up
			$cleanup_sql = "DROP TABLE {$temp_table}";
			$this->dbal->exec( $cleanup_sql );

		} catch ( Exception $e ) {
			// Attempt cleanup on failure
			try {
				$this->dbal->exec( "DROP TABLE IF EXISTS {$temp_table}" );
			} catch ( Exception ) {
				// Silently ignore cleanup errors
			}
			throw new Exception(
				"Failed to recreate table '{$this->table}': " . $e->getMessage()
			);
		}
	}

	/**
	 * Build a CREATE TABLE statement for SQLite with constraints.
	 *
	 * @param array $columns     Column definitions keyed by column name.
	 * @param array $constraints Constraint definitions from to_array().
	 * @return string CREATE TABLE SQL statement.
	 */
	private function sqlite_build_create_table_sql( array $columns, array $constraints ) : string {
		$sql_parts = [ "CREATE TABLE {$this->table} (\n" ];

		// Add column definitions
		$col_defs = [];
		foreach ( $columns as $name => $def ) {
			$col_defs[] = "\t" . $def;
		}

		// Add table-level constraints
		foreach ( $constraints as $constraint ) {
			$col_defs[] = $this->sqlite_build_constraint_sql( $constraint );
		}

		$sql_parts[] = implode( ",\n", $col_defs );
		$sql_parts[] = "\n)";

		return implode( '', $sql_parts );
	}

	/**
	 * Build constraint SQL fragment for inclusion in CREATE TABLE.
	 *
	 * @param array $constraint Constraint definition from to_array().
	 * @return string SQL fragment for the constraint.
	 */
	private function sqlite_build_constraint_sql( array $constraint ) : string {
		$type = $constraint['type'] ?? '';

		return match ( $type ) {
			'unique' => sprintf(
                "\tCONSTRAINT %s UNIQUE (%s)", 
                $constraint['name'], 
                implode( ', ', $constraint['columns'] )
            ),

			'foreign' => sprintf(
				"\tFOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s",
				implode( ', ', $constraint['columns'] ?? [] ),
				$constraint['references_table'] ?? '',
				implode( ', ', $constraint['references_columns'] ?? [] ),
				$constraint['on_delete'] ?? 'CASCADE',
				$constraint['on_update'] ?? 'CASCADE'
			),

			'primary' => sprintf(
				"\tPRIMARY KEY (%s)",
				implode( ', ', $constraint['columns'] ?? [] )
			),

			default => ''
		};
	}
}