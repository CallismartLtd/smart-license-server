<?php
/**
 * Htaccess writer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use InvalidArgumentException;
use RuntimeException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Class HtaccessWriter
 *
 * Safe, marker-aware Apache .htaccess configuration writer.
 *
 * All file access goes through the FileSystem API.
 */
class HtaccessWriter {

    /**
     * Path to the target production .htaccess file.
     *
     * @var string
     */
    private string $target_path;

    /**
     * Filesystem API.
     *
     * @var FileSystem
     */
    private FileSystem $fs;

    /**
     * File content represented as an array of individual lines.
     *
     * @var array<int, string>
     */
    private array $lines = [];

    /*
    |--------------------------
    | CONSTRUCTOR & FACTORY
    |--------------------------
    */

    /**
     * HtaccessWriter constructor.
     *
     * @param string          $target_path Absolute or relative path to the target .htaccess file.
     * @param FileSystem|null $fs          Filesystem API; the shared instance when null.
     * @throws RuntimeException If the target file exists but cannot be read.
     */
    public function __construct( string $target_path, ?FileSystem $fs = null ) {
        $this->target_path = $target_path;
        $this->fs          = $fs ?? FileSystem::instance();

        if ( $this->fs->is_file( $this->target_path ) ) {
            $this->load_from_file( $this->target_path );
        }
    }

    /**
     * Create an instance pre-populated with the production block extracted from an example file.
     *
     * @param string          $example_path Path to the template file (.htaccess.example).
     * @param string          $target_path  Path to the output .htaccess file.
     * @param FileSystem|null $fs           Filesystem API; the shared instance when null.
     * @return static
     * @throws InvalidArgumentException If the example template file does not exist.
     * @throws RuntimeException         If reading the template file fails or production markers are missing.
     */
    public static function create_from_example( string $example_path, string $target_path, ?FileSystem $fs = null ): static {
        $fs ??= FileSystem::instance();

        if ( ! $fs->is_file( $example_path ) ) {
            throw new InvalidArgumentException( "Example template not found at path: {$example_path}" );
        }

        $content = $fs->get_contents( $example_path );
        if ( false === $content ) {
            throw new RuntimeException( "Failed to read content from example path: {$example_path}" );
        }

        $pattern = '/#\s*<SMLISER-PRODUCTION-BEGIN>(.*?)#\s*<SMLISER-PRODUCTION-END>/s';
        if ( preg_match( $pattern, $content, $matches ) ) {
            $extracted_content = trim( $matches[1] );
        } else {
            throw new RuntimeException( "Missing required production markers (<SMLISER-PRODUCTION-BEGIN> and <SMLISER-PRODUCTION-END>) in {$example_path}" );
        }

        $instance = new static( $target_path, $fs );
        $instance->parse_lines( explode( "\n", $extracted_content ) );

        return $instance;
    }

    /*
    |-----------
    | SETTERS & MODIFIERS
    |-----------
    */

    /**
     * Append a line or block of raw directives.
     *
     * @param string $directive Single directive line or multi-line string block.
     * @return static
     */
    public function append( string $directive ): static {
        $new_lines = explode( "\n", $directive );
        foreach ( $new_lines as $line ) {
            $this->lines[] = rtrim( $line, "\r\n" );
        }

        return $this;
    }

    /**
     * Prepend a line or block of raw directives to the beginning of the file.
     *
     * @param string $directive Single directive line or multi-line string block.
     * @return static
     */
    public function prepend( string $directive ): static {
        $raw_lines = explode( "\n", $directive );
        $prepended = [];

        foreach ( $raw_lines as $line ) {
            $prepended[] = rtrim( $line, "\r\n" );
        }

        $this->lines = array_merge( $prepended, $this->lines );

        return $this;
    }

    /*
    |--------------
    | PERSISTENCE
    |--------------
    */

    /**
     * Write the current state to the target file atomically.
     *
     * Creates the target directory when missing, writes a temporary file next
     * to the target, then renames it over the target, so the web server never
     * reads a partially written file.
     *
     * @return bool True on successful write, false on failure.
     * @throws RuntimeException If target directory cannot be created or is not writable.
     */
    public function save(): bool {
        $target_dir = dirname( $this->target_path );

        if ( ! $this->fs->is_dir( $target_dir ) && ! $this->fs->mkdir( $target_dir, false, true ) && ! $this->fs->is_dir( $target_dir ) ) {
            throw new RuntimeException( "Target directory could not be created: {$target_dir}" );
        }

        if ( ! $this->fs->is_writable( $target_dir ) ) {
            throw new RuntimeException( "Target directory is not writable: {$target_dir}" );
        }

        $content   = implode( "\n", $this->lines ) . "\n";
        $temp_path = $this->target_path . '.' . uniqid( 'tmp_', true );

        if ( ! $this->fs->put_contents( $temp_path, $content ) ) {
            return false;
        }

        if ( ! $this->fs->rename( $temp_path, $this->target_path ) ) {
            $this->fs->delete( $temp_path );
            return false;
        }

        return true;
    }

    /*
    |-----------------------------
    | INTERNAL PARSING & HELPERS
    |-----------------------------
    */

    /**
     * Read file content and load into lines array.
     *
     * @param string $file_path Absolute or relative file path.
     * @return void
     * @throws RuntimeException If unable to read target file.
     */
    private function load_from_file( string $file_path ): void {
        $content = $this->fs->get_contents( $file_path );
        if ( false === $content ) {
            throw new RuntimeException( "Failed to read target file: {$file_path}" );
        }

        // save() ends the file with one line break; drop it so a reload does not
        // add an empty line, which would grow by one on every save.
        $content = preg_replace( '/\r?\n\z/', '', $content );

        $this->parse_lines( explode( "\n", $content ) );
    }

    /**
     * Parse array of raw lines into memory.
     *
     * @param array<int, string> $raw_lines Raw lines from template or file.
     * @return void
     */
    private function parse_lines( array $raw_lines ): void {
        $this->lines = [];

        foreach ( $raw_lines as $line ) {
            $this->lines[] = rtrim( $line, "\r\n" );
        }
    }
}