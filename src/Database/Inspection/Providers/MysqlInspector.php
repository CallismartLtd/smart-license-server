<?php
/**
 * MySQL database inspector implementation
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Inspection\Providers
 */

namespace SmartLicenseServer\Database\Inspection\Providers;

/**
 * Inspector for MySQL/MariaDB databases.
 * 
 * Implements schema inspection using MySQL-specific information_schema queries
 * via the DatabaseAdapterInterface.
 */
class MysqlInspector extends AbstractInspector {

	/**
	 * Normalize MySQL column types.
	 * 
	 * Converts types like "int(11)", "varchar(255)" to normalized forms.
	 */
	protected function normalize_type( string $type ): string {
		// Remove length specifications and trim whitespace
		$type = preg_replace( '/\(\d+(?:,\d+)?\)/', '', $type );
		$type = trim( strtolower( $type ) );

		// Normalize MySQL-specific types
		$type_map = array(
			'integer'      => 'int',
			'smallint'     => 'smallint',
			'tinyint'      => 'tinyint',
			'bigint'       => 'bigint',
			'float'        => 'float',
			'double'       => 'double',
			'decimal'      => 'decimal',
			'char'         => 'char',
			'varchar'      => 'varchar',
			'text'         => 'text',
			'tinytext'     => 'tinytext',
			'mediumtext'   => 'mediumtext',
			'longtext'     => 'longtext',
			'blob'         => 'blob',
			'tinyblob'     => 'tinyblob',
			'mediumblob'   => 'mediumblob',
			'longblob'     => 'longblob',
			'date'         => 'date',
			'time'         => 'time',
			'datetime'     => 'datetime',
			'timestamp'    => 'timestamp',
			'year'         => 'year',
			'enum'         => 'enum',
			'set'          => 'set',
			'json'         => 'json',
			'boolean'      => 'tinyint',
			'bool'         => 'tinyint',
		);

		return $type_map[ $type ] ?? $type;
	}

	/**
	 * Get all tables in the database.
	 */
	protected function sql_all_tables(): string {
		return sprintf(
			"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_TYPE = 'BASE TABLE'",
			addslashes( $this->db_name() )
		);
	}

	/**
	 * Check if a table exists.
	 */
	protected function sql_table_exists( string $table ): string {
		return sprintf(
			"SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' LIMIT 1",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Check if a column exists.
	 */
	protected function sql_column_exists( string $table, string $column ): string {
		return sprintf(
			"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s' LIMIT 1",
			addslashes( $this->db_name() ),
			addslashes( $table ),
			addslashes( $column )
		);
	}

	/**
	 * Get column details.
	 */
	protected function sql_column_details( string $table ): string {
		return sprintf(
			"SELECT 
				COLUMN_NAME as column_name,
				COLUMN_TYPE as column_type,
				IS_NULLABLE as is_nullable,
				COLUMN_DEFAULT as column_default,
				EXTRA as extra
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'
			ORDER BY ORDINAL_POSITION",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Override of InspectionInterface::is_column_nullable()
	 */
	public function is_column_nullable( string $table, string $column ): ?bool {
		$rows = $this->execute_query( $this->sql_column_details( $table ) );

		foreach ( $rows as $row ) {
			if ( $row['column_name'] === $column ) {
				return $row['is_nullable'] === 'YES';
			}
		}

		return null;
	}
	/**
	 * Get column details with auto_increment detection.
	 * 
	 * Override to detect auto_increment from EXTRA column.
	 */
	public function get_column_details( string $table ): array {
		$rows    = $this->execute_query( $this->sql_column_details( $table ) );
		$details = array();

		foreach ( $rows as $row ) {
			$column_name = $row['column_name'];
			$details[ $column_name ] = array(
				'type'           => $this->normalize_type( $row['column_type'] ),
				'nullable'       => $row['is_nullable'] === 'YES',
				'default'        => $row['column_default'] ?? null,
				'auto_increment' => strpos( $row['extra'], 'auto_increment' ) !== false,
			);
		}

		return $details;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_index( string $table, string $index_name ) : bool {
		$sql	= "SELECT INDEX_NAME FROM information_schema.statistics
		WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1;";
		
		$result	= $this->dbal->get_var( $sql, [$table, $index_name ] );
		
		return $result ? true : false;
	}

	/**
	 * Get indexes.
	 */
	protected function sql_indexes( string $table ): string {
		return sprintf(
			"SELECT 
				INDEX_NAME as index_name,
				COLUMN_NAME as column_name,
				NOT NON_UNIQUE as is_unique,
				SEQ_IN_INDEX as seq_in_index
			FROM INFORMATION_SCHEMA.STATISTICS
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND INDEX_NAME != 'PRIMARY'
			ORDER BY INDEX_NAME, SEQ_IN_INDEX",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get primary key.
	 */
	protected function sql_primary_key( string $table ): string {
		return sprintf(
			"SELECT 
				COLUMN_NAME as column_name,
				SEQ_IN_INDEX as seq_in_index
			FROM INFORMATION_SCHEMA.STATISTICS
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND INDEX_NAME = 'PRIMARY'
			ORDER BY SEQ_IN_INDEX",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get foreign keys.
	 */
	protected function sql_foreign_keys( string $table ): string {
		return sprintf(
			"SELECT 
				CONSTRAINT_NAME as constraint_name,
				COLUMN_NAME as column_name,
				REFERENCED_TABLE_NAME as referenced_table,
				REFERENCED_COLUMN_NAME as referenced_column
			FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND REFERENCED_TABLE_NAME IS NOT NULL
			ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get unique constraints.
	 * 
	 * MySQL doesn't distinguish unique constraints from unique indexes, so we query indexes.
	 */
	protected function sql_unique_constraints( string $table ): string {
		return sprintf(
			"SELECT 
				INDEX_NAME as constraint_name,
				COLUMN_NAME as column_name,
				SEQ_IN_INDEX as seq_in_index
			FROM INFORMATION_SCHEMA.STATISTICS
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
			ORDER BY INDEX_NAME, SEQ_IN_INDEX",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get check constraints.
	 * 
	 * MySQL 8.0.16+ supports CHECK constraints via information_schema.CHECK_CONSTRAINTS.
	 * For earlier versions, this returns empty array.
	 */
	protected function sql_check_constraints( string $table ): string {
		return sprintf(
			"SELECT 
				CONSTRAINT_NAME as constraint_name,
				CHECK_CLAUSE as definition
			FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
			WHERE CONSTRAINT_SCHEMA = '%s' AND TABLE_NAME = '%s'",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get table metadata.
	 */
	protected function sql_table_metadata( string $table ): string {
		return sprintf(
			"SELECT 
				ENGINE as engine,
				TABLE_COLLATION as collation,
				TABLE_COMMENT as comment,
				TABLE_ROWS as row_count
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'",
			addslashes( $this->db_name() ),
			addslashes( $table )
		);
	}

	/**
	 * Get table metadata with charset extraction.
	 * 
	 * Override to extract charset from collation.
	 */
	public function get_table_metadata( string $table ): array {
		$rows = $this->execute_query( $this->sql_table_metadata( $table ) );

		if ( empty( $rows ) ) {
			return array();
		}

		$row = $rows[0];

		// Extract charset from collation (e.g., "utf8mb4_unicode_ci" -> "utf8mb4")
		$charset = null;
		if ( ! empty( $row['collation'] ) ) {
			$parts   = explode( '_', $row['collation'] );
			$charset = $parts[0];
		}

		return array(
			'engine'    => $row['engine'] ?? null,
			'charset'   => $charset,
			'collation' => $row['collation'] ?? null,
			'row_count' => (int) ( $row['row_count'] ?? 0 ),
			'comment'   => $row['comment'] ?? '',
		);
	}

	/**
	 * Get protocol version.
	 */
	public function get_protocol_version() {
		$version = $this->dbal->get_row( 'SHOW VARIABLES LIKE "protocol_version"' );
		if ( ! empty( $version ) ) {
			return (int) $version['Value'];
		}
		return null;
	}

	/**
	 * Get server version.
	 */
	public function get_server_version(): string {
		$version = $this->dbal->get_var( 'SELECT VERSION() as version' );
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
		return 'mysql';
	}
}