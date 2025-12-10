<?php
/**
 * Unified WordPress Repository methods
 * 
 * @author Callistus Nwachukwu
 */
namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\HostedApps\Theme;
use TypeError;

trait WPRepoUtils {
    /**
     * Get the content of a plugins app.json file.
     * 
     * @param Plugin|Theme $app
     * @throws TypeError When the app instance is not of hosted plugin type.
     * @return array
     */
    public function get_app_dot_json( $app ) {
        if ( ! ( $app instanceof Plugin || $app instanceof Theme ) ) {
            throw new TypeError( sprintf(
                '%s #1 ($app) must be an instance of %s or %s, %s given',
                __METHOD__,
                Plugin::class,
                Theme::class,
                is_object( $app ) ? get_class( $app ) : gettype( $app )
            ) );
        }

        $slug = $this->real_slug( $app->get_slug() );
        $metadata   = $this->get_metadata( $slug );
        $default    = \smliser_build_wp_manifest( $app, $metadata );
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return $default;
        }

        $file_path  = FileSystemHelper::join_path( $base_dir, 'app.json' );
        $file_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        if ( ! $this->exists( $file_path ) ) {
            // Get it from the readme.txt metadata
            $json_content = \smliser_safe_json_encode( $default, $file_flags );

            if ( ! $this->put_contents( $file_path, $json_content ) ) {
                // TODO: Log unable to insert app.json file.
            }

            return $default;
        }

        $json_content   = $this->get_contents( $file_path );

        $data = \json_decode( $json_content, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }
        // Repair bad file:
        $this->put_contents(
            $file_path,
            \smliser_safe_json_encode( $default, $file_flags )
        );
        return $default;
    }
}
