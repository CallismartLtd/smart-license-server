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
 * All route definitions live in define_routes(); route_register() and
 * query_vars() keep the same names and signatures the plugin's existing
 * `init` / `query_vars` hooks already call, so wiring this in is a drop-in
 * swap for whatever previously called add_rewrite_rule() by hand.
 */
class RoutesManager {

	private Router $router;

	public function __construct() {
		$this->router = new Router();
		$this->define_routes();
	}

	/**
	 * Registers all rewrite rules with WordPress. Hook to `init`.
	 */
	public function route_register(): void {
		$this->router->register();
	}

	/**
	 * Registers all query vars this plugin uses. Hook to the `query_vars` filter.
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
					'{app_type}/{app_slug}/assets/{asset_name:.+}',
					'smliser-repository-assets'
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
			'smliser-uploads/{smliser_upload_path:path}',
			'smliser-uploads'
		);

		/*
		|------------------
		| Client dashboard
		|------------------
		*/
		$this->router->add( $dashboard_slug, 'smliser-dashboard' );

		/*
		|-------------------------
		| Software download rules
		|-------------------------
		*/
		$this->router->group(
			$download_slug,
			function ( Router $router ): void {
				// The base downloads page.
				$router->add( '', 'smliser-downloads' );

				// Downloads category page.
				$router->add( '{download_type}', 'smliser-downloads' );

				// License document download rule (specific): numeric license ID.
				$router->group( '{download_type}', function( Router $r ){
					$r->add(
						'{license_id:int}',
						'smliser-downloads'
					);

					$r->add(
						'license-document-{license_id:int}.txt',
						'smliser-downloads'
					);					
				});


				// siteurl/$download_slug/{download_type}/app-slug|app-slug.zip
				//
				// Two explicit rules instead of one regex with an optional,
				// uncaptured ".zip" suffix (which the placeholder DSL can't express
				// — see Router::raw()'s docblock). Both constraints below matter:
				//
				//   - (?![0-9]+$)   excludes pure-numeric values, which would
				//                   otherwise collide with {license_id:int} above —
				//                   both are a bare second segment under {download_type},
				//                   and [^/]+ matches digits just as happily as letters.
				//   - (?!.*\.zip$)  excludes anything ending in ".zip", so this rule
				//                   and the ".zip"-specific one below can never both
				//                   match the same URL. Without it, both match
				//                   "my-plugin.zip" and only WordPress' internal
				//                   registration-order tie-break decides which wins —
				//                   fragile, and not something to depend on.
				$router->group(
					'{download_type}',
					function ( Router $r ): void {
						$r->add(
							'{app_slug_filename_ext:(?![0-9]+$)(?!.*\.zip$)[^/]+}',
							'smliser-downloads'
						);

						$r->add(
							'{app_slug_filename.ext:zip}',
							'smliser-downloads'
						);
					}
				);

				// Artifact download URI.
				$router->add(
					'{app_type}/{app_slug}/{download_type}/{filename:.+}',
					'smliser-downloads'
				);
			}
		);

		/*
		|--------------------------------------------------------------------
		| OAuth authorization endpoint
		|--------------------------------------------------------------------
		*/
		// NOTE: the original rule referenced $matches[1] in a pattern with no
		// capturing group ('^smliser-auth/v1/authorize$'), so smliser_auth would
		// silently resolve empty. This fixes that by passing a fixed value
		// through $extraVars instead of a nonexistent match.
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
}