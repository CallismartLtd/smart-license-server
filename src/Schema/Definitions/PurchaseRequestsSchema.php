<?php
/**
 * Purchase Requests Schema definition file.
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
 * Stores what buyers submit to the local provider: a claim that they paid
 * for a pricing tier, with their receipt.
 *
 * `price` is the price the buyer was shown, kept so the reviewer checks the
 * payment against it even if the tier's price changes. `receipt_file` is
 * relative to the private receipts directory. `license_id` is set when an
 * admin approves the request.
 */
class PurchaseRequestsSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'Purchase Requests';
	}

	public static function get_description() : string {
		return 'Stores payment receipts buyers submit for review before a license is issued.';
	}

	public static function get_table_name() : string {
		return TableName::PURCHASE_REQUESTS->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'tier_id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->required(),

			Column::make( 'price' )
				->type( ColumnType::VARCHAR )
				->size( 100 ),

			Column::make( 'buyer_name' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'buyer_email' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			// The signed-in buyer; NULL for a guest.
			Column::make( 'user_id' )
				->type( ColumnType::BIG_INT )
				->unsigned(),

			// A guest asked for an account with their license.
			Column::make( 'create_account' )
				->type( ColumnType::BOOLEAN )
				->default( 0 )
				->required(),

			Column::make( 'payment_reference' )
				->type( ColumnType::VARCHAR )
				->size( 255 ),

			Column::make( 'note' )
				->type( ColumnType::TEXT ),

			Column::make( 'receipt_file' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'receipt_name' )
				->type( ColumnType::VARCHAR )
				->size( 255 ),

			Column::make( 'receipt_mime' )
				->type( ColumnType::VARCHAR )
				->size( 100 ),

			Column::make( 'ip_address' )
				->type( ColumnType::VARCHAR )
				->size( 45 ),

			Column::make( 'status' )
				->type( ColumnType::VARCHAR )
				->size( 20 )
				->default( 'pending' )
				->required(),

			// Why the request was rejected; shown to the buyer.
			Column::make( 'review_note' )
				->type( ColumnType::TEXT ),

			Column::make( 'reviewed_by' )
				->type( ColumnType::BIG_INT )
				->unsigned(),

			Column::make( 'reviewed_at' )
				->type( ColumnType::DATETIME ),

			Column::make( 'license_id' )
				->type( ColumnType::BIG_INT )
				->unsigned(),

			Column::make( 'created_at' )
				->type( ColumnType::DATETIME )
				->required(),

			Column::make( 'updated_at' )
				->type( ColumnType::DATETIME )
				->required(),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::index( "{$prefx}status_index" )->on( 'status' ),
			Constraint::index( "{$prefx}buyer_email_index" )->on( 'buyer_email' ),
			Constraint::index( "{$prefx}tier_id_index" )->on( 'tier_id' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_purchase_requests_';
	}
}