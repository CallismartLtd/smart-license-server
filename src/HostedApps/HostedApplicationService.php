<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

namespace SmartLicenseServer\HostedApps;

use Callismart\DBPrism\Query\QueryIntents\PersistenceIntent;
use Callismart\DBPrism\Query\QueryIntents\SelectionIntent;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\SQLBuilder;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\HostedApps\Storage\AppStorage;
use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * The core API class for perform CRUD operations on hosted applications.
 * 
 * This class provides all methods required to manage hosted applications and their data, 
 * it also supports caching, pagination, searching, asset management, and bulk operations via the request API.
 */
class HostedApplicationService extends DataStore {
	use SanitizeAwareTrait;

	private const APP_COLUMNS = [
		'id', 'app_type', 'owner_id', 'name', 'slug', 'status',
		'author', 'download_link', 'created_at', 'updated_at',
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
		$db = static::$DB;
	
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
	
		$key     = static::make_cache_key( __METHOD__, compact( 'page', 'limit', 'status', 'types' ) );
		$results = static::cache_get( $key );
	
		if ( false === $results ) {
			$types = static::registered_types( $types );

			if ( empty( $types ) ) {
				return static::make_paginated_result( [], $page, $limit );
			}

			[ $rows, $total ] = static::fetch_page( $types, $status, limit: $limit, offset: $offset );
			$results          = static::make_paginated_result( static::hydrate_rows( $rows ), $page, $limit, $total );

			static::cache_set( $key, $results, static::default_ttl() );
		}
	
		return $results;
	}

	/**
	 * Get WordPress plugins from the repository.
	 *
	 * @param array{
	 *   page?: int,
	 *   limit?: int,
	 *   status?: string
	 * } $args
	 * 
	 * @return array{
	 *  items: \SmartLicenseServer\HostedApps\WPPlugin[],
	 *  pagination: array{
	 *      total: int,
	 *      page: int,
	 *      limit: int,
	 *      total_pages: int
	 *  }
	 * }
	 */
	public static function get_plugins( array $args = [] ) {
		$args['types'] = [ 'wpplugin' ];
		return static::get_apps( $args );
	}

	/**
	 * Get WordPress themes from the repository.
	 *
	 * @param array{
	 *  page?: int,
	 *  limit?: int,
	 *  status?: string
	 * } $args
	 * @return array{
	 *  items: \SmartLicenseServer\HostedApps\WPTheme[],
	 *  pagination: array{
	 *      total: int,
	 *      page: int,
	 *      limit: int,
	 *      total_pages: int
	 *  }
	 * }
	 */
	public static function get_themes( array $args = [] ) {
		$args['types'] = [ 'wptheme' ];
		return static::get_apps( $args );
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
	public static function get_trashed_apps( array $args = [] ) {
		$args['status'] = AbstractHostedApp::STATUS_TRASH;
		return static::get_apps( $args );
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
	public static function get_software( array $args = [] ) {
		$args['types'] = [ 'software' ];
		return static::get_apps( $args );
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
		$db = static::$DB;
	
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
	
		$key     = static::make_cache_key( __METHOD__, compact( 'term', 'page', 'limit', 'status', 'types' ) );
		$results = static::cache_get( $key );
	
		if ( false === $results ) {
			if ( '' === $term ) {
				return static::make_paginated_result( data: [], page: $page, limit: $limit );
			}
	
			$types = static::registered_types( $types );

			if ( empty( $types ) ) {
				return static::make_paginated_result( data: [], page: $page, limit: $limit );
			}

			[ $rows, $total ] = static::fetch_page( $types, $status, term: $term, limit: $limit, offset: $offset );
			$results          = static::make_paginated_result( data: static::hydrate_rows( $rows ), page: $page, limit: $limit, total: $total );
	
			static::cache_set( $key, $results, static::default_ttl() );
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
		$key = static::make_cache_key( __METHOD__, [$app_type, $app_slug] );
		$app = static::cache_get( $key );

		if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {
			$app_class = HostedAppsRegistry::instance()->get_app_type_class( $app_type );
			$app       = null;

			if ( $app_class && class_exists( $app_class ) ) {
				$db  = static::$DB;
				$sql = static::query()
					->select( '*' )
					->from( AbstractHostedApp::get_db_table() )
					->where( 'app_type', '=', HostedAppsRegistry::instance()->normalize_app_type( $app_type ) )
					->where( 'slug', '=', $app_slug )
					->limit( 1 );

				$row = $db->get_row( $sql->build(), $sql->get_bindings() );

				if ( $row ) {
					/** @var AbstractHostedApp $app */
					$app = $app_class::from_array( $row );
				}
			}

			static::cache_set( $key, $app, static::default_ttl() );
		}

		return $app;
	}
	
	/**
	 * Get a single hosted application using its ID.
	 *
	 * App IDs are unique across all types; the type is checked too, so an ID
	 * given with the wrong type finds nothing.
	 *
	 * @param string $app_type The app type can be one of the allowed app types.
	 * @param int $id The app ID.
	 * @return AbstractHostedApp|null The instance of a hosted application or null on failure.
	 */
	public static function get_app_by_id( $app_type, $id ) : AbstractHostedApp|null {
		$key = static::make_cache_key( __METHOD__, [$app_type, $id] );
		$app = static::cache_get( $key );

		if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {

			$registry  = HostedAppsRegistry::instance();
			$app_class = $registry->get_app_type_class( (string) $app_type );
			$app       = null;

			if ( $app_class && class_exists( $app_class ) ) {
				$db  = static::$DB;
				$sql = static::query()
					->select( '*' )
					->from( AbstractHostedApp::get_db_table() )
					->where( 'app_type', '=', $registry->normalize_app_type( (string) $app_type ) )
					->where( 'id', '=', $id )
					->limit( 1 );

				$row = $db->get_row( $sql->build(), $sql->get_bindings() );

				if ( $row ) {
					/** @var AbstractHostedApp $app */
					$app = $app_class::from_array( $row );
				}
			}

			static::cache_set( $key, $app, static::default_ttl() );
		}

		
		return $app;
	}

	/**
	 * Get all applications belonging to a specific owner, across all types.
	 *
	 * @param int $owner_id
	 * @param array{
	 *   page?:   int,
	 *   limit?:  int,
	 *   status?: string,
	 *   types?:  string[]
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
	public static function get_apps_for_owner( int $owner_id, array $args = [] ) : array {
		$db = static::$DB;
	
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
	
		$key     = static::make_cache_key( __METHOD__, compact( 'owner_id', 'page', 'limit', 'status', 'types' ) );
		$results = static::cache_get( $key );
	
		if ( false === $results ) {
			$types = static::registered_types( $types );

			if ( empty( $types ) ) {
				return static::make_paginated_result( [], $page, $limit );
			}

			[ $rows, $total ] = static::fetch_page( $types, $status, owner_id: $owner_id, limit: $limit, offset: $offset );
			$results          = static::make_paginated_result( static::hydrate_rows( $rows ), $page, $limit, $total );
	
			static::cache_set( $key, $results, static::default_ttl() );
		}
	
		return $results;
	}

	/**
	 * Count the apps a resource owner owns, whatever their type or status.
	 *
	 * Trashed apps count: they still belong to the owner until they are
	 * deleted for good.
	 *
	 * @param int $owner_id Resource owner ID.
	 * @return int
	 */
	public static function count_apps_for_owner( int $owner_id ) : int {
		if ( $owner_id <= 0 ) {
			return 0;
		}

		$sql = static::query()
			->select( 'COUNT(*)' )
			->from( AbstractHostedApp::get_db_table() )
			->where( 'owner_id', '=', $owner_id );

		return (int) static::$DB->get_var( $sql->build(), $sql->get_bindings() );
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
	
		$key = static::make_cache_key( __METHOD__, compact( 'status', 'types' ) );
		/** @var int|false $count */
		$count = static::cache_get( $key );
	
		if ( false === $count ) {
			$types = static::registered_types( $types );

			if ( empty( $types ) ) {
				return 0;
			}

			$count_sql = static::apps_query( [ 'COUNT(*)' ], $types, $status );
			$count     = (int) static::$DB->get_var( $count_sql->build(), $count_sql->get_bindings() );

			static::cache_set( $key, $count, static::default_ttl() );
		}
	
		return $count;
	}

	/**
	 * List all apps in trash, across every registered app type.
	 *
	 * Read from app storage, so it lists what each type's storage adapter
	 * holds in its trash. A type whose storage cannot be read lists nothing.
	 * 
	 * @return list<array{
	 *  app_type: string,
	 *  app_slug: string,
	 *  timestamp: int
	 * }> An empty array if no trashed app are found, or an array of trashed app data.
	 *     `timestamp` is when the app was trashed.
	 */
	public static function list_trashed_apps() : array {
		$registry = HostedAppsRegistry::instance();
		$app_data = [];

		foreach ( $registry->app_types() as $app_type ) {
			$repo_class = $registry->get_app_type_directory_class( $app_type );

			if ( null === $repo_class ) {
				continue;
			}

			foreach ( $repo_class->list_trashed() as $trashed ) {
				$app_data[] = [
					'app_type'  => $app_type,
					'app_slug'  => $trashed['slug'],
					'timestamp' => $trashed['trashed_at'],
				];
			}
		}

		return $app_data;
	}

	/*
	|------------------
	| SLUG MANAGEMENT
	|------------------
	*/

	/**
	 * Give a hosted app a new slug.
	 *
	 * The slug is the app's public identity, so it is changed everywhere it
	 * is recorded, in one database transaction: the app's record, the
	 * licenses and download tokens issued for it, its analytics and the
	 * bulk messages sent about it. Its files are renamed in storage last,
	 * and its app.json is rebuilt with the new slug. A failure at any step
	 * leaves everything as it was.
	 *
	 * The new slug must be in its final form (Repository::slug_from() gives
	 * it back unchanged) and nothing of the app's type may use it: no app
	 * record whatever its status, no live or trashed files, and no license,
	 * token, analytics or message left behind by a deleted app, which would
	 * otherwise attach to this app. An app in the trash cannot be renamed.
	 *
	 * Copies of the app already installed elsewhere still know it by its
	 * old slug.
	 *
	 * @param AbstractHostedApp $app The app, as loaded from the database.
	 * @param string $new_slug The new slug.
	 * @return true|Exception True on success; the reason otherwise, with a
	 *                        "status" in its data. Does not throw.
	 */
	public static function rename_app_slug( AbstractHostedApp $app, string $new_slug ) : true|Exception {
		$type     = $app->get_type();
		$old_slug = $app->get_slug();

		try {
			if ( ! $app->exists() ) {
				throw new Exception( 'app_not_found', sprintf( 'The %s does not exist.', $type ), [ 'status' => 404 ] );
			}

			if ( AbstractHostedApp::STATUS_TRASH === $app->get_status() ) {
				throw new Exception( 'app_in_trash', sprintf( 'This %s is in the trash. Restore it before renaming it.', $type ), [ 'status' => 400 ] );
			}

			if ( $new_slug === $old_slug ) {
				throw new Exception( 'slug_unchanged', sprintf( 'The %s already has the slug "%s".', $type, $new_slug ), [ 'status' => 400 ] );
			}

			$canonical = Repository::slug_from( $new_slug );

			if ( $canonical !== $new_slug || ! AppFile::is_segment( $new_slug ) ) {
				throw new Exception(
					'invalid_slug',
					'' !== $canonical && AppFile::is_segment( $canonical )
						? sprintf( '"%s" is not a valid slug. Did you mean "%s"?', $new_slug, $canonical )
						: sprintf( '"%s" is not a valid slug.', $new_slug ),
					[ 'status' => 400 ]
				);
			}

			$repo_class = static::get_app_repository_class( $type );

			if ( null === $repo_class ) {
				throw new Exception( 'repo_error', sprintf( 'No repository is registered for the app type "%s".', $type ), [ 'status' => 500 ] );
			}

			static::$DB->transactional( function( Database $db ) use ( $app, $type, $old_slug, $new_slug, $repo_class ) {
				$table = $app::get_db_table();
				$lock  = static::query()->select( 'id', 'slug' )->from( $table )
					->where( 'id', '=', $app->get_id() )
					->where( 'app_type', '=', $type )
					->limit( 1 )->lock_for_update();
				$row   = $db->get_row( $lock->build(), $lock->get_bindings() );

				if ( ! $row || $old_slug !== $row['slug'] ) {
					throw new Exception( 'app_changed', sprintf( 'The %s was changed or deleted while renaming it. Reload it and try again.', $type ), [ 'status' => 409 ] );
				}

				$used_by = static::slug_used_by( $db, $type, $table, $new_slug, $repo_class );

				if ( null !== $used_by ) {
					throw new Exception(
						'app_slug_exists',
						sprintf( 'The slug "%s" is not available: %s.', $new_slug, $used_by ),
						[ 'status' => 409 ]
					);
				}

				foreach ( static::slug_references( $type, $table, $old_slug, $new_slug, (int) $app->get_id() ) as $update ) {
					$db->execute( $update->build(), $update->get_bindings() );
				}

				// Files last: if they cannot be renamed, the database changes
				// are rolled back.
				$renamed = $repo_class->rename( $old_slug, $new_slug );

				if ( $renamed instanceof Exception ) {
					throw $renamed;
				}

				$app->set_slug( $new_slug );

				try {
					$repo_class->regenerate_app_dot_json( $app );
				} catch ( \Throwable $e ) {
					$repo_class->rename( $new_slug, $old_slug );

					throw $e;
				}
			} );
		} catch ( Exception $e ) {
			$app->set_slug( $old_slug );

			return $e;
		} catch ( \Throwable $e ) {
			$app->set_slug( $old_slug );

			return new Exception( 'rename_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		static::cache_clear();

		return true;
	}

	/**
	 * Tell what of an app type already uses a slug.
	 *
	 * @param Database $db The database, inside the rename transaction.
	 * @param string $type App type.
	 * @param string $table The apps table.
	 * @param string $slug The slug.
	 * @param Repository $repo_class The type's repository.
	 * @return string|null What uses it, for the error message; null when free.
	 */
	protected static function slug_used_by( Database $db, string $type, string $table, string $slug, Repository $repo_class ) : ?string {
		$storage = AppStorage::instance();

		if ( $storage->has_app( $type, $slug ) || $repo_class->is_trashed( $slug ) ) {
			return sprintf( 'another %s has it in storage', $type );
		}

		$app_prop = sprintf( '%s/%s', $type, $slug );
		$checks   = [
			sprintf( 'another %s has it', $type )        => static::query()->select( 'id' )->from( $table )->where( 'app_type', '=', $type )->where( 'slug', '=', $slug ),
			'licenses still refer to it'                  => static::query()->select( 'id' )->from( TableName::LICENSES->table() )->where( 'app_prop', '=', $app_prop ),
			'download tokens still refer to it'           => static::query()->select( 'id' )->from( TableName::APP_DOWNLOAD_TOKENS->table() )->where( 'app_prop', '=', $app_prop ),
			'analytics still refer to it'                 => static::query()->select( 'app_slug' )->from( TableName::ANALYTICS_DAILY->table() )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $slug ),
			'the analytics log still refers to it'        => static::query()->select( 'app_slug' )->from( TableName::ANALYTICS_LOG->table() )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $slug ),
			'bulk messages still refer to it'             => static::query()->select( 'app_slug' )->from( TableName::BULK_MESSAGES_APPS->table() )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $slug ),
		];

		foreach ( $checks as $reason => $query ) {
			$query->limit( 1 );

			if ( null !== $db->get_var( $query->build(), $query->get_bindings() ) ) {
				return $reason;
			}
		}

		return null;
	}

	/**
	 * Build the updates that move every record of an app's slug to a new one.
	 *
	 * @param string $type App type.
	 * @param string $table The apps table.
	 * @param string $old_slug The app's slug.
	 * @param string $new_slug The new slug.
	 * @param int $app_id The app's ID.
	 * @return list<PersistenceIntent>
	 */
	protected static function slug_references( string $type, string $table, string $old_slug, string $new_slug, int $app_id ) : array {
		$old_prop = sprintf( '%s/%s', $type, $old_slug );
		$new_prop = sprintf( '%s/%s', $type, $new_slug );

		return [
			static::query()->update( $table )->set( [ 'slug' => $new_slug ] )->where( 'id', '=', $app_id )->where( 'app_type', '=', $type ),
			static::query()->update( TableName::LICENSES->table() )->set( [ 'app_prop' => $new_prop ] )->where( 'app_prop', '=', $old_prop ),
			static::query()->update( TableName::APP_DOWNLOAD_TOKENS->table() )->set( [ 'app_prop' => $new_prop ] )->where( 'app_prop', '=', $old_prop ),
			static::query()->update( TableName::ANALYTICS_DAILY->table() )->set( [ 'app_slug' => $new_slug ] )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $old_slug ),
			static::query()->update( TableName::ANALYTICS_LOG->table() )->set( [ 'app_slug' => $new_slug ] )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $old_slug ),
			static::query()->update( TableName::BULK_MESSAGES_APPS->table() )->set( [ 'app_slug' => $new_slug ] )->where( 'app_type', '=', $type )->where( 'app_slug', '=', $old_slug ),
		];
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
	 * @return class-string<HostedAppsInterface>|null The app type's class; null when the type is not registered.
	 */
	public static function get_app_class( $type ) {
		return HostedAppsRegistry::instance()->get_app_type_class( (string) $type );
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
		return \smliserQueryBuilder( static::$DB->get_driver() );
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
	private static function make_paginated_result( mixed $data, int $page = 1, int $limit = 20 , int $total = 0 ) : array {
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
	 * Keep the given app types that are registered, normalized.
	 *
	 * @param string[] $types
	 * @return list<string>
	 */
	private static function registered_types( array $types ) : array {
		$registry = HostedAppsRegistry::instance();
		$types    = array_map( static fn ( $type ) : string => $registry->normalize_app_type( (string) $type ), $types );

		return array_values( array_unique( array_filter( $types, [ $registry, 'is_app_type_registered' ] ) ) );
	}

	/**
	 * Build a query on the apps table for apps of the given types and status.
	 *
	 * @param string[] $columns  The columns to select.
	 * @param string[] $types    Registered app types (see registered_types()).
	 * @param string   $status   App status.
	 * @param string   $term     Optional search term; matches the start of the name, slug or author.
	 * @param int|null $owner_id Optional owner.
	 * @return SelectionIntent
	 */
	private static function apps_query( array $columns, array $types, string $status, string $term = '', ?int $owner_id = null ) : SelectionIntent {
		$query = static::query()
			->select( ...$columns )
			->from( AbstractHostedApp::get_db_table() )
			->where_in( 'app_type', $types )
			->where( 'status', '=', $status );

		if ( null !== $owner_id ) {
			$query->where( 'owner_id', '=', $owner_id );
		}

		if ( '' !== $term ) {
			$query->where_group( static function ( SelectionIntent $group ) use ( $term ) {
				$group->where_starts_with( 'name', $term )
					->or_where_starts_with( 'slug', $term )
					->or_where_starts_with( 'author', $term );
			} );
		}

		return $query;
	}

	/**
	 * Fetch one page of apps, ordered by name, and the total number of
	 * matching apps.
	 *
	 * @param string[] $types    Registered app types (see registered_types()).
	 * @param string   $status   App status.
	 * @param string   $term     Optional search term.
	 * @param int|null $owner_id Optional owner.
	 * @param int      $limit
	 * @param int      $offset
	 * @return array{ 0: array<int, array<string, mixed>>, 1: int } [ $rows, $total ]
	 */
	private static function fetch_page( array $types, string $status, string $term = '', ?int $owner_id = null, int $limit = 20, int $offset = 0 ) : array {
		$db = static::$DB;

		$count_sql = static::apps_query( [ 'COUNT(*)' ], $types, $status, $term, $owner_id );
		$total     = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

		if ( 0 === $total ) {
			return [ [], 0 ];
		}

		$data_sql = static::apps_query( static::APP_COLUMNS, $types, $status, $term, $owner_id )
			->order_by( 'name', 'ASC' )
			->order_by( 'id', 'ASC' )
			->limit( $limit )
			->offset( $offset );

		$rows = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );

		return [ $rows ?: [], $total ];
	}

	/**
	 * Hydrate raw DB rows into AbstractHostedApp instances.
	 *
	 * Rows whose type is not registered are dropped.
	 *
	 * @param  array<int, array<string, mixed>> $rows Raw associative rows from the apps table.
	 * @return AbstractHostedApp[]
	 */
	private static function hydrate_rows( array $rows ) : array {
		$registry = HostedAppsRegistry::instance();
		$apps     = [];

		foreach ( $rows as $row ) {
			$class = $registry->get_app_type_class( (string) ( $row['app_type'] ?? '' ) );

			if ( ! $class || ! class_exists( $class ) ) {
				continue;
			}

			/** @var AbstractHostedApp $app */
			$app    = $class::from_array_minimal( (array) $row );
			$apps[] = $app;
		}

		return $apps;
	}
}