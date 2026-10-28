<?php
/**
 * Repository pages class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\HostedApps\ContentHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\CorePages;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Monetization\Monetization;

/**
 * The public repository: one route and one theme template per page.
 *
 *   GET <repository prefix>/                       index     'repository.index'
 *   GET <repository prefix>/{app_type}/            app_type  'repository.type'
 *   GET <repository prefix>/{app_type}/{app_slug}/ app       'repository.app'
 *
 * Every page is built from HostedAppsRegistry::app_types(), so an app type
 * registered with HostedAppsRegistry::register() is listed, browsed and
 * searched with no change here or in a theme. Only active apps are shown.
 *
 * Every page receives:
 *   list<array{slug: string, label: string, plural: string, url: string, count: int}> $types
 *       Every app type, in registry order, with its number of active apps.
 *   string $search The search term (?search=); empty for none.
 *
 * Apps in lists are cards, the same shape on every page:
 *   array{
 *       type: string, slug: string, name: string, url: string, icon: string,
 *       summary: string, version: string, author: string, updated: \DateTimeImmutable
 *   }
 *
 * Paginated lists come with:
 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string}
 *   (an empty URL when there is no previous or next page).
 */
final class RepositoryPages {

	/**
	 * Apps per page on a type page and in search results.
	 */
	public const PER_PAGE = 24;

	/**
	 * Apps of each type on the repository's front page.
	 */
	public const PER_SECTION = 6;

	/**
	 * Sections of an app page, in display order: section key => heading.
	 */
	public const SECTIONS = [
		'description'  => 'Description',
		'installation' => 'Installation',
		'faq'          => 'FAQ',
		'changelog'    => 'Changelog',
	];

	/**
	 * Constructor.
	 *
	 * @param HostedAppsRegistry $registry   The app types.
	 * @param URLManager         $urls       URL API.
	 * @param CorePages          $core_pages The 404 page.
	 */
	public function __construct(
		private HostedAppsRegistry $registry,
		private URLManager $urls,
		private CorePages $core_pages
	) {}

	/*
	|-------
	| PAGES
	|-------
	*/

	/**
	 * GET <repository prefix>/ — every app type, or search results across them.
	 *
	 * Template 'repository.index'. Data: $types, $search, and
	 *   list<array{type: array, apps: list<array>}> $sections
	 *       Without a search: per app type that has apps, its summary (as in
	 *       $types) and its first PER_SECTION cards (by name). Empty with a search.
	 *   list<array> $apps        With a search: the cards found. Empty without.
	 *   array|null  $pagination  With a search: the results' pagination. Null without.
	 *
	 * A page of results past the last is the theme's 404 page.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function index( Request $request ) : View {
		$types  = $this->types();
		$search = $this->search_term( $request );
		$view   = View::make( 'repository.index' )
			->set_title( 'Repository' )
			->set_description( sprintf( 'Browse the %s hosted on this server.', $this->list_labels( $types ) ) )
			->set( 'types', $types )
			->set( 'search', $search );

		if ( '' !== $search ) {
			[ $apps, $pagination ] = $this->query( $request, $this->registry->app_types(), $search, $this->urls->repository_url() );

			if ( $this->past_last_page( $pagination ) ) {
				return $this->core_pages->not_found( $request );
			}

			return $view
				->set_title( sprintf( 'Search results for "%s"', $search ) )
				->set( 'sections', [] )
				->set( 'apps', $apps )
				->set( 'pagination', $pagination );
		}

		$sections = [];

		foreach ( $types as $type ) {
			if ( 0 === $type['count'] ) {
				continue;
			}

			$result     = HostedApplicationService::get_apps( [ 'types' => [ $type['slug'] ], 'page' => 1, 'limit' => self::PER_SECTION ] );
			$sections[] = [ 'type' => $type, 'apps' => $this->cards( $result['items'] ) ];
		}

		return $view
			->set( 'sections', $sections )
			->set( 'apps', [] )
			->set( 'pagination', null );
	}

	/**
	 * GET <repository prefix>/{app_type}/ — the apps of one type, paginated and searchable.
	 *
	 * Template 'repository.type'. Data: $types, $search, and
	 *   array       $type       The type's summary (as in $types).
	 *   list<array> $apps       The cards of this page.
	 *   array       $pagination The pagination.
	 *
	 * An unregistered type, or a page past the last, is the theme's 404 page.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function app_type( Request $request ) : View {
		$types = $this->types();
		$type  = $this->find_type( $types, (string) $request->route_param( 'app_type', '' ) );

		if ( null === $type ) {
			return $this->core_pages->not_found( $request );
		}

		$search                = $this->search_term( $request );
		[ $apps, $pagination ] = $this->query( $request, [ $type['slug'] ], $search, URL::from( $type['url'] ) );

		if ( $this->past_last_page( $pagination ) ) {
			return $this->core_pages->not_found( $request );
		}

		return View::make( 'repository.type' )
			->set_title( '' === $search ? $type['plural'] : sprintf( '%s matching "%s"', $type['plural'], $search ) )
			->set_description( sprintf( 'Browse the %s hosted on this server.', $type['plural'] ) )
			->set( 'types', $types )
			->set( 'search', $search )
			->set( 'type', $type )
			->set( 'apps', $apps )
			->set( 'pagination', $pagination );
	}

	/**
	 * GET <repository prefix>/{app_type}/{app_slug}/ — one app.
	 *
	 * Template 'repository.app'. Data: $types, $search (always empty), and
	 *   array             $type The type's summary (as in $types).
	 *   array             $card The app's card.
	 *   AbstractHostedApp $app  The app, for what only its type has. A theme
	 *                           shows that in an optional partial per type,
	 *                           e.g. 'repository.details-wpplugin'.
	 *   array<string, string> $sections Heading => HTML, the non-empty
	 *                           SECTIONS, in order. The HTML comes from the
	 *                           app's readme: print it through sanitize_html().
	 *   list<array{src: string, caption: string}> $screenshots
	 *   array{url: string, monetized: bool} $download
	 *                           A monetized app is downloaded with a license's
	 *                           download token, never from a plain link.
	 *   array{homepage: string, support: string, author: string} $links
	 *                           URLs; empty when the app has none.
	 *   list<string>      $tags
	 *   array{license: string, license_uri: string} $license
	 *
	 * An unregistered type, or an app that does not exist or is not active,
	 * is the theme's 404 page.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function app( Request $request ) : View {
		$types = $this->types();
		$type  = $this->find_type( $types, (string) $request->route_param( 'app_type', '' ) );
		$app   = null === $type ? null : HostedApplicationService::get_app_by_slug( $type['slug'], (string) $request->route_param( 'app_slug', '' ) );

		if ( null === $app || ! $app->is_active() ) {
			return $this->core_pages->not_found( $request );
		}

		$sections = [];

		foreach ( self::SECTIONS as $key => $heading ) {
			$html = trim( $app->get_section( $key ) );

			if ( '' !== $html ) {
				$sections[ $heading ] = $html;
			}
		}

		$license = $app->get_license();
		$card    = $this->card( $app );

		return View::make( 'repository.app' )
			->set_title( $card['name'] )
			->set_description( $card['summary'] )
			->set( 'types', $types )
			->set( 'search', '' )
			->set( 'type', $type )
			->set( 'card', $card )
			->set( 'app', $app )
			->set( 'sections', $sections )
			->set( 'screenshots', $this->screenshots( $app ) )
			->set( 'download', [ 'url' => $app->get_download_url()->url(), 'monetized' => $app->is_monetized() ] )
			->set( 'pricing', $this->pricing( $app ) )
			->set(
				'links',
				[
					'homepage' => $this->valid_url( $app->get_homepage() ),
					'support'  => $this->valid_url( $app->get_support_url() ),
					'author'   => $this->valid_url( $app->get_author_profile() ),
				]
			)
			->set( 'tags', array_values( array_filter( array_map( 'strval', $app->get_tags() ) ) ) )
			->set(
				'license',
				[
					'license'     => (string) ( $license['license'] ?? '' ),
					'license_uri' => (string) ( $license['license_uri'] ?? '' ),
				]
			);
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * What buyers can buy for an app: its active pricing tiers as buyers see them.
	 *
	 * Empty when the app is not monetized or its monetization is disabled.
	 * Each tier carries its provider's offer; a tier whose offer has no
	 * checkout URL (e.g. licenses only an admin issues) is listed without a
	 * way to buy it here.
	 *
	 * @param AbstractHostedApp $app
	 * @return list<array{
	 *  id: int,
	 *  name: string,
	 *  term_label: string,
	 *  max_sites: int,
	 *  features: list<string>,
	 *  price: string,
	 *  checkout_url: string
	 * }>
	 */
	private function pricing( AbstractHostedApp $app ) : array {
		$monetization = Monetization::get_by_app( $app->get_type(), $app->get_id() );

		if ( ! $monetization || ! $monetization->is_enabled() ) {
			return [];
		}

		$tiers = [];

		foreach ( $monetization->to_array()['tiers'] as $tier ) {
			$tiers[] = [
				'id'           => (int) $tier['id'],
				'name'         => (string) $tier['name'],
				'term_label'   => (string) $tier['term_label'],
				'max_sites'    => (int) $tier['max_sites'],
				'features'     => array_values( array_map( 'strval', (array) $tier['features'] ) ),
				'price'        => (string) ( $tier['offer']['price'] ?? '' ),
				'checkout_url' => $this->http_url( (string) ( $tier['offer']['checkout_url'] ?? '' ) ),
			];
		}

		return $tiers;
	}

	/**
	 * Every registered app type, with its URL and number of active apps.
	 *
	 * @return list<array{slug: string, label: string, plural: string, url: string, count: int}>
	 */
	private function types() : array {
		$types = [];

		foreach ( $this->registry->app_types() as $slug ) {
			$labels  = (array) $this->registry->get_app_type_labels( $slug );
			$types[] = [
				'slug'   => $slug,
				'label'  => $labels['singular'],
				'plural' => $labels['plural'],
				'url'    => $this->urls->repository_url( $slug )->url(),
				'count'  => HostedApplicationService::count_apps( [ 'types' => [ $slug ] ] ),
			];
		}

		return $types;
	}

	/**
	 * The summary of the app type a route parameter names.
	 *
	 * @param list<array{slug: string, label: string, plural: string, url: string, count: int}> $types From types().
	 * @param string $param The {app_type} route parameter.
	 * @return array{slug: string, label: string, plural: string, url: string, count: int}|null Null when not registered.
	 */
	private function find_type( array $types, string $param ) : ?array {
		if ( '' === $param || ! $this->registry->is_app_type_registered( $param ) ) {
			return null;
		}

		$slug = $this->registry->normalize_app_type( $param );

		foreach ( $types as $type ) {
			if ( $type['slug'] === $slug ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * One page of active apps of the given types, found by a search term or listed by name.
	 *
	 * @param Request  $request The request; ?page= picks the page.
	 * @param string[] $types   App types.
	 * @param string   $search  Search term; empty to list.
	 * @param URL      $base    The page's URL, for the previous and next links.
	 * @return array{0: list<array>, 1: array{page: int, total_pages: int, total: int, previous_url: string, next_url: string}}
	 */
	private function query( Request $request, array $types, string $search, URL $base ) : array {
		$args = [
			'types' => $types,
			'page'  => max( 1, (int) $request->get( 'page', 1 ) ),
			'limit' => self::PER_PAGE,
		];

		$result = '' === $search
			? HostedApplicationService::get_apps( $args )
			: HostedApplicationService::search_apps( $args + [ 'term' => $search ] );

		$page        = (int) $result['pagination']['page'];
		$total_pages = (int) $result['pagination']['total_pages'];
		$query       = '' === $search ? [] : [ 'search' => $search ];
		$link        = fn ( int $to ) : string => $base->add_query_params( $query + ( $to > 1 ? [ 'page' => $to ] : [] ) )->url();

		return [
			$this->cards( $result['items'] ),
			[
				'page'         => $page,
				'total_pages'  => $total_pages,
				'total'        => (int) $result['pagination']['total'],
				'previous_url' => $page > 1 ? $link( min( $page - 1, max( 1, $total_pages ) ) ) : '',
				'next_url'     => $page < $total_pages ? $link( $page + 1 ) : '',
			],
		];
	}

	/**
	 * Whether ?page= asks for a page past the last one (page 1 always exists).
	 *
	 * @param array{page: int, total_pages: int} $pagination
	 * @return bool
	 */
	private function past_last_page( array $pagination ) : bool {
		return $pagination['page'] > max( 1, $pagination['total_pages'] );
	}

	/**
	 * The cards of listed apps.
	 *
	 * Lists hold apps hydrated from their table row only (no readme, icons
	 * or version), so each card is made from the fully loaded app, which
	 * HostedApplicationService caches; the listed app is used when it
	 * cannot be loaded.
	 *
	 * @param AbstractHostedApp[] $items
	 * @return list<array>
	 */
	private function cards( array $items ) : array {
		$cards = [];

		foreach ( $items as $item ) {
			$cards[] = $this->card( HostedApplicationService::get_app_by_slug( $item->get_type(), $item->get_slug() ) ?? $item );
		}

		return $cards;
	}

	/**
	 * An app's card.
	 *
	 * @param AbstractHostedApp $app
	 * @return array{type: string, slug: string, name: string, url: string, icon: string, summary: string, version: string, author: string, updated: \DateTimeImmutable}
	 */
	private function card( AbstractHostedApp $app ) : array {
		return [
			'type'    => $app->get_type(),
			'slug'    => $app->get_slug(),
			'name'    => $app->get_name(),
			'url'     => $app->get_url()->url(),
			'icon'    => $app->get_icon(),
			'summary' => trim( strip_tags( $app->get_short_description() ) ),
			'version' => $app->get_version(),
			'author'  => $app->get_author(),
			'updated' => $app->get_updated_at(),
		];
	}

	/**
	 * An app's screenshots as a list of image URLs with captions.
	 *
	 * @param AbstractHostedApp $app
	 * @return list<array{src: string, caption: string}>
	 */
	private function screenshots( AbstractHostedApp $app ) : array {
		$screenshots = [];

		foreach ( $app->get_screenshots() as $screenshot ) {
			$src     = is_array( $screenshot ) ? (string) ( $screenshot['src'] ?? $screenshot['url'] ?? '' ) : (string) $screenshot;
			$caption = is_array( $screenshot ) ? (string) ( $screenshot['caption'] ?? '' ) : '';

			if ( '' !== $src ) {
				$screenshots[] = [ 'src' => $src, 'caption' => $caption ];
			}
		}

		return $screenshots;
	}

	/**
	 * A URL's string when it is valid; empty otherwise.
	 *
	 * @param URL|null $url
	 * @return string
	 */
	private function valid_url( ?URL $url ) : string {
		return null !== $url && $url->is_valid() ? $url->url() : '';
	}

	/**
	 * A URL when it is an absolute http(s) URL, otherwise empty.
	 *
	 * @param string $url
	 * @return string
	 */
	private function http_url( string $url ) : string {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( $scheme, [ 'http', 'https' ], true ) ? $url : '';
	}

	/**
	 * The search term of the request (?search=).
	 *
	 * @param Request $request
	 * @return string
	 */
	private function search_term( Request $request ) : string {
		return trim( (string) $request->get( 'search', '' ) );
	}

	/**
	 * "WordPress Plugins, WordPress Themes and Software" from the type summaries.
	 *
	 * @param list<array{plural: string}> $types
	 * @return string
	 */
	private function list_labels( array $types ) : string {
		$labels = array_map( fn ( array $type ) : string => $type['plural'], $types );
		$last   = array_pop( $labels );

		return [] === $labels ? (string) $last : implode( ', ', $labels ) . ' and ' . $last;
	}
}