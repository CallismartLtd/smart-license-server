<?php
/**
 * Repository files aware trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Exceptions\FileSystemException;

/**
 * Provides unified methods to get known and shared repository files like 
 * - readme.md
 * - installation.md
 * - changelog.md
 */

trait RepoFilesAwareTrait {
    /**
     * Get theme changelog.
     * 
     * @return string The changelog in HTML format.
     */
    public function get_changelog( $slug ) : string {
        $changelog_md = $this->file_get_contents( $slug, 'changelog.md' );
        return $this->parser->parse( $changelog_md ?: '' );
    }

    /**
     * Get installation.md file
     * 
     * @param string $slug
     * @return string
     */
    public function get_installation( $slug ) {
        $installation = $this->file_get_contents( $slug, 'installation.md' );
        return $this->parser->parse( $installation ?: '' );
    }

    /**
     * Get readme.md file
     * 
     * @param string $slug
     * @return string
     */
    public function get_readme( string $slug ) : string {
        $readme = $this->file_get_contents( $slug, 'readme.md' );
        return $this->parser->parse( $readme ?: '' );
    }

    /**
     * Get the content of a file from the repository and optionally look for it in the zipped file.
     * 
     * @param string $slug
     * @param string $filename The file name to look for.
     * @return string the content of the file.
     */
    private function file_get_contents( string $slug, string $filename ) : string  {
        $slug           = $this->real_slug( $slug );
        $file_contents  = '';
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return $file_contents;
        }
        $file_path = FileSystemHelper::join_path( $base_dir, $filename );

        if ( ! $this->exists( $file_path ) ) {
            // Let's try to read from the zip file as a fallback.
            $zip_path   = $this->locate( $slug );
            if ( is_smliser_error( $zip_path ) ) {
                return $file_contents;
            }

            $zip = new \ZipArchive();
            if ( $zip->open( $zip_path ) !== true ) {
                return $file_contents;
            }

            $firstEntry = $zip->getNameIndex(0);
            $rootDir    = explode( '/', $firstEntry)[0];
            $name       = FileSystemHelper::join_path( $rootDir, $filename );
            $file_index = $zip->locateName( $name, \ZipArchive::FL_NOCASE );

            if ( false === $file_index ) {
                $zip->close();
                return $file_contents;
            }

            $file_contents = $zip->getFromIndex( $file_index );
            $zip->close();

            // cache the file for future use.
            if ( ! $this->put_contents( $file_path, $file_contents ) ) {
                // TODO: Log error?
            }

        } else {
            $file_contents = $this->get_contents( $file_path );
        }

        return $file_contents;
    }
}