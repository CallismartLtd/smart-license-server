<?php
/**
 * PurchaseRequestService class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Background\Jobs\Purchases\PurchaseRequestEmailJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\Providers\LocalProvider;
use SmartLicenseServer\Security\Actors\User;

/**
 * Takes and reviews purchase requests for tiers sold by the local provider.
 *
 * Taking: a request is accepted only for a tier buyers can buy now: it
 * exists, is active, is sold by the local provider, and its app is active
 * with monetization enabled. The receipt is stored in the private
 * directory, which only the app serves, under a random name. One email
 * address has at most one pending request per tier, so a buyer waiting for
 * review cannot pile up duplicates. The buyer and the admin email address
 * are told.
 *
 * Reviewing: reviewers add notes, internal or also emailed to the buyer,
 * at any time. A pending request is decided once: approving issues the
 * license (provider "local", reference "request-<ID>") and emails it;
 * rejecting needs a reason, which is emailed. A decided request is final.
 */
class PurchaseRequestService {
	use QueueAwareTrait;

	/**
	 * Largest receipt accepted, in bytes.
	 *
	 * @var int
	 */
	public const MAX_RECEIPT_BYTES = 5 * 1024 * 1024;

	/**
	 * Receipt types accepted: detected MIME type => label.
	 *
	 * @var array<string, string>
	 */
	public const RECEIPT_TYPES = [
		'image/jpeg'      => 'JPG',
		'image/png'       => 'PNG',
		'image/webp'      => 'WEBP',
		'application/pdf' => 'PDF',
	];

	/**
	 * @param FileSystem       $fs        The filesystem API.
	 * @param LicensingService $licensing Issues the license of an approved request.
	 * @param JobQueue         $job_queue Sends the emails.
	 */
	public function __construct(
		protected FileSystem $fs,
		protected LicensingService $licensing,
		JobQueue $job_queue
	) {
		$this->job_queue = $job_queue;
	}

	/**
	 * Absolute path of the receipts directory.
	 *
	 * @return string
	 */
	public static function receipts_dir() : string {
		return \SMLISER_PRIVATE_DIR . 'receipts/';
	}

	/**
	 * Get a tier buyers can buy now through the local provider.
	 *
	 * @param int $tier_id Pricing tier ID.
	 * @return PricingTier|Exception The tier, or why it cannot be bought (with a "status").
	 */
	public function get_purchasable_tier( int $tier_id ) : PricingTier|Exception {
		$tier = PricingTier::get_by_id( $tier_id );

		if ( ! $tier || LocalProvider::get_id() !== $tier->get_provider_id() ) {
			return new Exception( 'tier_not_found', 'This product is not sold here.', [ 'status' => 404 ] );
		}

		if ( ! $tier->is_active() ) {
			return new Exception( 'tier_archived', 'This option is no longer sold.', [ 'status' => 410 ] );
		}

		$monetization = Monetization::get_by_id( $tier->get_monetization_id() );
		$app          = $monetization?->get_app();

		if ( ! $monetization || ! $monetization->is_enabled() || ! ( $app instanceof AbstractHostedApp ) || ! $app->is_active() ) {
			return new Exception( 'not_for_sale', 'This product is not for sale at the moment.', [ 'status' => 410 ] );
		}

		return $tier;
	}

	/**
	 * Record a purchase request.
	 *
	 * @param int               $tier_id The tier bought.
	 * @param array{
	 *  buyer_name?: string,
	 *  buyer_email?: string,
	 *  payment_reference?: string,
	 *  note?: string,
	 *  create_account?: bool
	 * }                        $input   What the buyer submitted.
	 * @param UploadedFile|null $receipt The uploaded receipt.
	 * @param User|null         $user    The signed-in buyer; null for a guest.
	 * @param string            $ip      The buyer's IP address.
	 * @return PurchaseRequest|Exception The saved request, or why it was refused
	 *                                   (with a "status" and, when it applies, a "field_id").
	 */
	public function submit( int $tier_id, array $input, ?UploadedFile $receipt, ?User $user, string $ip ) : PurchaseRequest|Exception {
		$tier = $this->get_purchasable_tier( $tier_id );

		if ( $tier instanceof Exception ) {
			return $tier;
		}

		// A signed-in buyer buys as their account; the license follows its email.
		$email = $user ? $user->get_email() : trim( (string) ( $input['buyer_email'] ?? '' ) );
		$name  = trim( (string) ( $input['buyer_name'] ?? '' ) );
		$name  = '' === $name && $user ? $user->get_display_name() : $name;

		if ( '' === $name ) {
			return new Exception( 'missing_name', 'Enter the name the license is issued to.', [ 'status' => 400, 'field_id' => 'buyer_name' ] );
		}

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return new Exception( 'invalid_email', 'Enter a valid email address; the license is sent there.', [ 'status' => 400, 'field_id' => 'buyer_email' ] );
		}

		if ( PurchaseRequest::has_pending( $email, $tier->get_id() ) ) {
			return new Exception( 'request_pending', 'A purchase request for this option from this email address is already awaiting review. You will get an email once it is reviewed.', [ 'status' => 409 ] );
		}

		$problem = $this->check_receipt( $receipt );

		if ( null !== $problem ) {
			return $problem;
		}

		// Only a guest whose address has no account can ask for one.
		$create_account = ! $user && ! empty( $input['create_account'] ) && ! User::get_by_email( $email );

		/** @var UploadedFile $receipt */
		// Read before the move: both come from the temporary file.
		$mime         = (string) $receipt->get_detected_mime();
		$client_name  = (string) $receipt->get_client_name();
		$name_on_disk = bin2hex( random_bytes( 16 ) );
		$subdir       = gmdate( 'Y/m' );

		try {
			$stored = $receipt->move( static::receipts_dir() . $subdir, $name_on_disk );
		} catch ( \Throwable $e ) {
			return new Exception( 'receipt_not_saved', 'Your receipt could not be saved. Please try again.', [ 'status' => 500 ] );
		}

		$request = ( new PurchaseRequest() )
			->set_tier_id( $tier->get_id() )
			->set_price( (string) ( $tier->get_provider_data()['price'] ?? '' ) )
			->set_buyer_name( $name )
			->set_buyer_email( $email )
			->set_user_id( $user?->get_id() ?? 0 )
			->set_create_account( $create_account )
			->set_payment_reference( (string) ( $input['payment_reference'] ?? '' ) )
			->set_note( (string) ( $input['note'] ?? '' ) )
			->set_receipt( $subdir . '/' . basename( $stored ), $client_name, $mime )
			->set_ip_address( $ip );

		try {
			$request->save();
		} catch ( \Throwable $e ) {
			$this->fs->delete( $stored );

			return new Exception( 'request_not_saved', 'Your purchase request could not be saved. Please try again.', [ 'status' => 500 ] );
		}

		$this->email( $request, PurchaseRequestEmailJob::EVENT_RECEIVED );
		$this->email( $request, PurchaseRequestEmailJob::EVENT_ADMIN );

		return $request;
	}

	/*
	|-----------
	| REVIEWING
	|-----------
	*/

	/**
	 * Add a reviewer's note to a request, optionally emailing it to the buyer.
	 *
	 * Allowed whatever the request's status: a buyer may still need telling
	 * something after the decision.
	 *
	 * @param PurchaseRequest $request       The request.
	 * @param int             $reviewer_id   Who wrote it.
	 * @param string          $body          The note; plain text.
	 * @param bool            $send_to_buyer Whether to email it to the buyer.
	 * @return PurchaseRequestNote|Exception
	 */
	public function add_note( PurchaseRequest $request, int $reviewer_id, string $body, bool $send_to_buyer ) : PurchaseRequestNote|Exception {
		if ( '' === trim( $body ) ) {
			return new Exception( 'missing_note', 'Write the note.', [ 'status' => 400, 'field_id' => 'note' ] );
		}

		$note = PurchaseRequestNote::make( $request->get_id(), $reviewer_id, $body, $send_to_buyer );

		try {
			$note->save();
		} catch ( \Throwable $e ) {
			return new Exception( 'note_not_saved', 'The note could not be saved. Please try again.', [ 'status' => 500 ] );
		}

		if ( $send_to_buyer ) {
			$this->email( $request, PurchaseRequestEmailJob::EVENT_NOTE, [ 'note_id' => $note->get_id() ] );
		}

		return $note;
	}

	/**
	 * Approve a pending request: issue its license and email it to the buyer.
	 *
	 * Issuing follows LicensingService::issue(): the tier must still exist and
	 * be active, and the reference "request-<ID>" makes it happen once even
	 * if two reviewers approve at the same moment.
	 *
	 * @param PurchaseRequest $request     The request.
	 * @param int             $reviewer_id Who approved it.
	 * @param string          $message     Optional message to the buyer, sent with the license.
	 * @return License|Exception The license issued.
	 */
	public function approve( PurchaseRequest $request, int $reviewer_id, string $message = '' ) : License|Exception {
		if ( ! $request->is_pending() ) {
			return new Exception( 'request_decided', sprintf( 'This request was already %s.', $request->get_status() ), [ 'status' => 409 ] );
		}

		$tier = $request->get_tier();

		if ( ! $tier ) {
			return new Exception( 'tier_not_found', 'The pricing tier of this request was deleted, so no license can be issued from it. Reject the request instead.', [ 'status' => 409 ] );
		}

		$license = $this->licensing->issue(
			$tier,
			$request->get_buyer_email(),
			$request->get_buyer_name(),
			LocalProvider::get_id(),
			sprintf( 'request-%d', $request->get_id() )
		);

		if ( $license instanceof Exception ) {
			return $license;
		}

		$request->set_review( PurchaseRequest::STATUS_APPROVED, $reviewer_id, $message, (int) $license->get_id() );

		try {
			$request->save();
		} catch ( \Throwable $e ) {
			return new Exception( 'request_not_saved', sprintf( 'License #%d was issued, but the request could not be marked approved. Please try again.', $license->get_id() ), [ 'status' => 500 ] );
		}

		$this->email( $request, PurchaseRequestEmailJob::EVENT_APPROVED );

		return $license;
	}

	/**
	 * Reject a pending request and email the reason to the buyer.
	 *
	 * @param PurchaseRequest $request     The request.
	 * @param int             $reviewer_id Who rejected it.
	 * @param string          $reason      Why; shown to the buyer.
	 * @return PurchaseRequest|Exception
	 */
	public function reject( PurchaseRequest $request, int $reviewer_id, string $reason ) : PurchaseRequest|Exception {
		if ( ! $request->is_pending() ) {
			return new Exception( 'request_decided', sprintf( 'This request was already %s.', $request->get_status() ), [ 'status' => 409 ] );
		}

		if ( '' === trim( $reason ) ) {
			return new Exception( 'missing_reason', 'Tell the buyer why the request is rejected.', [ 'status' => 400, 'field_id' => 'reason' ] );
		}

		$request->set_review( PurchaseRequest::STATUS_REJECTED, $reviewer_id, $reason );

		try {
			$request->save();
		} catch ( \Throwable $e ) {
			return new Exception( 'request_not_saved', 'The request could not be rejected. Please try again.', [ 'status' => 500 ] );
		}

		$this->email( $request, PurchaseRequestEmailJob::EVENT_REJECTED );

		return $request;
	}

	/**
	 * What a request bought, as people read it: "<app> — <tier>".
	 *
	 * @param PurchaseRequest $request
	 * @return string
	 */
	public function get_product_name( PurchaseRequest $request ) : string {
		$tier = $request->get_tier();
		$app  = $tier ? Monetization::get_by_id( $tier->get_monetization_id() )?->get_app() : null;

		return sprintf(
			'%s — %s',
			$app ? $app->get_name() : 'Unknown app',
			$tier ? $tier->get_name() : sprintf( 'deleted tier #%d', $request->get_tier_id() )
		);
	}

	/**
	 * Queue an email about a request (PurchaseRequestEmailJob).
	 *
	 * @param PurchaseRequest      $request
	 * @param string               $event   A PurchaseRequestEmailJob::EVENT_* value.
	 * @param array<string, mixed> $extra   More payload, e.g. "note_id".
	 * @return void
	 */
	protected function email( PurchaseRequest $request, string $event, array $extra = [] ) : void {
		$this->dispatch_job(
			PurchaseRequestEmailJob::class,
			array_merge( [ 'request_id' => $request->get_id(), 'event' => $event ], $extra )
		);
	}

	/**
	 * Absolute path of a request's receipt, when the file is there.
	 *
	 * @param PurchaseRequest $request
	 * @return string|null
	 */
	public function get_receipt_path( PurchaseRequest $request ) : ?string {
		$relative = FileSystemHelper::sanitize_path( $request->get_receipt_file() );

		if ( '' === $request->get_receipt_file() || \is_smliser_error( $relative ) ) {
			return null;
		}

		$path = FileSystemHelper::join_path( static::receipts_dir(), $relative );

		return '' !== $path && $this->fs->is_file( $path ) ? $path : null;
	}

	/**
	 * Why an uploaded receipt is not accepted.
	 *
	 * @param UploadedFile|null $receipt
	 * @return Exception|null Null when it is accepted.
	 */
	protected function check_receipt( ?UploadedFile $receipt ) : ?Exception {
		if ( ! $receipt || ! $receipt->exists() ) {
			return new Exception( 'missing_receipt', 'Upload your payment receipt.', [ 'status' => 400, 'field_id' => 'receipt' ] );
		}

		if ( ! $receipt->is_upload_successful() ) {
			return new Exception( 'receipt_upload_failed', $receipt->get_error_message(), [ 'status' => 400, 'field_id' => 'receipt' ] );
		}

		if ( (int) $receipt->get_size() > self::MAX_RECEIPT_BYTES ) {
			return new Exception( 'receipt_too_large', sprintf( 'The receipt must be %d MB or smaller.', self::MAX_RECEIPT_BYTES / 1024 / 1024 ), [ 'status' => 413, 'field_id' => 'receipt' ] );
		}

		if ( ! isset( self::RECEIPT_TYPES[ (string) $receipt->get_detected_mime() ] ) ) {
			return new Exception( 'receipt_type', sprintf( 'The receipt must be a %s file.', implode( ', ', self::RECEIPT_TYPES ) ), [ 'status' => 415, 'field_id' => 'receipt' ] );
		}

		return null;
	}
}