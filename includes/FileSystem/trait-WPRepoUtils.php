<?php
/**
 * Unified WordPress Repository utilities.
 *
 * Handles app.json resolution, reading, creation, and regeneration
 * for hosted WordPress plugins and themes.
 *
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\HostedApps\Theme;
use TypeError;

trait WPRepoUtils {

    /**
     * Get the content of an app's app.json file.
     *
     * If the file does not exist or is corrupted, it will be regenerated
     * from the canonical manifest builder.
     *
     * @param Plugin|Theme $app
     * @return array
     *
     * @throws TypeError When $app is not a Plugin or Theme.
     */
    public function get_app_dot_json( $app ) : array {

        try {
            $resolved = $this->resolve_app_manifest( $app );
        } catch ( \InvalidArgumentException $e ) {
            // Slug directory does not exist yet — return canonical manifest
            return $this->build_app_manifest( $app );
        }

        $file_path = $resolved['file_path'];
        $default   = $resolved['manifest'];

        if ( ! $this->exists( $file_path ) ) {
            return $this->write_app_json_file( $file_path, $default );
        }

        $json = $this->get_contents( $file_path );
        $data = \json_decode( $json, true );

        if ( \json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        // Repair corrupted or unreadable file
        return $this->write_app_json_file( $file_path, $default );
    }

    /**
     * Regenerate an app.json file from the canonical manifest.
     *
     * This method ALWAYS rewrites the file, regardless of its current state.
     *
     * @param Plugin|Theme $app
     * @return array The regenerated manifest content.
     *
     * @throws TypeError When $app is not a Plugin or Theme.
     */
    public function regenerate_app_dot_json( $app ) : array {
        $resolved = $this->resolve_app_manifest( $app );

        return $this->write_app_json_file(
            $resolved['file_path'],
            $resolved['manifest']
        );
    }

    /**
     * Resolve app.json file path and canonical manifest.
     *
     * @param Plugin|Theme $app
     * @return array {
     *     @type string $file_path
     *     @type array  $manifest
     * }
     *
     * @throws TypeError
     * @throws \InvalidArgumentException When slug directory cannot be entered.
     */
    protected function resolve_app_manifest( $app ) : array {

        if ( ! ( $app instanceof Plugin || $app instanceof Theme ) ) {
            throw new TypeError( sprintf(
                '%s #1 ($app) must be an instance of %s or %s, %s given',
                __METHOD__,
                Plugin::class,
                Theme::class,
                is_object( $app ) ? get_class( $app ) : gettype( $app )
            ) );
        }

        $slug      = $this->real_slug( $app->get_slug() );
        $manifest  = $this->build_app_manifest( $app, $slug );
        $base_dir  = $this->enter_slug( $slug );
        $file_path = FileSystemHelper::join_path( $base_dir, 'app.json' );

        return array(
            'file_path' => $file_path,
            'manifest'  => $manifest,
        );
    }

    /**
     * Build the canonical app manifest.
     *
     * @param Plugin|Theme $app
     * @param string|null  $slug Optional resolved slug.
     * @return array
     */
    protected function build_app_manifest( $app, ?string $slug = null ) : array {
        $slug     = $slug ?? $this->real_slug( $app->get_slug() );
        $metadata = $this->get_metadata( $slug );

        return \smliser_build_wp_manifest( $app, $metadata );
    }

    /**
     * Write (or overwrite) an app.json file.
     *
     * @param string $file_path Full file path.
     * @param array  $manifest  Manifest content.
     * @return array The written manifest.
     */
    protected function write_app_json_file( string $file_path, array $manifest ) : array {

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $json  = \smliser_safe_json_encode( $manifest, $flags );

        $this->put_contents( $file_path, $json );

        return $manifest;
    }
}
