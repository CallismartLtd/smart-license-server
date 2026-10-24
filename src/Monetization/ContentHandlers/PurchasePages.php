<?php
/**
 * PurchasePages class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization\ContentHandlers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization\ContentHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Monetization\Providers\LocalProvider;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The purchase page of a tier sold by the local provider.
 *
 *   GET /purchase/{tier_id}  What is sold, how to pay, and the form for the receipt.
 *
 * The form posts to PurchaseActions::submit().
 */
class PurchasePages {

	/**
	 * @param PurchaseRequestService $requests
	 * @param MonetizationRegistry   $providers
	 * @param Guard                  $guard
	 */
	public function __construct(
		private PurchaseRequestService $requests,
		private MonetizationRegistry $providers,
		private Guard $guard
	) {}

	/**
	 * GET /purchase/{tier_id}
	 *
	 * Template 'purchase'. Data:
	 *   string   $unavailable  Why the tier cannot be bought; empty when it can.
	 *   string   $app_name     The app the license is for.
	 *   string   $tier_name    The tier.
	 *   string   $term         The license term, e.g. "1 year".
	 *   int      $max_sites    Sites per license; -1 for unlimited.
	 *   string[] $features     Features of the tier.
	 *   string   $price        The price buyers pay.
	 *   string   $instructions How to pay (plain text).
	 *   string   $action       URL the form posts to.
	 *   string   $buyer_name   The signed-in buyer's name; empty for a guest.
	 *   string   $buyer_email  The signed-in buyer's email; empty for a guest.
	 *   string   $accept       The receipt types, for the file input's accept attribute.
	 *   string   $types_label  The receipt types, as people read them.
	 *   int      $max_mb       Largest receipt, in megabytes.
	 *   int      $max_bytes    Largest receipt, in bytes (for the uploader).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function purchase( Request $request ) : View {
		$tier = $this->requests->get_purchasable_tier( (int) $request->route_param( 'tier_id', 0 ) );

		if ( $tier instanceof Exception ) {
			$status = (int) ( $tier->get_error_data()['status'] ?? 404 );

			return View::make( 'purchase', [ 'unavailable' => $tier->get_error_message() ], $status )
				->set_title( 'Not available' )
				->set_header( 'Cache-Control', 'no-store' );
		}

		/** @var LocalProvider $provider */
		$provider = $this->providers->get_provider( LocalProvider::get_id() );
		$app      = Monetization::get_by_id( $tier->get_monetization_id() )?->get_app();
		$user     = $this->guard->get_principal()?->get_actor();
		$user     = $user instanceof User ? $user : null;

		return View::make(
			'purchase',
			[
				'unavailable'  => '',
				'app_name'     => (string) $app?->get_name(),
				'tier_name'    => $tier->get_name(),
				'term'         => $tier->get_term_label(),
				'max_sites'    => $tier->get_max_sites(),
				'features'     => $tier->get_features(),
				'price'        => (string) $provider->get_offer( $tier )['price'],
				'instructions' => $provider->get_payment_instructions( $tier ),
				'action'       => $provider->get_submit_url( $tier )->url(),
				'buyer_name'   => (string) $user?->get_display_name(),
				'buyer_email'  => (string) $user?->get_email(),
				'accept'       => implode( ',', array_keys( PurchaseRequestService::RECEIPT_TYPES ) ),
				'types_label'  => implode( ', ', PurchaseRequestService::RECEIPT_TYPES ),
				'max_mb'       => (int) ( PurchaseRequestService::MAX_RECEIPT_BYTES / 1024 / 1024 ),
				'max_bytes'    => PurchaseRequestService::MAX_RECEIPT_BYTES,
			]
		)
			->set_title( sprintf( 'Buy %s — %s', (string) $app?->get_name(), $tier->get_name() ) )
			->set_header( 'Cache-Control', 'no-store' )
			->add_script( View::FORMS_SCRIPT )
			->add_script( View::UPLOADER_ASSET )
			->add_style( View::UPLOADER_ASSET );
	}
}