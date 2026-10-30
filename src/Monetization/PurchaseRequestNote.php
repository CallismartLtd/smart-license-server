<?php
/**
 * PurchaseRequestNote class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * A reviewer's note on a purchase request.
 *
 * Internal unless it was also emailed to the buyer (is_sent_to_buyer()).
 * A note is written once and never edited, so the timeline of a request
 * is what the reviewers and the buyer actually saw.
 */
class PurchaseRequestNote extends DataStore {
	use SanitizeAwareTrait, DatePropertyAwareTrait;

	protected int $id = 0;

	protected int $request_id = 0;

	protected int $author_id = 0;

	protected string $body = '';

	protected bool $sent_to_buyer = false;

	protected ?DateTimeImmutable $created_at = null;

	/**
	 * Create an unsaved note.
	 *
	 * @param int    $request_id    The purchase request.
	 * @param int    $author_id     The reviewer who wrote it.
	 * @param string $body          The note; plain text.
	 * @param bool   $sent_to_buyer Whether it is also emailed to the buyer.
	 * @return static
	 */
	public static function make( int $request_id, int $author_id, string $body, bool $sent_to_buyer ) : static {
		$note = new static();

		$note->request_id    = max( 0, $request_id );
		$note->author_id     = max( 0, $author_id );
		$note->body          = static::sanitize_textarea( $body );
		$note->sent_to_buyer = $sent_to_buyer;

		return $note;
	}

	/*
	|---------
	| GETTERS
	|---------
	*/

	public function get_id() : int {
		return $this->id;
	}

	public function get_request_id() : int {
		return $this->request_id;
	}

	public function get_author_id() : int {
		return $this->author_id;
	}

	public function get_body() : string {
		return $this->body;
	}

	public function is_sent_to_buyer() : bool {
		return $this->sent_to_buyer;
	}

	public function get_created_at() : ?DateTimeImmutable {
		return $this->created_at;
	}

	/*
	|---------
	| READERS
	|---------
	*/

	/**
	 * Get a note by ID.
	 *
	 * @param int $id
	 * @return static|null
	 */
	public static function get_by_id( int $id ) : ?static {
		$data = $id > 0 ? static::fetch_by( 'id', $id, TableName::PURCHASE_REQUEST_NOTES->table() ) : null;

		return $data ? static::from_array( $data ) : null;
	}

	/**
	 * The notes of a request, oldest first.
	 *
	 * @param int $request_id
	 * @return static[]
	 */
	public static function get_for_request( int $request_id ) : array {
		$sql = static::query()->select( '*' )->from( TableName::PURCHASE_REQUEST_NOTES->table() )
			->where( 'request_id', '=', $request_id )
			->order_by( 'id', 'ASC' );

		return array_map( [ static::class, 'from_array' ], static::$DB->get_results( $sql->build(), $sql->get_bindings() ) );
	}

	/*
	|---------
	| WRITERS
	|---------
	*/

	/**
	 * Save a new note. Notes are never updated.
	 *
	 * @return bool
	 * @throws DatabaseException When the database refuses the insert.
	 * @throws \LogicException When the note was saved before.
	 */
	public function save() : bool {
		if ( $this->id ) {
			throw new \LogicException( 'A purchase request note is never edited.' );
		}

		$now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$result = static::$DB->insert(
			TableName::PURCHASE_REQUEST_NOTES->table(),
			[
				'request_id'    => $this->request_id,
				'author_id'     => $this->author_id ?: null,
				'body'          => $this->body,
				'sent_to_buyer' => $this->sent_to_buyer ? 1 : 0,
				'created_at'    => $now->format( 'Y-m-d H:i:s' ),
			]
		);

		if ( false === $result ) {
			throw new DatabaseException( 'insert_failed', static::$DB->get_last_error() );
		}

		$this->id         = (int) $result;
		$this->created_at = $now;

		return true;
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

		$static->id            = (int) ( $data['id'] ?? 0 );
		$static->request_id    = (int) ( $data['request_id'] ?? 0 );
		$static->author_id     = (int) ( $data['author_id'] ?? 0 );
		$static->body          = (string) ( $data['body'] ?? '' );
		$static->sent_to_buyer = (bool) ( $data['sent_to_buyer'] ?? false );
		$static->set_date_prop( $data['created_at'] ?? null, 'created_at' );

		return $static;
	}
}
