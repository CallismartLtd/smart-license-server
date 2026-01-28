<?php
/**
 * Owner subject interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\OwnerSubject
 */

namespace SmartLicenseServer\Security\OwnerSubjects;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * An owner subject is an entity that can own resources in the system.
 * The contracts all the classes that can be owners' subjects must implement.
 */
interface OwnerSubjectInterface {
    /**
     * Get the ID of the owner subject.
     * 
     * @return int
     */
    public function get_id() : int;

    /**
     * Get name of the owner subject.
     * 
     * @return string
     */
    public function get_name() : string;

    /**
     * Get type of the owner subject.
     * 
     * @return string
     */
    public function get_type() : string;
}