<?php
/**
 * PostgreSQL database inspector implementation
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Inspection\Providers
 */

namespace SmartLicenseServer\Database\Inspection\Providers;

/**
 * Inspector for PostgreSQL databases.
 * 
 * Implements schema inspection using PostgreSQL information_schema and pg_catalog queries
 * via the DatabaseAdapterInterface.
 */
class PostgresInspector extends AbstractInspector {

	/**
	 * Normalize PostgreSQL column types.
	 * 
	 * Converts types like "character varying", "integer[]" to normalized forms.
	 */
	protected function normalize_type( string $type ): string {
		$type = trim( strtolower( $type ) );

		// Remove array notation
		$type = preg_replace( '/\[\]$/', '', $type );

		// Normalize PostgreSQL-specific types
		$type_map = array(
			'character varying' => 'varchar',
			'character'         => 'char',
			'smallint'          => 'smallint',
			'integer'           => 'int',
			'bigint'            => 'bigint',
			'real'              => 'float',
			'double precision'  => 'double',
			'numeric'           => 'decimal',
			'decimal'           => 'decimal',
			'boolean'           => 'boolean',
			'text'              => 'text',
			'bytea'             => 'bytea',
			'date'              => 'date',
			'time'              => 'time',
			'timestamp'         => 'timestamp',
			'timestamp without time zone' => 'timestamp',
			'timestamp with time zone'    => 'timestamptz',
			'time without time zone'      => 'time',
			'time with time zone'         => 'timetz',
			'interval'          => 'interval',
			'uuid'              => 'uuid',
			'json'              => 'json',
			'jsonb'             => 'jsonb',
		);

		return $type_map[ $type ] ?? $type;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_index( string $table, string $index_name ) : bool {
		$sql	= "SELECT i.relname AS index_name FROM pg_class t JOIN pg_index ix 
			ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid 
			WHERE t.relname = ? AND i.relname = ? LIMIT 1;";
		
		$result	= $this->dbal->get_var( $sql, [$table, $index_name ] );
		
		return $result ? true : false;
	}

	/**
	 * Get all tables in the database.
	 */
	protected function sql_all_tables(): string {
		return "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name";
	}

	/**
	 * Check if a table exists.
	 */
	protected function sql_table_exists( string $table ): string {
		return sprintf(
			"SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '%s' LIMIT 1",
			addslashes( $table )
		);
	}

	/**
	 * Check if a column exists.
	 */
	protected function sql_column_exists( string $table, string $column ): string {
		return sprintf(
			"SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s' AND column_name = '%s' LIMIT 1",
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
				c.column_name,
				c.udt_name as column_type,
				c.is_nullable,
				c.column_default,
				CASE WHEN pg_get_serial_sequence(t.table_name, c.column_name) IS NOT NULL THEN true ELSE false END as is_auto_increment
			FROM information_schema.columns c
			JOIN information_schema.tables t ON c.table_name = t.table_name AND c.table_schema = t.table_schema
			WHERE c.table_schema = 'public' AND c.table_name = '%s'
			ORDER BY c.ordinal_position",
			addslashes( $table )
		);
	}

	/**
	 * Get indexes.
	 */
	protected function sql_indexes( string $table ): string {
		return sprintf(
			"SELECT 
				i.indexname as index_name,
				a.attname as column_name,
				NOT ix.indisunique as is_unique,
				a.attnum as seq_in_index
			FROM pg_indexes pi
			JOIN pg_class t ON t.relname = pi.tablename
			JOIN pg_class i ON i.relname = pi.indexname
			JOIN pg_index ix ON ix.indexrelid = i.oid
			JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
			WHERE pi.schemaname = 'public' AND pi.tablename = '%s' AND pi.indexname NOT LIKE '%%_pkey'
			ORDER BY pi.indexname, a.attnum",
			addslashes( $table )
		);
	}

	/**
	 * Get primary key.
	 */
	protected function sql_primary_key( string $table ): string {
		return sprintf(
			"SELECT 
				a.attname as column_name,
				a.attnum as seq_in_index
			FROM pg_index i
			JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
			JOIN pg_class t ON t.oid = i.indrelid
			WHERE i.indisprimary AND t.relname = '%s'
			ORDER BY a.attnum",
			addslashes( $table )
		);
	}

	/**
	 * Get foreign keys.
	 */
	protected function sql_foreign_keys( string $table ): string {
		return sprintf(
			"SELECT 
				tc.constraint_name,
				kcu.column_name,
				ccu.table_name AS referenced_table,
				ccu.column_name AS referenced_column
			FROM information_schema.table_constraints AS tc
			JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
			JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
			WHERE tc.constraint_type = 'FOREIGN KEY' AND kcu.table_name = '%s'
			ORDER BY tc.constraint_name, kcu.ordinal_position",
			addslashes( $table )
		);
	}

	/**
	 * Get unique constraints.
	 */
	protected function sql_unique_constraints( string $table ): string {
		return sprintf(
			"SELECT 
				tc.constraint_name,
				kcu.column_name,
				kcu.ordinal_position as seq_in_index
			FROM information_schema.table_constraints tc
			JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
			WHERE tc.constraint_type = 'UNIQUE' AND kcu.table_name = '%s' AND tc.table_schema = 'public'
			ORDER BY tc.constraint_name, kcu.ordinal_position",
			addslashes( $table )
		);
	}

	/**
	 * Get check constraints.
	 */
	protected function sql_check_constraints( string $table ): string {
		return sprintf(
			"SELECT 
				constraint_name,
				check_clause as definition
			FROM information_schema.check_constraints
			WHERE constraint_schema = 'public'
			AND constraint_name IN (
				SELECT constraint_name FROM information_schema.table_constraints
				WHERE table_name = '%s' AND constraint_type = 'CHECK'
			)",
			addslashes( $table )
		);
	}

	/**
	 * Get table metadata.
	 */
	protected function sql_table_metadata( string $table ): string {
		return sprintf(
			"SELECT 
				NULL as engine,
				(SELECT datcollate FROM pg_database WHERE datname = current_database()) as charset,
				NULL as collation,
				n_live_tup as row_count,
				obj_description((SELECT oid FROM pg_class WHERE relname = '%s'), 'pg_class') as comment
			FROM pg_stat_user_tables
			WHERE relname = '%s'",
			addslashes( $table ),
			addslashes( $table )
		);
	}

	/**
	 * Override get_table_metadata to handle PostgreSQL specifics.
	 */
	public function get_table_metadata( string $table ): array {
		$rows = $this->execute_query( $this->sql_table_metadata( $table ) );

		if ( empty( $rows ) ) {
			return array();
		}

		$row = $rows[0];

		return array(
			'engine'    => null,
			'charset'   => $row['charset'] ?? null,
			'collation' => null,
			'row_count' => (int) ( $row['row_count'] ?? 0 ),
			'comment'   => $row['comment'] ?? '',
		);
	}

	/**
	 * Get protocol version.
	 */
	public function get_protocol_version() {
		return '3';
	}

	/**
	 * Get server version.
	 */
	public function get_server_version(): string {
		$rows = $this->dbal->get_results( "SHOW server_version" );
		if ( ! empty( $rows ) ) {
			return (string) $rows[0]['server_version'];
		}
		return 'unknown';
	}

	/**
	 * Get engine type.
	 */
	public function get_engine_type(): string {
		return 'pgsql';
	}
}