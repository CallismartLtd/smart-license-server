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
     * Get the content of a plugin's or theme's app.json file.
     * 
     * @param Plugin|Theme $app
     * @throws TypeError When the app instance is not a Plugin or Theme.
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

        $slug      = $this->real_slug( $app->get_slug() );
        $metadata  = $this->get_metadata( $slug );
        $default   = \smliser_build_wp_manifest( $app, $metadata );

        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return $default;
        }

        $file_path  = FileSystemHelper::join_path( $base_dir, 'app.json' );

        if ( ! $this->exists( $file_path ) ) {
            return $this->create_app_json_file( $file_path, $default );
        }

        $json_content = $this->get_contents( $file_path );
        $data         = \json_decode( $json_content, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        // Repair corrupted file
        return $this->create_app_json_file( $file_path, $default );
    }

    /**
     * Create or repair an app.json file from defaults.
     * 
     * @param string $file_path Full path to the app.json file.
     * @param array  $default   Default app.json content.
     * @return array The content written to the file.
     */
    public function create_app_json_file( string $file_path, array $default ) : array {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $json_content = \smliser_safe_json_encode( $default, $flags );

        if ( ! $this->put_contents( $file_path, $json_content ) ) {
            // TODO: log failure to write file
        }

        return $default;
    }
}
