<?php
/**
 * PurchaseRequest class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization;

use Callismart\DBPrism\Database;
use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * A buyer's claim that they paid for a pricing tier sold by the local provider.
 *
 * The request carries the receipt and the price the buyer was shown. It is
 * pending until an admin approves it, which issues the license, or rejects
 * it with a reason. A request is never edited by the buyer after submission.
 */
class PurchaseRequest extends DataStore {
	use SanitizeAwareTrait, DatePropertyAwareTrait;

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	protected int $id = 0;

	protected int $tier_id = 0;

	protected string $price = '';

	protected string $buyer_name = '';

	protected string $buyer_email = '';

	protected int $user_id = 0;

	protected bool $create_account = false;

	protected string $payment_reference = '';

	protected string $note = '';

	protected string $receipt_file = '';

	protected string $receipt_name = '';

	protected string $receipt_mime = '';

	protected string $ip_address = '';

	protected string $status = self::STATUS_PENDING;

	protected string $review_note = '';

	protected int $reviewed_by = 0;

	protected ?DateTimeImmutable $reviewed_at = null;

	protected int $license_id = 0;

	protected ?DateTimeImmutable $created_at = null;

	protected ?DateTimeImmutable $updated_at = null;

	/*
	|---------
	| SETTERS
	|---------
	*/

	/**
	 * @param int $tier_id The pricing tier bought.
	 * @return static
	 */
	public function set_tier_id( $tier_id ) : static {
		$this->tier_id = max( 0, (int) $tier_id );
		return $this;
	}

	/**
	 * @param string $price The price the buyer was shown.
	 * @return static
	 */
	public function set_price( $price ) : static {
		$this->price = static::sanitize_text( (string) $price );
		return $this;
	}

	/**
	 * @param string $name The buyer's name.
	 * @return static
	 */
	public function set_buyer_name( $name ) : static {
		$this->buyer_name = static::sanitize_text( (string) $name );
		return $this;
	}

	/**
	 * @param string $email The buyer's email address; stored lowercase.
	 * @return static
	 */
	public function set_buyer_email( $email ) : static {
		$this->buyer_email = strtolower( trim( (string) $email ) );
		return $this;
	}

	/**
	 * @param int $user_id The signed-in buyer; 0 for a guest.
	 * @return static
	 */
	public function set_user_id( $user_id ) : static {
		$this->user_id = max( 0, (int) $user_id );
		return $this;
	}

	/**
	 * @param bool $create Whether a guest asked for an account with the license.
	 * @return static
	 */
	public function set_create_account( $create ) : static {
		$this->create_account = (bool) $create;
		return $this;
	}

	/**
	 * @param string $reference The buyer's payment reference, e.g. a transfer ID.
	 * @return static
	 */
	public function set_payment_reference( $reference ) : static {
		$this->payment_reference = static::sanitize_text( (string) $reference );
		return $this;
	}

	/**
	 * @param string $note The buyer's note to the reviewer.
	 * @return static
	 */
	public function set_note( $note ) : static {
		$this->note = static::sanitize_textarea( (string) $note );
		return $this;
	}

	/**
	 * Set the stored receipt.
	 *
	 * @param string $file Path relative to the receipts directory.
	 * @param string $name The file name the buyer uploaded.
	 * @param string $mime The detected MIME type.
	 * @return static
	 */
	public function set_receipt( string $file, string $name, string $mime ) : static {
		$this->receipt_file = $file;
		$this->receipt_name = static::sanitize_text( $name );
		$this->receipt_mime = $mime;
		return $this;
	}

	/**
	 * @param string $ip The IP address the request came from.
	 * @return static
	 */
	public function set_ip_address( $ip ) : static {
		$this->ip_address = substr( (string) $ip, 0, 45 );
		return $this;
	}

	/**
	 * Record a review.
	 *
	 * @param string $status      STATUS_APPROVED or STATUS_REJECTED.
	 * @param int    $reviewer_id The user who reviewed it.
	 * @param string $note        Why it was rejected; shown to the buyer.
	 * @param int    $license_id  The license issued, for an approval.
	 * @return static
	 * @throws \InvalidArgumentException When the status is not a review outcome.
	 */
	public function set_review( string $status, int $reviewer_id, string $note = '', int $license_id = 0 ) : static {
		if ( ! in_array( $status, [ self::STATUS_APPROVED, self::STATUS_REJECTED ], true ) ) {
			throw new \InvalidArgumentException( sprintf( '"%s" is not a review outcome.', $status ) );
		}

		$this->status      = $status;
		$this->reviewed_by = max( 0, $reviewer_id );
		$this->review_note = static::sanitize_textarea( $note );
		$this->license_id  = max( 0, $license_id );
		$this->reviewed_at = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		return $this;
	}

	/*
	|---------
	| GETTERS
	|---------
	*/

	public function get_id() : int {
		return $this->id;
	}

	public function get_tier_id() : int {
		return $this->tier_id;
	}

	/**
	 * The tier bought; null when it was deleted.
	 *
	 * @return PricingTier|null
	 */
	public function get_tier() : ?PricingTier {
		return PricingTier::get_by_id( $this->tier_id );
	}

	public function get_price() : string {
		return $this->price;
	}

	public function get_buyer_name() : string {
		return $this->buyer_name;
	}

	public function get_buyer_email() : string {
		return $this->buyer_email;
	}

	public function get_user_id() : int {
		return $this->user_id;
	}

	public function wants_account() : bool {
		return $this->create_account;
	}

	public function get_payment_reference() : string {
		return $this->payment_reference;
	}

	public function get_note() : string {
		return $this->note;
	}

	/**
	 * The receipt's path relative to the receipts directory.
	 *
	 * @return string
	 */
	public function get_receipt_file() : string {
		return $this->receipt_file;
	}

	public function get_receipt_name() : string {
		return $this->receipt_name;
	}

	public function get_receipt_mime() : string {
		return $this->receipt_mime;
	}

	public function get_ip_address() : string {
		return $this->ip_address;
	}

	public function get_status() : string {
		return $this->status;
	}

	public function is_pending() : bool {
		return self::STATUS_PENDING === $this->status;
	}

	public function get_review_note() : string {
		return $this->review_note;
	}

	public function get_reviewed_by() : int {
		return $this->reviewed_by;
	}

	public function get_reviewed_at() : ?DateTimeImmutable {
		return $this->reviewed_at;
	}

	public function get_license_id() : int {
		return $this->license_id;
	}

	public function get_created_at() : ?DateTimeImmutable {
		return $this->created_at;
	}

	public function get_updated_at() : ?DateTimeImmutable {
		return $this->updated_at;
	}

	/*
	|---------
	| READERS
	|---------
	*/

	/**
	 * Get a request by ID.
	 *
	 * @param int $id
	 * @return static|null
	 */
	public static function get_by_id( int $id ) : ?static {
		$data = $id > 0 ? static::fetch_by( 'id', $id, TableName::PURCHASE_REQUESTS->table() ) : null;

		return $data ? static::from_array( $data ) : null;
	}

	/**
	 * Get requests, newest first.
	 *
	 * @param int    $page   Page number.
	 * @param int    $limit  Requests per page.
	 * @param string $status Only requests with this status; empty for all.
	 * @return array{
	 *  items: static[],
	 *  pagination: array{page: int, limit: int, total: int, total_pages: int}
	 * }
	 */
	public static function get_all( int $page = 1, int $limit = 20, string $status = '' ) : array {
		$page   = max( 1, $page );
		$limit  = max( 1, $limit );
		$table  = TableName::PURCHASE_REQUESTS->table();
		$offset = static::$DB->calculate_query_offset( $page, $limit );

		$sql   = static::query()->select( '*' )->from( $table );
		$count = static::query()->select( 'COUNT(*)' )->from( $table );

		if ( '' !== $status ) {
			$sql->where( 'status', '=', $status );
			$count->where( 'status', '=', $status );
		}

		$sql->order_by( 'id', 'DESC' )->limit( $limit )->offset( $offset );

		$items = array_map( [ static::class, 'from_array' ], static::$DB->get_results( $sql->build(), $sql->get_bindings() ) );
		$total = (int) static::$DB->get_var( $count->build(), $count->get_bindings() );

		return [
			'items'      => $items,
			'pagination' => [
				'page'        => $page,
				'limit'       => $limit,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $limit ),
			],
		];
	}

	/**
	 * Whether the email address already has a request for the tier awaiting review.
	 *
	 * @param string $email   Buyer email address.
	 * @param int    $tier_id Pricing tier ID.
	 * @return bool
	 */
	public static function has_pending( string $email, int $tier_id ) : bool {
		$sql = static::query()->select( 'id' )->from( TableName::PURCHASE_REQUESTS->table() )
			->where( 'buyer_email', '=', strtolower( trim( $email ) ) )
			->where( 'tier_id', '=', $tier_id )
			->where( 'status', '=', self::STATUS_PENDING )
			->limit( 1 );

		return (bool) static::$DB->get_var( $sql->build(), $sql->get_bindings() );
	}

	/**
	 * Number of requests awaiting review.
	 *
	 * @return int
	 */
	public static function count_pending() : int {
		$sql = static::query()->select( 'COUNT(*)' )->from( TableName::PURCHASE_REQUESTS->table() )
			->where( 'status', '=', self::STATUS_PENDING );

		return (int) static::$DB->get_var( $sql->build(), $sql->get_bindings() );
	}

	/*
	|---------
	| WRITERS
	|---------
	*/

	/**
	 * Save the request.
	 *
	 * @return bool
	 * @throws DatabaseException When the database refuses the write.
	 */
	public function save() : bool {
		return (bool) static::$DB->transactional( function ( Database $db ) {
			$now   = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$table = TableName::PURCHASE_REQUESTS->table();
			$data  = [
				'tier_id'           => $this->tier_id,
				'price'             => $this->price,
				'buyer_name'        => $this->buyer_name,
				'buyer_email'       => $this->buyer_email,
				'user_id'           => $this->user_id ?: null,
				'create_account'    => $this->create_account ? 1 : 0,
				'payment_reference' => $this->payment_reference,
				'note'              => $this->note,
				'receipt_file'      => $this->receipt_file,
				'receipt_name'      => $this->receipt_name,
				'receipt_mime'      => $this->receipt_mime,
				'ip_address'        => $this->ip_address,
				'status'            => $this->status,
				'review_note'       => $this->review_note,
				'reviewed_by'       => $this->reviewed_by ?: null,
				'reviewed_at'       => $this->reviewed_at?->format( 'Y-m-d H:i:s' ),
				'license_id'        => $this->license_id ?: null,
				'updated_at'        => $now->format( 'Y-m-d H:i:s' ),
			];

			if ( $this->id ) {
				$result = $db->update( $table, $data, [ 'id' => $this->id ] );
			} else {
				$data['created_at'] = $now->format( 'Y-m-d H:i:s' );
				$result             = $db->insert( $table, $data );
			}

			if ( false === $result ) {
				throw new DatabaseException( $this->id ? 'update_failed' : 'insert_failed', $db->get_last_error() );
			}

			if ( ! $this->id ) {
				$this->id         = (int) $result;
				$this->created_at = $now;
			}

			$this->updated_at = $now;

			return true;
		} );
	}

	/*
	|----------
	| HYDRATION
	|----------
	*/

	/**
	 * @param array<string, mixed> $data A database row.
	 * @return static
	 */
	public static function from_array( array $data ) : static {
		$static = new static();

		$static->id                = (int) ( $data['id'] ?? 0 );
		$static->tier_id           = (int) ( $data['tier_id'] ?? 0 );
		$static->price             = (string) ( $data['price'] ?? '' );
		$static->buyer_name        = (string) ( $data['buyer_name'] ?? '' );
		$static->buyer_email       = (string) ( $data['buyer_email'] ?? '' );
		$static->user_id           = (int) ( $data['user_id'] ?? 0 );
		$static->create_account    = (bool) ( $data['create_account'] ?? false );
		$static->payment_reference = (string) ( $data['payment_reference'] ?? '' );
		$static->note              = (string) ( $data['note'] ?? '' );
		$static->receipt_file      = (string) ( $data['receipt_file'] ?? '' );
		$static->receipt_name      = (string) ( $data['receipt_name'] ?? '' );
		$static->receipt_mime      = (string) ( $data['receipt_mime'] ?? '' );
		$static->ip_address        = (string) ( $data['ip_address'] ?? '' );
		$static->status            = (string) ( $data['status'] ?? self::STATUS_PENDING );
		$static->review_note       = (string) ( $data['review_note'] ?? '' );
		$static->reviewed_by       = (int) ( $data['reviewed_by'] ?? 0 );
		$static->license_id        = (int) ( $data['license_id'] ?? 0 );
		$static->set_date_prop( $data['reviewed_at'] ?? null, 'reviewed_at' );
		$static->set_date_prop( $data['created_at'] ?? null, 'created_at' );
		$static->set_date_prop( $data['updated_at'] ?? null, 'updated_at' );

		return $static;
	}
}