<?php
/**
 * PSR-4 Autoloader for Smart License Server.
 * 
 * Handles the our naming convention:
 * - class-ClassName.php
 * - interface-InterfaceName.php
 * - Other prefixes as needed
 * 
 * @package SmartLicenseServer
 * @since   0.2.0
 */

namespace SmartLicenseServer;

use const DIRECTORY_SEPARATOR;

class Autoloader {
    
    /**
     * Namespace to directory mappings
     * 
     * @var array
     */
    private static array $namespaces = array(
        'SmartLicenseServer\\' => SMLISER_PATH . 'includes/',
    );

    /**
     * Function directories to autoload
     * 
     * @var array
     */
    private static array $function_dirs = array(
        SMLISER_PATH . 'includes/Utils/functions/',
    );

    /**
     * Loaded function files
     * 
     * @var array
     */
    private static array $loaded_function_files = array();
    
    /**
     * File prefixes for our naming convention
     * 
     * @var array
     */
    private static array $prefixes = array(
        'class-',
        'interface-',
        'trait-',
        'abstract-',
    );
    
    /**
     * Register the autoloader
     */
    public static function boot() : void {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
        static::autoload_functions();
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class The fully-qualified class name.
     */
    public static function autoload( $class ) : void {       
        // Check each registered namespace.
        foreach ( self::$namespaces as $namespace => $base_dir ) {
            // Does the class use the namespace prefix?
            $len = strlen( $namespace );
            if ( strncmp( $namespace, $class, $len ) !== 0 ) {
                continue;
            }
            
            // Get the relative class name.
            $relative_class = substr( $class, $len );
            
            // Try to load the file
            $file = self::get_file_path( $base_dir, $relative_class );
            
            if ( $file ) {
                require_once $file;
                break;
            }
        }
    }
    
    /**
     * Convert class name to file path
     * 
     * @param string $base_dir   Base directory
     * @param string $class_name Relative class name
     * @return string|false File path or false if not found
     */
    private static function get_file_path( $base_dir, $class_name ) : string|bool {
        // Replace namespace separators with directory separators.
        $class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );
        // Try each prefix.
        foreach ( self::$prefixes as $prefix ) {
            // Convert ClassName to class-ClassName.php
            $filename = self::class_to_filename( $prefix, $class_name );
             
            $full_path = $base_dir . $filename;
            
            if ( file_exists( $full_path ) ) {
                return $full_path;
            }
        }
        
        // Try without prefix as fallback.
        $filename = $class_name . '.php';
        $full_path = $base_dir . $filename;
        
        return file_exists( $full_path ) ? $full_path : false;
    }
    
    /**
     * Convert class name to filename
     * 
     * Examples:
     * - Admin\Menu + class- → Admin/class-Menu.php
     * - Database\DatabaseAdapterInterface + interface- → Database/interface-DatabaseAdapterInterface.php
     * 
     * @param string $prefix     The file prefix (class-, interface-, etc.)
     * @param string $class_name The relative class name with namespace separators replaced
     * @return string The complete file path
     */
    private static function class_to_filename( string $prefix, string $class_name ) : string {
        // Split by directory separator.
        $parts = explode( DIRECTORY_SEPARATOR, $class_name );
        
        // Get the last part (actual class name).
        $className = array_pop( $parts );
        
        // Add prefix to the class name.
        $filename = $prefix . $className . '.php';
        
        // Rebuild the path: directory/prefix-ClassName.php
        if ( ! empty( $parts ) ) {
            return implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR . $filename;
        }
        
        return $filename;
    }
    
    /**
     * Add a namespace mapping
     * 
     * @param string $namespace The namespace
     * @param string $base_dir  The base directory
     */
    public static function add_namespace( $namespace, $base_dir ) {
        self::$namespaces[ $namespace ] = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
    }

    /**
     * Add a function directory to autoload
     * 
     * @param string $dir The directory path
     */
    public static function add_function_dir( $dir ) : void {
        $dir = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

        if ( ! in_array( $dir, self::$function_dirs, true ) ) {
            self::$function_dirs[] = $dir;

            // Immediately load functions from this directory.
            self::load_function_dir( $dir );
        }
    }

    /**
     * Autoload all function files from registered function directories.
     */
    private static function autoload_functions() : void {
        require_once SMLISER_PATH . 'vendor/autoload.php';

        foreach ( self::$function_dirs as $dir ) {
            self::load_function_dir( $dir );
        }
    }

    /**
     * Load function files from a directory, ensuring each file is only loaded once.
     */
    private static function load_function_dir( string $dir ) : void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        foreach ( glob( $dir . '*.php' ) as $file ) {
            if ( isset( self::$loaded_function_files[ $file ] ) ) {
                continue;
            }

            require_once $file;

            self::$loaded_function_files[ $file ] = true;
        }
    }
}

Autoloader::boot();