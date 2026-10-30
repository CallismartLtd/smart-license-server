<?php
/**
 * My licenses page class file.
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
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\PricingTier;
use SmartLicenseServer\Security\Context\Guard;

/**
 * My licenses: the licenses linked to the signed-in user (a personal page).
 *
 * Licenses are linked when issued to the account, or to the account's
 * email address once it is verified. Each has its key, with a copy button,
 * and its license document.
 */
final class LicensesPage implements ClientPageInterface {
	use PersonalPageTrait;

	/**
	 * @param Guard      $guard      The signed-in principal.
	 * @param URLManager $urlmanager URL API.
	 */
	public function __construct( Guard $guard, URLManager $urlmanager ) {
		$this->guard      = $guard;
		$this->urlmanager = $urlmanager;
	}

	public function get_menu_key() : string {
		return 'licenses';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'My licenses',
			'slug'       => 'licenses',
			'handler'    => $this,
			'icon'       => 'ti ti-certificate',
			'visibility' => fn () : bool => $this->is_visible(),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'licenses' ];
	}

	/**
	 * The page.
	 *
	 * View data (template client.licenses):
	 *   list<array{id: int, app: string, tier: string, status: string, status_key: string,
	 *       expires: string, sites: string, key: string, partial_key: string, document_url: string}> $licenses
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *   bool $verified Whether the user's email address is verified (unverified: licenses bought with it are not linked yet).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function licenses( Request $request ) : View {
		$user   = $this->current_user();
		$page	= (int) $request->get( 'paged', 1 );
		$limit	= (int) $request->get( 'limit', 20 );
		$result = $user->is_email_verified()
			? License::get_for_user( (int) $user?->get_id(), $page, $limit )
			: [
				'items'			=> [],
				'pagination'	=> [
					'page'			=> $page,
					'limit'			=> $limit,
					'total'			=> 0,
					'total_pages'	=> 0
				]
			];

		$licenses = array_map(
			fn ( License $license ) : array => [
				'id'				=> (int) $license->get_id(),
				'app'				=> (string) ( $license->get_app()?->get_name() ?? 'App no longer available' ),
				'tier'				=> (string) PricingTier::get_by_id( $license->get_tier_id() )?->get_name(),
				'status'			=> ucfirst( $license->get_status() ),
				'status_key'		=> $license->get_status(),
				'expires'			=> $this->format_date( $license->get_end_date(), 'Never' ),
				'sites'				=> sprintf( '%d of %s', $license->get_total_active_domains(), $license->get_max_allowed_domains() ),
				'key'				=> (string) $license->get_license_key(),
				'partial_key'		=> $license->get_partial_key(),
				'document_url'		=> $this->urlmanager->client_dashboard_url( sprintf( 'download/license/%d', $license->get_id() ) )->url(),
				'app_download_url'	=> $license->get_app() ? $this->urlmanager->client_dashboard_url( sprintf( 'download/%d/%s/%s.zip', $license->get_id(), $license->get_app()->get_type(), $license->get_app()->get_slug() ) ) : null
			],
			$result['items']
		);

		return View::make(
			'client.licenses',
			[
				'licenses'   => $licenses,
				'pagination' => $this->pagination( $result['pagination'], 'licenses' ),
				'verified'   => (bool) $user?->is_email_verified(),
			]
		);
	}
}
