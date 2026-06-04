<?php
/**
 * Bulk messages class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Messaging;

use DateTimeImmutable;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * The bulk message model class.
 */
class BulkMessage {
    use SanitizeAwareTrait, DatePropertyAwareTrait;
    /**
     * The dataabase ID of this message
     * 
     * @var int $id
     */
    protected int $id   = 0;

    /**
     * Public message ID
     * 
     * @var string $message_id
     */
    protected string $message_id   = '';

    /**
     * The message subject
     * 
     * @var string $subject
     */
    protected string $subject  = '';

    /**
     * The message body
     * 
     * @var string $body
     */
    protected string $body = '';

    /**
     * When the message was created
     * 
     * @var DateTimeImmutable|null $created_at
     */
    protected ?DateTimeImmutable  $created_at = null;

    /**
     * Last time the message was updated
     * 
     * @var DateTimeImmutable|null $updated_at
     */
    protected ?DateTimeImmutable  $updated_at = null;

    /**
     * Falsey property indicating the message has not been read by default.
     * 
     * @var bool $is_read
     */
    protected bool $is_read = false;

    /**
     * Holds the app type and slug(s) of the hosted applications this message is associated with
     * 
     * @var array $associated_apps
     */
    protected array $associated_apps  = [];


    /*
    |---------------
    | SETTERS
    |---------------
    */
    /**
     * Set the ID property
     * 
     * @param int|string $id
     * @return static
     */
    public function set_id( int|string $id ) : static {
        $this->id = static::sanitize_int( $id );

        return $this;
    }

    /**
     * Set the the public message id
     * 
     * @param string $message_id
     * @return static
     */
    public function set_message_id( string $message_id ) : static {
        $this->message_id = $this->sanitize_text( $message_id );

        return $this;
    }

    /**
     * Set the message subject
     * 
     * @param string $subject
     * @return static
     */
    public function set_subject(string $subject ) : static {
        $this->subject = $this->sanitize_text( $subject );

        return $this;
    }

    /**
     * Set message body
     * 
     * @param string $body
     * @return static
     */
    public function set_body( string $body ) : static {
        $this->body = $this->sanitize_html( $body );

        return $this;
    }

    /**
     * Set created at
     * 
     * @param DateTimeImmutable|string|null $date
     * @return static
     */
    public function set_created_at( DateTimeImmutable|string|null $date ) : static {
        return $this->set_date_prop( $date, 'created_at' );
    }

    /**
     * Set the last updated.
     * 
     * @param DateTimeImmutable|string|null $date
     * @return static
     */
    public function set_updated_at( DateTimeImmutable|string|null $date ) : static {
        return $this->set_date_prop( $date, 'updated_at' );
    }

    /**
     * Set the value of is_read
     * 
     * @param int|bool $value
     */
    public function set_is_read( $value ) : static {
        $this->is_read = boolval( $value );

        return $this;
    }

    /**
     * Set associated apps
     * 
     * @param string|array $apps_data
     * @param boolean $reset Whether or not to reset the associated apps property.
     * @return static
     */
    public function set_associated_apps( string|array $apps_data, bool $reset = false ) : static {
        if ( $reset ) {
            $this->associated_apps = [];
        }

        if ( is_json( $apps_data ) ) {
            $apps_data = (array) json_decode( $apps_data, true );
        }

        if ( ! is_array( $apps_data ) ) {
            $apps_data  = (array) $apps_data;
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
     * @return static
     */
    public function set_associated_app( string $app_type, string $slug ) : static {
        $app_type = sanitize_key( $app_type );
        $slug     = $this->sanitize_text( $slug );

        if ( ! isset( $this->associated_apps[$app_type] ) ) {
            $this->associated_apps[$app_type] = [];
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
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get the public message ID.
     * 
     * @return string
     */
    public function get_message_id() : string {
        return $this->message_id;
    }

    /**
     * Get the message subject
     * 
     * @return string
     */
    public function get_subject() : string {
        return $this->subject;
    }

    /**
     * Get the message body
     * 
     * @return string
     */
    public function get_body() : string {
        return $this->body;
    }

    /**
     * Get the date this message was created
     * 
     * @return DateTimeImmutable|null
     */
    public function get_created_at() : ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Get the last updated date
     * 
     * @return DateTimeImmutable|null
     */
    public function get_updated_at() : ?DateTimeImmutable {
        return $this->updated_at;
    }

    /**
     * Get all associated app
     * 
     * @return array<string, string[]>
     */
    public function get_associated_apps() : array {
        return $this->associated_apps;
    }

    /**
     * Get the is_read status of a message.
     * 
     * @return false Is always false, the clients and extending class should track the read status of messages.
     */
    public function get_is_read() : bool {
        return $this->is_read;
    }

    /**
    |-----------------
    | UTILITY METHODS
    |-----------------
    */

    /**
     * Convert this message object to an associative array.
     *
     * @return array{
     *  id: int,
     *  message_id: string,
     *  subject: string,
     *  body: string,
     *  created_at: string,
     *  updated_at: string,
     *  read: bool
     * }
     */
    public function to_array() {
        return array(
            'id'                => $this->get_id(),
            'message_id'        => $this->get_message_id(),
            'subject'           => $this->get_subject(),
            'body'              => $this->get_body(),
            'created_at'        => $this->get_created_at()->format( DateTimeImmutable::ATOM ),
            'updated_at'        => $this->get_updated_at()->format( DateTimeImmutable::ATOM ),
            'read'              => (bool) $this->get_is_read(),
        );
    }

    /**
     * Print a readable summary of associated apps.
     *
     * @param bool $show_slugs Optional. Whether to include individual slugs. Default false.
     * @param bool $as_html Optional. Whether to format the output in HTML. Default false.
     * @return string The formatted summary.
     */
    public function print_associated_apps_summary( $show_slugs = false, $as_html = false ) {
        if ( empty( $this->associated_apps ) ) {
            return $as_html ? '<em>No associated apps</em>' : 'No associated apps';
        }

        $output = array();

        foreach ( $this->associated_apps as $app_type => $slugs ) {
            $count = count( $slugs );

            if ( $show_slugs ) {
                $list = implode( $as_html ? ', ' : ', ', $slugs );
                $formatted = $as_html
                    ? sprintf( '<strong>%s</strong> (%d): %s', escHtml( ucfirst( $app_type ) ), $count, escHtml( $list ) )
                    : sprintf( '%s (%d): %s', ucfirst( $app_type ), $count, $list );
            } else {
                $formatted = $as_html
                    ? sprintf( '<strong>%s</strong> (%d)', escHtml( ucfirst( $app_type ) ), $count )
                    : sprintf( '%s (%d)', ucfirst( $app_type ), $count );
            }

            $output[] = $formatted;
        }

        if ( $as_html ) {
            return '<ul><li>' . implode( '</li><li>', $output ) . '</li></ul>';
        }

        return implode( PHP_EOL, $output );
    }

}