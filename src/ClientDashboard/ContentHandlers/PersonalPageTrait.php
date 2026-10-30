<?php
/**
 * Personal page trait file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Shared by the client dashboard's personal pages.
 *
 * A personal page shows the signed-in person's own things (their licenses,
 * their purchases) whoever they act for: the ownership context changes
 * what they manage, not what they own. It is shown to users only, not to
 * service accounts.
 *
 * Classes using it set $guard and $urlmanager.
 */
trait PersonalPageTrait {

	protected Guard $guard;

	protected URLManager $urlmanager;

	/**
	 * The signed-in user, or null for anyone else.
	 *
	 * @return User|null
	 */
	protected function current_user() : ?User {
		$actor = $this->guard->get_principal()?->get_actor();

		return $actor instanceof User ? $actor : null;
	}

	/**
	 * Whether the page shows: to signed-in users.
	 *
	 * @return bool
	 */
	public function is_visible() : bool {
		return null !== $this->current_user();
	}

	/**
	 * Pagination for the theme's pagination partial.
	 *
	 * @param array{page: int, total: int, total_pages: int} $pagination From a model reader.
	 * @param string                                         $slug       The page's slug.
	 * @return array{page: int, total_pages: int, total: int, previous_url: string, next_url: string}
	 */
	protected function pagination( array $pagination, string $slug ) : array {
		$page  = (int) $pagination['page'];
		$pages = (int) $pagination['total_pages'];
		$url   = fn ( int $to ) : string => $this->urlmanager->client_dashboard_url( $slug, $to > 1 ? [ 'paged' => $to ] : [] )->url();

		return [
			'page'         => $page,
			'total_pages'  => $pages,
			'total'        => (int) $pagination['total'],
			'previous_url' => $page > 1 ? $url( $page - 1 ) : '',
			'next_url'     => $page < $pages ? $url( $page + 1 ) : '',
		];
	}

	/**
	 * A date as people read it, or a fallback.
	 *
	 * @param \DateTimeImmutable|null $date
	 * @param string                  $none
	 * @return string
	 */
	protected function format_date( ?\DateTimeImmutable $date, string $none = '' ) : string {
		return $date ? $date->format( \smliser_datetime_format() ) : $none;
	}
}
