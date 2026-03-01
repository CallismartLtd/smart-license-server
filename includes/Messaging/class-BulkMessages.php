<?php
/**
 * Bulk messages class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Messaging;

use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The bulk messages class provides the API to broadcast messages for the entire site
 * or for the given hosted application(s).
 */
class BulkMessages {
    use SanitizeAwareTrait;
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
     * @var false $is_read
     */
    protected  $is_read = false;

    /**
     * Holds the app type and slug(s) of the hosted applications this message is associated with
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
        $this->message_id = $this->sanitize_text( $message_id );

        return $this;
    }

    /**
     * Set the message subject
     * 
     * @param string $subject
     * @return self
     */
    public function set_subject( $subject ) {
        $this->subject = $this->sanitize_text( $subject );

        return $this;
    }

    /**
     * Set message body
     * 
     * @param string $body
     * @return self
     */
    public function set_body( $body ) {
        $this->body = $this->sanitize_html( $body );

        return $this;
    }

    /**
     * Set created at
     * 
     * @param string $date
     * @return self
     */
    public function set_created_at( $date ) {
        $this->created_at = $this->sanitize_text( $date );
        return $this; 
    }

    /**
     * Set the last updated.
     * 
     * @param string $date
     * @return self
     */
    public function set_updated_at( $date ) {
        $this->updated_at = $this->sanitize_text( $date );
        return $this;
    }

    /**
     * Set the value of is_read
     * 
     * @param int|bool $value
     */
    protected function set_is_read( $value ) {
        $this->is_read = boolval( $value );
    }

    /**
     * Set associated apps
     * 
     * @param string|array $apps_data
     * @param boolean $reset Whether or not to reset the associated apps property.
     * @return self
     */
    public function set_associated_apps( $apps_data, $reset = false ) {
        if ( $reset ) {
            $this->associated_apps = [];
        }
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
        $slug     = $this->sanitize_text( $slug );

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
     * Get all associated app
     * 
     * @return array
     */
    public function get_associated_apps() {
        return $this->associated_apps;
    }

    /**
     * Get the is_read status of a message.
     * 
     * @return false Is always false, the clients and extending class should track the read status of messages.
     */
    public function get_is_read() {
        return $this->is_read;
    }


    /*
    |-----------------
    | CRUD METHODS
    |-----------------
    */
    /**
     * Save or update a message.
     * 
     * @return boolean
     */
    public function save() : bool {
        $db = \smliser_dbclass();

        $table = SMLISER_BULK_MESSAGES_TABLE;

        if ( empty( $this->get_message_id() ) ) {
            $this->set_message_id( uniqid( 'smliser-msg_', true ) );
        }

        $data = array(
            'message_id' => $this->message_id,
            'subject'    => $this->subject,
            'body'       => $this->body,
            'updated_at' => \gmdate( 'Y-m-d H:i:s' ),
        );

        if ( $this->id > 0 ) {
            $result  = $db->update( $table, $data, array( 'id' => $this->id ) );
        } else {
            $data['created_at'] = \gmdate( 'Y-m-d H:i:s' );
            $result  = $db->insert( $table, $data );
            
            $this->set_id( $db->get_insert_id() );
        }

        // Sync associated apps
        $this->save_associated_apps();

        return false !== $result;
    }

    /**
     * Save associated apps to the relation table.
     * 
     * @return void
     */
    protected function save_associated_apps() {
        $db = \smliser_dbclass();

        if ( ! $this->id ) {
            return;
        }

        $table = SMLISER_BULK_MESSAGES_APPS_TABLE;
        // Delete old associations
        $db->delete( $table, array( 'message_id' => $this->message_id ) );
        
        if ( empty( $this->associated_apps ) ) {
            return;
        }

        foreach ( $this->associated_apps as $type => $slugs ) {
            foreach ( (array) $slugs as $slug ) {
                $db->insert(
                    $table,
                    array(
                        'message_id' => $this->message_id,
                        'app_type'   => $type,
                        'app_slug'   => $slug,
                    ),
                );
            }
        }
    }

    /**
     * Get all bulk messages.
     *
     * @param array $args {
     *     @type int $page  Page number.
     *     @type int $limit Items per page.
     * }
     * @return array {
     *     @type self[] $items
     *     @type array  $pagination
     * }
     */
    public static function get_all( $args = array() ) {

        $db = \smliser_dbclass();

        $defaults = array(
            'page'  => 1,
            'limit' => 30,
        );

        $args = parse_args( $args, $defaults );

        $page  = max( 1, (int) $args['page'] );
        $limit = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );

        $table = SMLISER_BULK_MESSAGES_TABLE;

        // Get total count first
        $count_sql = "SELECT COUNT(*) FROM {$table}";
        $total     = (int) $db->get_var( $count_sql );

        $total_pages = $limit > 0
            ? (int) ceil( $total / $limit )
            : 0;

        // Get paginated results
        $sql    = "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = array( $limit, $offset );

        $results = $db->get_results( $sql, $params );

        $items = array();

        foreach ( $results as $row ) {
            $items[] = self::from_array( $row );
        }

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ),
        );
    }

    /**
     * Get a message by its message ID or ID
     * 
     * @param string|int $id_or_message_id
     * @return self|null
     */
    public static function get_message( $id_or_message_id ) {
        $db = \smliser_dbclass();
        $id_or_message_id = is_numeric( $id_or_message_id ) ? self::sanitize_int( $id_or_message_id ) : self::sanitize_text( $id_or_message_id );

        $table = SMLISER_BULK_MESSAGES_TABLE;

        $query  = "SELECT * FROM {$table} WHERE `id` = ? OR `message_id` = ? LIMIT 1";
        $result = $db->get_row( $query, [ $id_or_message_id, $id_or_message_id ] );

        if ( ! empty( $result ) ) {
            return self::from_array( $result );
        }

        return null;
    }

    /**
     * Get messages for a specific app.
     *
     * @param array $args {
     *     @type string $app_type App type.
     *     @type string $app_slug App slug.
     *     @type int    $page     Page number.
     *     @type int    $limit    Items per page.
     * }
     * @return array {
     *     @type self[] $items
     *     @type array  $pagination
     * }
     */
    public static function get_for_app( $args = array() ) {

        $db = \smliser_dbclass();

        $defaults = array(
            'app_type' => '',
            'app_slug' => '',
            'page'     => 1,
            'limit'    => 20,
        );

        $args = parse_args( $args, $defaults );

        $page  = max( 1, (int) $args['page'] );
        $limit = max( 1, (int) $args['limit'] );
        $offset = ( $page - 1 ) * $limit;

        $app_type = (string) $args['app_type'];
        $app_slug = (string) $args['app_slug'];

        $msg_table       = SMLISER_BULK_MESSAGES_TABLE;
        $msgs_apps_table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        // ---- Total count query ----
        $count_sql = "
            SELECT COUNT(*)
            FROM {$msg_table} m
            INNER JOIN {$msgs_apps_table} a
                ON m.message_id = a.message_id
            WHERE a.app_type = ?
            AND a.app_slug = ?
        ";

        $total = (int) $db->get_var( $count_sql, array( $app_type, $app_slug ) );

        $total_pages = $limit > 0
            ? (int) ceil( $total / $limit )
            : 0;

        // ---- Paginated results ----
        $sql = "
            SELECT m.*
            FROM {$msg_table} m
            INNER JOIN {$msgs_apps_table} a
                ON m.message_id = a.message_id
            WHERE a.app_type = ?
            AND a.app_slug = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $results = $db->get_results(
            $sql,
            array( $app_type, $app_slug, $limit, $offset )
        );

        $items = array();

        foreach ( $results as $row ) {
            $items[] = self::from_array( $row );
        }

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ),
        );
    }

    /**
     * Get messages for one or more app slugs, optionally filtered by app type(s).
     *
     * @param array $args {
     *     @type array|string $app_slugs One or more app slugs.
     *     @type array|string $app_types Optional. One or more app types.
     *     @type int          $page      Page number.
     *     @type int          $limit     Results per page.
     * }
     *
     * @return array {
     *     @type self[] $items
     *     @type array  $pagination
     * }
     */
    public static function get_for_slugs( $args = array() ) {

        $db = \smliser_dbclass();

        $defaults = array(
            'app_slugs' => array(),
            'app_types' => array(),
            'page'      => 1,
            'limit'     => 20,
        );

        $args = parse_args( $args, $defaults );

        $app_slugs = array_filter( (array) $args['app_slugs'] );
        $app_types = array_filter( (array) $args['app_types'] );

        $page  = max( 1, (int) $args['page'] );
        $limit = max( 1, (int) $args['limit'] );
        $offset = ( $page - 1 ) * $limit;

        if ( empty( $app_slugs ) ) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'page'        => $page,
                    'limit'       => $limit,
                    'total'       => 0,
                    'total_pages' => 0,
                ),
            );
        }

        $msg_table   = SMLISER_BULK_MESSAGES_TABLE;
        $assoc_table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        // ---- WHERE builder ----
        $where  = array();
        $params = array();

        // Slugs condition
        $slug_placeholders = implode(
            ',',
            array_fill( 0, count( $app_slugs ), '?' )
        );

        $where[] = "a.app_slug IN ( {$slug_placeholders} )";
        $params  = array_merge( $params, $app_slugs );

        // App types condition (optional)
        if ( ! empty( $app_types ) ) {
            $type_placeholders = implode(
                ',',
                array_fill( 0, count( $app_types ), '?' )
            );

            $where[] = "a.app_type IN ( {$type_placeholders} )";
            $params  = array_merge( $params, $app_types );
        }

        $where_sql = implode( ' AND ', $where );

        // ---- COUNT query ----
        $count_sql = "
            SELECT COUNT( DISTINCT m.message_id )
            FROM {$msg_table} m
            INNER JOIN {$assoc_table} a
                ON m.message_id = a.message_id
            WHERE {$where_sql}
        ";

        $total = (int) $db->get_var( $count_sql, $params );

        $total_pages = (int) ceil( $total / $limit );

        // ---- Paginated query ----
        $sql = "
            SELECT DISTINCT m.*
            FROM {$msg_table} m
            INNER JOIN {$assoc_table} a
                ON m.message_id = a.message_id
            WHERE {$where_sql}
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $query_params   = $params;
        $query_params[] = $limit;
        $query_params[] = $offset;

        $results = $db->get_results( $sql, $query_params );

        $items = array();

        foreach ( $results as $row ) {
            $items[] = self::from_array( $row );
        }

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ),
        );
    }

    /**
     * Search bulk messages.
     *
     * @param array $args {
     *     @type string $search Optional search keyword (subject or message_id).
     *     @type int    $page   Page number.
     *     @type int    $limit  Results per page.
     * }
     *
     * @return array {
     *     @type self[] $items
     *     @type array  $pagination
     * }
     */
    public static function search( $args = array() ) {

        $db = \smliser_dbclass();

        $defaults = array(
            'search' => '',
            'page'   => 1,
            'limit'  => 20,
        );

        $args = parse_args( $args, $defaults );

        $search = trim( (string) $args['search'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = ( $page - 1 ) * $limit;

        $table = SMLISER_BULK_MESSAGES_TABLE;

        $where_sql  = "WHERE `subject` LIKE ? OR `message_id` LIKE ?";
        $like       = $search . '%';
        $params     = array( $like, $like );

        // ---- Total count ----
        $total = (int) $db->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params );
        $total_pages = (int) ceil( $total / $limit );

        // ---- Paginated query ----
        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $results = $db->get_results( $sql, $params );
        $items   = array_map( [self::class, 'from_array'], $results );

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ),
        );
    }
    
    /**
     * Delete a message and its associations.
     */
    public function delete() {
        $db = \smliser_dbclass();

        if ( ! $this->id && ! $this->message_id ) {
            return false;
        }

        $msg_table          = SMLISER_BULK_MESSAGES_TABLE;
        $msgs_apps_table    = SMLISER_BULK_MESSAGES_APPS_TABLE;

        $db->delete( $msg_table, array( 'id' => $this->id ) );
        $db->delete( $msgs_apps_table, array( 'message_id' => $this->message_id ) );

        return true;
    }

    /**
    |-----------------
    | UTILITY METHODS
    |-----------------
    */

    /**
     * Converts associative array to an object of this class.
     * 
     * @param array $data 
     * @return self
     */
    public static function from_array( $data ) {
        $self = new self();

        foreach ( (array) $data as $k => $v ) {
            $method = "set_{$k}";
            if ( method_exists( $self, $method ) ) {
                $self->$method( $v );
            }
        }

        if ( ! empty( $self->get_message_id() ) ) {
            $assos_apps    = self::load_associated_apps( $self->get_message_id() );
            $self->set_associated_apps( $assos_apps );
        }
        return $self;
    }

    /**
     * Convert this message object to an associative array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'                => $this->get_id(),
            'message_id'        => $this->get_message_id(),
            'subject'           => $this->get_subject(),
            'body'              => $this->get_body(),
            'created_at'        => $this->get_created_at(),
            'updated_at'        => $this->get_updated_at(),
            'read'              => (bool) $this->get_is_read(),
        );
    }

    /**
     * Load apps that are associated with this message from the DB.
     * 
     * @param string $message_id The public message ID.
     * @return array
     */
    private static function load_associated_apps( $message_id ) {
        $db = \smliser_dbclass();

        $table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        $results = $db->get_results( "SELECT `app_type`, `app_slug` FROM `{$table}` WHERE `message_id` = ?", [$message_id] );

        $apps = array();

        foreach ( $results as $row ) {
            $type = self::sanitize_key( $row['app_type'] );
            $slug = self::sanitize_text( $row['app_slug'] );

            if ( ! isset( $apps[$type] ) ) {
                $apps[$type] = array();
            }

            $apps[$type][] = $slug;
        }

        return $apps;
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
                    ? sprintf( '<strong>%s</strong> (%d): %s', esc_html( ucfirst( $app_type ) ), $count, esc_html( $list ) )
                    : sprintf( '%s (%d): %s', ucfirst( $app_type ), $count, $list );
            } else {
                $formatted = $as_html
                    ? sprintf( '<strong>%s</strong> (%d)', esc_html( ucfirst( $app_type ) ), $count )
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