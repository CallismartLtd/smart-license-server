<?php
/**
 * Column Helper for Fluent Column Operations
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Query\SQLBuilder;
defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Column migration orchestrator.
 *
 * Responsibilities:
 * - Validates column state
 * - Delegates SQL generation to SQLBuilder
 * - Executes via Database abstraction
 *
 * @since 0.2.0
 */
class ColumnHelper {

	private Database $database;
	private SQLBuilder $builder;
	private string $table;

	public function __construct(
		Database $database,
		SQLBuilder $builder,
		string $table
	) {
		$this->database = $database;
		$this->builder  = $builder;
		$this->table    = $table;
	}

	/**
	 * Add column.
	 */
	public function add(
		string $column,
		string $type,
		string $definition = '',
		string $position = ''
	) : self {

		if ( $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' already exists in '{$this->table}'" );
		}

		$sql = $this->builder
			->alter_table( $this->table )
			->add_column( $column, $type, $definition, $position )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
	}

	/**
	 * Drop column.
	 */
	public function drop( string $column ) : self {

		if ( ! $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' does not exist in '{$this->table}'" );
		}

		$sql = $this->builder
			->alter_table( $this->table )
			->drop_column( $column )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
	}

	/**
	 * Rename column.
	 */
	public function rename( string $old, string $new ) : self {

		if ( ! $this->exists( $old ) ) {
			throw new \Exception( "Column '{$old}' does not exist in '{$this->table}'" );
		}

		if ( $this->exists( $new ) ) {
			throw new \Exception( "Column '{$new}' already exists in '{$this->table}'" );
		}

		try {
			$sql = $this->builder
				->alter_table( $this->table )
				->rename_column( $old, $new )
				->build();

			$this->database->exec( $sql );
			$this->builder->reset();

		} catch ( \Exception $e ) {
			// Let engine-specific fallback be handled at a higher migration layer
			throw $e;
		}

		return $this;
	}

	/**
	 * Change column type.
	 */
	public function changeType(
		string $column,
		string $type,
		string $definition = ''
	) : self {

		if ( ! $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' does not exist in '{$this->table}'" );
		}

		$sql = $this->builder
			->alter_table( $this->table )
			->modify_column( $column, $type, $definition )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
	}

	/**
	 * Modify column (structured input).
	 */
	public function modify( string $column, array $spec ) : self {

		if ( ! $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' does not exist in '{$this->table}'" );
		}

		$definition = '';

		if ( array_key_exists( 'nullable', $spec ) ) {
			$definition .= $spec['nullable'] ? 'NULL' : 'NOT NULL';
		}

		if ( array_key_exists( 'default', $spec ) ) {
			$definition .= ' DEFAULT ' . (
				$spec['default'] === null ? 'NULL' : $spec['default']
			);
		}

		return $this->changeType(
			$column,
			$spec['type'],
			trim( $definition )
		);
	}

	/**
	 * Check existence.
	 */
	public function exists( string $column ) : bool {
		return $this->database->column_exists( $this->table, $column );
	}

	/**
	 * Get type.
	 */
	public function getType( string $column ) : ?string {
		return $this->database->get_column_type( $this->table, $column );
	}

	/**
	 * List columns.
	 */
	public function list() : array {
		return $this->database->get_columns( $this->table );
	}
}