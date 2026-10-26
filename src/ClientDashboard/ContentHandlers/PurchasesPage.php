<?php
/**
 * My purchases page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestNote;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Context\Guard;

/**
 * My purchases: the signed-in user's purchase requests (a personal page).
 *
 * The user's requests are those made while signed in, and, once their
 * email address is verified, those made as a guest with it
 * (PurchaseRequest::get_for_buyer()). Each shows its status, the
 * reviewer's message or reason, and the notes that were emailed to the
 * buyer; internal notes and the receipt are not shown.
 */
final class PurchasesPage implements ClientPageInterface {
	use PersonalPageTrait;

	/**
	 * @param Guard                  $guard      The signed-in principal.
	 * @param URLManager             $urlmanager URL API.
	 * @param PurchaseRequestService $requests   Names what a request bought.
	 */
	public function __construct( Guard $guard, URLManager $urlmanager, private PurchaseRequestService $requests ) {
		$this->guard      = $guard;
		$this->urlmanager = $urlmanager;
	}

	public function get_menu_key() : string {
		return 'purchases';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'My purchases',
			'slug'       => 'purchases',
			'handler'    => $this,
			'icon'       => 'ti ti-receipt',
			'visibility' => fn () : bool => $this->is_visible(),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'purchases' ];
	}

	/**
	 * The page.
	 *
	 * View data (template client.purchases):
	 *   list<array{id: int, product: string, price: string, status: string, status_key: string,
	 *       submitted: string, decided: string, message_label: string, message: string,
	 *       license_url: string, receipt: string,
	 *       notes: list<array{date: string, body: string}>}> $purchases
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *   bool $verified Whether the user's email address is verified (unverified: guest purchases with it are not shown yet).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function purchases( Request $request ) : View {
		$user   = $this->current_user();
		$result = $user ? PurchaseRequest::get_for_buyer( $user, (int) $request->get( 'paged', 1 ), 20 ) : [ 'items' => [], 'pagination' => [ 'page' => 1, 'limit' => 20, 'total' => 0, 'total_pages' => 0 ] ];

		$purchases = array_map(
			fn ( PurchaseRequest $purchase ) : array => [
				'id'            => $purchase->get_id(),
				'product'       => $this->requests->get_product_name( $purchase ),
				'price'         => $purchase->get_price() ?: 'N/A',
				'status'        => ucfirst( $purchase->get_status() ),
				'status_key'    => $purchase->get_status(),
				'submitted'     => $this->format_date( $purchase->get_created_at() ),
				'decided'       => $purchase->is_pending() ? '' : $this->format_date( $purchase->get_reviewed_at() ),
				'message_label' => PurchaseRequest::STATUS_APPROVED === $purchase->get_status() ? 'Message from the seller' : 'Reason',
				'message'       => $purchase->is_pending() ? '' : $purchase->get_review_note(),
				'license_url'   => $purchase->get_license_id() ? $this->urlmanager->client_dashboard_url( 'licenses' )->url() : '',
				'receipt'       => $purchase->get_receipt_name(),
				'notes'         => array_values(
					array_map(
						fn ( PurchaseRequestNote $note ) : array => [ 'date' => $this->format_date( $note->get_created_at() ), 'body' => $note->get_body() ],
						array_filter( PurchaseRequestNote::get_for_request( $purchase->get_id() ), static fn ( PurchaseRequestNote $note ) : bool => $note->is_sent_to_buyer() )
					)
				),
			],
			$result['items']
		);

		return View::make(
			'client.purchases',
			[
				'purchases'  => $purchases,
				'pagination' => $this->pagination( $result['pagination'], 'purchases' ),
				'verified'   => (bool) $user?->is_email_verified(),
			]
		);
	}
}