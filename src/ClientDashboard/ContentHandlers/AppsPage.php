<?php
/**
 * Apps page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\FileSystem\WPPluginRepository;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Apps: the apps the current owner hosts.
 *
 * The current owner is the user, or the organization they act for; the
 * page lists and manages that owner's apps only, of every type and status.
 * It shows while the user acts for an owner and their role there holds a
 * hosted_apps capability. Each part shows only with its capability in
 * that role:
 *   uploading an app (apps/new)        hosted_apps.create
 *   editing details, a new version     hosted_apps.update
 *   active or inactive                 hosted_apps.change_status
 *   the trash, deleting for good       hosted_apps.delete
 *   adding images                      hosted_apps.upload_assets
 *   deleting images                    hosted_apps.delete_assets
 *
 * A suspended app is the platform's decision: its owner sees why it is
 * not offered and cannot change its status (HostingController enforces it).
 *
 * URLs: apps (the list; ?status= filters, ?paged= pages), apps?type=&app=
 * (one app), apps/new (upload). The actions are AppActions'.
 */
final class AppsPage implements ClientPageInterface {

	/**
	 * Capabilities any of which shows the page.
	 */
	private const CAPABILITIES = [
		'hosted_apps.create',
		'hosted_apps.update',
		'hosted_apps.change_status',
		'hosted_apps.delete',
		'hosted_apps.upload_assets',
		'hosted_apps.edit_assets',
		'hosted_apps.delete_assets',
	];

	/**
	 * Apps per page of the list.
	 */
	private const PER_PAGE = 20;

	/**
	 * @param Guard      $guard      The signed-in principal.
	 * @param URLManager $urlmanager URL API.
	 */
	public function __construct(
		private Guard $guard,
		private URLManager $urlmanager
	) {}

	public function get_menu_key() : string {
		return 'apps';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'Apps',
			'slug'       => 'apps',
			'handler'    => $this,
			'icon'       => 'ti ti-apps',
			'visibility' => fn () : bool => null !== $this->owner_id() && (bool) $this->guard->get_principal()?->can_any( self::CAPABILITIES ),
		];
	}

	public function get_submenu() : array {
		return [
			[
				'title'      => 'Upload an app',
				'slug'       => 'new',
				'callback'   => [ $this, 'upload' ],
				'visibility' => fn () : bool => null !== $this->owner_id() && $this->can( 'hosted_apps.create' ),
			],
		];
	}

	public function index_page_handler() : callable {
		return [ $this, 'apps' ];
	}

	/*
	|-------
	| PAGES
	|-------
	*/

	/**
	 * The list, or one app when the URL names it (?type=&app=).
	 *
	 * View data (template client.apps):
	 *   list<array{name: string, type: string, slug: string, status: string,
	 *       status_label: string, updated: string, url: string}> $apps
	 *   list<array{label: string, url: string, current: bool}> $filters
	 *   string $status The status shown; "" for every status.
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *   string $new_url The upload page; "" without hosted_apps.create.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function apps( Request $request ) : View {
		if ( $request->hasValue( 'app' ) ) {
			return $this->app( $request );
		}

		$statuses = AbstractHostedApp::get_statuses();
		$status   = (string) $request->get( 'status', '' );
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$page     = max( 1, (int) $request->get( 'paged', 1 ) );
		$result   = HostedApplicationService::get_apps_for_owner( (int) $this->owner_id(), [ 'status' => $status, 'page' => $page, 'limit' => self::PER_PAGE ] );
		$registry = HostedAppsRegistry::instance();
		$list_url = fn ( array $args ) : string => $this->urlmanager->client_dashboard_url( 'apps', array_filter( $args ) )->url();

		$apps = array_map(
			fn ( AbstractHostedApp $app ) : array => [
				'name'         => $app->get_name(),
				'type'         => $registry->get_app_type_label( $app->get_type() ),
				'slug'         => $app->get_slug(),
				'status'       => $app->get_status(),
				'status_label' => $statuses[ $app->get_status() ] ?? $app->get_status(),
				'updated'      => $app->get_updated_at()->format( \smliser_datetime_format() ),
				'url'          => $this->app_url( $app ),
			],
			$result['items']
		);

		$filters = [ [ 'label' => 'All', 'url' => $list_url( [] ), 'current' => '' === $status ] ];

		foreach ( $statuses as $key => $label ) {
			$filters[] = [ 'label' => $label, 'url' => $list_url( [ 'status' => $key ] ), 'current' => $key === $status ];
		}

		$pagination = $result['pagination'];

		return View::make(
			'client.apps',
			[
				'apps'       => $apps,
				'filters'    => $filters,
				'status'     => $status,
				'pagination' => [
					'page'         => $pagination['page'],
					'total_pages'  => $pagination['total_pages'],
					'total'        => $pagination['total'],
					'previous_url' => $pagination['page'] > 1 ? $list_url( [ 'status' => $status, 'paged' => $pagination['page'] - 1 ] ) : '',
					'next_url'     => $pagination['page'] < $pagination['total_pages'] ? $list_url( [ 'status' => $status, 'paged' => $pagination['page'] + 1 ] ) : '',
				],
				'new_url'    => $this->can( 'hosted_apps.create' ) ? $this->urlmanager->client_dashboard_url( 'apps/new' )->url() : '',
			]
		);
	}

	/**
	 * Upload an app.
	 *
	 * View data (template client.app-new):
	 *   array<string, string> $types App types, type => label.
	 *   int $max_size Largest upload, in bytes.
	 *   string $action The save action's URL.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function upload( Request $request ) : View {
		$registry = HostedAppsRegistry::instance();
		$types    = [];

		foreach ( $registry->app_types() as $type ) {
			$types[ $type ] = $registry->get_app_type_label( $type );
		}

		return View::make(
			'client.app-new',
			[
				'types'          => $types,
				'max_size'       => \smliser_max_upload_size(),
				'action'         => $this->action_url( 'apps/save' ),
			]
		)
			->add_script( View::UPLOADER_ASSET )
			->add_style( View::UPLOADER_ASSET );
	}

	/**
	 * One app.
	 *
	 * View data (template client.app):
	 *   array{id: int, type: string, type_label: string, name: string, slug: string, version: string,
	 *       status: string, status_label: string, created: string, updated: string, public_url: string} $app
	 *   list<array{name: string, label: string, value: string, type: string, required: bool, hint: string}> $fields
	 *       The details form's fields, the app type's included.
	 *   array{update: bool, change_status: bool, delete: bool, upload_assets: bool, delete_assets: bool} $can
	 *   array<string, string> $statuses Statuses the owner may choose, status => label.
	 *   list<array{type: string, label: string, hint: string, items: list<array{name: string, url: string}>}> $assets
	 *   int $max_size Largest upload, in bytes.
	 *   string $list_url The list.
	 *   array<string, string> $actions Action URLs: save, status, delete, asset_upload, asset_delete.
	 *
	 * @param Request $request
	 * @return View
	 */
	private function app( Request $request ) : View {
		$app = $this->owned_app( (string) $request->get( 'type', '' ), (int) $request->get( 'app', 0 ) );

		if ( null === $app ) {
			return View::make(
				'client.not-found',
				[
					'message'    => 'This app does not exist, or it belongs to someone else. Switch to its owner to manage it.',
					'back_url'   => $this->urlmanager->client_dashboard_url( 'apps' )->url(),
					'back_label' => 'Your apps',
				]
			)
				->set_status_code( 404 )
				->set_title( 'App not found' );
		}

		$registry = HostedAppsRegistry::instance();
		$statuses = AbstractHostedApp::get_statuses();
		$repo     = $registry->get_app_type_directory_class( $app->get_type() );
		$labels   = $repo ? $repo->asset_types() : [];
		$assets   = [];

		// How each kind of image must be named, where the name decides it.
		$names = static fn ( array $stems ) : string => sprintf( 'Name each file %s, with its image extension.', implode( ' or ', $stems ) );
		$hints = [
			'icon'        => $names( Repository::ALLOWED_ICON_NAMES ),
			'banner'      => $names( array_values( WPPluginRepository::ALLOWED_BANNER_NAMES ) ),
			'cover'       => 'One image; delete the current one to upload another.',
			'screenshot'  => 'wptheme' === $app->get_type() ? 'One image; delete the current one to upload another.' : 'Numbered in the order added; name a file screenshot-2 to give it that place.',
			'screenshots' => 'Numbered in the order added; name a file screenshot-2 to give it that place.',
		];

		foreach ( $repo ? $repo->assets_by_type( $app->get_slug() ) : [] as $type => $items ) {
			$assets[] = [ 'type' => $type, 'label' => $labels[ $type ] ?? $type, 'hint' => $hints[ $type ] ?? '', 'items' => $items ];
		}

		// An unset URL reads as an empty field, not as the "/" an empty URL builds.
		$string = static fn ( mixed $value ) : string => match ( true ) {
			$value instanceof URL => $value->is_valid() ? $value->url() : '',
			is_string( $value )   => $value,
			default               => '',
		};

		$fields = [
			[ 'name' => 'app_name', 'label' => 'Name', 'value' => $app->get_name(), 'type' => 'text', 'required' => true, 'hint' => '' ],
			[ 'name' => 'app_author', 'label' => 'Author', 'value' => $app->get_author(), 'type' => 'text', 'required' => true, 'hint' => '' ],
			[ 'name' => 'app_author_url', 'label' => 'Author profile URL', 'value' => $string( $app->get_author_profile() ), 'type' => 'url', 'required' => false, 'hint' => '' ],
			[ 'name' => 'app_homepage_url', 'label' => 'Homepage URL', 'value' => $string( $app->get_homepage() ), 'type' => 'url', 'required' => false, 'hint' => '' ],
		];

		// The app type's own fields: what HostingController::update_{type}() reads.
		$extra = match ( $app->get_type() ) {
			'wptheme'  => [
				'app_preview_url'             => [ 'Preview URL', $app->get_meta( 'preview_url' ) ],
				'app_external_repository_url' => [ 'External repository URL', $app->get_meta( 'external_repository_url' ) ],
			],
			'software' => [
				'app_documentation_url' => [ 'Documentation URL', $app->get_meta( 'documentation_url' ) ],
			],
			default    => [],
		};

		$extra['app_support_url']  = [ 'Support URL', $app->get_support_url() ];

		foreach ( $extra as $name => [ $label, $value ] ) {
			$fields[] = [ 'name' => $name, 'label' => $label, 'value' => $string( $value ), 'type' => 'url', 'required' => false, 'hint' => '' ];
		}

		// Only a link elsewhere is stored; empty means this site serves the download.
		$fields[] = [
			'name'     => 'app_download_url',
			'label'    => 'Download URL',
			'value'    => $string( $app->get_custom_download_url() ),
			'type'     => 'url',
			'required' => false,
			'hint'     => sprintf( 'Leave empty to serve downloads from this site, at %s', $this->urlmanager->app_downloads_url( $app->get_type(), $app->get_slug() )->url() ),
		];

		return View::make(
			'client.app',
			[
				'app'            => [
					'id'           => $app->get_id(),
					'type'         => $app->get_type(),
					'type_label'   => $registry->get_app_type_label( $app->get_type() ),
					'name'         => $app->get_name(),
					'slug'         => $app->get_slug(),
					'version'      => $app->get_version(),
					'status'       => $app->get_status(),
					'status_label' => $statuses[ $app->get_status() ] ?? $app->get_status(),
					'created'      => $app->get_created_at()->format( \smliser_datetime_format() ),
					'updated'      => $app->get_updated_at()->format( \smliser_datetime_format() ),
					'public_url'   => $app->get_url()->url(),
				],
				'fields'         => $fields,
				'can'            => [
					'update'        => $this->can( 'hosted_apps.update' ),
					'change_status' => $this->can( 'hosted_apps.change_status' ),
					'delete'        => $this->can( 'hosted_apps.delete' ),
					'upload_assets' => $this->can( 'hosted_apps.upload_assets' ),
					'delete_assets' => $this->can( 'hosted_apps.delete_assets' ),
				],
				'statuses'       => [
					AbstractHostedApp::STATUS_ACTIVE   => $statuses[ AbstractHostedApp::STATUS_ACTIVE ],
					AbstractHostedApp::STATUS_INACTIVE => $statuses[ AbstractHostedApp::STATUS_INACTIVE ],
				],
				'assets'         => $assets,
				'max_size'       => \smliser_max_upload_size(),
				'list_url'       => $this->urlmanager->client_dashboard_url( 'apps' )->url(),
				'actions'        => [
					'save'         => $this->action_url( 'apps/save' ),
					'status'       => $this->action_url( 'apps/status' ),
					'delete'       => $this->action_url( 'apps/delete' ),
					'asset_upload' => $this->action_url( 'apps/assets/upload' ),
					'asset_delete' => $this->action_url( 'apps/assets/delete' ),
				],
			]
		)
			->set_title( $app->get_name() )
			->add_script( View::UPLOADER_ASSET )
			->add_style( View::UPLOADER_ASSET );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The ID of the owner the user acts for.
	 *
	 * @return int|null
	 */
	private function owner_id() : ?int {
		$id = (int) $this->guard->get_principal()?->get_owner()?->get_id();

		return $id > 0 ? $id : null;
	}

	/**
	 * Whether the user's role where they act holds a capability.
	 *
	 * @param string $capability
	 * @return bool
	 */
	private function can( string $capability ) : bool {
		return (bool) $this->guard->get_principal()?->can( $capability );
	}

	/**
	 * An app of the current owner; null for any other.
	 *
	 * @param string $type
	 * @param int    $id
	 * @return AbstractHostedApp|null
	 */
	private function owned_app( string $type, int $id ) : ?AbstractHostedApp {
		$registry = HostedAppsRegistry::instance();
		$type     = $registry->normalize_app_type( $type );
		$owner_id = $this->owner_id();

		if ( $id <= 0 || null === $owner_id || ! $registry->is_app_type_registered( $type ) ) {
			return null;
		}

		$app = HostedApplicationService::get_app_by_id( $type, $id );

		return $app && $app->get_owner_id() === $owner_id ? $app : null;
	}

	/**
	 * The app's page.
	 *
	 * @param AbstractHostedApp $app
	 * @return string
	 */
	private function app_url( AbstractHostedApp $app ) : string {
		return $this->urlmanager->client_dashboard_url( 'apps', [ 'type' => $app->get_type(), 'app' => $app->get_id() ] )->url();
	}

	/**
	 * An action's URL.
	 *
	 * @param string $path
	 * @return string
	 */
	private function action_url( string $path ) : string {
		return $this->urlmanager->client_dashboard_url( 'json/' . $path )->url();
	}
}
