<?php
/**
 * Bulk messages class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer;

defined( 'ABSPATH' ) || exit;

/**
 * The bulk messages class provides the API to broadcast messages for the entire site
 * or for the given hosted application(s).
 */
class Bulk_Messages {
    /**
     * The dataabase ID of this message
     * 
     * @var int $id
     */
    protected $id   = 0;

    /**
     * Public message ID
     * 
     * @var string $message_id
     */
    protected $message_id   = '';

    /**
     * The message subject
     * 
     * @var string $subject
     */
    protected $subject  = '';

    /**
     * The message body
     * 
     * @var string $body
     */
    protected $body = '';

    /**
     * When the message was created
     * 
     * @var string $created_at
     */
    protected $created_at = '';

    /**
     * Last time the message was updated
     * 
     * @var string $updated_at
     */
    protected $updated_at = '';

    /**
     * Falsey property indicating the message has not been read by default.
     * 
     * @var false $read
     */
    protected  $read = false;

    /**
     * Holds the app type and slug of the hosted applications this message is associated with
     * 
     * @var array $associated_apps
     */
    protected $associated_apps  = array();


    /*
    |---------------
    | SETTERS
    |---------------
    */
    /**
     * Set the ID property
     * 
     * @param int $id
     * @return self
     */
    public function set_id( $id ) {
        $this->id = absint( $id );

        return $this;
    }

    /**
     * Set the the public message id
     * 
     * @param $message_id
     * @return self
     */
    public function set_message_id( $message_id ) {
        $this->message_id = sanitize_text_field( unslash( $message_id ) );

        return $this;
    }

    /**
     * Set the message subject
     * 
     * @param string $subject
     * @return self
     */
    public function set_subject( $subject ) {
        $this->subject = sanitize_text_field( unslash( $subject ) );

        return $this;
    }

    /**
     * Set message body
     * 
     * @param string $body
     * @return self
     */
    public function set_body( $body ) {
        $this->body = wp_kses_post( unslash( $body ) );

        return $this;
    }

    /**
     * Set created at
     * 
     * @param string $date
     * @return self
     */
    public function set_created_at( $date ) {
        $this->created_at = sanitize_text_field( unslash( $date ) );
        return $this; 
    }

    /**
     * Set the last updated.
     * 
     * @param string $date
     * @return self
     */
    public function set_updated_at( $date ) {
        $this->updated_at = sanitize_text_field( unslash( $date ) );
        return $this;
    }

    /**
     * Set associated apps
     * 
     * @param string|array $apps_data
     * @return self
     */
    public function set_associated_apps( $apps_data ) {
        if ( is_json( $apps_data ) ) {
            $apps_data = json_decode( $apps_data, true );
        }

        foreach ( $apps_data as $app_type => $slugs ) {
            if ( is_array( $slugs ) ) {
                foreach ( $slugs as $slug ) {
                    $this->set_associated_app( $app_type, $slug );
                }
            } else {
                $this->set_associated_app( $app_type, $slugs );
            }
        }

        return $this;
    }

    /**
     * Set associated app
     * 
     * @param string $app_type The app type
     * @param string $slug The App slug
     * @return self
     */
    public function set_associated_app( $app_type, $slug ) {
        $app_type = sanitize_key( $app_type );
        $slug     = sanitize_text_field( unslash( $slug ) );

        if ( ! isset( $this->associated_apps[$app_type] ) ) {
            $this->associated_apps[$app_type] = array();
        }

        $this->associated_apps[$app_type][] = $slug;
        $this->associated_apps[$app_type]   = array_values( array_unique( $this->associated_apps[$app_type] ) );

        return $this;
    }

    /*
    |------------
    | GETTERS
    |------------
    */

    /**
     * Get ID
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the public message ID.
     * 
     * @return string
     */
    public function get_message_id() {
        return $this->message_id;
    }

    /**
     * Get the message subject
     * 
     * @return string
     */
    public function get_subject() {
        return $this->subject;
    }

    /**
     * Get the message body
     * 
     * @return string
     */
    public function get_body() {
        return $this->body;
    }

    /**
     * Get the date this message was created
     * 
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get the last updated date
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * Get the read status
     * 
     * 
     * @return false
     */
    public function get_read() {
        return $this->read;
    }

    /**
     * Get all associated app
     * 
     * @return array
     */
    public function get_associated_apps() {
        return $this->associated_apps;
    }


}