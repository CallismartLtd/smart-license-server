<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

namespace SmartLicenseServer\HostedApps;

use Callismart\DBPrism\Query\QueryIntents\SelectionIntent;
use Callismart\DBPrism\Query\SQLBuilder;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * The core API class for perform CRUD operations on hosted applications.
 * 
 * This class provides all methods required to manage hosted applications and their data, 
 * it also supports caching, pagination, searching, asset management, and bulk operations via the request API.
 */
class HostedApplicationService {
    use CacheAwareTrait, SanitizeAwareTrait;

    private const APP_COLUMNS = [
        'id', 'owner_id', 'name', 'slug', 'status',
        'download_link', 'created_at', 'updated_at',
    ];

    /**
     * Get hosted applications across multiple types with pagination.
     *
     * @param array{
     *   page?: int,
     *   limit?: int,
     *   status?: string,
     *   types?: array
     * } $args
     * @return array{
     *   items: AbstractHostedApp[],
     *   pagination: array{
     *     total: int,
     *     page: int,
     *     limit: int,
     *     total_pages: int
     *   }
     * }
     */
    public static function get_apps( array $args = [] ) : array {
        $db = smliser_db();
    
        $defaults = [
            'page'   => 1,
            'limit'  => 20,
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => HostedAppsRegistry::instance()->app_types(),
        ];
    
        $args   = parse_args( $args, $defaults );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );
        $status = $args['status'];
        $types  = array_filter( (array) $args['types'] );
    
        $key     = self::make_cache_key( __METHOD__, compact( 'page', 'limit', 'status', 'types' ) );
        $results = self::cache_get( $key );
    
        if ( false === $results ) {
            $sql_parts = static::build_type_queries( $types, $status );
    
            if ( empty( $sql_parts ) ) {
                return static::make_paginated_result( [], $page, $limit );
            }
    
            [ $rows, $total ] = static::resolve_union_results( sql_parts: $sql_parts, limit: $limit, offset: $offset, use_window_fn: true );
            $rows    = static::hydrate_rows( $rows );
            $results = static::make_paginated_result( $rows, $page, $limit, $total );
    
            self::cache_set( $key, $results, static::default_ttl() );
        }
    
        return $results;
    }

    /**
     * Get plugins from the repository.
     *
     * @param array{
     *   page?: int,
     *   limit?: int,
     *   status?: string
     * } $args
     * 
     * @return array{
     *  items: \SmartLicenseServer\HostedApps\Plugin[],
     *  pagination: array{
     *      total: int,
     *      page: int,
     *      limit: int,
     *      total_pages: int
     *  }
     * }
     */
    public static function get_plugins( array $args = array() ) {
        $args['types']  = array( 'plugin' );
        return self::get_apps( $args );
    }

    /**
     * Get themes from the repository.
     *
     * @param array{
     *  page?: int,
     *  limit?: int,
     *  status?: string
     * } $args
     * @return array{
     *  items: \SmartLicenseServer\HostedApps\Theme[],
     *  pagination: array{
     *      total: int,
     *      page: int,
     *      limit: int,
     *      total_pages: int
     *  }
     * }
     */
    public static function get_themes( array $args = array() ) {
        $args['types']  = array( 'theme' );
        return self::get_apps( $args );
    }

    /**
     * Get trashed applications across multiple types with pagination.
     *
     * @param array{
     *  page?: int,
     *  limit?: int,
     *  status?: string
     * } $args
     * 
     * @return array{
     *  items: AbstractHostedApp[],
     *  pagination: array{
     *      total: int,
     *      page: int,
     *      limit: int,
     *      total_pages: int
     *  }
     * }
     */
    public static function get_trashed_apps( array $args = array() ) {
        $args['status'] = AbstractHostedApp::STATUS_TRASH;
        return self::get_apps( $args );
    }

    /**
     * Get software applications from the repository.
     *
     * @param array{
     *  page?: int,
     *  limit?: int,
     *  status?: string
     * } $args
     * @return array{
     *  items: \SmartLicenseServer\HostedApps\Software[],
     *  pagination: array{
     *      total: int,
     *      page: int,
     *      limit: int,
     *      total_pages: int
     *  }
     * }
     */
    public static function get_software( array $args = array() ) {
        $args['types']  = array( 'software' );
        return self::get_apps( $args );
    }

    /**
     * Search hosted applications across multiple types with pagination.
     *
     * @param array{
     *  term?: string,
     *  page?: int,
     *  limit?: int,
     *  status?: string,
     *  types?: string[]
     * } $args
     *
     * @return array{
     *  items: AbstractHostedApp[],
     *  pagination: array{
     *      total: int,
     *      page: int,
     *      limit: int,
     *      total_pages: int
     *  }
     * }
     */
    public static function search_apps( array $args = [] ) : array {
        $db = smliser_db();
    
        $defaults = [
            'term'   => '',
            'page'   => 1,
            'limit'  => 20,
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => HostedAppsRegistry::instance()->app_types(),
        ];
    
        $args   = parse_args( $args, $defaults );
        $term   = static::sanitize_text( $args['term'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );
        $status = $args['status'];
        $types  = array_filter( (array) $args['types'] );
    
        $key     = self::make_cache_key( __METHOD__, compact( 'term', 'page', 'limit', 'status', 'types' ) );
        $results = self::cache_get( $key );
    
        if ( false === $results ) {
            if ( '' === $term ) {
                return static::make_paginated_result( [], page: $page, limit: $limit );
            }
    
            $sql_parts = static::build_type_queries( $types, $status, $term );
    
            if ( empty( $sql_parts ) ) {
                return static::make_paginated_result( data: [], page: $page, limit: $limit );
            }
    
            [ $rows, $total ] = static::resolve_union_results( sql_parts: $sql_parts, limit: $limit, offset: $offset, use_window_fn: false );
            $rows    = static::hydrate_rows( $rows );
            $results = static::make_paginated_result( data: $rows, page: $page, limit: $limit, total: $total );
    
            self::cache_set( $key, $results, static::default_ttl() );
        }
    
        return $results;
    }

    /**
     * Get a single hosted application using its slug.
     *
     * @param string $app_type The app type can be one of the allowed app types.
     * @param string $app_slug The app slug.
     * @return AbstractHostedApp|null The instance of a hosted application or null on failure.
     */
    public static function get_app_by_slug( string $app_type, string $app_slug ) : AbstractHostedApp|null {
        $key    = self::make_cache_key( __METHOD__, [$app_type, $app_slug] );
        $app    = self::cache_get( $key );

        if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {
            $app_class  = HostedAppsRegistry::instance()->get_app_type_class( $app_type );

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, 'get_by_slug' ) ) {
                $app    = null;
            } else {
                $app    = $app_class::get_by_slug( $app_slug );
            }

            self::cache_set( $key, $app, static::default_ttl() );
        }

        /** @var AbstractHostedApp|null $app */
        return $app;
    }
    
    /**
     * Get a single hosted application using its ID.
     *
     * @param string $app_type The app type can be one of the allowed app types.
     * @param int $id The app ID.
     * @return AbstractHostedApp|null The instance of a hosted application or null on failure.
     */
    public static function get_app_by_id( $app_type, $id ) : AbstractHostedApp|null {
        $key    = self::make_cache_key( __METHOD__, [$app_type, $id] );
        $app    = self::cache_get( $key );

        if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {
            $app_class  = self::get_app_class( $app_type );
            $method     = "get_{$app_type}";

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $method ) ) {
                $app    = null;
            } else {
                $app    = $app_class::$method( $id );
            }

            self::cache_set( $key, $app, static::default_ttl() );
        }

        /** @var AbstractHostedApp|null */
        return $app;
    }

    /**
     * Get app by owner ID
     * @param int $owner_id
     * @return AbstractHostedApp[]
     */
    public static function get_all_by_owner( $owner_id ) : array {
        return []; //TODO: find and hydrate.
    }

    /**
     * Count hosted applications across multiple types by status.
     *
     * @param array{
     *   status?: string,
     *   types?: string[]
     * } $args
     * @return int Total count of matching applications.
     */
    public static function count_apps( array $args = [] ) : int {
        $defaults = [
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => HostedAppsRegistry::instance()->app_types(),
        ];
    
        $args   = parse_args( $args, $defaults );
        $status = $args['status'];
        $types  = array_filter( (array) $args['types'] );
    
        $key   = self::make_cache_key( __METHOD__, compact( 'status', 'types' ) );
        $count = self::cache_get( $key );
    
        if ( false === $count ) {
            $sql_parts = static::build_type_queries( $types, $status );
    
            if ( empty( $sql_parts ) ) {
                return 0;
            }
    
            $db = smliser_db();
    
            if ( 1 === count( $sql_parts ) ) {
                $count_sql = ( clone $sql_parts[0] )->select( 'COUNT(*) as total_records' );
                $count     = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );
            } else {
                $union_sql = static::build_union_query( $sql_parts );
                $count_sql = ( clone $union_sql )->select( 'COUNT(*) as total_records' )->as( 'app_count' );
                $count     = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );
            }
    
            self::cache_set( $key, $count, static::default_ttl() );
        }
    
        return $count;
    }

    /**
     * List all apps in trash.
     * 
     * @return array<int, array{
     *  app_type: string,
     *  app_slug: string,
     *  timestamp: int,
     *  trash_path: string
     * }> An empty array if no trashed app are found, or an array of trashed app data.
     */
    public static function list_trashed_apps() : array {
        $trash_dir	= rtrim( SMLISER_TRASH_DIR, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $pattern	= $trash_dir . '*/*/.smliser_meta';
        $files		= glob( $pattern ) ?: [];
        $app_data	= [];

        foreach ( $files as $file ) {

            $rel_path = substr( $file, strlen( $trash_dir ) );

            $rel_path = dirname( $rel_path );

            $parts = explode( DIRECTORY_SEPARATOR, $rel_path, 2 );

            if ( 2 !== count( $parts ) ) {
                continue;
            }

            [$app_type, $app_slug] = $parts;

            $timestamp  = (int) \smliser_filesystem()->get_contents( $file );
            $trash_path = dirname( $file );

            $app_data[] = compact( 'app_type', 'app_slug', 'timestamp', 'trash_path' );
        }

        return $app_data;
    }

    /*
    |-------------------
    | UTILITY  METHODS
    |-------------------
    */

    /**
     * Get the class for a hosted application type
     * 
     * @param string $type The type name.
     * @return class-string<AbstractHostedApp> $class_name The app's class name.
     */
    public static function get_app_class( $type ) {
        $class = '\\SmartLicenseServer\\HostedApps\\' . ucfirst( (string) $type );

        return $class;
    }

    /**
     * Get the repository class for a hosted application type
     * 
     * @param string $type The type name.
     * @return Repository|null.
     */
    public static function get_app_repository_class( string $type ) : ?Repository {
        return HostedAppsRegistry::instance()->get_app_type_directory_class( $type );
    }

    /**
     * Get allowed app types
     * 
     * @return array
     */
    public static function get_allowed_app_types() {
        return HostedAppsRegistry::instance()->app_types();
    }

    /**
     * Get the query builder instance.
     */
    protected static function query() : SQLBuilder {
        return \smliserQueryBuilder();
    }

    /**
     * Check whether the given app type is supported.
     * 
     * @param mixed $app_type The app type to check.
     */
    public static function app_type_is_allowed( $app_type ) {
        return HostedAppsRegistry::instance()->is_app_type_registered( (string) $app_type );
    }

    /*
    |-----------------------
    | PRIVATE HELPERS
    |-----------------------
    */

    /**
     * Calculate total pages.
     * 
     * @param int $total The total items.
     * @param int $limit The pagination limit.
     */
    private static function cal_total_pages( int $total, int $limit ) : int {
        return ( $limit > 0 ) ? (int) ceil( $total / $limit ) : 1;
    }

    /**
     * Make a paginated result.
     * 
     * @param mixed $data
     * @param int $page
     * @param int $total
     * @param int $limit
     */
    private static function make_paginated_result( mixed $data, int $page = 1, int $limit = 20 , int $total = 0) : array {
        return [
            'items'      => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => static::cal_total_pages( $total, $limit ),
            ],
        ];
    }

    /**
     * Build one SelectionIntent per valid app type.
     *
     * When $term is provided each intent gets a WHERE group that filters by
     * name/slug/author prefix.  When $term is omitted only the status filter
     * is applied (used by get_apps).
     *
     * @param  string[] $types
     * @param  string   $status
     * @param  string   $term   Optional search term.
     * @return SelectionIntent[]  Keyed by numeric index; empty when no valid tables exist.
     */
    private static function build_type_queries( array $types, string $status, string $term = '' ) : array {
        $registry  = HostedAppsRegistry::instance();
        $sql_parts = [];
    
        foreach ( $types as $type ) {
            $table = $registry->get_app_type_table( $type );
    
            if ( ! $table ) {
                continue;
            }
    
            $query = static::query()
                ->select( "'{$type}' as type", ...static::APP_COLUMNS )
                ->from( $table )
                ->where( 'status', '=', $status );
    
            if ( '' !== $term ) {
                $query->where_group( static function ( SelectionIntent $group ) use ( $term ) {
                    $group->where_starts_with( 'name', $term )
                        ->or_where_starts_with( 'slug', $term )
                        ->or_where_starts_with( 'author', $term );
                } );
            }
    
            $sql_parts[] = $query;
        }
    
        return $sql_parts;
    }

    /**
    * Execute query/queries and return raw rows plus a total record count.
    *
    * Handles three cases transparently:
    *   1. Single SelectionIntent  — uses a window function (when $use_window_fn
    *      is true) or a separate COUNT query (when false) to obtain the total.
    *   2. Two or more intents     — builds a UNION ALL sub-query and wraps it in
    *      a COUNT(*) sub-select for the total; always uses separate queries.
    *
    * @param  SelectionIntent[] $sql_parts       One or more query intents.
    * @param  int               $limit
    * @param  int               $offset
    * @param  bool              $use_window_fn   When true and there is only one
    *                                            intent, piggyback COUNT(*) OVER()
    *                                            onto the data query instead of
    *                                            issuing a separate count query.
    * @return array{ 0: array[], 1: int }        [ $rows, $total ]
    */
    private static function resolve_union_results( array $sql_parts, int $limit, int $offset, bool $use_window_fn ) : array {
        $db = smliser_db();
    
        if ( 1 === count( $sql_parts ) ) {
            $data_sql = $sql_parts[0];
    
            if ( $use_window_fn ) {
                $data_sql->select( 'COUNT(*) OVER() as total_records' )
                    ->limit( $limit )
                    ->offset( $offset );
    
                $rows  = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );
                $total = ! empty( $rows ) ? (int) $rows[0]['total_records'] : 0;
    
                foreach ( $rows as &$row ) {
                    unset( $row['total_records'] );
                }
                unset( $row );
            } else {
                $count_sql = static::query()
                    ->select( 'COUNT(*) as total_records' )
                    ->from( $data_sql->get_table_name() );
    
                // Copy WHERE clauses onto the count query by cloning and stripping
                // projection; simplest approach is cloning the intent directly.
                $count_clone = clone $data_sql;
                $count_clone->select( 'COUNT(*) as total_records' );
    
                $data_sql->limit( $limit )->offset( $offset );
    
                $rows  = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );
                $total = (int) $db->get_var( $count_clone->build(), $count_clone->get_bindings() );
            }
    
            return [ $rows, $total ];
        }
    
        // Two or more intents: build a UNION ALL.
        $base_union = static::build_union_query( $sql_parts );
    
        $count_sql = ( clone $base_union )->select( 'COUNT(*) as total_records' )->as( 'app_count' );
        $total     = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );
    
        $data_sql = $base_union->select( '*' )
            ->as( 'apps' )
            ->order_by( 'name', 'ASC' )
            ->limit( $limit )
            ->offset( $offset );
    
        $rows = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );
    
        return [ $rows, $total ];
    }
    
    /**
     * Hydrate raw DB rows into AbstractHostedApp instances in-place.
     *
     * Rows whose type maps to an unknown/missing class are silently dropped.
     *
     * @param  array[] $rows  Raw associative rows from the DB.
     * @return AbstractHostedApp[]
     */
    private static function hydrate_rows( array $rows ) : array {
        $registry = HostedAppsRegistry::instance();
    
        foreach ( $rows as $i => &$row ) {
            $class = $registry->get_app_type_class( $row['type'] ?? '' );
    
            if ( ! $class || ! class_exists( $class ) ) {
                unset( $rows[ $i ] );
                continue;
            }
    
            /** @var AbstractHostedApp $row */
            $row = $class::from_array_minimal( (array) $row );
        }
        unset( $row );
    
        return $rows;
    }

    /**
     * Fold a list of SelectionIntents into a single UNION ALL CompoundQueryIntent.
     *
     * @param  SelectionIntent[] $sql_parts  At least two intents.
     * @return \Callismart\DBPrism\Query\QueryIntents\CompoundQueryIntent
     */
    private static function build_union_query( array $sql_parts ) : mixed {
        $base = null;
    
        foreach ( $sql_parts as $sql ) {
            $base = ( null === $base ) ? $sql : $base->union_all( $sql );
        }
    
        return $base;
    }
}