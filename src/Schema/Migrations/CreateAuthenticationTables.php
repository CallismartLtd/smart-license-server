<?php
/**
 * Create authentication factor and challenge tables.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Migrations\AbstractMigration;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Schema\TableName;

/** Adds the tables used by MFA factors, passkeys, and pending ceremonies. */
final class CreateAuthenticationTables extends AbstractMigration {
	public static function get_description() : string {
		return 'Create user security, passkey, and authentication challenge tables';
	}

	public function up() : void {
		$schema   = SchemaRegistry::instance();
		$inspector = new Inspector( $this->database() );

		foreach ( [ TableName::USER_SECURITY, TableName::USER_PASSKEYS, TableName::AUTH_CHALLENGES ] as $name ) {
			$table = $schema->get_table( $name );
			if ( null === $table || $inspector->table_exists( $table->get_name() ) ) {
				continue;
			}

			$query = \smliserQueryBuilder( $this->database()->get_driver() )
				->create_table( $table->get_name() )
				->add_columns( $table->get_columns() )
				->add_constraints( $table->get_constraints() );

			$sql = $query->build() . $this->database()->get_charset_collate();
			if ( ! $this->database()->exec( $sql ) ) {
				throw new \RuntimeException( sprintf( 'Could not create authentication table "%s": %s', $table->get_name(), $this->database()->get_last_error() ) );
			}
		}
	}
}
