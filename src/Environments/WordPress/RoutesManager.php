<?php
/**
 * RoutesManager class file.
 *
 * @package SmartLicenseServer\Environments\WordPress
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Environments\WordPress\Routing\Router;

/**
 * Registers every SmartLicenseServer rewrite rule and query var.
 *
 * All route definitions live in define_routes(); route_register().
 */
class RoutesManager {

	private Router $router;

	public function __construct() {
		$this->router = new Router();
		$this->define_routes();
	}

	/**
	 * Registers all rewrite rules with WordPress. Hooks to `init` action.
	 */
	public function route_register(): void {
		$this->router->register();
	}

	/**
	 * Registers all query vars this plugin uses. Hooks to the `query_vars` filter.
	 *
	 * @param string[] $vars
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		return $this->router->query_vars( $vars );
	}

	/**
	 * All route definitions, in the same order and grouping as the original
	 * hand-written rules.
	 */
	private function define_routes(): void {
		$repo_prefix    = smliser_get_repository_url_prefix();
		$dashboard_slug = smliser_get_client_dashboard_url_prefix();
		$download_slug  = smliser_get_download_url_prefix();

		/*
		|-------------------
		| Repository routes
		|-------------------
		*/
		$this->router->group(
			$repo_prefix,
			function ( Router $router ): void {
				// siteurl/repository
				$router->add( '', 'smliser-repository' );

				// siteurl/repository/{app_type}
				$router->add( '{app_type}', 'smliser-repository' );

				// siteurl/repository/{app_type}/{app_slug}
				$router->add( '{app_type}/{app_slug}', 'smliser-repository' );

				// siteurl/repository/{app_type}/{app_slug}/assets/{filename}
				$router->add(
					pattern: '{app_type}/{app_slug}/assets/{asset_name:.+}',
					pagename: 'smliser-repository-assets',
					handler: [Dispatcher::class, 'handle_app_asset_request']
				);
			}
		);

		/*
		|---------------------------
		| Uploads directory serving
		|---------------------------
		*/

		// siteurl/smliser-uploads/{path_to_file}
		$this->router->add(
			pattern: 'smliser-uploads/{smliser_upload_path:path}',
			pagename: 'smliser-uploads-directory',
			handler: [Dispatcher::class, 'handle_uploads_dir_request']
		);

		/*
		|------------------
		| Client dashboard
		|------------------
		*/
		$this->router->add(
			pattern: $dashboard_slug,
			pagename: 'smliser-client-dashboard',
			handler: [ Dispatcher::class, 'render_client_dashboard' ]	
		);

		/*
		|-------------------------
		| Software download rules
		|-------------------------
		*/
		$this->router->group(
			$download_slug,
			function ( Router $router ): void {
				
				// License document download rule (specific): numeric license ID.
				// siteurl/$download_slug/document/license-document-1.txt
				$router->add(
					pattern: 'document/license-document-{license_id:int}.txt',
					pagename: 'smliser-downloads',
					handler: [Dispatcher::class, 'handle_license_document_download_request']
				);

				// App download rule.
				// siteurl/$download_slug/{download_type}/app-slug.zip
				$router->add(
					pattern: '{download_type}/{app_slug_filename.ext:zip}',
					pagename: 'smliser-downloads',
					handler: [Dispatcher::class, 'handle_public_package_download_request']
				);

				// Artifact download URI.
				$router->add(
					pattern: '{app_type}/{app_slug}/{download_type}/{filename:.+}',
					pagename: 'smliser-downloads',
					handler: [Dispatcher::class, 'handle_public_artifact_download_request']
				);
			}
		);

		/*
		|--------------------------------------------------------------------
		| OAuth authorization endpoint
		|--------------------------------------------------------------------
		*/
		$this->router->add(
			'smliser-auth/v1/authorize',
			'',
			array( 'smliser_auth' => '1' )
		);
	}

	/**
	 * Get all compiled routes.
	 * 
	 * @return array<int, array{regex: string, query: string, priority: string}>
	 */
	public function getCompiledRoutes() {
		return $this->router->getCompiledRules();
	}

	/**
	 * Get the underlying Router instance.
	 * 
	 * @return Router
	 */
	public function get_router(): Router {
		return $this->router;
	}
}