<?php
/**
 * Core runtime class file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments\Application;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environment;

/**
 * Smart License Server running as a standalone web application.
 */
class ApplicationEnvironment extends Environment {
   
   
   /**
    * {@inheritdoc}
    */
    public static function assetsUrl( string $path = '', array $q = [] ) : URL {
        return static::url( '/assets/', $q )
        ->append_path( $path );
    }
}