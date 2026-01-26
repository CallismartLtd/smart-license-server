<?php
/**
 * The actor interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\Actors;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

interface ActorInterface {
    /*
    |--------------
    | SETTERS
    |--------------
    */

    /**
     * Set actor ID.
     * 
     * @param int $id
     */
    public function set_id( $id ) : static;

    /**
     * Set actor display name
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
     * @param string|\DateTimeImmutable $date
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
    public function get_id() : int;

    /**
     * Get principal display name
     * 
     * @return string
     */
    public function get_display_name() : string;

    /**
     * Get status
     * 
     * @return string
     */
    public function get_status() : string;

    /**
     * Get date created
     * 
     * @return null|\DateTimeImmutable
     */
    public function get_created_at() : ?\DateTimeImmutable;

    /**
     * Get last updated
     * 
     * @return null|\DateTimeImmutable
     */
    public function get_updated_at() : ?\DateTimeImmutable;

    /**
     * Get the actor type.
     * - eg. user, service_account e.t.c
     * @return string
     */
    public function get_type() : string;

    /**
     * Get the actor avatar URL.
     * 
     * @return \SmartLicenseServer\Core\URL
     */
    public function get_avatar() : \SmartLicenseServer\Core\URL;

    /**
     * Get allowed statuses.
     * 
     * @return array
     */
    public static function get_allowed_statuses() : array;

    /**
     * Count total records by status
     * 
     * @param string $status
     * @return int
     */
    public static function count_status( $status ) : int;

    /**
     * Hydrate from array.
     * 
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ) : static;
    /**
     * Convert to array.
     * 
     * @return array
     */
    public function to_array();
}