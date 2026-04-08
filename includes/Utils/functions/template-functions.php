<?php
/**
 * Template functions API.
 *
 * Procedural helpers for template resolution and rendering.
 *
 * @package SmartLicenseServer\Utils\Functions
 */

use SmartLicenseServer\Templates\TemplateLocator;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Get the template locator instance.
 *
 * @return TemplateLocator
 */
function smliser_template_locator() : TemplateLocator {
    return smliser_envProvider()->templateLocator();
}

/**
 * Resolve a template slug to an absolute file path.
 *
 * Returns null if the slug cannot be resolved from any registered path.
 *
 * @param string $slug Dot-notation slug, e.g. 'admin.license.view'.
 * @return string|null
 */
function smliser_resolve_template( string $slug ) : ?string {
    return smliser_template_locator()->resolve( $slug );
}

/**
 * Check whether a template slug resolves to an existing file.
 *
 * @param string $slug
 * @return bool
 */
function smliser_template_exists( string $slug ) : bool {
    return smliser_template_locator()->exists( $slug );
}

/**
 * Render a template, extracting $data into the template scope.
 *
 * @param string               $slug Dot-notation slug, e.g. 'admin.license.view'.
 * @param array<string, mixed> $data Variables to extract into the template scope.
 *
 * @throws SmartLicenseServer\Exceptions\EnvironmentBootstrapException If template cannot be resolved.
 */
function smliser_render_template( string $slug, array $data = [] ) : void {
    smliser_template_locator()->render( $slug, $data );
}

/**
 * Render a template and return the output as a string.
 *
 * @param string               $slug
 * @param array<string, mixed> $data
 * @return string
 *
 * @throws SmartLicenseServer\Exceptions\EnvironmentBootstrapException If template cannot be resolved.
 */
function smliser_render_template_to_string( string $slug, array $data = [] ) : string {
    return smliser_template_locator()->render_to_string( $slug, $data );
}

/**
 * Render a template only if it exists, silently skip if not.
 *
 * Useful for optional partials where absence is not an error.
 *
 * @param string               $slug
 * @param array<string, mixed> $data
 * @return bool True if the template was rendered, false if it did not exist.
 */
function smliser_render_template_if_exists( string $slug, array $data = [] ) : bool {
    if ( ! smliser_template_exists( $slug ) ) {
        return false;
    }

    smliser_render_template( $slug, $data );
    return true;
}

/**
 * Return all discovered template slugs and their resolved paths.
 *
 * Intended for development inspection and debugging only.
 *
 * @return array<string, string> Slug => absolute path, sorted alphabetically.
 */
function smliser_list_templates() : array {
    return smliser_envProvider()->templateLocator()->list_discovered();
}