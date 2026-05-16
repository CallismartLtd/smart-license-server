<?php
/**
 * Abstract database inspector implementation
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Inspection\Providers
 */

namespace SmartLicenseServer\Database\Inspection\Providers;

use SmartLicenseServer\Database\Inspection\Contracts\InspectionInterface;
use SmartLicenseServer\Database\Adapters\DatabaseAdapterInterface;
use SmartLicenseServer\Database\DBConfigDTO;

/**
 * Base inspector class with shared logic for all database engines.
 * 
 * Concrete subclasses must implement engine-specific SQL query methods.
 */
abstract class AbstractInspector implements InspectionInterface {

	/**
	 * Database adapter instance.
	 *
	 * @var DatabaseAdapterInterface
	 */
	protected DatabaseAdapterInterface $adapter;

	/**
	 * Current database name.
	 *
	 * @var string
	 */
	protected string $database;

	/**
	 * Constructor.
	 *
	 * @param DatabaseAdapterInterface $adapter Database adapter instance.
	 * @param string $database                  Database name.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, string $database ) {
		$this->adapter   = $adapter;
		$this->database  = $database;
	}

	/**
	 * Execute a raw SQL query and return results via the adapter.
	 *
	 * @param string $query SQL query string.
	 * @return array Array of result rows.
	 */
	protected function execute_query( string $query ): array {
		return $this->adapter->get_results( $query );
	}

    /**
     * Get the database configuration object.
     * 
     * @return DBConfigDTO
     */
    public function get_config() : DBConfigDTO {
        return $this->adapter->get_config();
    }

	/**
	 * Normalize column type string to a canonical form.
	 *
	 * Converts database-specific type strings (e.g., "int(11)", "character varying")
	 * to a normalized form for consistency across engines.
	 *
	 * @param string $type Raw type from database.
	 * @return string Normalized type.
	 */
	abstract protected function normalize_type( string $type ): string;

	/**
	 * Get the SQL query to list all tables.
	 *
	 * @return string SQL query.
	 */
	abstract protected function sql_all_tables(): string;

	/**
	 * Get the SQL query to check if a table exists.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_table_exists( string $table ): string;

	/**
	 * Get the SQL query to check if a column exists.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return string SQL query.
	 */
	abstract protected function sql_column_exists( string $table, string $column ): string;

	/**
	 * Get the SQL query to retrieve column information.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_column_details( string $table ): string;

	/**
	 * Get the SQL query to retrieve indexes.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_indexes( string $table ): string;

	/**
	 * Get the SQL query to retrieve primary key.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_primary_key( string $table ): string;

	/**
	 * Get the SQL query to retrieve foreign keys.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_foreign_keys( string $table ): string;

	/**
	 * Get the SQL query to retrieve unique constraints.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_unique_constraints( string $table ): string;

	/**
	 * Get the SQL query to retrieve check constraints.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_check_constraints( string $table ): string;

	/**
	 * Get the SQL query to retrieve table metadata.
	 *
	 * @param string $table Table name.
	 * @return string SQL query.
	 */
	abstract protected function sql_table_metadata( string $table ): string;

	/**
	 * Implementation of InspectionInterface::get_all_tables()
	 */
	public function get_all_tables(): array {
		$rows   = $this->execute_query( $this->sql_all_tables() );
		$tables = array();

		foreach ( $rows as $row ) {
			$tables[] = reset( $row );
		}

		return $tables;
	}

	/**
	 * Implementation of InspectionInterface::table_exists()
	 */
	public function table_exists( string $table ): bool {
		$rows = $this->execute_query( $this->sql_table_exists( $table ) );
		return ! empty( $rows );
	}

	/**
	 * Implementation of InspectionInterface::column_exists()
	 */
	public function column_exists( string $table, string $column ): bool {
		$rows = $this->execute_query( $this->sql_column_exists( $table, $column ) );
		return ! empty( $rows );
	}

	/**
	 * Implementation of InspectionInterface::get_columns()
	 */
	public function get_columns( string $table ): array {
		$rows    = $this->execute_query( $this->sql_column_details( $table ) );
		$columns = array();

		foreach ( $rows as $row ) {
			$columns[] = $row['column_name'];
		}

		return $columns;
	}

	/**
	 * Implementation of InspectionInterface::get_column_type()
	 */
	public function get_column_type( string $table, string $column ): ?string {
		$rows = $this->execute_query( $this->sql_column_details( $table ) );

		foreach ( $rows as $row ) {
			if ( $row['column_name'] === $column ) {
				return $this->normalize_type( $row['column_type'] );
			}
		}

		return null;
	}

	/**
	 * Implementation of InspectionInterface::get_column_details()
	 */
	public function get_column_details( string $table ): array {
		$rows    = $this->execute_query( $this->sql_column_details( $table ) );
		$details = array();

		foreach ( $rows as $row ) {
			$column_name = $row['column_name'];
			$details[ $column_name ] = array(
				'type'           => $this->normalize_type( $row['column_type'] ),
				'nullable'       => (bool) $row['is_nullable'],
				'default'        => $row['column_default'] ?? null,
				'auto_increment' => (bool) ( $row['is_auto_increment'] ?? false ),
			);
		}

		return $details;
	}

	/**
	 * Implementation of InspectionInterface::has_index()
	 */
	public function has_index( string $index_name ): bool {
		// Note: This is a simple implementation. Subclasses may override
		// for database-specific behavior if needed.
		// Since we don't know which table, we'd need to search all tables.
		// For now, return false. Callers should use get_indexes() instead.
		return false;
	}

	/**
	 * Implementation of InspectionInterface::get_indexes()
	 */
	public function get_indexes( string $table ): array {
		$rows    = $this->execute_query( $this->sql_indexes( $table ) );
		$indexes = array();

		foreach ( $rows as $row ) {
			$index_name = $row['index_name'];
			if ( ! isset( $indexes[ $index_name ] ) ) {
				$indexes[ $index_name ] = array(
					'columns' => array(),
					'unique'  => (bool) $row['is_unique'],
				);
			}
			$indexes[ $index_name ]['columns'][] = $row['column_name'];
		}

		return $indexes;
	}

	/**
	 * Implementation of InspectionInterface::get_primary_key()
	 */
	public function get_primary_key( string $table ): ?array {
		$rows = $this->execute_query( $this->sql_primary_key( $table ) );

		if ( empty( $rows ) ) {
			return null;
		}

		$columns = array();
		foreach ( $rows as $row ) {
			$columns[] = $row['column_name'];
		}

		return $columns;
	}

	/**
	 * Implementation of InspectionInterface::get_foreign_keys()
	 */
	public function get_foreign_keys( string $table ): array {
		$rows          = $this->execute_query( $this->sql_foreign_keys( $table ) );
		$foreign_keys  = array();

		foreach ( $rows as $row ) {
			$constraint_name = $row['constraint_name'];
			if ( ! isset( $foreign_keys[ $constraint_name ] ) ) {
				$foreign_keys[ $constraint_name ] = array(
					'columns'              => array(),
					'referenced_table'     => $row['referenced_table'],
					'referenced_columns'   => array(),
				);
			}
			$foreign_keys[ $constraint_name ]['columns'][]            = $row['column_name'];
			$foreign_keys[ $constraint_name ]['referenced_columns'][] = $row['referenced_column'];
		}

		return $foreign_keys;
	}

	/**
	 * Implementation of InspectionInterface::has_foreign_key()
	 */
	public function has_foreign_key( string $table, string $constraint ): bool {
		$foreign_keys = $this->get_foreign_keys( $table );
		return isset( $foreign_keys[ $constraint ] );
	}

	/**
	 * Implementation of InspectionInterface::get_unique_constraints()
	 */
	public function get_unique_constraints( string $table ): array {
		$rows        = $this->execute_query( $this->sql_unique_constraints( $table ) );
		$constraints = array();

		foreach ( $rows as $row ) {
			$constraint_name = $row['constraint_name'];
			if ( ! isset( $constraints[ $constraint_name ] ) ) {
				$constraints[ $constraint_name ] = array();
			}
			$constraints[ $constraint_name ][] = $row['column_name'];
		}

		return $constraints;
	}

	/**
	 * Implementation of InspectionInterface::get_check_constraints()
	 */
	public function get_check_constraints( string $table ): array {
		$rows        = $this->execute_query( $this->sql_check_constraints( $table ) );
		$constraints = array();

		foreach ( $rows as $row ) {
			$constraint_name = $row['constraint_name'];
			$constraints[ $constraint_name ] = array(
				'definition' => $row['definition'],
			);
		}

		return $constraints;
	}

	/**
	 * Implementation of InspectionInterface::get_table_metadata()
	 */
	public function get_table_metadata( string $table ): array {
		$rows = $this->execute_query( $this->sql_table_metadata( $table ) );

		if ( empty( $rows ) ) {
			return array();
		}

		$row = $rows[0];

		return array(
			'engine'    => $row['engine'] ?? null,
			'charset'   => $row['charset'] ?? null,
			'collation' => $row['collation'] ?? null,
			'row_count' => (int) ( $row['row_count'] ?? 0 ),
			'comment'   => $row['comment'] ?? '',
		);
	}

	/**
	 * Implementation of InspectionInterface::is_column_nullable()
	 */
	public function is_column_nullable( string $table, string $column ): ?bool {
		$rows = $this->execute_query( $this->sql_column_details( $table ) );

		foreach ( $rows as $row ) {
			if ( $row['column_name'] === $column ) {
				return (bool) $row['is_nullable'];
			}
		}

		return null;
	}

	/**
	 * Implementation of InspectionInterface::get_column_default()
	 */
	public function get_column_default( string $table, string $column ) {
		$rows = $this->execute_query( $this->sql_column_details( $table ) );

		foreach ( $rows as $row ) {
			if ( $row['column_name'] === $column ) {
				return $row['column_default'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Get connection host info.
	 */
	public function get_host_info(): string {
        if ( ! empty( $this->get_config()->socket ) ) {
            return 'Localhost via UNIX socket';
        }

        $host = $this->get_config()->host ?? '127.0.0.1';
        $port = $this->get_config()->port ?? '3306';

        if ( strtolower( $host ) === 'localhost' ) {
            return 'Localhost via UNIX socket';
        }

        return sprintf( '%s:%s via TCP/IP', $host, $port );
	}

	/**
	 * Implementation of InspectionInterface::get_protocol_version()
	 */
	abstract public function get_protocol_version();

	/**
	 * Implementation of InspectionInterface::get_server_version(): string
	 */
	abstract public function get_server_version(): string;

	/**
	 * Implementation of InspectionInterface::get_engine_type(): string
	 */
	abstract public function get_engine_type(): string;
}