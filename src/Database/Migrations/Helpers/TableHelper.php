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

defined( 'SMLISER_ABSPATH' ) || exit;

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
	 * Database instance.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * SQL builder instance.
	 *
	 * @var SQLBuilder
	 */
	private SQLBuilder $builder;

	/**
	 * Database engine type.
	 *
	 * @var string
	 */
	private string $engine;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param Database    $database
	 * @param SQLBuilder  $builder
	 * @param string      $table
	 */
	public function __construct( Database $database, SQLBuilder $builder, string $table ) {
		$this->database = $database;
		$this->builder  = $builder;
		$this->engine   = $database->get_engine_type();
		$this->table    = $table;
	}

	/**
	 * Rename table.
	 *
	 * @param string $new_name
	 *
	 * @return self
	 */
	public function rename( string $new_name ) : self {

		$sql = match ( $this->engine ) {
			'mysql', 'pgsql', 'sqlite'
				=> "ALTER TABLE {$this->quote($this->table)} RENAME TO {$this->quote($new_name)}",

			default
				=> throw new \Exception( "Unsupported engine: {$this->engine}" )
		};

		$this->database->exec( $sql );
		$this->table = $new_name;

		return $this;
	}

	/**
	 * Add column.
	 *
	 * @param string $name
	 * @param string $type
	 * @param string $definition
	 * @param string $position
	 *
	 * @return self
	 */
	public function addColumn(
		string $name,
		string $type,
		string $definition = '',
		string $position = ''
	) : self {

		$sql = $this->builder
			->alter_table( $this->table )
			->add_column( $name, $type, $definition, $position )
			->build();

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Drop column.
	 *
	 * @param string $name
	 *
	 * @return self
	 */
	public function dropColumn( string $name ) : self {

		$sql = $this->builder
			->alter_table( $this->table )
			->drop_column( $name )
			->build();

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Rename column.
	 *
	 * @param string $old
	 * @param string $new
	 *
	 * @return self
	 */
	public function renameColumn( string $old, string $new ) : self {

		$sql = $this->builder
			->alter_table( $this->table )
			->rename_column( $old, $new )
			->build();

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Modify column.
	 *
	 * @param string $name
	 * @param string $type
	 * @param string $definition
	 *
	 * @return self
	 */
	public function modifyColumn(
		string $name,
		string $type,
		string $definition = ''
	) : self {

		$sql = $this->builder
			->alter_table( $this->table )
			->modify_column( $name, $type, $definition )
			->build();

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Add index.
	 *
	 * @param string       $name
	 * @param string|array $columns
	 * @param string       $type
	 *
	 * @return self
	 */
	public function addIndex( string $name, $columns, string $type = '' ) : self {

		$sql = $this->builder->add_index( $name, $columns, $type );

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Drop index.
	 *
	 * @param string $name
	 *
	 * @return self
	 */
	public function dropIndex( string $name ) : self {

		$sql = $this->builder->drop_index( $name );

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Truncate table.
	 *
	 * @return self
	 */
	public function truncate() : self {

		$sql = match ( $this->engine ) {
			'mysql', 'pgsql'
				=> "TRUNCATE TABLE {$this->quote($this->table)}",

			'sqlite'
				=> "DELETE FROM {$this->quote($this->table)}",

			default
				=> throw new \Exception( "Unsupported engine: {$this->engine}" )
		};

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Set MySQL engine.
	 *
	 * @param string $engine
	 *
	 * @return self
	 */
	public function setEngine( string $engine ) : self {

		if ( $this->engine !== 'mysql' ) {
			throw new \Exception( 'Setting engine is only supported in MySQL' );
		}

		$sql = "ALTER TABLE {$this->quote($this->table)} ENGINE = {$engine}";
		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Set charset/collation (MySQL only).
	 *
	 * @param string $charset
	 * @param string $collation
	 *
	 * @return self
	 */
	public function setCharset( string $charset, string $collation = '' ) : self {

		if ( $this->engine !== 'mysql' ) {
			throw new \Exception( 'Setting charset is only supported in MySQL' );
		}

		$sql = "ALTER TABLE {$this->quote($this->table)} CONVERT TO CHARACTER SET {$charset}";

		if ( $collation ) {
			$sql .= " COLLATE {$collation}";
		}

		$this->database->exec( $sql );

		return $this;
	}

	/**
	 * Quote identifier.
	 *
	 * @param string $identifier
	 *
	 * @return string
	 */
	private function quote( string $identifier ) : string {
		return match ( $this->engine ) {
			'mysql'  => "`{$identifier}`",
			'pgsql'  => "\"{$identifier}\"",
			'sqlite' => "\"{$identifier}\"",
			default  => $identifier
		};
	}
}