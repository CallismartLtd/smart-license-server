<?php
/**
 * PSR-4 Autoloader for Smart License Server.
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
        'SmartLicenseServer\\' => SMLISER_SRC_DIR,
    );

    /**
     * Function directories to autoload
     * 
     * @var array
     */
    private static array $function_dirs = array(
        SMLISER_SRC_DIR . 'Utils/functions/',
    );

    /**
     * Loaded function files
     * 
     * @var array
     */
    private static array $loaded_function_files = array();
    
    /**
     * Register the autoloader
     */
    public static function boot() : void {
        static::require_vendor();
        // spl_autoload_register( array( __CLASS__, 'autoload' ), true, false );
        static::autoload_functions();
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class The fully-qualified class name.
     */
    public static function autoload( $class ) : void {    
        error_log( 'autoload called' );   
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
        $filename   = $class_name . '.php';
        $full_path  = $base_dir . $filename;
        
        return file_exists( $full_path ) ? $full_path : false;
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
        foreach ( self::$function_dirs as $dir ) {
            self::load_function_dir( $dir );
        }
    }

    /**
     * Require composer autoloader
     */
    private static function require_vendor() : void {
        require_once SMLISER_PATH . 'vendor/autoload.php';
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