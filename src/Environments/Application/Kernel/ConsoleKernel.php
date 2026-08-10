<?php
/**
 * Console kernel class file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Environments\Kernel\Application;

/**
 * Console kernel class coordinates console command lifecycle.
 */
class ConsoleKernel extends Kernel {
    /**
     * {@inheritdoc}
     */
    public function boot() : void {}

    /**
     * {@inheritdoc}
     */
    public function run() : int {


        return 0;
    }
}