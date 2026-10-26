<?php
/**
 * Organizations page class file.
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
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Organizations\OrganizationInvitations;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Organizations: the organizations the user belongs to (a personal page).
 *
 * Lists each with the user's role there, to switch to it or leave it; lets
 * a user whose own role has security.organization.create create one; and,
 * opened from an invitation email (?invitation=<token>), shows the
 * invitation to accept or decline.
 */
final class OrganizationsPage implements ClientPageInterface {
	use PersonalPageTrait;

	/**
	 * @param Guard                   $guard       The signed-in principal.
	 * @param URLManager              $urlmanager  URL API.
	 * @param OrganizationInvitations $invitations Invitations.
	 */
	public function __construct(
		Guard $guard,
		URLManager $urlmanager,
		private OrganizationInvitations $invitations
	) {
		$this->guard      = $guard;
		$this->urlmanager = $urlmanager;
	}

	public function get_menu_key() : string {
		return 'organizations';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'Organizations',
			'slug'       => 'organizations',
			'handler'    => $this,
			'icon'       => 'ti ti-building-community',
			'visibility' => fn () : bool => $this->is_visible(),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'organizations' ];
	}

	/**
	 * The page.
	 *
	 * View data (template client.organizations):
	 *   list<array{id: int, name: string, role: string, status: string, current: bool,
	 *       can_switch: bool, context: string}> $organizations
	 *   bool $can_create Whether the user's own role can create organizations.
	 *   array{token: string, organization: string, role: string, inviter: string, email: string,
	 *       expires: string, for_you: bool}|null $invitation The invitation in ?invitation=,
	 *       or null when there is none; for_you is false when it is for another address.
	 *   bool $invitation_missing Whether ?invitation= was given but is expired, revoked or unknown.
	 *   array{create: string, leave: string, switch: string, accept: string, decline: string} $actions
	 *
	 * @param Request $request
	 * @return View
	 */
	public function organizations( Request $request ) : View {
		$user    = $this->current_user();
		$current = $this->guard->get_principal()?->get_owner();
		$rows    = [];

		foreach ( $user ? ( ContextServiceProvider::get_user_organizations( $user ) ?? [] ) : [] as $organization ) {
			$role   = ContextServiceProvider::get_principal_role( $user, $organization );
			$usable = null !== ContextServiceProvider::get_context_role( $user, $organization );
			$rows[] = [
				'id'         => $organization->get_id(),
				'name'       => $organization->get_display_name(),
				'role'       => ! $role ? 'No role' : ( ContextServiceProvider::is_platform_role( $role )
					? sprintf( '%s (a platform role; it has no effect in an organization)', $role->get_label() )
					: $role->get_label() ),
				'status'     => ucfirst( $organization->get_status() ),
				'current'    => $current && $current->is_organization() && $current->get_subject_id() === $organization->get_id(),
				'can_switch' => $usable,
				'context'    => sprintf( '%s:%d', $organization->get_type(), $organization->get_id() ),
			];
		}

		usort( $rows, static fn ( array $a, array $b ) : int => strcasecmp( $a['name'], $b['name'] ) );

		$token = trim( (string) $request->get( 'invitation', '' ) );

		return View::make(
			'client.organizations',
			[
				'organizations'      => $rows,
				'can_create'         => (bool) ( $user ? ContextServiceProvider::get_principal_role( $user )?->has_capability( 'security.organization.create' ) : false ),
				'invitation'         => '' !== $token && $user ? $this->invitation( $token, $user ) : null,
				'invitation_missing' => '' !== $token && $user && null === $this->invitation( $token, $user ),
				'actions'            => [
					'create'  => $this->urlmanager->client_dashboard_url( 'json/organizations/create' )->url(),
					'leave'   => $this->urlmanager->client_dashboard_url( 'json/organizations/leave' )->url(),
					'switch'  => $this->urlmanager->client_dashboard_url( 'json/context' )->url(),
					'accept'  => $this->urlmanager->client_dashboard_url( 'json/invitations/accept' )->url(),
					'decline' => $this->urlmanager->client_dashboard_url( 'json/invitations/decline' )->url(),
				],
			]
		)->set_header( 'Cache-Control', 'no-store' )
			->set_header( 'Referrer-Policy', 'no-referrer' );
	}

	/**
	 * The invitation in a token, for the page.
	 *
	 * @param string $token
	 * @param User   $user
	 * @return array{token: string, organization: string, role: string, inviter: string, email: string, expires: string, for_you: bool}|null
	 */
	private function invitation( string $token, User $user ) : ?array {
		$invitation   = $this->invitations->find( $token );
		$organization = $invitation ? Organization::get_by_id( (int) $invitation['organization_id'] ) : null;

		if ( ! $invitation || ! $organization ) {
			return null;
		}

		return [
			'token'        => $token,
			'organization' => $organization->get_display_name(),
			'role'         => (string) ( Role::get_by_slug( $invitation['role'] )?->get_label() ?? $invitation['role'] ),
			'inviter'      => (string) ( User::get_by_id( (int) $invitation['invited_by'] )?->get_display_name() ?? 'A member' ),
			'email'        => $invitation['email'],
			'expires'      => $this->format_date( ( new \DateTimeImmutable( '@' . $invitation['expires_at'] ) ) ),
			'for_you'      => strtolower( $user->get_email() ) === $invitation['email'],
		];
	}
}