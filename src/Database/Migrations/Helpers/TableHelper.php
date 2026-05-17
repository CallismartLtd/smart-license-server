<?php
/**
 * Table Helper class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Query\SQLBuilder;

/**
 * Table-level migration orchestrator.
 *
 * Responsibilities:
 * - Delegates SQL generation to SQLBuilder
 * - Executes SQL via Database abstraction
 * - Keeps migration logic DB-agnostic
 *
 * @since 0.2.0
 */
class TableHelper {

	/**
	 * Database engine type.
	 *
	 * @var string
	 */
	private string $engine;

	/**
	 * Constructor.
	 *
	 * @param Database    $dbal
	 * @param SQLBuilder  $queryBuilder
	 * @param string      $table
	 */
	public function __construct( 
		private Database $dbal, 
		private SQLBuilder $queryBuilder, 
		private string $table 
	) {
		$this->engine   = $dbal->get_driver();
	}

	/**
	 * Rename this table.
	 *
	 * @param string $new_name
	 *
	 * @return static
	 */
	public function rename( string $new_name ) : static {
		$sql	= $this->queryBuilder
			->alter_table( $this->table )
			->rename( $new_name );
		$this->dbal->exec( $sql->build() );

		return $this;
	}

	/**
	 * Begin column operation.
	 *
	 * @return ColumnHelper
	 */
	public function column() : ColumnHelper {
		return new ColumnHelper(
			$this->dbal,
			$this->queryBuilder,
			$this->table
		);
	}

	/**
	 * Begin constraint operation on table.
	 * 
	 * @return ConstraintHelper
	 */
	public function constraint() {
		return new ConstraintHelper(
			$this->dbal,
			$this->queryBuilder,
			$this->table
		);
	}

	/**
	 * Truncate table.
	 *
	 * @return static
	 */
	public function truncate( bool $restart = true, bool $cascade = false ) : static {
		$sql = $this->queryBuilder
			->truncate_table( $this->table )
			->restart_identity( $restart )
			->cascade( $cascade );

		$this->dbal->exec( $sql->build() );
		return $this;
	}

	/**
	 * Drop this table.
	 * 
	 * @param bool $exists_check
	 * @return static Fluent
	 */
	public function drop( bool $exists_check = true ) : static {

		$sql	= $this->queryBuilder
			->drop_table( $this->table );
		if ( $exists_check ) {
			$sql->if_exists();
		}

		$this->dbal->exec( $sql->build() );

		return $this;

	}

	/**
	 * Drop index.
	 */
	public function drop_index( string $name ) :static {
		$sql	= $this->queryBuilder
			->alter_table( $this->table )
			->drop_index( $name );
		
		$this->dbal->exec( $sql->build() );

		return $this;

	}
}