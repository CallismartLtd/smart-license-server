<?php
/**
 * The REST API user class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Models a REST API user.
 */
class Smliser_Rest_User {
    /**
     * The REST API user data
     * 
     * @var array $data
     */
    protected $data = array(
        'id'            => '',
        'name'          => '',
        'capabilities'  => array(),
        'status'        => '',
        'access_token'  => '',
    );

    /**
     * Related user object.
     * 
     * @var WP_User $user
     */
    protected $user;

    /**
     *  Class constructor.
     * 
     * @param WP_Rest_Request $request The REST API request object.
     */
    public function __construct( WP_REST_Request $request ) {
        
    }
}