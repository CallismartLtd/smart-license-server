<?php
/**
 * Authentication challenges schema.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Definitions;

use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\ColumnType;
use Callismart\DBPrism\Utils\Constraint;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Schema\TableName;

/**
 * Stores short-lived, single-use authentication and enrollment challenges.
 */
final class AuthChallengesSchema implements DatabaseSchemaInterface {
	public static function get_label() : string {
		return 'Authentication Challenges';
	}

	public static function get_description() : string {
		return 'Stores short-lived authentication and factor-enrollment challenges.';
	}

	public static function get_table_name() : string {
		return TableName::AUTH_CHALLENGES->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )->type( ColumnType::CHAR )->size( 64 )->required(),
			Column::make( 'user_id' )->type( ColumnType::BIG_INT )->unsigned(),
			Column::make( 'purpose' )->type( ColumnType::VARCHAR )->size( 32 )->required(),
			Column::make( 'payload' )->type( ColumnType::LONG_TEXT )->required(),
			Column::make( 'attempts' )->type( ColumnType::INTEGER )->unsigned()->default( 0 )->required(),
			Column::make( 'expires_at' )->type( ColumnType::DATETIME )->required(),
			Column::make( 'created_at' )->type( ColumnType::DATETIME )->required(),
		];
	}

	public static function get_constraints() : array {
		return [
			Constraint::primary( 'smliser_authchallenges_primary' )->on( 'id' ),
			Constraint::index( 'smliser_authchallenges_expiry' )->on( 'expires_at' ),
			Constraint::index( 'smliser_authchallenges_user_purpose' )->on( 'user_id', 'purpose' ),
		];
	}
}
