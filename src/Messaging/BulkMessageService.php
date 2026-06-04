<?php
/**
 * Bulk Message Service Architecture class file.
 * * @package SmartLicenseServer\Messaging
 * @author Callistus Nwachukwu
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Messaging;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\QueryIntents\SelectionIntent;
use Callismart\DBPrism\Query\SQLBuilder;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Messaging\BulkMessage;
use InvalidArgumentException;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Service orchestrator handling data persistence, pagination boundaries,
 * and relational associations for bulk system messages.
 */
class BulkMessageService {
    use SanitizeAwareTrait;

    /**
     * Constructor.
     * 
     * @param string $db_table The database table name.
     * @param BulkMessage $message The message model state entity.
     * @param string $apps_table The database table name for message-app associations.
     */
    public function __construct( 
        private string $db_table = SMLISER_BULK_MESSAGES_TABLE,
        private string $apps_table = SMLISER_BULK_MESSAGES_APPS_TABLE,
        private ?BulkMessage $message = null,
    ) {}

    /*
    |-------------------------------------
    | MUTABLE PERSISTENCE WRITERS (CRUD)
    |-------------------------------------
    */

    /**
     * Save or update a bulk message payload instance.
     * 
     * @return bool True on successful persistence execution, false otherwise.
     * @throws Exception Sensitive database error, caller must handle accordingly.
     */
    public function save() : bool {

        if ( empty( $this->message->get_message_id() ) ) {
            $this->message->set_message_id( uniqid( 'smliser-msg_', true ) );
        }

        
        $result = (bool) smliser_db()->transactional( function( Database $db ) {
            $now = new \DateTimeImmutable();

            $data = [
                'message_id' => $this->message->get_message_id(),
                'subject'    => $this->message->get_subject(),
                'body'       => $this->message->get_body(),
                'updated_at' => $now->format( 'Y-m-d H:i:s' ),
            ];

            if ( $this->message->get_id() > 0 ) {
                $result = $db->update( $this->db_table, $data, [ 'id' => $this->message->get_id() ] );
            } else {
                $data['created_at'] = $now->format( 'Y-m-d H:i:s' );
                $result = $db->insert( $this->db_table, $data );
                
                if ( $result ) {
                    $this->message->set_id( (int) $db->get_insert_id() );
                }
            }

            if ( false === $result ) {
                throw new Exception( $db->get_last_error() );
            }

            $this->message->set_updated_at( $now );
            $this->save_associated_apps( $db );
            return true;
        });

        return $result;
    }

    /**
     * Synchronize associated external application tracking keys inside relationship tables.
     * 
     * @param Database $db
     * @return void
     */
    protected function save_associated_apps( Database $db ) : void {

        if ( ! $this->message->get_id() ) {
            return;
        }

        // Strip previous associations cleanly using baseline keys.
        $db->delete( $this->apps_table, [ 'message_id' => $this->message->get_message_id() ] );
        
        if ( empty( $this->message->get_associated_apps() ) ) {
            return;
        }

        // Bulk array matrix compilation
        $bulk_rows = [];

        foreach ( $this->message->get_associated_apps() as $type => $slugs ) {
            foreach ( $slugs as $slug ) {
                $bulk_rows[] = [
                    'message_id' => $this->message->get_message_id(),
                    'app_type'   => $type,
                    'app_slug'   => $slug,
                ];
            }
        }

        $multi_insert_sql   = $this->query()
            ->insert( $this->apps_table )
            ->multi_values( $bulk_rows );

        if ( ! empty( $bulk_rows ) ) {
            $db->execute( $multi_insert_sql->build(), $multi_insert_sql->get_bindings() );
        }
    }

    /**
     * Purge a target message record and all related application associations.
     * 
     * @return bool
     * @throws Exception Sensitive database error, caller must handle accordingly.
     */
    public function delete() : bool {
        if ( ! $this->message->get_id() ) {
            return false;
        }

        $result = (bool) \smliser_db()->transactional( function( Database $db ) {
            $msg_table       = SMLISER_BULK_MESSAGES_TABLE;
            $msgs_apps_table = SMLISER_BULK_MESSAGES_APPS_TABLE;

            $deleted    = $db->delete( $msg_table, [ 'id' => $this->message->get_id() ] );

            if ( ! $deleted ) {
                throw new Exception( 'delete_failed', $db->get_last_error() );
            }

            $db->delete( $msgs_apps_table, [ 'message_id' => $this->message->get_message_id() ] );

            return true;            
        });

        return $result;
    }

    /*
    |-------------------------------------------------
    | READ SELECTION READERS (DQL ACCESSORS)
    |-------------------------------------------------
    */

    /**
     * Retrieve a singular message mapping instance by database ID or unique UUID string.
     * 
     * @param string|int $id_or_message_id
     * @return BulkMessage|null
     */
    public function get_message( mixed $id_or_message_id ) : ?BulkMessage {
        // Utilize query builder layout logic with grouped fallback criteria
        $query = $this->query()
            ->select( '*' )
            ->from( $this->db_table )
            ->where_group( function ( $q ) use ( $id_or_message_id ) {
                $q->where( 'id', '=', $id_or_message_id )
                  ->or_where( 'message_id', '=', $id_or_message_id );
            })
            ->limit( 1 );

        $result = smliser_db()->get_row( $query->build(), $query->get_bindings() );

        if ( ! empty( $result ) ) {
            return static::from_array( (array) $result );
        }

        return null;
    }

    /**
     * Fetch all bulk records with unified framework index mapping pagination.
     *
     * @param array{
     *  page?: int,
     *  limit?: int
     * } $args
     * 
     * @return array{
     *  items: BulkMessage[],
     *  pagination: array{
     *      page: int,
     *      limit: int,
     *      total: int,
     *      total_pages: int
     *  }
     * }
     */
    public function get_all( array $args = [] ) : array {
        $db = \smliser_db();
        $table = SMLISER_BULK_MESSAGES_TABLE;

        $page   = (int) max( 1, (int) ( $args['page'] ?? 1 ) );
        $limit  = (int) max( 1, (int) ( $args['limit'] ?? 30 ) );
        $offset = $db->calculate_query_offset( $page, $limit );

        // Count via SelectionIntent abstraction
        $count_query = $this->query()->select( 'COUNT(*) as total' )->from( $table );
        $total       = (int) $db->get_var( $count_query->build(), $count_query->get_bindings() );

        // Fetch using explicit schema builders
        $fetch_query = $this->query()
            ->select( '*' )
            ->from( $table )
            ->order_by( 'created_at', 'DESC' )
            ->limit( $limit )
            ->offset( $offset );

        $results = $db->get_results( $fetch_query->build(), $fetch_query->get_bindings() );
        $items   = array_map( [$this, 'from_array' ], $results );
        $total_pages = (int) ceil( $total / $limit );

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];
    }

    /**
     * Get paginated messages tailored specifically to a singular application target component.
     *
     * @param array{
     *  app_type?: string,
     *  app_slug?: string,
     *  page?: int,
     *  limit?: int
     * } $args
     * 
     * @return array{
     *  items: BulkMessage[],
     *  pagination: array{
     *      page: int,
     *      limit: int,
     *      total: int,
     *      total_pages: int
     *  }
     * }
     * 
     */
    public function get_for_app( array $args = [] ) : array {
        $db = \smliser_db();

        $page     = (int) max( 1, (int) ( $args['page'] ?? 1 ) );
        $limit    = (int) max( 1, (int) ( $args['limit'] ?? 20 ) );
        $offset   = $db->calculate_query_offset( $page, $limit );
        $app_type = (string) ( $args['app_type'] ?? '' );
        $app_slug = (string) ( $args['app_slug'] ?? '' );

        $msg_table       = SMLISER_BULK_MESSAGES_TABLE;
        $msgs_apps_table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        // ---- Total Count Pipeline via Abstract Joins ----
        $count_query = $this->query()
            ->select( 'COUNT(*) as total' )
            ->from( "$msg_table m" )
            ->join( "$msgs_apps_table a", 'm.message_id', '=', 'a.message_id' )
            ->where( 'a.app_type',  '=', $app_type )
            ->where( 'a.app_slug', '=', $app_slug );

        $total          = (int) $db->get_var( $count_query->build(), $count_query->get_bindings() );
        $total_pages    = (int) ceil( $total / $limit );

        $fetch_query = $this->query()
            ->select( 'm.*' )
            ->from( "{$msg_table} m" )
            ->join( "{$msgs_apps_table} a", 'm.message_id', '=', 'a.message_id' )
            ->where( 'a.app_type', '=', $app_type )
            ->where( 'a.app_slug', '=', $app_slug )
            ->order_by( 'm.created_at', 'DESC' )
            ->limit( $limit )
            ->offset( $offset );

        $results = $db->get_results( $fetch_query->build(), $fetch_query->get_bindings() );
        $items   = array_map( [$this, 'from_array' ], $results );

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];
    }

    /**
     * Get unique relational selections matching multidimensional arrays of types and slugs.
     *
     * @param array $args {
     * @type array|string $app_slugs Array stream collections filtering slugs.
     * @type array|string $app_types Optional constraint classifications filtering types.
     * @type int          $page      Page index offset.
     * @type int          $limit     Total processing limits block.
     * }
     * @return array{items: BulkMessage[], pagination: array{page: int, limit: int, total: int, total_pages: int}}
     */
    public function get_for_slugs( array $args = [] ) : array {
        $db = \smliser_db();

        $app_slugs = array_filter( (array) ( $args['app_slugs'] ?? [] ) );
        $app_types = array_filter( (array) ( $args['app_types'] ?? [] ) );
        
        $page   = (int) max( 1, (int) ( $args['page'] ?? 1 ) );
        $limit  = (int) max( 1, (int) ( $args['limit'] ?? 20 ) );
        $offset = ( $page - 1 ) * $limit;

        if ( empty( $app_slugs ) ) {
            return [
                'items'      => [],
                'pagination' => [ 'page' => $page, 'limit' => $limit, 'total' => 0, 'total_pages' => 0 ],
            ];
        }

        $msg_table   = SMLISER_BULK_MESSAGES_TABLE;
        $assoc_table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        // Shared baseline builder criteria block to decouple query synthesis
        $apply_constraints = function( $query ) use ( $app_slugs, $app_types ) {
            $query->where_in( 'a.app_slug', $app_slugs );
            if ( ! empty( $app_types ) ) {
                $query->where_in( 'a.app_type', $app_types );
            }
        };

        // ---- Native Count Execution via Framework Core Tokens ----
        $count_query = $this->query()
            ->select( 'COUNT(m.message_id) as total' )->distinct()
            ->from( "{$msg_table} m" )
            ->join( "{$assoc_table} a", 'm.message_id', '=', 'a.message_id' );
        
        $apply_constraints( $count_query );

        $total = (int) $db->get_var( $count_query->build(), $count_query->get_bindings() );
        $total_pages = (int) ceil( $total / $limit );

        // ---- Distinct Entity Extraction ----
        $fetch_query = $this->query()
            ->select( 'm.*' )->distinct()
            ->from( "{$msg_table} m" )
            ->join( "{$assoc_table} a", 'm.message_id', '=', 'a.message_id' );
        
        $apply_constraints( $fetch_query );
        $fetch_query->order_by( 'm.created_at', 'DESC' )
            ->limit( $limit )->offset( $offset );

        $results = $db->get_results( $fetch_query->build(), $fetch_query->get_bindings() );
        $items   = array_map( [$this, 'from_array' ], $results );

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];
    }

    /**
     * Search message tracking datasets dynamically utilizing clean text criteria mapping.
     *
     * @param array{
     * search?: string,
     * page?: int,
     * limit?: int
     * } $args
     * 
     * @return array{
     *  items: BulkMessage[],
     *  pagination: array{
     *      page: int,
     *      limit: int,
     *      total: int,
     *      total_pages: int
     *  }
     * }
     */
    public function search( array $args = [] ) : array {
        $db = \smliser_db();

        $search = trim( (string) ( $args['search'] ?? '' ) );
        $page   = max( 1, (int) ( $args['page'] ?? 1 ) );
        $limit  = max( 1, (int) ( $args['limit'] ?? 20 ) );
        $offset = ( $page - 1 ) * $limit;
        $table  = SMLISER_BULK_MESSAGES_TABLE;

        $apply_search = function( $query ) use ( $search ) {
            if ( '' !== $search ) {
                $query->where_group( function( SelectionIntent $q ) use ( $search ) {
                    // Utilizes updated core where_like interface boundaries safely
                    $q->where_starts_with( 'subject', $search )
                      ->or_where_starts_with( 'message_id', $search );
                });
            }
        };

        // ---- Count Execution Context ----
        $count_query = $this->query()->select( 'COUNT(*) as total' )->from( $table );
        $apply_search( $count_query );
        $total = (int) $db->get_var( $count_query->build(), $count_query->get_bindings() );
        $total_pages = (int) ceil( $total / $limit );

        // ---- Data Extraction Context ----
        $fetch_query = $this->query()
            ->select( '*' )
            ->from( $table );
        $apply_search( $fetch_query );
        $fetch_query->order_by( 'created_at', 'DESC' )
                    ->limit( $limit )
                    ->offset( $offset );

        $results = $db->get_results( $fetch_query->build(), $fetch_query->get_bindings() );
        $items   = array_map( [ $this, 'from_array' ], (array) $results );

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];
    }

    /**
     * Load apps that are associated with this message from the DB.
     * 
     * @param string $message_id The public message ID.
     * @return array
     */
    private function load_associated_apps( $message_id ) {
        $db = \smliser_db();

        $table = SMLISER_BULK_MESSAGES_APPS_TABLE;

        $sql    = $this->query()
            ->select( 'app_type', 'app_slug' )->from( $table )
            ->where( 'message_id', '=', $message_id );

        $results = $db->get_results( $sql->build(), $sql->get_bindings() );

        $apps = array();

        foreach ( $results as $row ) {
            $type = static::sanitize_key( $row['app_type'] );
            $slug = static::sanitize_text( $row['app_slug'] );

            if ( ! isset( $apps[$type] ) ) {
                $apps[$type] = array();
            }

            $apps[$type][] = $slug;
        }

        return $apps;
    }

    /**
     * Hydrate a bulk message instance from array.
     * 
     * @param array $data 
     * @return BulkMessage
     */
    public function from_array( $data ) : BulkMessage {
        $msg    = new BulkMessage();
        $cols   = SchemaRegistry::instance()->get_table_column_names( $this->db_table );

        foreach ( $cols as $col ) {
            $method = "set_{$col}";

            if ( method_exists( $msg, $method ) ) {
                $msg->$method( $data[$col] ?? '' );
            }
        }

        if ( ! empty( $msg->get_message_id() ) ) {
            $assos_apps    = $this->load_associated_apps( $msg->get_message_id() );
            $msg->set_associated_apps( $assos_apps );
        }
        
        return $msg;
    }

    /**
     * Get the instance of the query builder.
     */
    protected static function query() : SQLBuilder {
        return \smliserQueryBuilder();
    }

    /**
     * Get instance without the message object.
     * 
     * @return static
     */
    public static function raw() : static {
        return new static();
    }

    /**
     * Get instance with a message object.
     * 
     * @param BulkMessage $message
     * @return static
     */
    public static function with_message( BulkMessage $message ) : static {
        $instance = new static();
        $instance->message = $message;
        return $instance;
    }
}