<?php
/**
 * Rest API provider class.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Environments\Application;

use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;

class RestAPIProvider implements RESTProviderInterface {
    
    /**
     * Class constructor.
     * 
     * @param RESTVersionInterface[] $versions
     */
    private function __construct( protected array $versions ) {}

    /**
     * {@inheritdoc}
     */
    public function enforce_https( mixed ...$params ) : mixed {


        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function namespaces() : array {
       static $namespaces;

        if ( ! isset( $namespaces ) ) {
            $namespaces = array_map(
                static fn( $ver ) => $ver->namespace(),
                $this->versions
            );
        }

        return $namespaces;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate() {
        
    }

    /**
     * {@inheritdoc}
     */
    public function version_instances() : array {
        return $this->versions;  
    }


    public static function init( RESTVersionInterface ...$versions ) : static {
        return new static( $versions );
    }
}