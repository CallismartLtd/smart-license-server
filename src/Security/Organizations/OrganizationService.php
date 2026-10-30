<?php
/**
 * Organization service class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Organizations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Organizations;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Creates and runs organizations: the rules, whoever calls them.
 *
 * An organization is managed from inside it, by its members, within what
 * their role there allows (callers check the capabilities). The rules here
 * hold whatever the caller:
 *
 * - Creating: a user whose own role has security.organization.create
 *   creates one, becomes its Organization Administrator, and it gets its
 *   resource owner record at once.
 * - Roles: members hold one of DefaultRoles::ORGANIZATION_ROLES.
 * - Nobody changes their own role in an organization; another
 *   Organization Administrator must.
 * - An organization always keeps an active Organization Administrator:
 *   the last one cannot be demoted, removed, or leave.
 * - Leaving: anyone else can leave whenever they like.
 * - Deleting: refused while the organization owns any app (trashed ones
 *   included); otherwise its members, roles, owner record, service
 *   accounts and pending invitations go with it.
 *
 * Failures are Exceptions with a "status" for the caller to answer with.
 */
class OrganizationService {

	/**
	 * Longest organization name.
	 *
	 * @var int
	 */
	public const MAX_NAME_LENGTH = 100;

	/**
	 * @param OrganizationInvitations $invitations Revoked when an organization is deleted.
	 */
	public function __construct( protected OrganizationInvitations $invitations ) {}

	/**
	 * Create an organization run by its creator.
	 *
	 * @param User   $creator The user creating it.
	 * @param string $name    Its name.
	 * @return Organization|Exception
	 */
	public function create( User $creator, string $name ) : Organization|Exception {
		if ( ! ContextServiceProvider::get_principal_role( $creator )?->has_capability( 'security.organization.create' ) ) {
			return new Exception( 'organization_create_forbidden', 'Your account cannot create organizations.', [ 'status' => 403 ] );
		}

		$name    = trim( $name );
		$problem = $this->check_name( $name );

		if ( $problem ) {
			return $problem;
		}

		$role = Role::get_by_slug( DefaultRoles::get( 'organization_admin' )['slug'] );

		if ( ! $role ) {
			return new Exception( 'role_missing', 'The Organization Administrator role is not installed. Ask the site administrator to install the default roles.', [ 'status' => 500 ] );
		}

		$organization = ( new Organization() )
			->set_display_name( $name )
			->set_slug( $this->unique_slug( $name ) )
			->set_status( Organization::STATUS_ACTIVE );

		try {
			if ( true !== $organization->save() ) {
				throw new \RuntimeException( 'The organization could not be saved.' );
			}

			$this->save_member( $organization, $creator, $role );
			ContextServiceProvider::ensure_owner( $organization );
		} catch ( \Throwable $e ) {
			if ( $organization->exists() ) {
				try {
					ContextServiceProvider::delete_entity( $organization );
				} catch ( \Throwable ) {
					// Leave it; the admin dashboard lists it.
				}
			}

			return new Exception( 'organization_not_created', 'The organization could not be created. Please try again.', [ 'status' => 500 ] );
		}

		return $organization;
	}

	/**
	 * Rename an organization (and its resource owner record).
	 *
	 * The slug is kept, so links and references stay valid.
	 *
	 * @param Organization $organization
	 * @param string       $name
	 * @return Organization|Exception
	 */
	public function rename( Organization $organization, string $name ) : Organization|Exception {
		$name    = trim( $name );
		$problem = $this->check_name( $name );

		if ( $problem ) {
			return $problem;
		}

		try {
			if ( true !== $organization->set_display_name( $name )->save() ) {
				throw new \RuntimeException( 'The organization could not be saved.' );
			}

			$owner = ContextServiceProvider::get_owner_for( $organization );

			if ( $owner && $owner->get_name() !== $name ) {
				$owner->set_name( $name )->save();
			}
		} catch ( \Throwable ) {
			return new Exception( 'organization_not_saved', 'The organization could not be renamed. Please try again.', [ 'status' => 500 ] );
		}

		return $organization;
	}

	/**
	 * Delete an organization that owns no apps.
	 *
	 * @param Organization $organization
	 * @return true|Exception
	 */
	public function delete( Organization $organization ) : true|Exception {
		$owner = ContextServiceProvider::get_owner_for( $organization );
		$apps  = $owner ? HostedApplicationService::count_apps_for_owner( $owner->get_id() ) : 0;

		if ( $apps > 0 ) {
			return new Exception(
				'organization_owns_apps',
				sprintf( '%s owns %d app(s), including any in the trash. Delete them, or move them to another owner, before deleting the organization.', $organization->get_display_name(), $apps ),
				[ 'status' => 409 ]
			);
		}

		try {
			$this->invitations->revoke_all( $organization );
			ContextServiceProvider::delete_entity( $organization );
		} catch ( \Throwable ) {
			return new Exception( 'organization_not_deleted', 'The organization could not be deleted. Please try again.', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Add a user to an organization with a role, or give a member a new role.
	 *
	 * Used by invitations; role changes by members go through change_role().
	 *
	 * @param Organization $organization
	 * @param User         $user
	 * @param string       $role_slug One of DefaultRoles::ORGANIZATION_ROLES.
	 * @return true|Exception
	 */
	public function add_member( Organization $organization, User $user, string $role_slug ) : true|Exception {
		$role = $this->resolve_role( $role_slug );

		if ( $role instanceof Exception ) {
			return $role;
		}

		try {
			$this->save_member( $organization, $user, $role );
		} catch ( \Throwable ) {
			return new Exception( 'member_not_saved', sprintf( '%s could not be added. Please try again.', $user->get_display_name() ), [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Give a member another role.
	 *
	 * @param Organization $organization
	 * @param User         $member    The member.
	 * @param string       $role_slug One of DefaultRoles::ORGANIZATION_ROLES.
	 * @param User         $by        Who makes the change.
	 * @return true|Exception
	 */
	public function change_role( Organization $organization, User $member, string $role_slug, User $by ) : true|Exception {
		if ( $member->get_id() === $by->get_id() ) {
			return new Exception( 'self_role_change', 'You cannot change your own role; another Organization Administrator must.', [ 'status' => 409 ] );
		}

		if ( ! $this->is_member( $organization, $member ) ) {
			return new Exception( 'not_a_member', sprintf( '%s is not a member of %s.', $member->get_display_name(), $organization->get_display_name() ), [ 'status' => 404 ] );
		}

		$role = $this->resolve_role( $role_slug );

		if ( $role instanceof Exception ) {
			return $role;
		}

		if ( ! $this->is_admin_role( $role ) ) {
			$problem = $this->assert_admin_remains( $organization, $member );

			if ( $problem ) {
				return $problem;
			}
		}

		try {
			$this->save_member( $organization, $member, $role );
		} catch ( \Throwable ) {
			return new Exception( 'member_not_saved', 'The role could not be changed. Please try again.', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Remove a member, or (when $member is $by) leave the organization.
	 *
	 * @param Organization $organization
	 * @param User         $member The member.
	 * @param User         $by     Who removes them.
	 * @return true|Exception
	 */
	public function remove_member( Organization $organization, User $member, User $by ) : true|Exception {
		$leaving = $member->get_id() === $by->get_id();

		if ( ! $this->is_member( $organization, $member ) ) {
			return new Exception( 'not_a_member', sprintf( '%s is not a member of %s.', $leaving ? 'You are' : $member->get_display_name() . ' is', $organization->get_display_name() ), [ 'status' => 404 ] );
		}

		$problem = $this->assert_admin_remains( $organization, $member, $leaving );

		if ( $problem ) {
			return $problem;
		}

		$record = ContextServiceProvider::get_organization_members( $organization )->filter(
			static fn ( OrganizationMember $m ) : bool => $m->get_member_id() === $member->get_id()
		)->first();

		try {
			// The organization's own member list, so is_member() sees the record.
			$organization->get_members()->add( $record );
			ContextServiceProvider::delete_organization_member( $record, $organization );
		} catch ( \Throwable ) {
			return new Exception( 'member_not_removed', $leaving ? 'You could not leave the organization. Please try again.' : 'The member could not be removed. Please try again.', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * The members of an organization with their roles, administrators first, then by name.
	 *
	 * @param Organization $organization
	 * @return list<array{user: User, role: Role|null, joined: \DateTimeImmutable|null}>
	 */
	public function get_members( Organization $organization ) : array {
		$members = [];

		foreach ( ContextServiceProvider::get_organization_members( $organization )->all() as $member ) {
			$user = $member->get_user();

			if ( $user ) {
				$members[] = [ 'user' => $user, 'role' => $member->get_role(), 'joined' => $member->get_created_at() ];
			}
		}

		$rank = array_flip( DefaultRoles::ORGANIZATION_ROLES );

		usort(
			$members,
			static fn ( array $a, array $b ) : int => ( ( $rank[ (string) $a['role']?->get_slug() ] ?? 99 ) <=> ( $rank[ (string) $b['role']?->get_slug() ] ?? 99 ) )
				?: strcasecmp( $a['user']->get_display_name(), $b['user']->get_display_name() )
		);

		return $members;
	}

	/**
	 * The roles a member can be given, slug => label, most powerful first.
	 *
	 * @return array<string, string> Only roles that are installed.
	 */
	public function get_assignable_roles() : array {
		$roles = [];

		foreach ( DefaultRoles::ORGANIZATION_ROLES as $slug ) {
			$role = Role::get_by_slug( $slug );

			if ( $role ) {
				$roles[ $slug ] = $role->get_label();
			}
		}

		return $roles;
	}

	/**
	 * Whether a user is a member of an organization.
	 *
	 * @param Organization $organization
	 * @param User         $user
	 * @return bool
	 */
	public function is_member( Organization $organization, User $user ) : bool {
		return null !== ContextServiceProvider::get_organization_members( $organization )->filter(
			static fn ( OrganizationMember $m ) : bool => $m->get_member_id() === $user->get_id()
		)->first();
	}

	/*
	|-----------------
	| PRIVATE HELPERS
	|-----------------
	*/

	/**
	 * Refuse a change that leaves the organization without an active Organization Administrator.
	 *
	 * @param Organization $organization
	 * @param User         $member  The member losing the role, or leaving.
	 * @param bool         $leaving Whether the member is leaving on their own.
	 * @return Exception|null
	 */
	protected function assert_admin_remains( Organization $organization, User $member, bool $leaving = false ) : ?Exception {
		$admins = array_filter(
			ContextServiceProvider::get_organization_admins( $organization ),
			static fn ( User $admin ) : bool => $admin->can_authenticate()
		);
		$ids    = array_map( static fn ( User $admin ) : int => (int) $admin->get_id(), $admins );

		if ( ! in_array( (int) $member->get_id(), $ids, true ) || count( array_unique( $ids ) ) > 1 ) {
			return null;
		}

		return new Exception(
			'last_organization_admin',
			$leaving
				? sprintf( 'You are the only Organization Administrator of %s. Make another member an Organization Administrator before you leave.', $organization->get_display_name() )
				: sprintf( '%s is the only Organization Administrator of %s. Make another member an Organization Administrator first.', $member->get_display_name(), $organization->get_display_name() ),
			[ 'status' => 409 ]
		);
	}

	/**
	 * Save a membership with its role.
	 *
	 * @param Organization $organization
	 * @param User         $user
	 * @param Role         $role
	 * @return void
	 * @throws \Throwable When it cannot be saved.
	 */
	protected function save_member( Organization $organization, User $user, Role $role ) : void {
		$member = OrganizationMember::from_array( [ 'id' => 0, 'member_id' => (int) $user->get_id(), 'status' => User::STATUS_ACTIVE ] );
		$member->set_role( $role );

		$organization->get_members()->add( $member );

		ContextServiceProvider::save_organization_member( $member, $organization, $role );
	}

	/**
	 * Find an organization role.
	 *
	 * @param string $slug
	 * @return Role|Exception
	 */
	protected function resolve_role( string $slug ) : Role|Exception {
		$role = in_array( $slug, DefaultRoles::ORGANIZATION_ROLES, true ) ? Role::get_by_slug( $slug ) : null;

		return $role ?? new Exception(
			'invalid_role',
			sprintf( 'Choose one of these roles: %s.', implode( ', ', $this->get_assignable_roles() ) ),
			[ 'status' => 400, 'field_id' => 'role' ]
		);
	}

	/**
	 * Whether a role runs the organization.
	 *
	 * @param Role $role
	 * @return bool
	 */
	protected function is_admin_role( Role $role ) : bool {
		return DefaultRoles::get( 'organization_admin' )['slug'] === $role->get_slug();
	}

	/**
	 * Check an organization name.
	 *
	 * @param string $name
	 * @return Exception|null
	 */
	protected function check_name( string $name ) : ?Exception {
		if ( '' === $name ) {
			return new Exception( 'missing_name', 'Enter the organization\'s name.', [ 'status' => 400, 'field_id' => 'name' ] );
		}

		if ( mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			return new Exception( 'name_too_long', sprintf( 'The name can be at most %d characters long.', self::MAX_NAME_LENGTH ), [ 'status' => 400, 'field_id' => 'name' ] );
		}

		return null;
	}

	/**
	 * A slug for a new organization, made unique with a number when taken.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function unique_slug( string $name ) : string {
		$base = trim( (string) preg_replace( '/[^a-z0-9]+/', '_', strtolower( $name ) ), '_' ) ?: 'organization';
		$slug = $base;

		for ( $n = 2; Organization::get_by_slug( $slug ); $n++ ) {
			$slug = sprintf( '%s_%d', $base, $n );
		}

		return $slug;
	}
}
