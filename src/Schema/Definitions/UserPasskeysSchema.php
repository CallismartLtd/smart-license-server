<?php
/**
 * User passkeys schema.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Definitions;

use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\ColumnType;
use Callismart\DBPrism\Utils\Constraint;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Schema\TableName;

/** Stores WebAuthn credentials registered by human users. */
final class UserPasskeysSchema implements DatabaseSchemaInterface {
	public static function get_label() : string {
		return 'User Passkeys';
	}

	public static function get_description() : string {
		return 'Stores registered WebAuthn passkey credential records.';
	}

	public static function get_table_name() : string {
		return TableName::USER_PASSKEYS->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )->type( ColumnType::BIG_INT )->unsigned()->auto_increment()->required(),
			Column::make( 'user_id' )->type( ColumnType::BIG_INT )->unsigned()->required(),
			Column::make( 'credential_id_hash' )->type( ColumnType::CHAR )->size( 64 )->required(),
			Column::make( 'credential_data' )->type( ColumnType::LONG_TEXT )->required(),
			Column::make( 'label' )->type( ColumnType::VARCHAR )->size( 120 )->required(),
			Column::make( 'created_at' )->type( ColumnType::DATETIME )->required(),
			Column::make( 'last_used_at' )->type( ColumnType::DATETIME ),
			Column::make( 'revoked_at' )->type( ColumnType::DATETIME ),
		];
	}

	public static function get_constraints() : array {
		return [
			Constraint::primary( 'smliser_userpasskeys_primary' )->on( 'id' ),
			Constraint::unique( 'smliser_userpasskeys_credential_hash' )->on( 'credential_id_hash' ),
			Constraint::index( 'smliser_userpasskeys_user_id' )->on( 'user_id' ),
		];
	}
}
