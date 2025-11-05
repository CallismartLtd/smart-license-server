<?php
/**
 * The license class file
 * 
 * @author Callistus <admin@callismart.com.ng>
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;
/**
 * The license class represents a licensing model for hosted applications in the repository.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */
class License {
    /**
     * The license ID
     * 
     * @var int $id
     */
    private int $id = 0;

    /**
     * @var int $user_id    The ID of user associated with this license.
     */
    private $user_id    = 0;

    /**
     * @var string $license_key The license key
     */
    private $license_key = '';

    /**
     * @var string $service_id  The ID of the service associated with the license.
     */
    private $service_id = '';

    /**
     * @var int $item_id    The ID of the item(maybe product) being licensed.
     */
    private $item_id    = 0;

    /**
     * @var string $status The status of the license
     */
    private $status = '';

    /**
     * @var string $start_date  The license commencement date
     */
    private $start_date = '';

    /**
     * @var string  $end_date   The license termination or deactivation date.
     */
    private $end_date   = '';

    /**
     * Allowed websites.
     * 
     * @var int $allowed_sites  Number of allowed website for a license
     */
    private $allowed_sites;

}