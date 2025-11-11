<?php
/**
 * The license class file
 * 
 * @author Callistus <admin@callismart.com.ng>
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;
use \SmartLicenseServer\HostedApps\Hosted_Apps_Interface;

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
    protected $id = 0;

    /**
     * The ID of user associated with this license
     * 
     * @var int $user_id
     */
    protected $user_id    = 0;

    /**
     * The license key
     * 
     * @var string $license_key
     */
    protected $license_key = '';

    /**
     * The ID of the service associated with the license.
     * This property will be required for license activation.
     * 
     * @var string $service_id
     */
    protected $service_id = '';

    /**
     * The type and slug of the hosted application this license is issued to.
     * 
     * @var array $app_prop
     */
    protected $app_prop    = [
        'type'  => '',
        'slug'  => ''
    ];

    /**
     * The instance of the hosted aplication this license is issued to.
     * 
     * @var Hosted_Apps_Interface
     */
    protected $app;

    /**
     * The status of the license.
     * 
     * @var string $status
     */
    protected $status = '';

    /**
     * The license commencement date.
     * 
     * @var string $start_date
     */
    protected $start_date = '';

    /**
     * The end date of the license.
     * 
     * @var string  $end_date
     */
    protected $end_date   = '';

    /**
     * Number of allowed domains that can request license activation.
     * 
     * @var int $max_allowed_domains
     */
    protected $max_allowed_domains;

    /**
     * The license meta data
     * 
     * @var array $meta_data
     */
    protected $meta_data = array();

    /**
     * Class constructor
     */
    public function __construct() {}

    /**
    |-------------
    | SETTERS
    |-------------
    */

    /**
     * Set the license ID.
     * 
     * @param int $id
     * @return self
     */
    public function set_id( $id ) : self {
        $this->id = max( 0, intval( $id ) );

        return $this;
    }

    /**
     * Set the the ID of the user associated with the license
     * 
     * @param int $user_id The user ID, pass 0 for guest license.
     * @return self
     */
    public function set_user_id( $user_id ) : self {
        $this->user_id = max( 0, intval( $user_id ) );

        return $this;
    }

    /**
     * Set the license key
     *
     * @param string $value
     * @return self
     */
    public function set_license_key( $value ) : self {
        $this->license_key = \sanitize_text_field( unslash( $value ) );

        return $this;
    }

    /**
     * Set the service ID for this license
     * 
     * @param string $service_id The service ID for this license.
     * @return self
     */
    public function set_service_id( $service_id ) : self {
        $this->service_id = \sanitize_text_field( \unslash( $service_id ) );

        return $this;
    }

    /**
     * Set the properties of the app associated with this license.
     * 
     * @param string $app_type  The app type
     * @param string $app_slug  The app slug.
     * @return self
     */
    public function set_app_prop( string $app_type, string $app_slug ) : self{
        $this->app_prop['type'] = $app_type;
        $this->app_prop['slug'] = $app_slug;

        return $this;
    }

    /**
     * Set the app this license is issued to.
     * 
     * @param Hosted_Apps_Interface $app
     * @return self
     */
    public function set_app( Hosted_Apps_Interface $app ) : self {
        $this->app = $app;

        $this->set_app_prop( $app->get_type(), $app->get_slug() );

        return $this;
    }

    /**
     * Set license status.
     * 
     * @param string $status
     * @return self
     */
    public function set_status( $status ) : self {
        $this->status = $status;

        return $this;
    }

    /**
     * Set the license start date
     * 
     * @param string $start_date
     * @return self
     */
    public function set_start_date( $start_date ) : self {
        $this->start_date = $start_date;

        return $this;
    }

    /**
     * Set the license end date
     * 
     * @param string $end_date
     * @return self
     */
    public function set_end_date( $end_date ) : self {
        $this->end_date = $end_date;

        return $this;
    }

    /**
     * Set the maximum allowed domains that can request license activation.
     * 
     * @param int $number_of_domains
     * @return self
     */
    public function set_max_allowed_domains( $number_of_domains ) : self {
        $this->max_allowed_domains = max( -1, intval( $number_of_domains ) );

        return $this;
    }

    /**
     * Set the value of the given meta data name
     * 
     * @param string $meta_name The name of the meta data to set.
     * @param string $meta_value The value of the meta data.
     * @return self
     */
    public function set_meta( $meta_name, $meta_value ) : self {
        $this->meta_data[\sanitize_key( $meta_name )]    = \sanitize_text_field( \unslash( $meta_value ) );

        return $this;
    }

    /**
     * Set the entire meta data of this license
     * 
     * @param array $meta_data
     * @return self
     */
    public function set_meta_data( array $meta_data ) : self {

        foreach( $meta_data as $name => $value ) {
            if ( \is_numeric( $name ) ) {
                continue;
            }

            $this->set_meta( $name, $value );
        }

        return $this;
    }

    /**
    |-------------
    | GETTERS
    |-------------
    */

    /**
     * Get the license ID.
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the the ID of the user associated with the license
     * 
     * @return int
     */
    public function get_user_id( $user_id ) {
        $this->user_id = max( 0, intval( $user_id ) );
    }

    /**
     * Get the license key
     *
     * @return string
     */
    public function get_license_key() {
        return $this->license_key;
    }

    /**
     * Get the service ID for this license
     * 
     * @return string
     */
    public function get_service_id() {
        return $this->service_id;
    }

    /**
     * Get the properties of the app associated with this license.
     * 
     * @return string
     */
    public function get_app_prop(){
        return $this->app_prop;
    }

    /**
     * Get the app this license is issued to.
     * 
     * @return Hosted_Apps_Interface $app
     */
    public function get_app() {
        return $this->app;
    }

    /**
     * Get license status.
     * 
     * @return string $status
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get the license start date
     * 
     * @return string $start_date
     */
    public function get_start_date() {
        return $this->start_date;
    }

    /**
     * Get the license end date
     * 
     * @return string $end_date
     */
    public function get_end_date() {
        return $this->end_date;
    }

    /**
     * Get the maximum allowed domains that can request license activation.
     * 
     * @return int $number_of_domains
     */
    public function get_max_allowed_domains() {
        return $this->max_allowed_domains;
    }

    /**
     * Get the value of the given meta data key
     * 
     * @param string $meta_name The name of the meta key.
     * @param mixed $default    The default value to return when the meta name is not found.
     * @return mixed
     */
    public function get_meta( $meta_name, $default = null ) {
        return $this->meta_data[$meta_name] ?? $default;
    }

    /**
     * Get the meta data
     * 
     * @return array
     */
    public function get_meta_data() {
        return $this->meta_data;
    }

    /*
    |-----------------
    |   CRUD METHODS
    |-----------------
    */

    /**
     * Get license object using service ID and the license key
     * 
     * @param string $service_id The service ID associated with the licence
     * @param string $license_key   The license key
     * @return self|null
     */
    public static function get_license() : ?self {
        $db = \smliser_dbclass();


        return null;
    }
 
}