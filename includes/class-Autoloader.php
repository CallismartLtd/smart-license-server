<?php
/**
 * PSR-4 Autoloader for Smliser Plugin
 * 
 * Handles the WordPress naming convention:
 * - class-ClassName.php
 * - interface-InterfaceName.php
 * - Other prefixes as needed
 * 
 * @package Smliser
 */

namespace SmartLicenseServer;

class Autoloader {
    
    /**
     * Namespace to directory mappings
     * 
     * @var array
     */
    private static $namespaces = array(
        'SmartLicenseServer\\' => SMLISER_PATH . 'includes/',
    );
    
    /**
     * File prefixes for WordPress naming convention
     * 
     * @var array
     */
    private static $prefixes = array(
        'class-',
        'interface-',
        'trait-',
        'abstract-',
    );
    
    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class The fully-qualified class name.
     */
    public static function autoload( $class ) {       
        // Check each registered namespace
        foreach ( self::$namespaces as $namespace => $base_dir ) {
            // Does the class use the namespace prefix?
            $len = strlen( $namespace );
            if ( strncmp( $namespace, $class, $len ) !== 0 ) {
                continue;
            }
            
            // Get the relative class name
            $relative_class = substr( $class, $len );
            
            // Try to load the file
            $file = self::get_file_path( $base_dir, $relative_class );
            
            if ( $file && file_exists( $file ) ) {
                require_once $file;
                return;
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
    private static function get_file_path( $base_dir, $class_name ) {
        // Replace namespace separators with directory separators
        $class_name = str_replace( '\\', \DIRECTORY_SEPARATOR, $class_name );

        $not_found = [];

        // Try each prefix
        foreach ( self::$prefixes as $prefix ) {
            // Convert ClassName to class-ClassName.php
            $filename = self::class_to_filename( $prefix, $class_name );
             
            $full_path = $base_dir . $filename;
            
            if ( file_exists( $full_path ) ) {
                return $full_path;
            } else {

                $not_found[] = $full_path;
           
            }
        }
        
        \pretty_print( $not_found );
        // Try without prefix (for edge cases)
        // $filename = self::class_to_filename( $class_base_name ) . '.php';
        // $full_path = $base_dir . 
        //              ( $directory ? $directory . '/' : '' ) . 
        //              $filename;
        
        // return file_exists( $full_path ) ? $full_path : false;
    }
    
    /**
     * Convert class name to filename.
     * 
     * @param string $prefix The file prefix.
     * @param string $class_name
     * @return string
     */
    private static function class_to_filename( $prefix, $class_name ) {
        $parts      = explode( '/', $class_name );
        $filename   = $prefix . end( $parts );

        array_splice( $parts,  count( $parts ) - 1, count( $parts ), $filename );
        
        return \sprintf( '%s.php', implode( '/', $parts ) );
    }
    
    /**
     * Add a namespace mapping
     * 
     * @param string $namespace The namespace
     * @param string $base_dir  The base directory
     */
    public static function add_namespace( $namespace, $base_dir ) {
        self::$namespaces[ $namespace ] = trailingslashit( $base_dir );
    }
}

// Register the autoloader
Autoloader::register();