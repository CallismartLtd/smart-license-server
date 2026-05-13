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
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;

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
	 * 
	 * @param string|Column $column The new column name.
	 * @param array{
     *     name: string,
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     unsigned?: bool,
     *     nullable?: bool,
     *     auto_increment?: bool,
     *     default?: mixed,
     *     comment?: string
     * } $definitions
	 * 
	 * @param array{
     *     type: string,
     *     name?: string,
     *     columns?: array<int, string>,
     *     references_table?: string,
     *     references_columns?: array<int, string>,
     *     on_delete?: string,
     *     on_update?: string
     * } $constraints
	 * @return static Fluent
	 * @throws \Exception
	 */
	public function add(
		string|Column $column,
		array $definitions = [],
		array $constraints = []
	) : static {
	
		if ( $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' already exists in '{$this->table}'" );
		}

		$column		= is_string( $column ) ? Column::make( $column ) : $column;
		$column		= $this->build_column_definitions( $column, $definitions );
		$constraint	= $this->build_constraint( $constraints );

		$sql = $this->builder
			->alter_table( $this->table )
			->add_column( $column, $constraint )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
	}

	/**
	 * Drop column.
	 */
	public function drop( string $column ) : static {

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
	public function rename( string $old, string $new ) : static {

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
	 * Modify column.
	 * 
	 * @param string|Column $column The column name.
	 * @param array{
     *     name: string,
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     unsigned?: bool,
     *     nullable?: bool,
     *     auto_increment?: bool,
     *     default?: mixed,
     *     comment?: string
     * } $definitions
	 * @param array{
     *     type: string,
     *     name?: string,
     *     columns?: array<int, string>,
     *     references_table?: string,
     *     references_columns?: array<int, string>,
     *     on_delete?: string,
     *     on_update?: string
     * } $constraints
	 * 
	 * @return static Fluent.
	 */
	public function modify(
		string|Column $column,
		array $definitions	= [],
		array $constraints	= []
	) : static {

		if ( ! $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' does not exist in '{$this->table}'" );
		}

		$column		= is_string( $column ) ? Column::make( $column ) : $column;
		$column		= $this->build_column_definitions( $column, $definitions );
		$constraint	= $this->build_constraint( $constraints );

		$sql = $this->builder
			->alter_table( $this->table )
			->modify_column( $column, $constraint )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
	}

	/**
	 * Change column type.
	 * 
	 * @param string $column The column name.
	 * @param int $type The column type @see \SmartLicenseServer\Database\Schema\Helpers\ColumnType
	 */
	public function changeType( string $column, int $type ) : static {

		if ( ! $this->exists( $column ) ) {
			throw new \Exception( "Column '{$column}' does not exist in '{$this->table}'" );
		}

		if ( 0 === $type ) {
			throw new \Exception( "Column type is required for this operation." );
		}

		$column	= Column::make( $column )->type( $type );

		$sql = $this->builder
			->alter_table( $this->table )
			->modify_column( $column )
			->build();

		$this->database->exec( $sql );
		$this->builder->reset();

		return $this;
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

	/*
	|-------------------
	| PRIVATE HELPERS
	|-------------------
	*/

	/**
	 * @param Column $column 
	 * @param array{
     *     name: string,
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     unsigned?: bool,
     *     nullable?: bool,
     *     auto_increment?: bool,
     *     default?: mixed,
     *     comment?: string
     * } $definitions
	 */
	protected function build_column_definitions( Column $column, array $definitions ) : Column {
		$expected_def	= ['name', 'type', 'length', 'precision', 'scale', 
			'unsigned',  'nullable', 'auto_increment', 'default', 'comment'
		];

		foreach ( $expected_def as $def ) {
			if ( ! array_key_exists( $def, $definitions ) ) {
				continue;
			}

			if ( ! property_exists( $column, $def ) ) {
				continue;
			}

			$column->{$def}	= $definitions[ $def ];
		}

		return $column;
	}

	/**
	 * @param array{
     *     type: string,
     *     name?: string,
     *     columns?: array<int, string>,
     *     references_table?: string,
     *     references_columns?: array<int, string>,
     *     on_delete?: string,
     *     on_update?: string
     * } $definitions
	 */
	protected function build_constraint( array $definitions ) : ?Constraint {
		$expected_constr	= ['name', 'columns', 'references_table',
			'references_columns', 'on_delete', 'on_update'
		];

		if ( empty( $definitions ) || empty( $definitions['type'] ) ) {
			return null;
		}

		$constraint	= Constraint::make( $definitions['type'] );

		foreach ( $expected_constr as $def ) {
			if ( ! array_key_exists( $def, $definitions ) ) {
				continue;
			}

			if ( ! property_exists( $constraint, $def ) ) {
				continue;
			}

			$constraint->{$def}	= $definitions[ $def ];
		}

		return $constraint;
	}
}