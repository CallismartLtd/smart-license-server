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
    
    public function __construct( protected RESTVersionInterface $version ) {

    }

    /**
     * {@inheritdoc}
     */
    public function enforce_https( mixed ...$params ) : mixed {


        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function namespace() : string {
        return 'smliser';
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate() {
        
    }

    /**
     * {@inheritdoc}
     */
    public function version_instance() : RESTVersionInterface {
        return $this->version;  
    }


}