<?php
/**
 * Create authentication factor and challenge tables.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Migrations\AbstractMigration;
use Callismart\DBPrism\Migrations\Contracts\ReversibleMigrationInterface;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Schema\TableName;

/** Adds the tables used by MFA factors, passkeys, and pending ceremonies. */
final class CreateAuthenticationTables extends AbstractMigration implements ReversibleMigrationInterface {
	public static function get_description() : string {
		return 'Create user security, passkey, and authentication challenge tables';
	}

	public function up() : void {
		$schema = SchemaRegistry::instance();
		$inspector = new Inspector( $this->database() );
		$snapshots = new MigrationSnapshotStore( $this->database() );

		foreach ( [ TableName::USER_SECURITY, TableName::USER_PASSKEYS, TableName::AUTH_CHALLENGES ] as $name ) {
			$table = $schema->get_table( $name );
			if ( null === $table || $inspector->table_exists( $table->get_name() ) ) {
				continue;
			}

			// Record ownership before DDL so an interrupted up() can resume safely.
			$snapshots->capture( self::class, 'table:' . $table->get_name(),
				[ 'kind' => 'table_absent', 'name' => $table->get_name() ],
				[ 'kind' => 'table_created', 'name' => $table->get_name() ]
			);
			$this->table( $table->get_name() )->create( static function ( $create ) use ( $table ) : void {
				$create->add_columns( $table->get_columns() )->add_constraints( $table->get_constraints() );
			} );
		}
		$snapshots->complete( self::class );
	}

	/** Drop only tables recorded as created by this migration. */
	public function down() : void {
		$snapshots = new MigrationSnapshotStore( $this->database() );
		foreach ( $snapshots->rows( self::class ) as $snapshot ) {
			if ( 'table_created' !== ( $snapshot['after']['kind'] ?? null ) ) {
				continue;
			}
			$name = (string) ( $snapshot['after']['name'] ?? '' );
			if ( '' !== $name ) {
				$this->table( $name )->drop();
			}
		}
		$snapshots->clear( self::class );
	}
}
