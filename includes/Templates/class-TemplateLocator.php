<?php
/**
 * Template Locator
 *
 * Resolves, locates, and renders PHP templates from one or more
 * registered base paths, with explicit priority ordering and
 * per-request path caching.
 *
 * Resolution order (highest priority first):
 *   1. Registered paths, sorted descending by priority.
 *   2. Fallback path (if registered).
 *
 * Slug convention (dot-notation):
 *   'admin.license.dashboard' → {base}/admin/license/dashboard.php
 *
 * @package SmartLicenseServer\Templates
 */

namespace SmartLicenseServer\Templates;

use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

defined( 'SMLISER_ABSPATH' ) || exit;

class TemplateLocator {

    /**
     * Registered base paths, keyed by label.
     *
     * @var array<string, array{ path: string, priority: int }>
     */
    protected array $paths = [];

    /**
     * Explicitly known slug → absolute path map.
     *
     * Populated by TemplateDiscovery. Checked before filesystem scan.
     *
     * @var array<string, string>
     */
    protected array $known = [];

    /**
     * Fallback base path (used when no registered path resolves).
     *
     * @var string|null
     */
    protected ?string $fallback_path = null;

    /**
     * Resolved path cache.
     *
     * Keyed by template slug. Busted when a new path is registered.
     *
     * @var array<string, string|null>
     */
    protected array $cache = [];

    /*
    |------------------
    | PATH REGISTRATION
    |------------------
    */

    /**
     * Register a base path.
     *
     * @param string $label    Unique identifier for this path (e.g. 'core', 'wp', 'plugin').
     * @param string $path     Absolute directory path (no trailing slash).
     * @param int    $priority Higher number wins. Default 0.
     *
     * @throws EnvironmentBootstrapException
     */
    public function register( string $label, string $path, int $priority = 0 ) : void {
        if ( '' === $label ) {
            throw new EnvironmentBootstrapException(
                'template_error',
                'Template path label cannot be empty.'
            );
        }

        if ( ! is_dir( $path ) ) {
            throw new EnvironmentBootstrapException(
                'template_error',
                sprintf( 'Template path "%s" does not exist or is not a directory.', $path )
            );
        }

        $this->paths[ $label ] = [
            'path'     => rtrim( $path, '/\\' ),
            'priority' => $priority,
        ];

        // Bust cache — resolution order may have changed.
        $this->cache = [];
    }

    /**
     * Deregister a path by label.
     *
     * @param string $label
     * @return bool
     */
    public function deregister( string $label ) : bool {
        if ( ! isset( $this->paths[ $label ] ) ) {
            return false;
        }

        unset( $this->paths[ $label ] );
        $this->cache = [];

        return true;
    }

    /**
     * Register a fallback base path.
     *
     * Used when no registered path resolves the template.
     *
     * @param string $path Absolute directory path.
     *
     * @throws EnvironmentBootstrapException
     */
    public function set_fallback( string $path ) : void {
        if ( ! is_dir( $path ) ) {
            throw new EnvironmentBootstrapException(
                'template_error',
                sprintf( 'Fallback template path "%s" does not exist or is not a directory.', $path )
            );
        }

        $this->fallback_path = rtrim( $path, '/\\' );
        $this->cache         = [];
    }

    /*
    |-----------
    | RESOLUTION
    |-----------
    */

    /**
     * Resolve a template slug to an absolute file path.
     *
     * Checks registered paths in descending priority order, then
     * the fallback path. Returns null if nothing resolves.
     *
     * @param string $slug Dot-notation slug, e.g. 'admin.license.dashboard'.
     * @return string|null Absolute path to the template file, or null.
     */
    public function resolve( string $slug ) : ?string {
        if ( array_key_exists( $slug, $this->cache ) ) {
            return $this->cache[ $slug ];
        }

        // Known map is checked first — populated by TemplateDiscovery.
        if ( isset( $this->known[ $slug ] ) && is_file( $this->known[ $slug ] ) ) {
            $this->cache[ $slug ] = $this->known[ $slug ];
            return $this->known[ $slug ];
        }

        $relative = $this->slug_to_relative( $slug );

        foreach ( $this->sorted_paths() as $entry ) {
            $candidate = $entry['path'] . DIRECTORY_SEPARATOR . $relative;

            if ( is_file( $candidate ) ) {
                $this->cache[ $slug ] = $candidate;
                return $candidate;
            }
        }

        // Try fallback.
        if ( $this->fallback_path !== null ) {
            $candidate = $this->fallback_path . DIRECTORY_SEPARATOR . $relative;

            if ( is_file( $candidate ) ) {
                $this->cache[ $slug ] = $candidate;
                return $candidate;
            }
        }

        $this->cache[ $slug ] = null;
        return null;
    }

    /**
     * Check whether a template slug resolves to an existing file.
     *
     * @param string $slug
     * @return bool
     */
    public function exists( string $slug ) : bool {
        return null !== $this->resolve( $slug );
    }

    /*
    |-----------
    | RENDERING
    |-----------
    */

    /**
     * Render a template, extracting $data into the template scope.
     *
     * Variables in $data are available directly (e.g. $title) and also
     * as $data['title'] inside the template.
     *
     * @param string               $slug
     * @param array<string, mixed> $data
     *
     * @throws EnvironmentBootstrapException If template cannot be resolved.
     */
    public function render( string $slug, array $data = [] ) : void {
        $path = $this->resolve( $slug );

        if ( null === $path ) {
            throw new EnvironmentBootstrapException(
                'template_error',
                sprintf( 'Template "%s" could not be resolved from any registered path.', $slug )
            );
        }

        $this->load( $path, $data );
    }

    /**
     * Render a template and return the output as a string.
     *
     * @param string               $slug
     * @param array<string, mixed> $data
     * @return string
     *
     * @throws EnvironmentBootstrapException If template cannot be resolved.
     */
    public function render_to_string( string $slug, array $data = [] ) : string {
        ob_start();
        $this->render( $slug, $data );
        return (string) ob_get_clean();
    }

    /**
     * Register a known slug → path mapping directly.
     *
     * Called by TemplateDiscovery — not intended for general use.
     *
     * @param string $slug
     * @param string $path
     */
    public function add_known( string $slug, string $path ) : void {
        $this->known[ $slug ] = $path;
    }

    /*
    |-----------
    | RETRIEVAL
    |-----------
    */

    /**
     * Return all registered paths, sorted descending by priority.
     *
     * @return array<string, array{ path: string, priority: int }>
     */
    public function all() : array {
        return $this->sorted_paths();
    }

    /**
     * Check whether a label is registered.
     *
     * @param string $label
     * @return bool
     */
    public function has( string $label ) : bool {
        return isset( $this->paths[ $label ] );
    }

    /**
     * Return all discovered slugs with their resolved absolute paths.
     *
     * Keyed by slug, sorted alphabetically.
     * Useful for development inspection and debugging.
     *
     * @return array<string, string>
     */
    public function list_discovered() : array {
        $known = $this->known;
        ksort( $known );
        return $known;
    }

    /*
    |----------------
    | PRIVATE HELPERS
    |----------------
    */

    /**
     * Isolate and include the template file.
     *
     * Using a dedicated method keeps the extract() scope clean —
     * no locator internals ($this, $slug, etc.) leak into the template.
     *
     * @param string               $__templateFile
     * @param array<string, mixed> $data
     */
    private function load( string $__templateFile, array $data ) : void {
        
        extract( $data, EXTR_SKIP ); // phpcs:ignore
        require $__templateFile;
    }

    /**
     * Return registered paths sorted by priority, highest first.
     *
     * @return array<string, array{ path: string, priority: int }>
     */
    private function sorted_paths() : array {
        $paths = $this->paths;

        uasort( $paths, static function ( array $a, array $b ) : int {
            return $b['priority'] <=> $a['priority'];
        } );

        return $paths;
    }

    /**
     * Convert a dot-notation slug to a relative filesystem path.
     *
     * 'admin.license.dashboard' → 'admin/license/dashboard.php'
     *
     * @param string $slug
     * @return string
     */
    private function slug_to_relative( string $slug ) : string {
        return str_replace( '.', DIRECTORY_SEPARATOR, $slug ) . '.php';
    }
}