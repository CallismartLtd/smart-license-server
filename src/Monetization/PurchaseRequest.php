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
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * A buyer's claim that they paid for a pricing tier sold by the local provider.
 *
 * The request carries the receipt and the price the buyer was shown. It is
 * pending until an admin approves it, which issues the license, or rejects
 * it with a reason. A rejected request can still be approved later; an
 * approved one is final. A request is never edited by the buyer after
 * submission.
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
	 * Forget the stored receipt file once it is deleted (receipt retention).
	 *
	 * The uploaded name and type are kept, so the request still says what
	 * the buyer sent.
	 *
	 * @return static
	 */
	public function clear_receipt_file() : static {
		$this->receipt_file = '';
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

	/**
	 * Whether the request can be approved: it is pending, or it was rejected
	 * and is being reconsidered (e.g. a disputed payment that was resolved).
	 * An approved request is final: its license exists.
	 *
	 * @return bool
	 */
	public function can_approve() : bool {
		return in_array( $this->status, [ self::STATUS_PENDING, self::STATUS_REJECTED ], true );
	}

	/**
	 * Whether the request can be rejected: only while it is pending.
	 *
	 * @return bool
	 */
	public function can_reject() : bool {
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
	 * Get a buyer's requests, newest first.
	 *
	 * The buyer's requests are those made while signed in to their account,
	 * and, once they verified their email address, those made as a guest with
	 * it: the same rule that links licenses to an account.
	 *
	 * @param User $user  The buyer.
	 * @param int  $page  Page number.
	 * @param int  $limit Requests per page.
	 * @return array{
	 *  items: static[],
	 *  pagination: array{page: int, limit: int, total: int, total_pages: int}
	 * }
	 */
	public static function get_for_buyer( User $user, int $page = 1, int $limit = 20 ) : array {
		$page    = max( 1, $page );
		$limit   = max( 1, $limit );
		$table   = TableName::PURCHASE_REQUESTS->table();
		$sql     = static::query()->select( '*' )->from( $table );
		$count   = static::query()->select( 'COUNT(*)' )->from( $table );
		$user_id = (int) $user->get_id();
		$email   = $user->is_email_verified() ? strtolower( trim( $user->get_email() ) ) : '';

		foreach ( [ $sql, $count ] as $query ) {
			$query->where_group(
				static function ( $group ) use ( $user_id, $email ) : void {
					$group->where( 'user_id', '=', $user_id );

					if ( '' !== $email ) {
						$group->or_where( 'buyer_email', '=', $email );
					}
				}
			);
		}

		$sql->order_by( 'id', 'DESC' )->limit( $limit )->offset( static::$DB->calculate_query_offset( $page, $limit ) );

		$total = $user_id > 0 ? (int) static::$DB->get_var( $count->build(), $count->get_bindings() ) : 0;
		$rows  = $user_id > 0 ? static::$DB->get_results( $sql->build(), $sql->get_bindings() ) : [];

		return [
			'items'      => array_map( [ static::class, 'from_array' ], $rows ),
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
	 * Number of requests from an IP address awaiting review.
	 *
	 * @param string $ip
	 * @return int
	 */
	public static function count_pending_from_ip( string $ip ) : int {
		$sql = static::query()->select( 'COUNT(*)' )->from( TableName::PURCHASE_REQUESTS->table() )
			->where( 'ip_address', '=', substr( $ip, 0, 45 ) )
			->where( 'status', '=', self::STATUS_PENDING );

		return (int) static::$DB->get_var( $sql->build(), $sql->get_bindings() );
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

	/**
	 * Number of requests with each status.
	 *
	 * @return array{pending: int, approved: int, rejected: int} A status with no requests counts 0.
	 */
	public static function count_by_status() : array {
		$sql    = static::query()->select( 'status', 'COUNT(*) as total' )->from( TableName::PURCHASE_REQUESTS->table() )->group_by( 'status' );
		$counts = [ self::STATUS_PENDING => 0, self::STATUS_APPROVED => 0, self::STATUS_REJECTED => 0 ];

		foreach ( static::$DB->get_results( $sql->build(), $sql->get_bindings() ) as $row ) {
			if ( isset( $counts[ $row['status'] ?? '' ] ) ) {
				$counts[ $row['status'] ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Decided requests whose receipt file is still stored and that were
	 * reviewed before a moment, oldest first (receipt retention).
	 *
	 * A decided request without a review date (set outside the review
	 * actions) counts from when it was last updated.
	 *
	 * @param DateTimeImmutable $before Reviewed before this moment.
	 * @param int               $limit  Most requests returned.
	 * @return static[]
	 */
	public static function get_with_receipts_reviewed_before( DateTimeImmutable $before, int $limit = 100 ) : array {
		$moment = $before->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$sql    = static::query()->select( '*' )->from( TableName::PURCHASE_REQUESTS->table() )
			->where_in( 'status', [ self::STATUS_APPROVED, self::STATUS_REJECTED ] )
			->where( 'receipt_file', '!=', '' )
			->where_group(
				static function ( $group ) use ( $moment ) : void {
					$group->where( 'reviewed_at', '<', $moment )
						->or_where_group(
							static function ( $undated ) use ( $moment ) : void {
								$undated->where_null( 'reviewed_at' )->where( 'updated_at', '<', $moment );
							}
						);
				}
			)
			->order_by( 'id', 'ASC' )
			->limit( max( 1, $limit ) );

		return array_map( [ static::class, 'from_array' ], static::$DB->get_results( $sql->build(), $sql->get_bindings() ) );
	}

	/**
	 * Which of these receipt files a request still refers to.
	 *
	 * @param list<string> $files Paths relative to the receipts directory, e.g. "2026/10/<name>.pdf".
	 * @return list<string> The files that belong to a request.
	 */
	public static function get_referenced_receipt_files( array $files ) : array {
		if ( [] === $files ) {
			return [];
		}

		$sql = static::query()->select( 'receipt_file' )->from( TableName::PURCHASE_REQUESTS->table() )
			->where_in( 'receipt_file', $files );

		return array_values( array_map( 'strval', (array) static::$DB->get_col( $sql->build(), $sql->get_bindings() ) ) );
	}

	/*
	|---------
	| WRITERS
	|---------
	*/

	/**
	 * Delete the request and its notes.
	 *
	 * Only the records: the receipt file is the caller's to delete
	 * (PurchaseRequestService::delete()), and a license issued from the
	 * request is kept.
	 *
	 * @return bool
	 * @throws DatabaseException When the database refuses the delete.
	 */
	public function delete() : bool {
		if ( ! $this->id ) {
			return false;
		}

		$table        = TableName::PURCHASE_REQUESTS->table();
		$lock_query   = static::query()->select( 'id' )->from( $table )->where( 'id', '=', $this->id )->limit( 1 );
		$delete_notes = static::query()->delete( TableName::PURCHASE_REQUEST_NOTES->table() )->where( 'request_id', '=', $this->id );
		$delete_row   = static::query()->delete( $table )->where( 'id', '=', $this->id );

		return (bool) static::$DB->transactional( function ( Database $db ) use ( $lock_query, $delete_notes, $delete_row ) {
			if ( ! $db->get_row( $lock_query->lock_for_update()->build(), $lock_query->get_bindings() ) ) {
				throw new DatabaseException( 'delete_failed', 'The purchase request was already deleted.' );
			}

			// A request may have no notes, so 0 fails only with an error.
			if ( 0 === $db->execute( $delete_notes->build(), $delete_notes->get_bindings() ) && ! empty( $db->get_last_error() ) ) {
				throw new DatabaseException( 'delete_failed', $db->get_last_error() );
			}

			if ( 0 === $db->execute( $delete_row->build(), $delete_row->get_bindings() ) ) {
				throw new DatabaseException( 'delete_failed', $db->get_last_error() ?: 'The purchase request could not be deleted.' );
			}

			$this->id = 0;

			return true;
		} );
	}

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