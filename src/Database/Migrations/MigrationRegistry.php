<?php
/**
 * Migration Registry class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Registry for discovering and managing migrations.
 *
 * Auto-discovers migration classes from a directory.
 * Organizes migrations by version and provides metadata.
 *
 * @since 0.2.0
 */
class MigrationRegistry {

    /**
     * Migrations organized by version.
     *
     * @var array<string, array{
     *      version: string,
     *      class: string,
     *      label: string,
     *      description: string
     * }> $migrations
     */
    private $migrations = [];

    /**
     * Directory to scan for migrations.
     *
     * @var string
     */
    private $migrations_dir;

    /**
     * Constructor.
     *
     * @param string $migrations_dir The directory containing migration files
     *
     * @throws \Exception If directory doesn't exist
     */
    public function __construct( string $migrations_dir ) {
        if ( ! is_dir( $migrations_dir ) ) {
            throw new \Exception( "Migrations directory does not exist: {$migrations_dir}" );
        }

        $this->migrations_dir = rtrim( $migrations_dir, DIRECTORY_SEPARATOR );
        $this->discover();
    }

    /**
     * Auto-discover migration classes from the migrations directory.
     *
     * Scans for files named Migration[4-digits].php and loads them.
     *
     * @return void
     */
    private function discover() : void {
        $files = glob( $this->migrations_dir . '/Migration[0-9][0-9][0-9][0-9].php' );

        if ( ! $files ) {
            return;
        }

        foreach ( $files as $file ) {
            // Extract class name from filename
            $class_file = basename( $file, '.php' );

            // Dynamically determine namespace from file location
            $namespace = $this->get_namespace_from_file( $file );
            $class_name = $namespace . '\\' . $class_file;

            // Load the file
            require_once $file;

            // Check if class exists and implements interface
            if ( ! class_exists( $class_name ) ) {
                continue;
            }

            if ( ! in_array( MigrationInterface::class, class_implements( $class_name ) ?: [], true ) ) {
                continue;
            }

            // Get version and register
            try {
                $version = $class_name::get_version();
                $this->register( $version, $class_name );
            } catch ( \Exception $e ) {
                // Skip invalid migrations
                continue;
            }
        }
    }

    /**
     * Get the namespace from a PHP file.
     *
     * Extracts the namespace declaration from the file.
     *
     * @param string $file The file path
     *
     * @return string The namespace, or empty string if none found
     */
    private function get_namespace_from_file( string $file ) : string {
        $content = file_get_contents( $file );
        if ( preg_match( '/namespace\s+([^;]+);/', $content, $matches ) ) {
            return trim( $matches[1] );
        }
        return 'SmartLicenseServer\Database\Migrations';
    }

    /**
     * Register a migration.
     *
     * @param string $version    The migration version
     * @param string $class_name The migration class name
     *
     * @return void
     */
    public function register( string $version, string $class_name ) : void {
        $this->migrations[ $version ] = [
            'version' => $version,
            'class' => $class_name,
            'label' => $class_name::get_label() ?? "Migration {$version}",
            'description' => $class_name::get_description() ?? '',
        ];
    }

    /**
     * Get all registered migrations.
     *
     * @return array<string, array{
     *      version: string,
     *      class: string,
     *      label: string,
     *      description: string
     * }> Migrations organized by version
     */
    public function get_all() : array {
        return $this->migrations;
    }

    /**
     * Get a migration by version.
     *
     * @param string $version The migration version
     *
     * @return array{
     *      version: string,
     *      class: string,
     *      label: string,
     *      description: string
     * }|null The migration metadata, or null if not found
     */
    public function get( string $version ) : ?array {
        return $this->migrations[ $version ] ?? null;
    }

    /**
     * Get all versions in order.
     *
     * @return array Array of version strings in ascending order
     */
    public function get_versions() : array {
        $versions = array_keys( $this->migrations );
        usort( $versions, 'version_compare' );
        return $versions;
    }

    /**
     * Get migrations after a specific version.
     *
     * @param string $after_version The version to start after (or empty for all)
     *
     * @return array{version: string, class: string, label: string, description: string}[] Array of migrations after the specified version
     */
    public function get_pending( string $after_version = '' ) : array {
        $versions = $this->get_versions();

        if ( empty( $after_version ) ) {
            return array_map( fn( $v ) => $this->migrations[ $v ], $versions );
        }

        $pending = [];
        $found = false;

        foreach ( $versions as $version ) {
            if ( $found ) {
                $pending[] = $this->migrations[ $version ];
            } elseif ( version_compare( $version, $after_version, '>' ) ) {
                $pending[] = $this->migrations[ $version ];
                $found = true;
            }
        }

        return $pending;
    }
}