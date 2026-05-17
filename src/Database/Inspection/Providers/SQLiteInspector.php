<?php
/**
 * SQLite database inspector implementation
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Inspection\Providers
 */

namespace SmartLicenseServer\Database\Inspection\Providers;

/**
 * Inspector for SQLite databases.
 * 
 * Implements schema inspection using SQLite PRAGMA statements via DatabaseAdapterInterface.
 * Note: SQLite uses PRAGMA for introspection instead of information_schema.
 */
class SQLiteInspector extends AbstractInspector {

	/**
	 * Normalize SQLite column types.
	 * 
	 * SQLite has dynamic typing; we normalize based on declared type.
	 */
	protected function normalize_type( string $type ): string {
		$type = trim( strtolower( $type ) );

		// Remove length specifications
		$type = preg_replace( '/\(\d+(?:,\d+)?\)/', '', $type );
		$type = trim( $type );

		// Normalize SQLite-specific types
		$type_map = array(
			'int'          => 'int',
			'integer'      => 'int',
			'tinyint'      => 'tinyint',
			'smallint'     => 'smallint',
			'bigint'       => 'bigint',
			'real'         => 'real',
			'float'        => 'real',
			'double'       => 'real',
			'decimal'      => 'real',
			'numeric'      => 'real',
			'text'         => 'text',
			'char'         => 'text',
			'varchar'      => 'text',
			'blob'         => 'blob',
			'date'         => 'text',
			'time'         => 'text',
			'datetime'     => 'text',
			'timestamp'    => 'text',
			'boolean'      => 'int',
			'bool'         => 'int',
			'json'         => 'text',
		);

		return $type_map[ $type ] ?? $type;
	}

	/**
	 * Get all tables in the database.
	 */
	protected function sql_all_tables(): string {
		return "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
	}

	/**
	 * Check if a table exists.
	 */
	protected function sql_table_exists( string $table ): string {
		return sprintf(
			"SELECT 1 FROM sqlite_master WHERE type='table' AND name='%s' LIMIT 1",
			addslashes( $table )
		);
	}

	/**
	 * Check if a column exists.
	 */
	protected function sql_column_exists( string $table, string $column ): string {
		// Use PRAGMA table_info for column existence check
		return $this->pragma_table_info( $table );
	}

    protected function pragma_table_info( string $table ) : string {
        return sprintf( 'PRAGMA table_info(%s)', $table );
    }

	/**
	 * Override column_exists for SQLite (since PRAGMA returns structured data).
	 */
	public function column_exists( string $table, string $column ): bool {
		$rows = $this->execute_query( $this->pragma_table_info( $table ) );
		foreach ( $rows as $col_info ) {
			if ( $col_info['name'] === $column ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get column details.
	 */
	protected function sql_column_details( string $table ): string {
		return $this->pragma_table_info( $table );
	}

	/**
	 * Override get_columns to use PRAGMA.
	 */
	public function get_columns( string $table ): array {
		$rows    = $this->execute_query( $this->pragma_table_info( $table ) );
		$columns = array();

		foreach ( $rows as $col_info ) {
			$columns[] = $col_info['name'];
		}

		return $columns;
	}

	/**
	 * Override get_column_type to use PRAGMA.
	 */
	public function get_column_type( string $table, string $column ): ?string {
		$rows = $this->execute_query( $this->pragma_table_info( $table ) );

		foreach ( $rows as $col_info ) {
			if ( $col_info['name'] === $column ) {
				return $this->normalize_type( $col_info['type'] );
			}
		}

		return null;
	}

	/**
	 * Override get_column_details to use PRAGMA.
	 */
	public function get_column_details( string $table ): array {
		$rows    = $this->execute_query( $this->pragma_table_info( $table ) );
		$details = array();

		foreach ( $rows as $col_info ) {
			$column_name = $col_info['name'];
			$details[ $column_name ] = array(
				'type'           => $this->normalize_type( $col_info['type'] ),
				'nullable'       => $col_info['notnull'] == 0,
				'default'        => $col_info['dflt_value'] ?? null,
				'auto_increment' => (bool) $col_info['pk'],
			);
		}

		return $details;
	}

	/**
	 * Override is_column_nullable to use PRAGMA.
	 */
	public function is_column_nullable( string $table, string $column ): ?bool {
		$rows = $this->execute_query( $this->pragma_table_info( $table ) );

		foreach ( $rows as $col_info ) {
			if ( $col_info['name'] === $column ) {
				return $col_info['notnull'] == 0;
			}
		}

		return null;
	}

	/**
	 * Override get_column_default to use PRAGMA.
	 */
	public function get_column_default( string $table, string $column ) {
		$rows = $this->execute_query( $this->pragma_table_info( $table ) );

		foreach ( $rows as $col_info ) {
			if ( $col_info['name'] === $column ) {
				return $col_info['dflt_value'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Get indexes.
	 */
	protected function sql_indexes( string $table ): string {
		return sprintf( 'PRAGMA index_list(%s)', $table );
	}

	/**
	 * Override get_indexes to use PRAGMA.
	 */
	public function get_indexes( string $table ): array {
		$index_list = $this->execute_query( $this->sql_indexes( $table ) );
		$indexes    = array();

		foreach ( $index_list as $idx ) {
			// Skip primary key index (unique=2 is the PK marker in SQLite).
			if ( $idx['unique'] == 2 ) {
				continue;
			}

			$index_name  = $idx['name'];
			$index_info  = $this->execute_query( sprintf( 'PRAGMA index_info(%s)', $index_name ) );
			$columns     = array();

			foreach ( $index_info as $col_info ) {
				$columns[] = $col_info['name'];
			}

			$indexes[ $index_name ] = array(
				'columns' => $columns,
				'unique'  => (bool) $idx['unique'],
			);
		}

		return $indexes;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_index( string $table, string $index_name ) : bool {
		$sql	= "SELECT name FROM sqlite_master WHERE type = 'index' 
			AND tbl_name = ? AND name = ? LIMIT 1";
		
		$result	= $this->dbal->get_var( $sql, [$table, $index_name] );
		
		return $result ? true : false;
	}

	/**
	 * Get primary key.
	 */
	protected function sql_primary_key( string $table ): string {
		return $this->pragma_table_info( $table );
	}

	/**
	 * Override get_primary_key to use PRAGMA.
	 */
	public function get_primary_key( string $table ): ?array {
		$rows        = $this->execute_query( $this->pragma_table_info( $table ) );
		$pk_columns  = array();

		foreach ( $rows as $col_info ) {
			if ( $col_info['pk'] > 0 ) {
				$pk_columns[ (int) $col_info['pk'] ] = $col_info['name'];
			}
		}

		if ( empty( $pk_columns ) ) {
			return null;
		}

		ksort( $pk_columns );
		return array_values( $pk_columns );
	}

	/**
	 * Get foreign keys.
	 */
	protected function sql_foreign_keys( string $table ): string {
		return sprintf( 'PRAGMA foreign_key_list(%s)', $table );
	}

	/**
	 * Override get_foreign_keys to use PRAGMA.
	 */
	public function get_foreign_keys( string $table ): array {
		$fk_list = $this->execute_query( sprintf( 'PRAGMA foreign_key_list(%s)', $table ) );
		$fks     = array();

		foreach ( $fk_list as $fk ) {
			// SQLite PRAGMA returns: id, seq, table, from, to, on_update, on_delete, match
			$constraint_id    = 'fk_' . $fk['id'];
			$referenced_col   = $fk['to'];
			$referenced_table = $fk['table'];
			$column_name      = $fk['from'];

			if ( ! isset( $fks[ $constraint_id ] ) ) {
				$fks[ $constraint_id ] = array(
					'columns'            => array(),
					'referenced_table'   => $referenced_table,
					'referenced_columns' => array(),
				);
			}

			$fks[ $constraint_id ]['columns'][]            = $column_name;
			$fks[ $constraint_id ]['referenced_columns'][] = $referenced_col;
		}

		return $fks;
	}

	/**
	 * Get unique constraints SQL.
	 * 
	 * SQLite uses PRAGMA index_list for constraint retrieval.
	 */
	protected function sql_unique_constraints( string $table ): string {
		return $this->sql_indexes( $table );
	}

	/**
	 * Get unique constraints.
	 * 
	 * SQLite doesn't provide direct access to unique constraints via PRAGMA.
	 * We retrieve them from the index list.
	 */
	public function get_unique_constraints( string $table ): array {
		$index_list   = $this->execute_query( $this->sql_indexes( $table ) );
		$constraints  = array();

		foreach ( $index_list as $idx ) {
			// Only include unique indexes that aren't primary key
			if ( ! $idx['unique'] || $idx['unique'] == 2 ) {
				continue;
			}

			$index_name  = $idx['name'];
			$index_info  = $this->execute_query( sprintf( 'PRAGMA index_info(%s)', $index_name ) );
			$columns     = array();

			foreach ( $index_info as $col_info ) {
				$columns[] = $col_info['name'];
			}

			$constraints[ $index_name ] = $columns;
		}

		return $constraints;
	}

	/**
	 * Get check constraints SQL.
	 * 
	 * SQLite doesn't expose check constraints via PRAGMA.
	 */
	protected function sql_check_constraints( string $table ): string {
		return "SELECT NULL as constraint_name, NULL as definition WHERE 0";
	}

	/**
	 * Get check constraints.
	 * 
	 * SQLite doesn't expose check constraints via PRAGMA.
	 * This would require parsing CREATE TABLE DDL, which is not implemented.
	 */
	public function get_check_constraints( string $table ): array {
		return array();
	}

	/**
	 * Get table metadata.
	 */
	protected function sql_table_metadata( string $table ): string {
		return sprintf(
			"SELECT sql FROM sqlite_master WHERE type='table' AND name='%s'",
			addslashes( $table )
		);
	}

	/**
	 * Override get_table_metadata for SQLite.
	 */
	public function get_table_metadata( string $table ): array {
		$rows = $this->execute_query( $this->sql_table_metadata( $table ) );

		return array(
			'engine'    => null,
			'charset'   => null,
			'collation' => null,
			'row_count' => 0,
			'comment'   => ! empty( $rows ) ? $rows[0]['sql'] : '',
		);
	}

	/**
	 * Get connection host info.
	 */
	public function get_host_info(): string {
		if ( $this->get_config()->path === ':memory:' ) {
            return 'Inmemory via Internal RAM';
        }

        // If it's a standard file-based database
        return sprintf( '%s via File System', (string) $this->get_config()->path );
	}

	/**
	 * Get protocol version.
	 */
	public function get_protocol_version() {
		return 'N/A (File-based)';
	}

	/**
	 * Get server version.
	 */
	public function get_server_version(): string {
		$version = $this->dbal->get_var( 'SELECT sqlite_version() as version' );

		if ( ! empty( $version ) ) {
			if ( preg_match( '/^\d+\.\d+\.\d+/', $version, $matches ) ) {
				return $matches[0];
			}

			return (string) $version;
		}

		return 'unknown';
	}

	/**
	 * Get engine type.
	 */
	public function get_engine_type(): string {
		return 'sqlite';
	}
}