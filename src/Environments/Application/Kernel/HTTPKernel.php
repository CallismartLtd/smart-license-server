<?php
/**
 * Http kernel class file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Environments\Kernel\Application;

/**
 * Http kernel class coordinates http request and response lifecycle.
 */
class HTTPKernel extends Kernel {
    /**
     * {@inheritdoc}
     */
    public function boot() : void {

    }

    /**
     * {@inheritdoc}
     */
    public function run() : int {



        return 0;
    }
}