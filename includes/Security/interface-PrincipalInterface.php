<?php
/**
 * The principal interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

interface PrincipalInterface {
    /*
    |--------------
    | SETTERS
    |--------------
    */

    /**
     * Set principal ID.
     * 
     * @param int $id
     */
    public function set_id( $id );

    /**
     * Set principal display name
     * 
     * @param string $name
     */
    public function set_display_name( $name ) : static;

    /**
     * Set status
     * 
     * @param string $status
     */
    public function set_status( $status ) : static;

    /**
     * Set date created
     * 
     * @param string|\DateTimeImmutable $date
     */
    public function set_created_at( $date ) : static;

    /**
     * Set last updated
     * 
     * @param string|\DatetimeImmutable $date
     */
    public function set_updated_at( $date ) : static;

    /*
    |--------------
    | GETTERS
    |--------------
    */

    /**
     * Get principal ID.
     * 
     * @return int
     */
    public function get_id();

    /**
     * Get principal display name
     * 
     * @return string $name
     */
    public function get_display_name();

    /**
     * Get status
     * 
     * @return string $status
     */
    public function get_status();

    /**
     * Get date created
     * 
     * @return null|\DateTimeImmutable $date
     */
    public function get_created_at();

    /**
     * Get last updated
     * 
     * @return null|\DatetimeImmutable $date
     */
    public function get_updated_at();
}