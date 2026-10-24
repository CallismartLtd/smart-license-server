<?php
/**
 * Purchase request email job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Purchases
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Purchases;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Email\Templates\Purchases\AdminPurchaseRequestEmail;
use SmartLicenseServer\Email\Templates\Purchases\PurchaseRequestApprovedEmail;
use SmartLicenseServer\Email\Templates\Purchases\PurchaseRequestMessageEmail;
use SmartLicenseServer\Email\Templates\Purchases\PurchaseRequestReceivedEmail;
use SmartLicenseServer\Email\Templates\Purchases\PurchaseRequestRejectedEmail;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestNote;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Sends the emails of a purchase request.
 *
 * One job per email; the payload names the event:
 *
 *   received  To the buyer: the request and receipt arrived.
 *   admin     To the admin email address: a request to review.
 *   note      To the buyer: a reviewer's note (payload "note_id").
 *   approved  To the buyer: the license, with the document attached.
 *   rejected  To the buyer: the reason.
 *
 * The email is built from the request as it is when the job runs.
 */
class PurchaseRequestEmailJob implements JobHandlerInterface {

	public const EVENT_RECEIVED = 'received';
	public const EVENT_ADMIN    = 'admin';
	public const EVENT_NOTE     = 'note';
	public const EVENT_APPROVED = 'approved';
	public const EVENT_REJECTED = 'rejected';

	public function __construct(
		protected Mailer $mailer,
		protected Settings $settings,
		protected URLManager $urlmanager,
		protected PurchaseRequestService $requests
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * @param array{request_id?: int, event?: string, note_id?: int} $payload
	 * @return mixed
	 */
	public function handle( array $payload = [] ) : mixed {
		$request = PurchaseRequest::get_by_id( (int) ( $payload['request_id'] ?? 0 ) );

		if ( ! $request ) {
			return [ 'success' => false, 'message' => 'The purchase request does not exist.' ];
		}

		$email = $this->build( $request, (string) ( $payload['event'] ?? '' ), (int) ( $payload['note_id'] ?? 0 ) );

		if ( ! $email instanceof EmailTemplate ) {
			return [ 'success' => false, 'message' => $email ];
		}

		$message = $email->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => sprintf( 'The "%s" email template is disabled.', $email->label() ) ];
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * The email of an event, or why there is none.
	 *
	 * @param PurchaseRequest $request
	 * @param string          $event
	 * @param int             $note_id
	 * @return EmailTemplate|string
	 */
	protected function build( PurchaseRequest $request, string $event, int $note_id ) : EmailTemplate|string {
		$buyer   = $request->get_buyer_email();
		$product = $this->requests->get_product_name( $request );

		switch ( $event ) {
			case self::EVENT_RECEIVED:
				return new PurchaseRequestReceivedEmail( $request, $buyer, $product );

			case self::EVENT_ADMIN:
				$admin = (string) $this->settings->get( Settings::ADMIN_EMAIL, '' );

				if ( '' === $admin ) {
					return 'No admin email address is set.';
				}

				$review_url = $this->urlmanager->admin_license_page_url( 'purchase-request', [ 'id' => $request->get_id() ] )->url();

				return new AdminPurchaseRequestEmail( $request, $admin, $product, $review_url );

			case self::EVENT_NOTE:
				$note = PurchaseRequestNote::get_by_id( $note_id );

				if ( ! $note || $note->get_request_id() !== $request->get_id() || ! $note->is_sent_to_buyer() ) {
					return 'The note does not exist or is not for the buyer.';
				}

				return new PurchaseRequestMessageEmail( $request, $buyer, $product, $note->get_body() );

			case self::EVENT_APPROVED:
				$license = License::get_by_id( $request->get_license_id() );

				if ( PurchaseRequest::STATUS_APPROVED !== $request->get_status() || ! $license ) {
					return 'The request is not approved, or its license does not exist.';
				}

				// The account is the buyer's to create; until they have one, invite them.
				$signup_url = $request->wants_account() && ! User::get_by_email( $buyer )
					? $this->urlmanager->login_url( 'signup', [ 'email' => $buyer ] )->url()
					: '';

				return new PurchaseRequestApprovedEmail( $request, $buyer, $product, $license, $signup_url );

			case self::EVENT_REJECTED:
				if ( PurchaseRequest::STATUS_REJECTED !== $request->get_status() ) {
					return 'The request is not rejected.';
				}

				return new PurchaseRequestRejectedEmail( $request, $buyer, $product );
		}

		return sprintf( 'Unknown purchase request email "%s".', $event );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Send Purchase Request Email';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Asynchronously send an email about a purchase request: receipt, admin notice, note, approval or rejection.';
	}
}