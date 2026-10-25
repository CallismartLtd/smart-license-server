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
use SmartLicenseServer\Background\Jobs\Purchases\RasterizeReceiptJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Core\Pdf\PdfRasterizer;
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
 * review cannot pile up duplicates, and one IP address has at most
 * MAX_PENDING_PER_IP pending requests, which bounds the storage one source
 * can use. The buyer and the admin email address are told.
 *
 * A receipt must be a JPG, PNG, WEBP or PDF by its content, not its name.
 * An image must decode as that type within MAX_IMAGE_SIDE and
 * MAX_IMAGE_PIXELS; a PDF must be complete and name no active content
 * (PDF_ACTIVE_CONTENT).
 *
 * Reviewing: reviewers add notes, internal or also emailed to the buyer,
 * at any time. Approving issues the license (provider "local", reference
 * "request-<ID>") and emails it; rejecting needs a reason, which is
 * emailed. A pending request can be approved or rejected. A rejected one
 * can still be approved, e.g. once a disputed payment is resolved: the
 * rejection is kept in the notes. An approved request is final, since its
 * license exists; change or revoke the license instead.
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
	 * Largest image receipt, in pixels per side. Larger images are refused
	 * before a reviewer's browser has to decode them.
	 *
	 * @var int
	 */
	public const MAX_IMAGE_SIDE = 10000;

	/**
	 * Largest image receipt, in pixels (width × height).
	 *
	 * @var int
	 */
	public const MAX_IMAGE_PIXELS = 40000000;

	/**
	 * Most pending requests one IP address may have. Each carries a receipt
	 * of up to MAX_RECEIPT_BYTES, so this bounds what one source can store.
	 *
	 * @var int
	 */
	public const MAX_PENDING_PER_IP = 5;

	/**
	 * PDF names for active content: scripts, launching programs, embedded
	 * files and forms that run code. A receipt needs none of them.
	 *
	 * @var string[]
	 */
	public const PDF_ACTIVE_CONTENT = [ '/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/XFA' ];

	/**
	 * Most pages of a PDF receipt drawn for reviewers. A receipt is a page or two.
	 *
	 * @var int
	 */
	public const RECEIPT_PAGES = 5;

	/**
	 * Resolution PDF receipts are drawn at, in dots per inch: an A4 page is 1240 × 1754 pixels.
	 *
	 * @var int
	 */
	public const RECEIPT_DPI = 150;

	/**
	 * @param FileSystem       $fs         The filesystem API.
	 * @param LicensingService $licensing  Issues the license of an approved request.
	 * @param PdfRasterizer    $rasterizer Draws PDF receipts as page images for reviewers.
	 * @param JobQueue         $job_queue  Runs the emails and the receipt drawing in the background.
	 */
	public function __construct(
		protected FileSystem $fs,
		protected LicensingService $licensing,
		protected PdfRasterizer $rasterizer,
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

		if ( '' !== $ip && PurchaseRequest::count_pending_from_ip( $ip ) >= self::MAX_PENDING_PER_IP ) {
			return new Exception( 'too_many_requests', 'Too many purchase requests are awaiting review from your network. Please wait until they are reviewed.', [ 'status' => 429 ] );
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

		// Reviewers see a PDF as images of its pages, drawn by the worker (PdfRasterizer), which may have a renderer the web PHP lacks.
		if ( 'application/pdf' === $mime ) {
			$this->dispatch_job( RasterizeReceiptJob::class, [ 'request_id' => $request->get_id() ] );
		}

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
	 * Approve a pending or rejected request: issue its license and email it.
	 *
	 * Issuing follows LicensingService::issue(): the tier must still exist and
	 * be active, and the reference "request-<ID>" makes it happen once even
	 * if two reviewers approve at the same moment. Approving a rejected
	 * request first records the rejection in its notes, since the approval
	 * replaces it on the request.
	 *
	 * @param PurchaseRequest $request     The request.
	 * @param int             $reviewer_id Who approved it.
	 * @param string          $message     Optional message to the buyer, sent with the license.
	 * @return License|Exception The license issued.
	 */
	public function approve( PurchaseRequest $request, int $reviewer_id, string $message = '' ) : License|Exception {
		if ( ! $request->can_approve() ) {
			return new Exception( 'request_decided', sprintf( 'This request was already approved; its license is #%d.', $request->get_license_id() ), [ 'status' => 409 ] );
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

		if ( PurchaseRequest::STATUS_REJECTED === $request->get_status() ) {
			$this->record_rejection( $request );
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
		if ( ! $request->can_reject() ) {
			return new Exception( 'request_decided', sprintf( 'This request was already %s; only a pending request can be rejected.', $request->get_status() ), [ 'status' => 409 ] );
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
	 * Keep a rejection in the request's notes before an approval replaces it.
	 *
	 * Written as the rejecting reviewer's note, marked as sent to the buyer,
	 * because the reason was emailed to them.
	 *
	 * @param PurchaseRequest $request A rejected request.
	 * @return void
	 */
	protected function record_rejection( PurchaseRequest $request ) : void {
		$body = sprintf(
			"Rejected on %s, before this approval. Reason sent to the buyer:\n%s",
			$request->get_reviewed_at()?->format( \smliser_datetime_format() ) ?? 'an unknown date',
			$request->get_review_note()
		);

		try {
			PurchaseRequestNote::make( $request->get_id(), $request->get_reviewed_by(), $body, true )->save();
		} catch ( \Throwable $e ) {
			// The approval goes ahead: the license is issued, and losing this note loses no money.
		}
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
	 * Draw a request's PDF receipt as page images for reviewers (PdfRasterizer).
	 *
	 * At most RECEIPT_PAGES pages at RECEIPT_DPI, written next to the receipt
	 * in "<receipt>.pages/", replacing pages drawn before. Run from
	 * RasterizeReceiptJob: drawing can take seconds.
	 *
	 * @param PurchaseRequest $request
	 * @return int|Exception The number of pages drawn, or why none were.
	 */
	public function render_receipt_pages( PurchaseRequest $request ) : int|Exception {
		if ( 'application/pdf' !== $request->get_receipt_mime() ) {
			return new Exception( 'not_pdf', 'The receipt is not a PDF.' );
		}

		$path = $this->get_receipt_path( $request );

		if ( null === $path ) {
			return new Exception( 'receipt_missing', 'The receipt file is missing.' );
		}

		return $this->rasterizer->rasterize( $path, static::receipt_pages_dir( $path ), self::RECEIPT_PAGES, self::RECEIPT_DPI );
	}

	/**
	 * The page images of a request's PDF receipt (render_receipt_pages()).
	 *
	 * @param PurchaseRequest $request
	 * @return list<string> Absolute paths in page order; empty for an image
	 *                      receipt, or a PDF not drawn (yet, or at all).
	 */
	public function get_receipt_pages( PurchaseRequest $request ) : array {
		$path = 'application/pdf' === $request->get_receipt_mime() ? $this->get_receipt_path( $request ) : null;

		return null === $path ? [] : $this->rasterizer->get_pages( static::receipt_pages_dir( $path ), self::RECEIPT_PAGES );
	}

	/**
	 * The directory a PDF receipt's page images are written to.
	 *
	 * @param string $receipt_path Absolute path of the receipt.
	 * @return string
	 */
	protected static function receipt_pages_dir( string $receipt_path ) : string {
		return $receipt_path . '.pages/';
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

		$mime = (string) $receipt->get_detected_mime();

		if ( ! isset( self::RECEIPT_TYPES[ $mime ] ) ) {
			return new Exception( 'receipt_type', sprintf( 'The receipt must be a %s file.', implode( ', ', self::RECEIPT_TYPES ) ), [ 'status' => 415, 'field_id' => 'receipt' ] );
		}

		return 'application/pdf' === $mime
			? $this->check_pdf( (string) $receipt->get_contents() )
			: $this->check_image( (string) $receipt->get_tmp_path(), $mime );
	}

	/**
	 * Why an image receipt is not accepted.
	 *
	 * The image must decode as the type its content claims, and stay within
	 * MAX_IMAGE_SIDE and MAX_IMAGE_PIXELS, so a small file cannot expand
	 * into an image that stalls the reviewer's browser.
	 *
	 * @param string $path Temporary file.
	 * @param string $mime Detected MIME type.
	 * @return Exception|null
	 */
	protected function check_image( string $path, string $mime ) : ?Exception {
		$info = @getimagesize( $path );

		if ( false === $info || $info['mime'] !== $mime ) {
			return new Exception( 'receipt_unreadable', 'The receipt image could not be read. Please upload it again or send a PDF.', [ 'status' => 422, 'field_id' => 'receipt' ] );
		}

		[ $width, $height ] = $info;

		if ( $width < 1 || $height < 1 || $width > self::MAX_IMAGE_SIDE || $height > self::MAX_IMAGE_SIDE || $width * $height > self::MAX_IMAGE_PIXELS ) {
			return new Exception( 'receipt_dimensions', sprintf( 'The receipt image is too large (%d × %d pixels). Please send a smaller image or a PDF.', $width, $height ), [ 'status' => 422, 'field_id' => 'receipt' ] );
		}

		return null;
	}

	/**
	 * Why a PDF receipt is not accepted.
	 *
	 * The file must start with a PDF header and end with a PDF trailer, and
	 * name none of PDF_ACTIVE_CONTENT. The scan reads the file as stored, so
	 * it does not see names inside compressed object streams: it refuses the
	 * common cases, it does not prove a PDF harmless. Receipts are viewed in
	 * the browser's own PDF viewer, never as a page of this site.
	 *
	 * @param string $contents The file.
	 * @return Exception|null
	 */
	protected function check_pdf( string $contents ) : ?Exception {
		if ( ! str_starts_with( $contents, '%PDF-' ) || ! str_contains( substr( $contents, -1024 ), '%%EOF' ) ) {
			return new Exception( 'receipt_unreadable', 'The receipt PDF is incomplete or damaged. Please upload it again.', [ 'status' => 422, 'field_id' => 'receipt' ] );
		}

		// Names may spell characters as #xx ("/J#61vaScript"); read them as written out.
		$names = (string) preg_replace_callback( '/#([0-9A-Fa-f]{2})/', static fn ( array $m ) : string => chr( (int) hexdec( $m[1] ) ), $contents );

		foreach ( self::PDF_ACTIVE_CONTENT as $name ) {
			// A name ends at a delimiter: "/JS" must not match "/JSomething".
			if ( preg_match( '#' . preg_quote( $name, '#' ) . '(?![A-Za-z0-9])#', $names ) ) {
				return new Exception( 'receipt_active_content', 'The receipt PDF contains scripts or embedded files, which receipts do not need. Please export or print it to a plain PDF and upload that.', [ 'status' => 422, 'field_id' => 'receipt' ] );
			}
		}

		return null;
	}
}