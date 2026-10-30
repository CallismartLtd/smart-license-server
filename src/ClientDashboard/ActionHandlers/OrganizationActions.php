<?php
/**
 * Client organization actions class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Organizations\OrganizationInvitations;
use SmartLicenseServer\Security\Organizations\OrganizationService;
use SmartLicenseServer\Security\OwnerSubjects\Organization;

/**
 * The client dashboard's organization actions (<client dashboard prefix>/json/…).
 *
 * The user's own:
 *   POST organizations/create     Create one ("name"); they run it and now act for it.
 *   POST organizations/leave      Leave one ("organization_id").
 *   POST invitations/accept       Join through an invitation ("token"); they now act for it.
 *   POST invitations/decline      Turn an invitation down ("token").
 *
 * The organization they act for (never one named in the request):
 *   POST organization/rename                security.organization.update          ("name")
 *   POST organization/delete                security.organization.delete
 *   POST organization/members/role          security.organization.update_members  ("user_id", "role")
 *   POST organization/members/remove        security.organization.remove_members  ("user_id")
 *   POST organization/invitations/send      security.user.invite                  ("email", "role")
 *   POST organization/invitations/resend    security.user.invite                  ("id")
 *   POST organization/invitations/revoke    security.user.invite                  ("id")
 *
 * The rules are OrganizationService's and OrganizationInvitations'. The
 * answer is { success, message, redirect?, field_id? } for page-forms.js.
 */
final class OrganizationActions {

	/**
	 * @param Guard                   $guard         The signed-in principal.
	 * @param OrganizationService     $organizations Organization rules.
	 * @param OrganizationInvitations $invitations   Invitations.
	 * @param WebIdentityProvider     $identity      Switches who the user acts for.
	 * @param UserAccounts            $accounts      Verifies an address on accepting.
	 * @param URLManager              $urlmanager    Redirects.
	 */
	public function __construct(
		private Guard $guard,
		private OrganizationService $organizations,
		private OrganizationInvitations $invitations,
		private WebIdentityProvider $identity,
		private UserAccounts $accounts,
		private URLManager $urlmanager
	) {}

	/*
	|-----------------
	| THE USER'S OWN
	|-----------------
	*/

	/**
	 * Create an organization and act for it.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function create( Request $request ) : Response {
		$user = $this->user();

		if ( ! $user ) {
			return $this->signed_out();
		}

		$organization = $this->organizations->create( $user, (string) $request->get( 'name', '' ) );

		if ( $organization instanceof Exception ) {
			return $this->failure( $organization );
		}

		$this->identity->switch_context( $organization );

		return $this->success( sprintf( '%s is created. You now act for it.', $organization->get_display_name() ), $this->page_url( 'organization' ) );
	}

	/**
	 * Leave an organization.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function leave( Request $request ) : Response {
		$user         = $this->user();
		$organization = Organization::get_by_id( (int) $request->get( 'organization_id', 0 ) );

		if ( ! $user ) {
			return $this->signed_out();
		}

		if ( ! $organization ) {
			return $this->failure( new Exception( 'organization_not_found', 'This organization does not exist.', [ 'status' => 404 ] ) );
		}

		$result = $this->organizations->remove_member( $organization, $user, $user );

		if ( $result instanceof Exception ) {
			return $this->failure( $result );
		}

		$this->act_for_self_if_in( $organization );

		return $this->success( sprintf( 'You left %s.', $organization->get_display_name() ), $this->page_url( 'organizations' ) );
	}

	/**
	 * Join an organization through an invitation, and act for it.
	 *
	 * The invitation must be for the signed-in user's address. Opening the
	 * emailed link proves they own it, so the address is marked verified
	 * (which also links licenses issued to it).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function accept( Request $request ) : Response {
		$user = $this->user();

		if ( ! $user ) {
			return $this->signed_out();
		}

		[ $invitation, $organization ] = $this->invitation_for( $user, (string) $request->get( 'token', '' ) );

		if ( $invitation instanceof Exception ) {
			return $this->failure( $invitation );
		}

		if ( ! $this->organizations->is_member( $organization, $user ) ) {
			$joined = $this->organizations->add_member( $organization, $user, $invitation['role'] );

			if ( $joined instanceof Exception ) {
				return $this->failure( $joined );
			}
		}

		$this->invitations->consume( $invitation );

		if ( ! $user->is_email_verified() ) {
			try {
				$this->accounts->mark_email_verified( $user );
			} catch ( \Throwable ) {
				// They joined; the address can still be verified from the notice.
			}
		}

		$this->identity->switch_context( $organization );

		return $this->success( sprintf( 'Welcome to %s. You now act for it.', $organization->get_display_name() ), $this->page_url() );
	}

	/**
	 * Turn an invitation down.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function decline( Request $request ) : Response {
		$user = $this->user();

		if ( ! $user ) {
			return $this->signed_out();
		}

		[ $invitation, $organization ] = $this->invitation_for( $user, (string) $request->get( 'token', '' ) );

		if ( $invitation instanceof Exception ) {
			return $this->failure( $invitation );
		}

		$this->invitations->consume( $invitation );

		return $this->success( sprintf( 'You declined the invitation to %s.', $organization->get_display_name() ), $this->page_url( 'organizations' ) );
	}

	/*
	|--------------------------------
	| THE ORGANIZATION ACTED FOR
	|--------------------------------
	*/

	/**
	 * Rename the organization.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function rename( Request $request ) : Response {
		return $this->in_organization( 'security.organization.update', function ( Organization $organization ) use ( $request ) {
			$result = $this->organizations->rename( $organization, (string) $request->get( 'name', '' ) );

			return $result instanceof Exception ? $result : sprintf( 'Renamed to %s.', $result->get_display_name() );
		} );
	}

	/**
	 * Delete the organization; the user acts for themselves again.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete( Request $request ) : Response {
		return $this->in_organization( 'security.organization.delete', function ( Organization $organization ) {
			$result = $this->organizations->delete( $organization );

			if ( $result instanceof Exception ) {
				return $result;
			}

			$this->act_for_self_if_in( $organization );

			return [ sprintf( '%s is deleted.', $organization->get_display_name() ), $this->page_url( 'organizations' ) ];
		} );
	}

	/**
	 * Give a member another role.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function change_role( Request $request ) : Response {
		return $this->in_organization( 'security.organization.update_members', function ( Organization $organization, User $user ) use ( $request ) {
			$member = User::get_by_id( (int) $request->get( 'user_id', 0 ) );

			if ( ! $member ) {
				return new Exception( 'member_not_found', 'This member does not exist.', [ 'status' => 404 ] );
			}

			$result = $this->organizations->change_role( $organization, $member, (string) $request->get( 'role', '' ), $user );

			return $result instanceof Exception ? $result : sprintf( '%s\'s role is changed.', $member->get_display_name() );
		} );
	}

	/**
	 * Remove a member.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function remove_member( Request $request ) : Response {
		return $this->in_organization( 'security.organization.remove_members', function ( Organization $organization, User $user ) use ( $request ) {
			$member = User::get_by_id( (int) $request->get( 'user_id', 0 ) );

			if ( ! $member ) {
				return new Exception( 'member_not_found', 'This member does not exist.', [ 'status' => 404 ] );
			}

			if ( $member->get_id() === $user->get_id() ) {
				return new Exception( 'remove_self', 'To leave the organization, use Leave on the Organizations page.', [ 'status' => 409 ] );
			}

			$result = $this->organizations->remove_member( $organization, $member, $user );

			return $result instanceof Exception ? $result : sprintf( '%s is removed.', $member->get_display_name() );
		} );
	}

	/**
	 * Invite someone by email.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function invite( Request $request ) : Response {
		return $this->in_organization( 'security.user.invite', function ( Organization $organization, User $user ) use ( $request ) {
			$email   = strtolower( trim( (string) $request->get( 'email', '' ) ) );
			$invitee = '' !== $email ? User::get_by_email( $email ) : null;
			$result  = $this->invitations->invite(
				$organization,
				$user,
				$email,
				(string) $request->get( 'role', '' ),
				$invitee && $this->organizations->is_member( $organization, $invitee )
			);

			return $result instanceof Exception ? $result : sprintf( 'An invitation is on its way to %s. It is valid for %d hours.', $result['email'], OrganizationInvitations::TTL_HOURS );
		} );
	}

	/**
	 * Send an invitation again, with a new link.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function resend_invitation( Request $request ) : Response {
		return $this->in_organization( 'security.user.invite', function ( Organization $organization, User $user ) use ( $request ) {
			$result = $this->invitations->resend( $organization, (string) $request->get( 'id', '' ), $user );

			return $result instanceof Exception ? $result : sprintf( 'A new invitation is on its way to %s; the earlier link no longer works.', $result['email'] );
		} );
	}

	/**
	 * Revoke an invitation.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function revoke_invitation( Request $request ) : Response {
		return $this->in_organization( 'security.user.invite', function ( Organization $organization ) use ( $request ) {
			return $this->invitations->revoke( $organization, (string) $request->get( 'id', '' ) )
				? 'The invitation is revoked; its link no longer works.'
				: new Exception( 'invitation_not_found', 'This invitation has already expired or was revoked.', [ 'status' => 404 ] );
		} );
	}

	/*
	|-----------------
	| PRIVATE HELPERS
	|-----------------
	*/

	/**
	 * Run an action on the organization the user acts for, if their role there has the capability.
	 *
	 * @param string   $capability
	 * @param callable( Organization, User ): (string|array{0: string, 1: string}|Exception) $action
	 *                 Returns the success message (reloads the Organization page), or
	 *                 [ message, redirect URL ], or the failure.
	 * @return Response
	 */
	private function in_organization( string $capability, callable $action ) : Response {
		$principal    = $this->guard->get_principal();
		$user         = $this->user();
		$owner        = $principal?->get_owner();
		$organization = $owner && $owner->is_organization() ? Organization::get_by_id( $owner->get_subject_id() ) : null;

		if ( ! $user ) {
			return $this->signed_out();
		}

		if ( ! $organization ) {
			return $this->failure( new Exception( 'no_organization', 'Switch to the organization first.', [ 'status' => 409 ] ) );
		}

		if ( ! $principal->can( $capability ) ) {
			return $this->failure( new Exception( 'forbidden', sprintf( 'Your role in %s does not allow this.', $organization->get_display_name() ), [ 'status' => 403 ] ) );
		}

		$result = $action( $organization, $user );

		if ( $result instanceof Exception ) {
			return $this->failure( $result );
		}

		[ $message, $redirect ] = is_array( $result ) ? $result : [ $result, $this->page_url( 'organization' ) ];

		return $this->success( $message, $redirect );
	}

	/**
	 * The invitation in a token, if it is for this user, with its organization.
	 *
	 * @param User   $user
	 * @param string $token
	 * @return array{0: array<string, mixed>|Exception, 1: Organization|null}
	 */
	private function invitation_for( User $user, string $token ) : array {
		$invitation   = $this->invitations->find( $token );
		$organization = $invitation ? Organization::get_by_id( (int) $invitation['organization_id'] ) : null;

		if ( ! $invitation || ! $organization ) {
			return [ new Exception( 'invitation_not_found', 'This invitation has expired or was revoked. Ask for a new one.', [ 'status' => 404 ] ), null ];
		}

		if ( strtolower( $user->get_email() ) !== $invitation['email'] ) {
			return [ new Exception( 'invitation_for_another', sprintf( 'This invitation was sent to %s. Sign in with that address to answer it.', $invitation['email'] ), [ 'status' => 403 ] ), null ];
		}

		if ( Organization::STATUS_ACTIVE !== $organization->get_status() ) {
			return [ new Exception( 'organization_inactive', sprintf( '%s is not active at the moment.', $organization->get_display_name() ), [ 'status' => 409 ] ), null ];
		}

		return [ $invitation, $organization ];
	}

	/**
	 * Act for oneself again if the session acts for this organization.
	 *
	 * @param Organization $organization
	 * @return void
	 */
	private function act_for_self_if_in( Organization $organization ) : void {
		$owner = $this->guard->get_principal()?->get_owner();
		$user  = $this->user();

		if ( $user && $owner && $owner->is_organization() && $owner->get_subject_id() === $organization->get_id() ) {
			$this->identity->switch_context( $user );
		}
	}

	/**
	 * @return User|null
	 */
	private function user() : ?User {
		$actor = $this->guard->get_principal()?->get_actor();

		return $actor instanceof User ? $actor : null;
	}

	/**
	 * A client dashboard page URL.
	 *
	 * @param string $slug Empty for the overview.
	 * @return string
	 */
	private function page_url( string $slug = '' ) : string {
		return $this->urlmanager->client_dashboard_url( $slug )->url();
	}

	/**
	 * @param string $message
	 * @param string $redirect
	 * @return Response
	 */
	private function success( string $message, string $redirect ) : Response {
		return Response::json( [ 'success' => true, 'message' => $message, 'redirect' => $redirect ], 200 );
	}

	/**
	 * @return Response
	 */
	private function signed_out() : Response {
		return $this->failure( new RequestException( 'not_signed_in', 'Sign in to continue.', [ 'status' => 401 ] ) );
	}

	/**
	 * @param Exception $e With a "status" and, when it applies, a "field_id".
	 * @return Response
	 */
	private function failure( Exception $e ) : Response {
		$data = $e->get_error_data();
		$data = is_array( $data ) ? $data : [];

		return Response::json(
			array_filter(
				[
					'success'  => false,
					'message'  => $e->get_error_message(),
					'field_id' => $data['field_id'] ?? null,
				],
				static fn ( $value ) : bool => null !== $value
			),
			(int) ( $data['status'] ?? 400 )
		);
	}
}
