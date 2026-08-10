<?php
/**
 * Console kernel class file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Environments\Application\Kernel;

/**
 * Console kernel class coordinates console command lifecycle.
 */
class ConsoleKernel extends Kernel {
    /**
     * {@inheritdoc}
     */
    public function boot() : static {

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {


        return $this;
    }
}