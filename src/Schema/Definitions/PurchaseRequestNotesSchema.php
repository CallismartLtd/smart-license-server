<?php
/**
 * Purchase Request Notes Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Definitions
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores what reviewers write on a purchase request while processing it.
 *
 * `sent_to_buyer` records whether the note was also emailed to the buyer;
 * a note that was not is internal. Notes are never edited.
 */
class PurchaseRequestNotesSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'Purchase Request Notes';
	}

	public static function get_description() : string {
		return 'Stores reviewers\' notes on purchase requests, and whether each was emailed to the buyer.';
	}

	public static function get_table_name() : string {
		return TableName::PURCHASE_REQUEST_NOTES->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'request_id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->required(),

			Column::make( 'author_id' )
				->type( ColumnType::BIG_INT )
				->unsigned(),

			Column::make( 'body' )
				->type( ColumnType::TEXT )
				->required(),

			Column::make( 'sent_to_buyer' )
				->type( ColumnType::BOOLEAN )
				->default( 0 )
				->required(),

			Column::make( 'created_at' )
				->type( ColumnType::DATETIME )
				->required(),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::index( "{$prefx}request_id_index" )->on( 'request_id' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_purchase_request_notes_';
	}
}
