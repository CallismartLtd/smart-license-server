<?php
/**
 * Temporary before/after snapshots used by reversible data migrations.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Migrations\Operators\TableOperator;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\ColumnType;
use Callismart\DBPrism\Utils\Constraint;
use SmartLicenseServer\Schema\SchemaRegistry;
use Throwable;

/** Keeps exact pre-migration values until a corresponding down() succeeds. */
final class MigrationSnapshotStore {
	private string $table;

	public function __construct( private Database $db ) {
		$this->table = SchemaRegistry::instance()->prefix() . 'migration_snapshots';
	}

	/** Save one before/after record, idempotently, before the migration changes it. */
	public function capture( string $migration, string $key, array $before, array $after ) : void {
		$this->ensure_table();
		$record_key = hash( 'sha256', $migration . "\0" . $key );
		$existing = $this->db->get_row(
			'SELECT id FROM ' . $this->table . ' WHERE record_key = ? LIMIT 1',
			[ $record_key ]
		);
		if ( $existing ) {
			return;
		}

		$inserted = $this->db->insert( $this->table, [
			'record_key' => $record_key,
			'migration'  => $migration,
			'payload'    => json_encode( [ 'before' => $before, 'after' => $after ], JSON_THROW_ON_ERROR ),
		] );
		if ( false === $inserted ) {
			throw new \RuntimeException( sprintf( 'Could not save rollback data for %s: %s', $migration, $this->db->get_last_error() ) );
		}
	}

	/** Mark even a no-op migration as snapshot-capable. */
	public function complete( string $migration ) : void {
		$this->capture( $migration, '__complete__', [ 'kind' => 'complete' ], [ 'kind' => 'complete' ] );
	}

	/** @return array<int,array{before:array<string,mixed>,after:array<string,mixed>}> */
	public function rows( string $migration ) : array {
		if ( ! $this->has_table() ) {
			throw new \RuntimeException( sprintf( 'Cannot safely revert %s: rollback snapshots were not recorded when it ran.', $migration ) );
		}

		$results = $this->db->get_results(
			'SELECT record_key, payload FROM ' . $this->table . ' WHERE migration = ?',
			[ $migration ]
		) ?: [];
		$complete = false;
		$rows = [];
		foreach ( $results as $result ) {
			try {
				$payload = json_decode( (string) $result['payload'], true, 32, JSON_THROW_ON_ERROR );
			} catch ( Throwable $e ) {
				throw new \RuntimeException( sprintf( 'Rollback snapshot for %s is corrupt.', $migration ), 0, $e );
			}
			if ( '__complete__' === $this->key_for_record( (string) $result['record_key'], $migration ) ) {
				$complete = true;
				continue;
			}
			if ( isset( $payload['before'], $payload['after'] ) && is_array( $payload['before'] ) && is_array( $payload['after'] ) ) {
				$rows[] = [ 'before' => $payload['before'], 'after' => $payload['after'] ];
			}
		}
		if ( ! $complete ) {
			throw new \RuntimeException( sprintf( 'Cannot safely revert %s: its rollback snapshot is incomplete.', $migration ) );
		}
		return $rows;
	}

	/** Remove this migration's snapshots and remove the helper table when unused. */
	public function clear( string $migration ) : void {
		if ( ! $this->has_table() ) {
			return;
		}
		$deleted = $this->db->execute( 'DELETE FROM ' . $this->table . ' WHERE migration = ?', [ $migration ] );
		if ( false === $deleted ) {
			throw new \RuntimeException( sprintf( 'Could not clear rollback snapshots for %s: %s', $migration, $this->db->get_last_error() ) );
		}
		$remaining = $this->db->get_row( 'SELECT id FROM ' . $this->table . ' LIMIT 1', [] );
		if ( ! $remaining ) {
			( new TableOperator( $this->db, $this->table ) )->drop();
		}
	}

	private function ensure_table() : void {
		( new TableOperator( $this->db, $this->table ) )->create( static function ( $table ) : void {
			$table
				->add_column( Column::make( 'id' )->type( ColumnType::BIG_INT )->unsigned()->auto_increment()->required() )
				->add_column( Column::make( 'record_key' )->type( ColumnType::CHAR )->size( 64 )->required() )
				->add_column( Column::make( 'migration' )->type( ColumnType::VARCHAR )->size( 191 )->required() )
				->add_column( Column::make( 'payload' )->type( ColumnType::LONG_TEXT )->required() )
				->add_constraint( Constraint::primary( 'smliser_migsnapshot_primary' )->on( 'id' ) )
				->add_constraint( Constraint::unique( 'smliser_migsnapshot_key' )->on( 'record_key' ) );
		} );
	}

	private function has_table() : bool {
		return ( new Inspector( $this->db ) )->table_exists( $this->table );
	}

	/** The marker is deliberately distinguished by its deterministic digest. */
	private function key_for_record( string $record_key, string $migration ) : string {
		return hash_equals( hash( 'sha256', $migration . "\0__complete__" ), $record_key ) ? '__complete__' : '';
	}
}
