<?php
/**
 * User security factors schema.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Definitions;

use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\ColumnType;
use Callismart\DBPrism\Utils\Constraint;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Schema\TableName;

/**
 * Stores security-factor state for human user accounts.
 */
final class UserSecuritySchema implements DatabaseSchemaInterface {
	public static function get_label() : string {
		return 'User Security Factors';
	}

	public static function get_description() : string {
		return 'Stores two-factor authentication settings for user accounts.';
	}

	public static function get_table_name() : string {
		return TableName::USER_SECURITY->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'user_id' )->type( ColumnType::BIG_INT )->unsigned()->required(),
			Column::make( 'email_enabled_at' )->type( ColumnType::DATETIME ),
			Column::make( 'totp_secret' )->type( ColumnType::LONG_TEXT ),
			Column::make( 'totp_enabled_at' )->type( ColumnType::DATETIME ),
			Column::make( 'totp_last_counter' )->type( ColumnType::BIG_INT )->unsigned(),
			Column::make( 'recovery_code_hashes' )->type( ColumnType::LONG_TEXT ),
			Column::make( 'updated_at' )->type( ColumnType::DATETIME )->required(),
		];
	}

	public static function get_constraints() : array {
		return [
			Constraint::primary( 'smliser_usersecurity_primary' )->on( 'user_id' ),
			Constraint::foreign_key( 'smliser_usersecurity_fk' )->on( 'user_id' )
				->references( TableName::USERS->table(), 'id' )->on_delete( 'CASCADE' )
		];
	}
}
