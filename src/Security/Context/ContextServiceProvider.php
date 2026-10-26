<?php
/**
 * The security context service file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security;
 */

namespace SmartLicenseServer\Security\Context;

use SmartLicenseServer\Schema\TableName;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\QueryIntents\JoinCriteria;
use Callismart\DBPrism\Query\QueryIntents\SelectionIntent;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Exceptions\SecurityException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\OwnerSubjects\OrganizationMembers;
use SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface;
use SmartLicenseServer\Security\Permission\DefaultRoles;

use function class_exists, parse_args_recursive, strtolower, method_exists,
sprintf, class_implements, in_array;

/**
 * The security context service provider holds the context of authenticated actor/principal
 * and provides unified methods to access security entities.
 */
class ContextServiceProvider extends DataStore {
	use CacheAwareTrait, SanitizeAwareTrait;

	/**
	 * Search security entities across multiple db tables with pagination.
	 *
	 * @param array{
	 *  search_term: string,
	 *  page: int,
	 *  limit: int,
	 *  types: array
	 * } $args
	 * @return array{items: mixed, pagination: array{total: int, page: int, limit: int, total_pages: int}}
	 */
	public static function search( array $args = [] ) {
		$db = static::$DB;

		$defaults = [
			'search_term' => '',
			'page'        => 1,
			'limit'       => 20,
			'types'       => Owner::get_allowed_owner_types(),
		];

		$args   = parse_args( $args, $defaults );
		$term   = self::sanitize_text( $args['search_term'] );
		$page   = max( 1, (int) $args['page'] );
		$limit  = max( 1, (int) $args['limit'] );
		$offset = $db->calculate_query_offset( $page, $limit );

		/** @var ('individual'|'organization'|'platform')[] $types */
		$types = array_filter( (array) $args['types'] );

		$cache_key = static::make_cache_key( __METHOD__, \compact( 'term', 'page', 'limit', 'types' ) );

		$results = static::cache_get( $cache_key );

		if ( false !== $results ) {
			return $results;
		}

		if ( empty( $term ) || empty( $types ) ) {
			return static::make_paginated_result( data: [], page: $page, limit: $limit );
		}

		$total_types = count( $types );

		if ( 1 === $total_types ) {
			$type  = $types[0];
			$table = static::get_entity_table( $type );

			if ( ! $table ) {
				return static::make_paginated_result( data: [], page: $page, limit: $limit );
			}

			$sql = static::query()
				->select( '*' )->from( $table )
				->where_contains( 'display_name', $term )
				->limit( $limit )->offset( $offset );

			$count_sql = static::query()
				->select( 'COUNT(*)' )->from( $table )
				->where_contains( 'display_name', $term );

			$rows  = $db->get_results( $sql->build(), $sql->get_bindings() );
			$total = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

			return static::make_paginated_result( data: static::hydrate_rows( $rows, $type ), page: $page, limit: $limit, total: $total );

		}

		$sql_map = fn( string $type, string $table ) => match( $type ) {
			Owner::TYPE_ORGANIZATION => static::query()
				->select(
					"'{$type}' as type", 'id', 'display_name', 'slug', 'NULL as email',
					'NULL as status', 'created_at', 'updated_at' )->from( $table )
				->where_contains( 'display_name', $term ),
			Owner::TYPE_INDIVIDUAL => static::query()
				->select(
					"'{$type}' as type", 'id', 'display_name', 'NULL as slug', 'email',
					'status', 'created_at', 'updated_at' )->from( $table )
				->where_contains( 'display_name', $term ),
			default => null
		};

		$sqls       = [];
		$added_type = '';

		foreach ( $types as $type ) {
			$table = static::get_entity_table( $type );

			if ( ! $table ) continue;

			$query = $sql_map( type: $type, table: $table );

			if ( ! $query ) continue;

			$sqls[]     = $query;
			$added_type = $type;
		}

		if ( empty( $sqls ) ) {
			return static::make_paginated_result( data: [], page: $page, limit: $limit );
		}

		if ( 1 === count( $sqls ) ) {
			$sql       = $sqls[0];
			$count_sql = ( clone $sql )->select( 'COUNT(*) as total_records' )->from( $sql->get_table_name() );

			$sql->limit( $limit )->offset( $offset );

			$rows  = $db->get_results( $sql->build(), $sql->get_bindings() );
			$total = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

			return static::make_paginated_result( data: static::hydrate_rows( $rows, $added_type ), page: $page, limit: $limit, total: $total );
		}

		$base_union = null;

		foreach( $sqls as $sql ) {
			if ( ! isset( $base_union ) ) {
				$base_union = $sql;
				continue;
			}

			$base_union = $base_union->union_all( $sql );
		}

		/** @var \Callismart\DBPrism\Query\QueryIntents\CompoundQueryIntent $base_union */
		$count_sql = ( clone $base_union )->select( 'COUNT(*) as total' )->as( 'counts' );
		$data_sql  = $base_union
			->select( '*' )
			->as( 'combined' )->order_by( 'updated_at', 'DESC' )
			->limit( $limit )->offset( $offset );

		$rows  = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );
		$total = $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

		foreach ( $rows as $index => &$row ) {

			$class = self::get_entity_classname( $row['type'] );

			if ( $class && method_exists( $class, 'from_array' ) ) {
				$row = $class::from_array( $row );
			} else {
				unset( $rows[$index] );
			}
		}

		return static::make_paginated_result( data: $rows, page: $page, limit: $limit, total: $total );
	}

	/**
	 * Perform a search in the resource owners table.
	 * 
	 * @param array $args {
	 *    Optional. Arguments to filter results.
	 *    @type string $term   Search term to match against name, type, ids. Required.
	 *    @type int    $page   Current page number. Default 1.
	 *    @type int    $limit  Number of items per page. Default 20.
	 *    @type string $status Entity status filter. Default 'active'.
	 * }
	 * 
	 * @return array{items: array, pagination: array{total: int, page: int, limit: int, total_pages: int}}
	 */
	public static function search_owners( array $args = [] ) : array {
		$db       = static::$DB;
		$defaults = [
			'search_term' => '',
			'page'        => 1,
			'limit'       => 20,
		];

		$args   = parse_args_recursive( $args, $defaults );
		$table  = TableName::RESOURCE_OWNERS->table();
		$term   = self::sanitize_text( $args['search_term'] );
		$limit  = max( 1, (int) $args['limit'] );
		$page   = max( 1, (int) $args['page'] );
		$offset = $db->calculate_query_offset( $page, $limit );

		if ( empty( $term ) ) {
			return static::make_paginated_result( data: [], page: $page, limit: $limit );
		}

		$sql = static::query()
			->select( '*' )->from( $table )
			->where_contains( 'type', $term )
			->or_where_contains( 'name', $term )
			->order_by( 'updated_at', 'DESC' )
			->limit( $limit )->offset( $offset );

		$count_sql = static::query()
			->select( 'COUNT(*)' )->from( $table )
			->where_contains( 'type', $term )
			->or_where_contains( 'name', $term );

		$rows  = $db->get_results( $sql->build(), $sql->get_bindings() );
		$total = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

		$owners = array_map( [Owner::class, 'from_array'], $rows );

		return static::make_paginated_result( data: $owners, page: $page, limit: $limit, total: $total );
	}

	/**
	 * Get a security entity class name.
	 * 
	 * @param string $entity The name of the security entity.
	 * - valid names are `owner`, `user`, `individual`, `organization`, `service_account`, and `role`.
	 * @return class-string<Owner|Organization|User|ServiceAccount|Role>|null
	 */
	public static function get_entity_classname( $entity ) : ?string {
		if ( ! is_string( $entity ) ) {
			return null;
		}

		$entity = str_replace( '_', '', ucwords( $entity, '_' ) );

		return match( strtolower( $entity ) ) {
			Owner::TYPE_INDIVIDUAL, 'user' => User::class,
			Owner::TYPE_ORGANIZATION       => Organization::class,
			'serviceaccount'               => ServiceAccount::class,
			'owner'                        => Owner::class,
			default                        => ''
		};

	}

	/**
	 * Saves the role of an actor.
	 *
	 * Does not touch the cache: callers clear it once the write has succeeded,
	 * so a write never fails, or rolls back, because of the cache.
	 *
	 * @param ActorInterface $actor The actor that can authenticate.
	 * @param Role $role
	 * @param OwnerSubjectInterface|null $subject The subject entity associated with resource owner.
	 * @throws InvalidArgumentException When one required field is missing.
	 * @throws DatabaseException Sensitive database error, caller must handle accordingly.
	 */
	public static function save_actor_role( ActorInterface $actor, Role $role, ?OwnerSubjectInterface $subject = null ) : bool {
		// A platform role: inside an organization it would grant nothing (Principal::in_organization()).
		if ( $subject && Owner::TYPE_ORGANIZATION === $subject->get_type() && static::is_platform_role( $role ) ) {
			throw new InvalidArgumentException(
				sprintf( '"%s" is a platform role and cannot be given inside an organization.', $role->get_label() ?: $role->get_slug() )
			);
		}

		$table        = TableName::PRINCIPAL_ROLES->table();
		$subject_type = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
		$subject_id   = $subject ? $subject->get_id() : $actor->get_id();

		$result = static::$DB->transactional( function( Database $db ) use ( $role, $subject, $table, $actor, $subject_type, $subject_id ) {
			$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$data = [
				'role_id'            => $role->get_id(),
				'principal_id'       => $actor->get_id(),
				'principal_type'     => $actor->get_type(),
				'owner_subject_type' => $subject_type,
				'owner_subject_id'   => $subject_id,
				'updated_at'         => $now->format( 'Y-m-d H:i:s' )
			];
			
			$missing_keys = Collection::make( $data )
				->filter( fn( $value ) => empty( $value ) )->keys()->all();

			if ( ! empty( $missing_keys ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'Role assignment failed. Missing required fields: %s',
						implode( ', ', $missing_keys )
					)
				);
			}

			$exists_sql = static::query()
				->select( 'role_id' )->from( $table )
				->where( 'principal_id', '=', $actor->get_id() )
				->where( 'principal_type', '=', $actor->get_type() )
				->where( 'owner_subject_type', '=', $subject_type )
				->where( 'owner_subject_id', '=', $subject_id )
				->limit( 1 )->lock_for_update();

			$role_id = (int) $db->get_var( $exists_sql->build(), $exists_sql->get_bindings() );

			if ( $role_id ) {
				if ( $role->get_id() !== $role_id ) {
					// Only the role assigned to this owner changes.
					// Existing owner and principal data remains immutable.
					$where = [
						'principal_id'       => $actor->get_id(),
						'principal_type'     => $actor->get_type(),
						'owner_subject_type' => $subject_type,
						'owner_subject_id'   => $subject ? $subject->get_id() : $actor->get_id(),
					];
					
					$data   = ['role_id' => $role->get_id()];
					$result = $db->update( $table, $data, $where );
				} else {
					$result = true;
				}

				$code = 'updated_failed';

			} else {
				$data['created_at'] = $now->format( 'Y-m-d H:i:s' );
				$result             = $db->insert( $table, $data );

				$code = 'insert_failed';
			}

			if ( ! $result ) {
				throw new DatabaseException( $code, $db->get_last_error() );
			}

			return $result;
		} );
		
		return false !== $result;
	}

	/**
	 * Delete an actors' assignedrole from the assignment table.
	 *
	 * Does not touch the cache: callers clear it once the write has succeeded,
	 * so a write never fails, or rolls back, because of the cache.
	 *
	 * @param ActorInterface $actor
	 * @param OwnerSubjectInterface|null $subject
	 * @throws DatabaseException Sensitive database error, caller must handle accordingly.
	 */
	public static function delete_actor_role( ActorInterface $actor, ?OwnerSubjectInterface $subject ) : void {
		if ( ! $subject && ! in_array( OwnerSubjectInterface::class, class_implements( $actor ), true ) ) {
			throw new SecurityException(
				'invalid_scope',
				'Principal must either be a valid resource owner or be acting for a resource owner.',
				['status' => 500]
			);
		}

		$table        = TableName::PRINCIPAL_ROLES->table();
		$db           = static::$DB;
		$subject_type = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;

		$deleted = $db->delete( $table,[
			'principal_id'       => $actor->get_id(),
			'principal_type'     => $actor->get_type(),
			'owner_subject_type' => $subject_type,
			'owner_subject_id'   => $subject ? $subject->get_id() : $actor->get_id(),
		] );

		if ( false === $deleted ) {
			throw new DatabaseException( 'delete_failed', $db->get_last_error() );
		}
	}

	/**
	 * Get the role assigned to an actor/principal.
	 * 
	 * @param ActorInterface $actor The authenticatable actor.
	 * @param OwnerSubjectInterface|null $subject The owner subject (organization or user).
	 * @return Role|null
	 */
	public static function get_principal_role( ActorInterface $actor, ?OwnerSubjectInterface $subject = null ) : ?Role {
		$db    = static::$DB;
		$table = TableName::PRINCIPAL_ROLES->table();
				
		$subject_type = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
		$sbj_owner_id = $subject ? $subject->get_id() : $actor->get_id();

		$sql = static::query()
			->select( 'role_id' )->from( $table )
			->where( 'principal_id', '=', $actor->get_id() )
			->where( 'principal_type', '=', $actor->get_type() )
			->where( 'owner_subject_type', '=', $subject_type )
			->where( 'owner_subject_id', '=', $sbj_owner_id )
			->limit( 1 );

		$role_id = $db->get_var( $sql->build(), $sql->get_bindings() );

		return $role_id ? Role::get_by_id( (int) $role_id ) : null;
	}

	/**
	 * Get the default owner entity this user object represents.
	 * 
	 * @param User $user The user object.
	 * @return Owner|null The owner instance or null when the user is not a resource owner
	 */
	public static function get_default_owner( User $user ) : ?Owner {
		$db    = static::$DB;
		$table = TableName::RESOURCE_OWNERS->table();
		$sql   = static::query()
			->select( 'id' )->from( $table )
			->where( 'subject_id', '=', $user->get_id() )
			->where( 'type', '=', Owner::TYPE_INDIVIDUAL )
			->limit( 1 );
		$id = (int) $db->get_var( $sql->build(), $sql->get_bindings() );

		return Owner::get_by_id( $id );
	}

	/**
	 * Get either the organization or the individual user associated with a
	 * resource owner object.
	 * 
	 * @param Owner $owner
	 * @return null|\SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface
	 */
	public static function get_owner_subject( Owner $owner ) : ?OwnerSubjectInterface {

		return match( $owner->get_type() ) {
			Owner::TYPE_ORGANIZATION => Organization::get_by_id( $owner->get_subject_id() ),
			Owner::TYPE_INDIVIDUAL   => User::get_by_id( $owner->get_subject_id() ),
			default                  => null
		};
	}

	/**
	 * Save a single member of an organization with their role.
	 *
	 * Does not touch the cache: callers clear it once the write has succeeded,
	 * so a write never fails, or rolls back, because of the cache.
	 *
	 * @param OrganizationMember $member
	 * @param Organization $organization
	 * @param Role $role
	 * @throws DatabaseException|InvalidArgumentException
	 */
	public static function save_organization_member( OrganizationMember $member, Organization $organization, Role $role ) {
		static::$DB->transactional( function( Database $db ) use ( $member, $organization, $role ) {
			if ( ! $organization->is_member( $member ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'%s does not belong to this organization "%s"',
						$member->get_display_name(),
						$organization->get_display_name()
					)
				);
			}

			if ( ! $role->exists() ) {
				throw new InvalidArgumentException( 'The role assigned to this member does not exist.' );
			}

			if ( ! in_array( $role->get_slug(), DefaultRoles::ORGANIZATION_ROLES, true ) ) {
				throw new InvalidArgumentException(
					sprintf( '"%s" cannot be held inside an organization. Choose one of: %s.', $role->get_label() ?: $role->get_slug(), implode( ', ', DefaultRoles::ORGANIZATION_ROLES ) )
				);
			}

			$table = TableName::ORGANIZATION_MEMBERS->table();
			$now   = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

			$exists_sql = static::query()
				->select( 'id' )->from( $table )
				->where( 'organization_id', '=', $organization->get_id() )
				->where( 'member_id', '=', $member->get_member_id() )
				->limit( 1 )->lock_for_update();

			$id = (int) $db->get_var( $exists_sql->build(), $exists_sql->get_bindings() );

			if ( $id ) {
				$data = ['updated_at' => $now->format( 'Y-m-d H:i:s' ) ];
				
				if ( false === $db->update( $table, $data, ['id' => $id] ) ) {
					throw new DatabaseException( 'update_failed', $db->get_last_error() );
				}

			} else {
				$data = [
					'organization_id' => $organization->get_id(),
					'member_id'       => $member->get_member_id(),
					'created_at'      => $now->format( 'Y-m-d H:i:s' ),
					'updated_at'      => $now->format( 'Y-m-d H:i:s' )
				];

				if ( false === $db->insert( $table, $data ) ) {
					throw new DatabaseException( 'insert_failed', $db->get_last_error() );
				}
			}
			
			static::save_actor_role( $member->get_user(), $role, $organization );
		} );
	}

	/**
	 * Get organization members with their roles
	 *
	 * @param Organization $organization
	 * @return OrganizationMembers
	 */
	public static function get_organization_members( Organization $organization ) : OrganizationMembers {
		$table = TableName::ORGANIZATION_MEMBERS->table();
		$db    = static::$DB;

		$sql = static::query()
			->select( '*' )->from( $table )
			->where( 'organization_id', '=', $organization->get_id() );
		$results = $db->get_results( $sql->build(), $sql->get_bindings() );

		$members = new OrganizationMembers();

		foreach ( $results as $result ) {
			$member_id = (int) $result['member_id'] ?? 0;
			$user      = User::get_by_id( $member_id );

			if ( ! $user ) {
				continue;
			}

			$role   = static::get_principal_role( $user, $organization );
			$member = OrganizationMember::from_array( $result );

			if ( $role ) {
				$member->set_role( $role );
			}

			$members->add( $member );
		}

		return $members;
	}

	/**
	 * Get all the organization a user belongs to.
	 * 
	 * @param User $user
	 * @return Organization[]|null
	 */
	public static function get_user_organizations( User $user ) : ?array {
		$sql = static::query()
			->select( 'organization_id' )->from( TableName::ORGANIZATION_MEMBERS->table() )
			->where( 'member_id', '=', $user->get_id() );

		$organizations = [];

		foreach ( (array) static::$DB->get_col( $sql->build(), $sql->get_bindings() ) as $organization_id ) {
			$organization = Organization::get_by_id( (int) $organization_id );

			// A membership whose organization is gone is skipped.
			if ( $organization ) {
				$organizations[ $organization->get_id() ] = $organization;
			}
		}

		return [] === $organizations ? null : array_values( $organizations );
	}

	/**
	 * Deletes an organization member and their roles
	 *
	 * Does not touch the cache: callers clear it once the write has succeeded,
	 * so a write never fails, or rolls back, because of the cache.
	 *
	 * @param OrganizationMember $member
	 * @param Organization $organization
	 * @throws InvalidArgumentException
	 * @throws DatabaseException Sensitive database error, caller must handle accordingly.
	 */
	public static function delete_organization_member( OrganizationMember $member, Organization $organization ) {
		static::$DB->transactional( function( Database $db ) use ( $member, $organization ) {
			if ( ! $organization->is_member( $member ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'%s does not belong to this organization "%s"',
						$member->get_display_name(),
						$organization->get_display_name()
					)
				);
			}
			$db    = static::$DB;
			$table = TableName::ORGANIZATION_MEMBERS->table();

			$deleted = $db->delete( $table, [
				'id'              => $member->get_id(),
				'member_id'       => $member->get_member_id(),
				'organization_id' => $organization->get_id()
			] );

			if ( false === $deleted ) {
				throw new DatabaseException( 'delete_failed', $db->get_last_error() );
			}

			// The role belongs to the member's user, not to the membership record.
			$user = $member->get_user();

			if ( $user ) {
				static::delete_actor_role( $user, $organization );
			} else {
				$db->delete( TableName::PRINCIPAL_ROLES->table(), [
					'principal_id'       => $member->get_member_id(),
					'principal_type'     => Owner::TYPE_INDIVIDUAL,
					'owner_subject_type' => Owner::TYPE_ORGANIZATION,
					'owner_subject_id'   => $organization->get_id(),
				] );
			}
		} );
	}

	/**
	 * Delete a security entity and all its related data in a single query where possible.
	 *
	 * Does not touch the cache: callers clear it once the write has succeeded,
	 * so a write never fails, or rolls back, because of the cache.
	 *
	 * @param User|Organization|ServiceAccount|Owner $entity
	 * @return void
	 * @throws SecurityException On failed or partial delete.
	 */
	public static function delete_entity( User|Organization|ServiceAccount|Owner $entity ) : void {

		try {
			switch ( true ) {
				case $entity instanceof User:
					$deleted = static::delete_user( $entity );
					break;

				case $entity instanceof Organization:
					$deleted = static::delete_organization( $entity );
					
					break;

				case $entity instanceof ServiceAccount:
					// Delete service account and all roles assigned to it.
					$deleted = static::delete_service_account( $entity );
					break;

				case $entity instanceof Owner:

					$deleted = static::delete_resource_owner( $entity );
					break;


				default:
					throw new SecurityException(
						'delete_error',
						'Unsupported entity type for deletion.',
						['status' => 400]
					);
			}

			if ( false === $deleted ) {
				throw new SecurityException(
					'delete_error',
					'Unable to delete the provided entity from the database.',
					['status' => 500]
				);
			}
		} catch ( \Exception $e ) {
			throw new SecurityException(
				'delete_error',
				'Failed to delete entity. Transaction rolled back.',
				['status' => 500, 'error' => $e->getMessage()]
			);
		}
	}

	/**
	 * Generate overview report for Accounts & Access dashboard.
	 *
	 * @return array{summary: array{users: int, organizations: int, service_accounts: int, organization_members: int, resource_owners: int}, integrity: array{orphaned_service_accounts: int, orphaned_members: int, orphaned_owners: int, has_issues: bool}, usage: array{service_accounts: array{total: int, ever_used: int, never_used: int, most_recent_use: mixed, oldest_use: mixed}}} $report
	 * @throws SecurityException
	 */
	public static function get_accounts_summary_report() : array {
		$cache_key = static::make_cache_key( __METHOD__ );
		$report    = static::cache_get( $cache_key );

		if ( false !== $report ) {
			return $report;
		}

		$db                     = static::$DB;
		$users_table            = TableName::USERS->table();
		$organizations_table    = TableName::ORGANIZATIONS->table();
		$org_members_table      = TableName::ORGANIZATION_MEMBERS->table();
		$service_accounts_table = TableName::SERVICE_ACCOUNTS->table();
		$owners_table           = TableName::RESOURCE_OWNERS->table();

		try {
			$counts_sql = static::query()
				->select_raw(
					"( SELECT COUNT(*) FROM [{$users_table}] ) AS users_total",
					"( SELECT COUNT(*) FROM [{$organizations_table}] ) AS organizations_total",
					"( SELECT COUNT(*) FROM [{$service_accounts_table}] ) AS service_accounts_total",
					"( SELECT COUNT(*) FROM [{$org_members_table}] ) AS organization_members_total",
					"( SELECT COUNT(*) FROM [{$owners_table}] ) AS resource_owners_total"
				);
			$counts = $db->get_row( $counts_sql->build(), $counts_sql->get_bindings() );

			$integrity_sql = static::query()
				->select_subquery( fn( SelectionIntent $q ) => $q
					->select( 'COUNT(*)' )->from( "$service_accounts_table as sa" )
					->left_join( "$owners_table ro", 'sa.owner_id', '=', 'ro.id' )
					->where_null( 'ro.id' )
					, 'orphaned_service_accounts' )
				
				->select_subquery( fn( SelectionIntent $q ) => $q
						->select( 'COUNT(*)' )->from( "$org_members_table om" )
						->left_join( "$users_table u", 'om.member_id', '=', 'u.id' )
						->where_null( 'u.id' )
					, 'orphaned_members' )
				
				->select_subquery( fn( SelectionIntent $q ) => $q
						->select( 'COUNT(*)' )->from( "$owners_table ro" )
						// Explicit multi-conditional ON mappings replacing the legacy linear splits
						->left_join( "$users_table u", fn( JoinCriteria $join ) => $join
							->on_value( 'ro.type', '=', Owner::TYPE_INDIVIDUAL )
							->on_column( 'ro.subject_id', '=', 'u.id' )
						)
						->left_join( "$organizations_table o", fn( JoinCriteria $join ) => $join
							->on_value( 'ro.type', '=', Owner::TYPE_ORGANIZATION )
							->on_column( 'ro.subject_id', '=', 'o.id' )
						)
						->where_group( fn( SelectionIntent $group ) => $group
							->where( 'ro.type', '=', Owner::TYPE_INDIVIDUAL )
							->where_null( 'u.id' )
						)
						->or_where_group( fn( SelectionIntent $group ) => $group
							->where( 'ro.type', '=', Owner::TYPE_ORGANIZATION )
							->where_null( 'o.id' )
						)
					, 'orphaned_owners' );

			$integrity = $db->get_row( $integrity_sql->build(), $integrity_sql->get_bindings() );

			$usage_sql = static::query()
				->select(
					'COUNT(*) as total',
					'COUNT( last_used_at ) as ever_used',
					'MAX( last_used_at ) AS most_recent_use',
					'MIN( last_used_at ) AS oldest_use'
				)->from( $service_accounts_table );
			$usage = $db->get_row( $usage_sql->build(), $usage_sql->get_bindings() );
			
			$report = [
				'summary' => [
					'users'                => (int) $counts['users_total'],
					'organizations'        => (int) $counts['organizations_total'],
					'service_accounts'     => (int) $counts['service_accounts_total'],
					'organization_members' => (int) $counts['organization_members_total'],
					'resource_owners'      => (int) $counts['resource_owners_total'],
				],

				'integrity' => [
					'orphaned_service_accounts' => (int) $integrity['orphaned_service_accounts'],
					'orphaned_members'          => (int) $integrity['orphaned_members'],
					'orphaned_owners'           => (int) $integrity['orphaned_owners'],
					'has_issues'                => $integrity['orphaned_service_accounts'] > 0 ||
						$integrity['orphaned_members'] > 0 ||
						$integrity['orphaned_owners'] > 0,
				],

				'usage' => [
					'service_accounts' => [
						'total'           => (int) $usage['total'],
						'ever_used'       => (int) $usage['ever_used'],
						'never_used'      => (int) $usage['total'] - (int) $usage['ever_used'],
						'most_recent_use' => $usage['most_recent_use'],
						'oldest_use'      => $usage['oldest_use'],
					],
				],
			];

			static::cache_set( $cache_key, $report, static::default_ttl() );
			return $report;

		} catch ( \Exception $e ) {
			throw new SecurityException(
				'accounts_summary_error',
				'Unable to build accounts summary report.',
				[
					'status' => 500,
					'error'  => $e->getMessage(),
				]
			);
		}
	}

	/*
	|-------------------
	| OWNERSHIP CONTEXT
	|-------------------
	*/

	/**
	 * Whether a role is the platform administrator role, which only exists outside organizations.
	 *
	 * @param Role $role
	 * @return bool
	 */
	public static function is_platform_role( Role $role ) : bool {
		return DefaultRoles::get( 'system_admin' )['slug'] === $role->get_slug();
	}

	/**
	 * The resource owner record of a person or an organization.
	 *
	 * @param OwnerSubjectInterface $subject A user or an organization.
	 * @return Owner|null Null when it has none yet (ensure_owner() makes one).
	 */
	public static function get_owner_for( OwnerSubjectInterface $subject ) : ?Owner {
		if ( ! $subject->get_id() ) {
			return null;
		}

		$sql = static::query()
			->select( 'id' )->from( TableName::RESOURCE_OWNERS->table() )
			->where( 'subject_id', '=', $subject->get_id() )
			->where( 'type', '=', $subject->get_type() )
			->limit( 1 );

		$id = (int) static::$DB->get_var( $sql->build(), $sql->get_bindings() );

		return $id ? Owner::get_by_id( $id ) : null;
	}

	/**
	 * The resource owner record of a person or an organization, made when missing.
	 *
	 * Every subject that someone acts for, or that hosts apps, has one: it
	 * is what apps, pricing and service accounts belong to. It is made the
	 * first time it is needed, named after the subject.
	 *
	 * @param OwnerSubjectInterface $subject A saved user or organization.
	 * @return Owner
	 * @throws SecurityException When it cannot be saved.
	 */
	public static function ensure_owner( OwnerSubjectInterface $subject ) : Owner {
		$owner = static::get_owner_for( $subject );

		if ( $owner ) {
			return $owner;
		}

		$owner = ( new Owner() )
			->set_name( $subject->get_display_name() )
			->set_subject_id( $subject->get_id() )
			->set_type( $subject->get_type() )
			->set_status( Owner::STATUS_ACTIVE );

		if ( ! $subject->get_id() || ! $owner->save() ) {
			throw new SecurityException( 'owner_save_error', sprintf( 'A resource owner record for %s could not be made.', $subject->get_display_name() ), [ 'status' => 500 ] );
		}

		return $owner;
	}

	/**
	 * A person's own resource owner record, made when their role lets them host apps.
	 *
	 * A user whose own role has hosted_apps.create (a resource owner) always
	 * has one, so they can own apps, made the first time it is needed (sign-in,
	 * signup or switching back to themselves). Anyone else gets theirs only
	 * when one already exists.
	 *
	 * @param User $user The user.
	 * @param Role $role Their own role.
	 * @return Owner|null
	 * @throws SecurityException When a needed record cannot be saved.
	 */
	public static function get_self_owner( User $user, Role $role ) : ?Owner {
		$owner = static::get_owner_for( $user );

		if ( $owner || ! $role->has_capability( 'hosted_apps.create' ) ) {
			return $owner;
		}

		return static::ensure_owner( $user );
	}

	/**
	 * The role a user has when acting for a subject, if they may act for it at all.
	 *
	 * A user may act:
	 * - for themselves, with their own role;
	 * - for an active organization they are a member of, with the role they
	 *   hold there. system_admin held inside an organization does not count
	 *   (it grants nothing there). A member's status is their account's, which
	 *   sign-in already requires to be active.
	 *
	 * @param User                  $user    The user.
	 * @param OwnerSubjectInterface $subject Themselves or an organization.
	 * @return Role|null Null when the user may not act for it.
	 */
	public static function get_context_role( User $user, OwnerSubjectInterface $subject ) : ?Role {
		if ( $subject instanceof User ) {
			return $subject->get_id() === $user->get_id() ? static::get_principal_role( $user ) : null;
		}

		if ( ! $subject instanceof Organization || Organization::STATUS_ACTIVE !== $subject->get_status() ) {
			return null;
		}

		$sql = static::query()
			->select( 'id' )->from( TableName::ORGANIZATION_MEMBERS->table() )
			->where( 'organization_id', '=', $subject->get_id() )
			->where( 'member_id', '=', $user->get_id() )
			->limit( 1 );

		if ( ! static::$DB->get_var( $sql->build(), $sql->get_bindings() ) ) {
			return null;
		}

		$role = static::get_principal_role( $user, $subject );

		return $role && ! static::is_platform_role( $role ) ? $role : null;
	}

	/**
	 * Everyone a user may act for: themselves first, then their organizations by name.
	 *
	 * @param User $user
	 * @return list<array{subject: OwnerSubjectInterface, role: Role}>
	 */
	public static function get_acting_contexts( User $user ) : array {
		$contexts = [];
		$own_role = static::get_context_role( $user, $user );

		if ( $own_role ) {
			$contexts[] = [ 'subject' => $user, 'role' => $own_role ];
		}

		$organizations = [];

		foreach ( static::get_user_organizations( $user ) ?? [] as $organization ) {
			$role = static::get_context_role( $user, $organization );

			if ( $role ) {
				$organizations[] = [ 'subject' => $organization, 'role' => $role ];
			}
		}

		usort( $organizations, static fn ( array $a, array $b ) : int => strcasecmp( $a['subject']->get_display_name(), $b['subject']->get_display_name() ) );

		return array_merge( $contexts, $organizations );
	}

	/*
	|-----------------------
	| ADMINISTRATOR LOCKOUT
	|-----------------------
	*/

	/**
	 * Refuse deleting a user when it would lock administrators out.
	 *
	 * Follows the two lockout rules (assert_admin_remains()):
	 * 1. Nobody deletes their own account.
	 * 2. The last active system administrator cannot be deleted.
	 *
	 * @param User                $user The user to delete, as stored.
	 * @param ActorInterface|null $by   Who deletes; null when no one is signed in (e.g. the CLI).
	 * @throws SecurityException When refused (status 409).
	 */
	public static function assert_can_delete_user( User $user, ?ActorInterface $by ) : void {
		if ( static::is_same_user( $user, $by ) ) {
			throw new SecurityException( 'self_lockout', 'You cannot delete your own account.', [ 'status' => 409 ] );
		}

		static::assert_admin_remains( $user, false );
	}

	/**
	 * Refuse changing a user's platform role or status when it would lock administrators out.
	 *
	 * Admin dashboard access needs both the system_admin platform role and
	 * an active status, so a change to either counts. The lockout rules:
	 * 1. Nobody changes their own platform role or status; another
	 *    administrator must. This also stops anyone raising their own access.
	 * 2. The last active system administrator keeps both.
	 *
	 * Call before the change is written, with the user as stored.
	 *
	 * @param User                $user   The user to change, as stored.
	 * @param ActorInterface|null $by     Who changes it; null when no one is signed in (e.g. the CLI).
	 * @param string              $role   The platform role slug the user will have.
	 * @param string              $status The status the user will have.
	 * @throws SecurityException When refused (status 409).
	 */
	public static function assert_can_change_user_access( User $user, ?ActorInterface $by, string $role, string $status ) : void {
		if ( ! $user->exists() ) {
			return;
		}

		$current_role = (string) static::get_principal_role( $user )?->get_slug();

		if ( static::is_same_user( $user, $by ) ) {
			if ( $role !== $current_role ) {
				throw new SecurityException( 'self_lockout', 'You cannot change your own role; another administrator must.', [ 'status' => 409 ] );
			}

			if ( $status !== $user->get_status() ) {
				throw new SecurityException( 'self_lockout', 'You cannot change your own status; another administrator must.', [ 'status' => 409 ] );
			}
		}

		$keeps_access = DefaultRoles::get( 'system_admin' )['slug'] === $role && User::STATUS_ACTIVE === $status;

		static::assert_admin_remains( $user, $keeps_access );
	}

	/**
	 * Active system administrators: platform admins (get_platform_admins()) who can sign in.
	 *
	 * @param int $excluding_user_id A user left out of the count, e.g. one about to lose access; 0 for none.
	 * @return int
	 */
	public static function count_active_platform_admins( int $excluding_user_id = 0 ) : int {
		$ids = [];

		foreach ( static::get_platform_admins() as $admin ) {
			if ( $admin->can_authenticate() && $admin->get_id() !== $excluding_user_id ) {
				$ids[ $admin->get_id() ] = true;
			}
		}

		return count( $ids );
	}

	/**
	 * Refuse a change that leaves no active system administrator.
	 *
	 * @param User $user         The user changed, as stored.
	 * @param bool $keeps_access Whether the user is still an active system administrator after the change.
	 * @throws SecurityException When refused (status 409).
	 */
	protected static function assert_admin_remains( User $user, bool $keeps_access ) : void {
		$has_access = $user->can_authenticate() && DefaultRoles::get( 'system_admin' )['slug'] === static::get_principal_role( $user )?->get_slug();

		if ( $has_access && ! $keeps_access && 0 === static::count_active_platform_admins( $user->get_id() ) ) {
			throw new SecurityException(
				'last_admin',
				sprintf( '%s is the only active system administrator. Make another user an active system administrator first.', $user->get_display_name() ?: $user->get_email() ),
				[ 'status' => 409 ]
			);
		}
	}

	/**
	 * Whether an actor is this user.
	 *
	 * @param User                $user
	 * @param ActorInterface|null $actor
	 * @return bool
	 */
	protected static function is_same_user( User $user, ?ActorInterface $actor ) : bool {
		return $actor instanceof User && $actor->get_id() === $user->get_id();
	}

	/*
	|-----------------------
	| PRIVATE HELPERS
	|-----------------------
	*/

	/**
	 * Delete user and all related data cascading cleanly through relational IDs.
	 * 
	 * @param User $user
	 * @return bool
	 * @throws SecurityException On super admin delete.
	 */
	public static function delete_user( User $user ) : bool {
		return static::$DB->transactional( function ( Database $db ) use ( $user ) {
			$users_table            = TableName::USERS->table();
			$role_assignment_table  = TableName::PRINCIPAL_ROLES->table();
			$resource_owners_table  = TableName::RESOURCE_OWNERS->table();
			$service_accounts_table = TableName::SERVICE_ACCOUNTS->table();
			$org_member_table       = TableName::ORGANIZATION_MEMBERS->table();

			$lock_sql = static::query()
				->select( 'id' )->from( $users_table )
				->where( 'id', '=', $user->get_id() )->lock_for_update();
			
			$lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
			if ( ! $lock ) {
				return false;
			}

			// Delete straight-linked user associations (Individual Roles & Org Memberships)
			$db->delete(
				$role_assignment_table,
				[
					'principal_type' => Owner::TYPE_INDIVIDUAL,
					'principal_id'   => $user->get_id()
				]
			);
			
			$db->delete(
				$org_member_table,
				[
					'member_id' => $user->get_id()
				]
			);

			// Extract the Resource Owner IDs linked to this user (Matches: ON ro.subject_id = u.id)
			$owner_ids_sql = static::query()
				->select( 'id' )->from( $resource_owners_table )
				->where( 'subject_id', '=', $user->get_id() )
				->where( 'type', '=', Owner::TYPE_INDIVIDUAL );
			
			$owner_ids = $db->get_col( $owner_ids_sql->build(), $owner_ids_sql->get_bindings() );

			// Process the deep dependent tables ONLY if resource owners exist
			if ( ! empty( $owner_ids ) ) {
				// Find all Service Account IDs pointing to these Resource Owners (Matches: ON sa.owner_id = ro.id)
				$sa_ids_sql = static::query()
					->select( 'id' )->from( $service_accounts_table )
					->where_in( 'owner_id', $owner_ids );
				
				$sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

				if ( ! empty( $sa_ids ) ) {
					// Delete roles assigned to these specific service accounts.
					$sa_roles_delete = static::query()
						->delete( $role_assignment_table )
						->where( 'principal_type', '=', 'service_account' )
						->where_in( 'principal_id', $sa_ids );
					
					$db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

					// Delete the actual Service Accounts.
					$sa_purge_delete = static::query()
						->delete( $service_accounts_table )
						->where_in( 'id', $sa_ids );

					$db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
				}
			}

			// Delete the Resource Ownership records now that dependencies are gone
			$db->delete(
				$resource_owners_table,
				[
					'type'       => Owner::TYPE_INDIVIDUAL,
					'subject_id' => $user->get_id()
				]
			);

			// Finally, safely delete primary user root identity.
			$db->delete( $users_table, [ 'id' => $user->get_id() ] );

			return true;
		} );
	}

	/**
	 * Delete an organization and all cascading dependencies safely.
	 * 
	 * @param Organization $organization
	 * @return bool
	 */
	public static function delete_organization( Organization $organization ) : bool {
		return static::$DB->transactional( function ( Database $db ) use ( $organization ) {
			$org_table              = TableName::ORGANIZATIONS->table();
			$org_member_table       = TableName::ORGANIZATION_MEMBERS->table();
			$role_assignment_table  = TableName::PRINCIPAL_ROLES->table();
			$resource_owners_table  = TableName::RESOURCE_OWNERS->table();
			$service_accounts_table = TableName::SERVICE_ACCOUNTS->table();

			$org_id = $organization->get_id();

			// Lock the parent organization record for update safety.
			$lock_sql = static::query()
				->select( 'id' )->from( $org_table )
				->where( 'id', '=', $org_id )->lock_for_update();
			
			$lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
			if ( ! $lock ) {
				return false;
			}

			// Delete direct simple mappings (Memberships & Specific Org Role Assignments).
			$db->delete(
				$org_member_table,
				[
					'organization_id' => $org_id
				]
			);

			$db->delete(
				$role_assignment_table,
				[
					'owner_subject_type' => Owner::TYPE_ORGANIZATION,
					'owner_subject_id'   => $org_id
				]
			);

			// Extract the Resource Owner IDs owned by this organization.
			$owner_ids_sql = static::query()
				->select( 'id' )->from( $resource_owners_table )
				->where( 'subject_id', '=', $org_id )
				->where( 'type', '=', Owner::TYPE_ORGANIZATION );
			
			$owner_ids = $db->get_col( $owner_ids_sql->build(), $owner_ids_sql->get_bindings() );

			// Trace and flush nested service accounts and their permissions.
			if ( ! empty( $owner_ids ) ) {
				// Find all Service Accounts pointing to these Resource Owners.
				$sa_ids_sql = static::query()
					->select( 'id' )->from( $service_accounts_table )
					->where_in( 'owner_id', $owner_ids );
				
				$sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

				if ( ! empty( $sa_ids ) ) {
					// Delete roles belonging to these service accounts via fluent DeleteIntent.
					$sa_roles_delete = static::query()
						->delete( $role_assignment_table )
						->where( 'principal_type', '=', 'service_account' )
						->where_in( 'principal_id', $sa_ids );
					
					$db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

					// Delete the actual Service Accounts via fluent DeleteIntent.
					$sa_purge_delete = static::query()
						->delete( $service_accounts_table )
						->where_in( 'id', $sa_ids );

					$db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
				}
			}

			// Delete the Resource Ownership base keys now that downstream tracks are wiped.
			$db->delete(
				$resource_owners_table,
				[
					'type'       => Owner::TYPE_ORGANIZATION,
					'subject_id' => $org_id
				]
			);

			$db->delete( $org_table, [ 'id' => $org_id ] );

			return true;
		} );
	}

	/**
	 * Delete a service account and all its assigned roles cleanly.
	 * 
	 * @param ServiceAccount $service_account
	 * @return bool
	 */
	public static function delete_service_account( ServiceAccount $service_account ) : bool {
		return static::$DB->transactional( function ( Database $db ) use ( $service_account ) {
			$service_accounts_table = TableName::SERVICE_ACCOUNTS->table();
			$role_assignment_table  = TableName::PRINCIPAL_ROLES->table();

			$sa_id = $service_account->get_id();

			$lock_sql = static::query()
				->select( 'id' )->from( $service_accounts_table )
				->where( 'id', '=', $sa_id )->lock_for_update();
			
			$lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
			if ( ! $lock ) {
				return false;
			}

			// Delete roles assigned to this specific service account via fluent DeleteIntent
			$sa_roles_delete = static::query()
				->delete( $role_assignment_table )
				->where( 'principal_type', '=', 'service_account' )
				->where( 'principal_id', '=', $sa_id );
			
			$db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

			// Finally, delete the parent service account record safely
			$db->delete( $service_accounts_table, [ 'id' => $sa_id ] );

			return true;
		} );
	}

	/**
	 * Delete a resource owner and all cascading service accounts and roles.
	 * 
	 * @param Owner $owner
	 * @return bool
	 */
	public static function delete_resource_owner( Owner $owner ) : bool {
		$owner_id = $owner->get_id();
		return static::$DB->transactional( function ( Database $db ) use ( $owner_id ) {
			$resource_owners_table  = TableName::RESOURCE_OWNERS->table();
			$service_accounts_table = TableName::SERVICE_ACCOUNTS->table();
			$role_assignment_table  = TableName::PRINCIPAL_ROLES->table();

			$lock_sql = static::query()
				->select( 'id' )->from( $resource_owners_table )
				->where( 'id', '=', $owner_id )->lock_for_update();
			
			$lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
			if ( ! $lock ) {
				return false;
			}

			// Extract Service Account IDs linked to this owner
			$sa_ids_sql = static::query()
				->select( 'id' )->from( $service_accounts_table )
				->where( 'owner_id', '=', $owner_id );
			
			$sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

			// If Service Accounts exist, wipe their roles and the accounts themselves
			if ( ! empty( $sa_ids ) ) {
				// Delete roles assigned to these specific service accounts
				$sa_roles_delete = static::query()
					->delete( $role_assignment_table )
					->where( 'principal_type', '=', 'service_account' )
					->where_in( 'principal_id', $sa_ids );
				
				$db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

				// Delete the actual Service Accounts
				$sa_purge_delete = static::query()
					->delete( $service_accounts_table )
					->where_in( 'id', $sa_ids );

				$db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
			}

			//  Finally, delete the Resource Ownership record
			$db->delete( $resource_owners_table, [ 'id' => $owner_id ] );

			return true;
		} );
	}

	/**
	 * Turn database rows of one entity type into entity objects.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param string                           $type An entity type, e.g. Owner::TYPE_INDIVIDUAL.
	 * @return list<object> Empty when the type has no class.
	 */
	protected static function hydrate_rows( array $rows, string $type ) : array {
		$class_name = static::get_entity_classname( $type );

		if ( ! $class_name || ! class_exists( $class_name ) || ! method_exists( $class_name, 'from_array' ) ) {
			return [];
		}

		return array_values( array_map( static fn ( $row ) => $class_name::from_array( (array) $row ), $rows ) );
	}

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
	 * @return array{items: mixed, pagination: array{total: int, page: int, limit: int, total_pages: int}}
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
	 * Get entity table
	 * 
	 * @param string $type
	 * @return string|null
	 */
	protected static function get_entity_table( string $type ) : ?string {
		return match ( $type ) {
			Owner::TYPE_ORGANIZATION       => TableName::ORGANIZATIONS->table(),
			Owner::TYPE_INDIVIDUAL, 'user' => TableName::USERS->table(),
			default                        => null,
		};
	}

	/**
	 * Get all human platform system administrators.
	 *
	 * A user is a platform admin when they hold a self-scoped role assignment
	 * (owner_subject_type = individual, owner_subject_id = principal_id) whose
	 * role slug is 'system_admin'.
	 *
	 * @return User[]
	 */
	public static function get_platform_admins() : array {
		$db  = static::$DB;
		$sql = static::query()
			->select( 'u.*' )->from( TableName::USERS->table() . ' u' )
			->left_join( TableName::PRINCIPAL_ROLES->table() . ' ra', 'ra.principal_id', '=', 'u.id' )
			->left_join( TableName::ROLES->table() . ' r', 'ra.role_id', '=', 'r.id' )
			->where( 'ra.principal_type', '=', Owner::TYPE_INDIVIDUAL )
			->where( 'ra.owner_subject_type', '=', Owner::TYPE_INDIVIDUAL )
			->where_column( 'ra.owner_subject_id', '=', 'ra.principal_id' )
			->where( 'r.slug', '=', DefaultRoles::get( 'system_admin' )['slug'] );

		$rows = $db->get_results( $sql->build(), $sql->get_bindings() );

		return array_map( [User::class, 'from_array'], $rows );
	}

	/**
	 * Get all human administrators of a specific organization.
	 *
	 * @param Organization $organization
	 * @return User[]
	 */
	public static function get_organization_admins( Organization $organization ) : array {
		$db  = static::$DB;
		$sql = static::query()
			->select( 'u.*' )->from( TableName::USERS->table() . ' u' )
			->left_join( TableName::PRINCIPAL_ROLES->table() . ' ra', 'ra.principal_id', '=', 'u.id' )
			->left_join( TableName::ROLES->table() . ' r', 'ra.role_id', '=', 'r.id' )
			->where( 'ra.principal_type', '=', Owner::TYPE_INDIVIDUAL )
			->where( 'ra.owner_subject_type', '=', Owner::TYPE_ORGANIZATION )
			->where( 'ra.owner_subject_id', '=', $organization->get_id() )
			->where( 'r.slug', '=', DefaultRoles::get( 'organization_admin' )['slug'] );

		$rows = $db->get_results( $sql->build(), $sql->get_bindings() );

		return array_map( [User::class, 'from_array'], $rows );
	}
}