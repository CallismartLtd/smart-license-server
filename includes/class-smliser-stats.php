<?php
/**
 * filename class-smliser-plugin-stats.php
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_Stats {
    /**
     * ID
     * 
     * @var int $id The database table id of a stat
     */
    /**
     * Plugin
     * 
     * @var Smliser_Plugin $plugin The instance of Smliser Plugin.
     */
    private $plugin;

    /**
     * License
     * 
     * @var Smliser_license $license The license object.
     */
    private $license;

    /**
     * Endpoint Access log.
     * 
     * @var array $access_log Log for each endpoint access.
     */
    private $access_log = array(
        'validation'    => 0,
        'deactivation'  => 0,
        'plugin_update' => 0,
        'download_log'  => 0,
    );

    /**
     * Response Log.
     * 
     * @var array $response_log Log for responses given.
     */
    private $response_log = array(
        'time'          => '',
        'status code'   => 0,
        'client IP'     => 0,
        'website'       => '',
    );

    /**
     * Class constructor
     */
    public function __construct() {
        $this->plugin   = new Smliser_Plugin();
        $this->license  = new Smliser_license();
    }

    /**
     * Plugin property getter
     */
    public function plugin() {
        return $this->plugin;
    }

    /**
     * License Property getter
     */
    public function license() {
        return $this->license;
    }

    /*
    |---------
    | SETTERS
    |---------
    */

    /**
     * Set access log for a property
     * 
     * @param array $log Data to log.
     */
    public function set_access_log( $log ) {
        if ( isset( $log['validation'] ) ) {
            $this->access_log['validation'] = sanitize_text_field( $log['validation'] );
        }

        if ( isset( $log['deactivation'] ) ) {
            $this->access_log['deactivation'] = sanitize_text_field( $log['deactivation'] );
        }

        if ( isset( $log['plugin_update'] ) ) {
            $this->access_log['plugin_update'] = sanitize_text_field( $log['plugin_update'] );
        }

        if ( isset( $log['download_log'] ) ) {
            $this->access_log['download_log'] = sanitize_text_field( $log['download_log'] );
        }
    }

    /**
     * Set reponse logs
     * 
     * @param array $response The response data.
     */
    public function set_response_log( $response ) {
      $this->response_log['time'] = isset( $response['time'] ) ? sanitize_text_field( $response['time'] ) : current_time( 'mysql' );

      if ( isset( $response['status code'] ) ) {
        $this->response_log['status code'] = sanitize_text_field( $response['status code'] );
      }

      if ( isset( $response['client IP'] ) ) {
        $this->response_log['client IP'] = sanitize_text_field( $response['client IP'] );
      }

      if ( isset( $response['website'] ) ) {
        $this->response_log['website'] = sanitize_url( $response['website'], array( 'http', 'https' ) );
      }
    }

    /**
     * 
     */
}