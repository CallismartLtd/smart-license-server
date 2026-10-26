<?php
/**
 * Organization page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Organizations\OrganizationInvitations;
use SmartLicenseServer\Security\Organizations\OrganizationService;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Organization: the organization the user acts for, its members and invitations.
 *
 * An owner page: shown only while acting for an organization whose role
 * gives security.organization.view. Each section shows only with its
 * capability in that role:
 *   members' roles          security.organization.update_members
 *   removing members        security.organization.remove_members
 *   inviting                security.user.invite
 *   renaming                security.organization.update
 *   deleting                security.organization.delete
 */
final class OrganizationPage implements ClientPageInterface {

	/**
	 * @param Guard                   $guard         The signed-in principal.
	 * @param URLManager              $urlmanager    URL API.
	 * @param OrganizationService     $organizations Organization rules.
	 * @param OrganizationInvitations $invitations   Invitations.
	 */
	public function __construct(
		private Guard $guard,
		private URLManager $urlmanager,
		private OrganizationService $organizations,
		private OrganizationInvitations $invitations
	) {}

	public function get_menu_key() : string {
		return 'organization';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'Organization',
			'slug'       => 'organization',
			'handler'    => $this,
			'icon'       => 'ti ti-users-group',
			'visibility' => fn () : bool => null !== $this->acting_for() && (bool) $this->guard->get_principal()?->can( 'security.organization.view' ),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'organization' ];
	}

	/**
	 * The page.
	 *
	 * View data (template client.organization):
	 *   array{name: string, slug: string, created: string} $organization
	 *   list<array{id: int, name: string, email: string, role: string, role_label: string, joined: string, you: bool}> $members
	 *   array<string, string> $roles Assignable roles, slug => label.
	 *   array{update: bool, delete: bool, update_members: bool, remove_members: bool, invite: bool} $can
	 *   bool $invitations_available Whether invitations can be kept (a persistent cache).
	 *   string $invitations_unavailable Why not, when they cannot.
	 *   list<array{id: string, email: string, role: string, invited: string, expires: string}> $invitations
	 *   array<string, string> $actions Action URLs: rename, delete, member_role, member_remove,
	 *       invite, invitation_resend, invitation_revoke.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function organization( Request $request ) : View {
		$organization = $this->acting_for();
		$principal    = $this->guard->get_principal();
		$user         = $principal?->get_actor();
		$date         = static fn ( ?\DateTimeImmutable $d ) : string => $d ? $d->format( \smliser_datetime_format() ) : '';
		$roles        = $this->organizations->get_assignable_roles();
		$can          = [
			'update'         => (bool) $principal?->can( 'security.organization.update' ),
			'delete'         => (bool) $principal?->can( 'security.organization.delete' ),
			'update_members' => (bool) $principal?->can( 'security.organization.update_members' ),
			'remove_members' => (bool) $principal?->can( 'security.organization.remove_members' ),
			'invite'         => (bool) $principal?->can( 'security.user.invite' ),
		];

		$members = array_map(
			static fn ( array $member ) : array => [
				'id'         => (int) $member['user']->get_id(),
				'name'       => $member['user']->get_display_name(),
				'email'      => $member['user']->get_email(),
				'role'       => (string) $member['role']?->get_slug(),
				'role_label' => (string) ( $member['role']?->get_label() ?? 'No role' ),
				'joined'     => $date( $member['joined'] ),
				'you'        => $user instanceof User && $member['user']->get_id() === $user->get_id(),
			],
			$organization ? $this->organizations->get_members( $organization ) : []
		);

		$invitations = $organization && $can['invite'] && $this->invitations->is_available()
			? array_map(
				static fn ( array $invitation ) : array => [
					'id'      => $invitation['id'],
					'email'   => $invitation['email'],
					'role'    => $roles[ $invitation['role'] ] ?? (string) ( Role::get_by_slug( $invitation['role'] )?->get_label() ?? $invitation['role'] ),
					'invited' => $date( new \DateTimeImmutable( '@' . $invitation['created_at'] ) ),
					'expires' => $date( new \DateTimeImmutable( '@' . $invitation['expires_at'] ) ),
				],
				$this->invitations->get_pending( $organization )
			)
			: [];

		$url = fn ( string $path ) : string => $this->urlmanager->client_dashboard_url( 'json/' . $path )->url();

		return View::make(
			'client.organization',
			[
				'organization'            => [
					'name'    => (string) $organization?->get_display_name(),
					'slug'    => (string) $organization?->get_slug(),
					'created' => $date( $organization?->get_created_at() ),
				],
				'members'                 => $members,
				'roles'                   => $roles,
				'can'                     => $can,
				'invitations_available'   => $this->invitations->is_available(),
				'invitations_unavailable' => OrganizationInvitations::NOT_PERSISTENT_MESSAGE,
				'invitations'             => $invitations,
				'actions'                 => [
					'rename'            => $url( 'organization/rename' ),
					'delete'            => $url( 'organization/delete' ),
					'member_role'       => $url( 'organization/members/role' ),
					'member_remove'     => $url( 'organization/members/remove' ),
					'invite'            => $url( 'organization/invitations/send' ),
					'invitation_resend' => $url( 'organization/invitations/resend' ),
					'invitation_revoke' => $url( 'organization/invitations/revoke' ),
				],
			]
		)->set_title( (string) $organization?->get_display_name() ?: 'Organization' );
	}

	/**
	 * The organization the user acts for.
	 *
	 * @return Organization|null
	 */
	private function acting_for() : ?Organization {
		$owner = $this->guard->get_principal()?->get_owner();

		return $owner && $owner->is_organization() ? Organization::get_by_id( $owner->get_subject_id() ) : null;
	}
}