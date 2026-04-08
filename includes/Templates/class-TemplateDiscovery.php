<?php
/**
 * Template Discovery
 *
 * Crawls a base directory recursively and registers all .php templates
 * with the TemplateLocator using dot-notation slugs derived from their
 * relative filesystem path.
 *
 * Convention: {section}/{subject}/{action}.php → {section}.{subject}.{action}
 * Hyphens in filenames are preserved in slugs: cache-adapter → cache-adapter
 *
 * @package SmartLicenseServer\Templates
 */

namespace SmartLicenseServer\Templates;

use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

defined( 'SMLISER_ABSPATH' ) || exit;

class TemplateDiscovery {

    /**
     * The locator to register discovered templates into.
     *
     * @var TemplateLocator
     */
    protected TemplateLocator $locator;

    /**
     * @param TemplateLocator $locator
     */
    public function __construct( TemplateLocator $locator ) {
        $this->locator = $locator;
    }

    /*
    |-------------
    | DISCOVERY
    |-------------
    */

    /**
     * Crawl a directory and register all .php files as templates.
     *
     * Each file's path relative to $base_path is converted to a
     * dot-notation slug and registered under $label at $priority.
     *
     * @param string $label    Unique path label passed to TemplateLocator.
     * @param string $base_path Absolute path to the templates root.
     * @param int    $priority  Resolution priority.
     *
     * @throws EnvironmentBootstrapException
     */
    public function discover( string $label, string $base_path, int $priority = 0 ) : void {
        $base_path = rtrim( $base_path, '/\\' );

        if ( ! is_dir( $base_path ) ) {
            throw new EnvironmentBootstrapException(
                'template_error',
                sprintf( 'Discovery path "%s" does not exist or is not a directory.', $base_path )
            );
        }

        $this->locator->register( $label, $base_path, $priority );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $base_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        /** @var SplFileInfo $file */
        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
                continue;
            }

            $slug = $this->path_to_slug( $base_path, $file->getPathname() );
            $this->locator->add_known( $slug, $file->getPathname() );
        }
    }

    /*
    |----------------
    | PRIVATE HELPERS
    |----------------
    */

    /**
     * Convert an absolute file path to a dot-notation slug.
     *
     * /var/www/templates/admin/license/view.php → admin.license.view
     *
     * @param string $base_path
     * @param string $file_path
     * @return string
     */
    private function path_to_slug( string $base_path, string $file_path ) : string {
        $relative = ltrim(
            str_replace( $base_path, '', $file_path ),
            '/\\'
        );

        // Strip .php extension.
        $relative = substr( $relative, 0, -4 );

        // Normalize directory separators and convert to dots.
        return str_replace(
            [ DIRECTORY_SEPARATOR, '/' ],
            '.',
            $relative
        );
    }
}