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
     */
    public function set_body( $body ) {
        $this->body = wp_kses_post( unslash( $body ) );
    }

}