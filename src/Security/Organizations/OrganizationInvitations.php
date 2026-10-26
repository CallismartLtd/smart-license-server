<?php
/**
 * Organization invitations class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Organizations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Organizations;

use SmartLicenseServer\Background\Jobs\Accounts\OrganizationInviteEmailJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Permission\DefaultRoles;

/**
 * Invitations to join an organization, kept in the cache.
 *
 * An invitation is a random token emailed to one address. Only the token's
 * SHA-256 hash is stored, under a known key, for TTL_HOURS; the cache
 * forgets it after that, so nothing needs cleaning up. Each organization
 * has an index of its pending invitations under its own key, so they can
 * be listed, resent and revoked.
 *
 * Rules:
 * - Invitations need a cache that keeps values between requests
 *   (Cache::is_persistent()); otherwise inviting is refused with
 *   NOT_PERSISTENT_MESSAGE, which asks the user to contact the site
 *   administrator.
 * - One pending invitation per address per organization: inviting the
 *   address again replaces it. A member's address cannot be invited.
 * - The role is one of DefaultRoles::ORGANIZATION_ROLES.
 * - Only the signed-in user with the invited address can accept or
 *   decline. Accepting proves they received the email, so it also marks
 *   their address verified (OrganizationService and UserAccounts do the
 *   joining and the verifying; see the client dashboard's handler).
 *
 * Keys: CACHE_PREFIX . "invite_" . <hash> holds the invitation;
 * CACHE_PREFIX . "index_" . <organization ID> maps hash => email.
 */
class OrganizationInvitations {
	use QueueAwareTrait;

	/**
	 * Hours an invitation is valid.
	 *
	 * @var int
	 */
	public const TTL_HOURS = 48;

	/**
	 * Prefix of the cache keys.
	 *
	 * @var string
	 */
	public const CACHE_PREFIX = 'org_invitation_';

	/**
	 * Why invitations cannot be sent on a cache that forgets everything between requests.
	 *
	 * @var string
	 */
	public const NOT_PERSISTENT_MESSAGE = 'Invitations cannot be sent on this site at the moment: its cache does not keep anything between requests, so an invitation would be lost as soon as it was made. Ask the site administrator to set up a persistent cache adapter (Redis, Memcached, APCu or SQLite).';

	/**
	 * @param Cache    $cache     Where invitations are kept.
	 * @param JobQueue $job_queue Sends the invitation emails.
	 */
	public function __construct( protected Cache $cache, JobQueue $job_queue ) {
		$this->job_queue = $job_queue;
	}

	/**
	 * Whether invitations can be kept on this site.
	 *
	 * @return bool
	 */
	public function is_available() : bool {
		return (bool) $this->cache->is_persistent();
	}

	/**
	 * Invite an email address to join an organization, and email the link.
	 *
	 * @param Organization $organization The organization.
	 * @param User         $by           Who invites.
	 * @param string       $email        The address invited.
	 * @param string       $role_slug    The role they will have.
	 * @param bool         $is_member    Whether the address already belongs to a member.
	 * @return array{id: string, organization_id: int, email: string, role: string, invited_by: int, created_at: int, expires_at: int}|Exception
	 */
	public function invite( Organization $organization, User $by, string $email, string $role_slug, bool $is_member = false ) : array|Exception {
		if ( ! $this->is_available() ) {
			return new Exception( 'invitations_unavailable', self::NOT_PERSISTENT_MESSAGE, [ 'status' => 503 ] );
		}

		$email = strtolower( trim( $email ) );

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return new Exception( 'invalid_email', 'Enter a valid email address.', [ 'status' => 400, 'field_id' => 'email' ] );
		}

		if ( ! in_array( $role_slug, DefaultRoles::ORGANIZATION_ROLES, true ) ) {
			return new Exception( 'invalid_role', 'Choose a role for the person you invite.', [ 'status' => 400, 'field_id' => 'role' ] );
		}

		if ( $is_member ) {
			return new Exception( 'already_member', sprintf( '%s is already a member of %s.', $email, $organization->get_display_name() ), [ 'status' => 409, 'field_id' => 'email' ] );
		}

		// Inviting the address again replaces its pending invitation.
		foreach ( $this->get_pending( $organization ) as $pending ) {
			if ( $pending['email'] === $email ) {
				$this->revoke( $organization, $pending['id'] );
			}
		}

		$token      = bin2hex( random_bytes( 32 ) );
		$now        = time();
		$invitation = [
			'id'              => hash( 'sha256', $token ),
			'organization_id' => (int) $organization->get_id(),
			'email'           => $email,
			'role'            => $role_slug,
			'invited_by'      => (int) $by->get_id(),
			'created_at'      => $now,
			'expires_at'      => $now + self::TTL_HOURS * 3600,
		];

		if ( ! $this->cache->set( $this->invitation_key( $invitation['id'] ), $invitation, self::TTL_HOURS * 3600 ) ) {
			return new Exception( 'invitation_not_saved', 'The invitation could not be saved. Please try again.', [ 'status' => 500 ] );
		}

		$this->cache->modify(
			$this->index_key( $organization ),
			static function ( $index ) use ( $invitation ) : array {
				$index                      = is_array( $index ) ? $index : [];
				$index[ $invitation['id'] ] = $invitation['email'];

				return $index;
			},
			self::TTL_HOURS * 3600,
			[]
		);

		// The token travels only in the job's payload and the email; the cache keeps its hash.
		$this->dispatch_job(
			OrganizationInviteEmailJob::class,
			[
				'organization_id' => $invitation['organization_id'],
				'email'           => $email,
				'invited_by'      => $invitation['invited_by'],
				'token'           => $token,
			]
		);

		return $invitation;
	}

	/**
	 * Send an invitation again, with a new link; the old link stops working.
	 *
	 * @param Organization $organization
	 * @param string       $id The invitation's ID (its token hash).
	 * @param User         $by Who resends it.
	 * @return array{id: string, organization_id: int, email: string, role: string, invited_by: int, created_at: int, expires_at: int}|Exception
	 */
	public function resend( Organization $organization, string $id, User $by ) : array|Exception {
		$invitation = $this->get( $id );

		if ( ! $invitation || $invitation['organization_id'] !== (int) $organization->get_id() ) {
			return new Exception( 'invitation_not_found', 'This invitation has expired or was revoked.', [ 'status' => 404 ] );
		}

		return $this->invite( $organization, $by, $invitation['email'], $invitation['role'] );
	}

	/**
	 * The pending invitations of an organization, newest first.
	 *
	 * Expired ones are dropped from the index as they are found.
	 *
	 * @param Organization $organization
	 * @return list<array{id: string, organization_id: int, email: string, role: string, invited_by: int, created_at: int, expires_at: int}>
	 */
	public function get_pending( Organization $organization ) : array {
		$index   = $this->cache->get( $this->index_key( $organization ) );
		$pending = [];
		$expired = [];

		foreach ( is_array( $index ) ? $index : [] as $id => $email ) {
			$invitation = $this->get( (string) $id );

			if ( $invitation && $invitation['organization_id'] === (int) $organization->get_id() ) {
				$pending[] = $invitation;
			} else {
				$expired[] = (string) $id;
			}
		}

		if ( [] !== $expired ) {
			$this->forget( $organization, $expired );
		}

		usort( $pending, static fn ( array $a, array $b ) : int => $b['created_at'] <=> $a['created_at'] );

		return $pending;
	}

	/**
	 * An invitation by the token in its link.
	 *
	 * @param string $token
	 * @return array{id: string, organization_id: int, email: string, role: string, invited_by: int, created_at: int, expires_at: int}|null Null when expired, revoked or unknown.
	 */
	public function find( string $token ) : ?array {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $token ) ? $this->get( hash( 'sha256', $token ) ) : null;
	}

	/**
	 * An invitation by its ID (the token's hash).
	 *
	 * @param string $id
	 * @return array{id: string, organization_id: int, email: string, role: string, invited_by: int, created_at: int, expires_at: int}|null
	 */
	public function get( string $id ) : ?array {
		$invitation = 1 === preg_match( '/^[a-f0-9]{64}$/', $id ) ? $this->cache->get( $this->invitation_key( $id ) ) : null;

		if ( ! is_array( $invitation ) || ( (int) ( $invitation['expires_at'] ?? 0 ) ) <= time() ) {
			return null;
		}

		return $invitation;
	}

	/**
	 * Revoke an invitation: its link stops working.
	 *
	 * @param Organization $organization
	 * @param string       $id The invitation's ID.
	 * @return bool Whether there was such an invitation.
	 */
	public function revoke( Organization $organization, string $id ) : bool {
		$invitation = $this->get( $id );

		if ( ! $invitation || $invitation['organization_id'] !== (int) $organization->get_id() ) {
			return false;
		}

		$this->cache->delete( $this->invitation_key( $id ) );
		$this->forget( $organization, [ $id ] );

		return true;
	}

	/**
	 * Remove an accepted or declined invitation.
	 *
	 * @param array{id: string, organization_id: int} $invitation
	 * @return void
	 */
	public function consume( array $invitation ) : void {
		$this->cache->delete( $this->invitation_key( $invitation['id'] ) );

		$organization = Organization::get_by_id( (int) $invitation['organization_id'] );

		if ( $organization ) {
			$this->forget( $organization, [ $invitation['id'] ] );
		}
	}

	/**
	 * Revoke every pending invitation of an organization (it is being deleted).
	 *
	 * @param Organization $organization
	 * @return void
	 */
	public function revoke_all( Organization $organization ) : void {
		$index = $this->cache->get( $this->index_key( $organization ) );

		foreach ( is_array( $index ) ? array_keys( $index ) : [] as $id ) {
			$this->cache->delete( $this->invitation_key( (string) $id ) );
		}

		$this->cache->delete( $this->index_key( $organization ) );
	}

	/*
	|-----------------
	| PRIVATE HELPERS
	|-----------------
	*/

	/**
	 * Drop IDs from an organization's index.
	 *
	 * @param Organization $organization
	 * @param list<string> $ids
	 * @return void
	 */
	protected function forget( Organization $organization, array $ids ) : void {
		$this->cache->modify(
			$this->index_key( $organization ),
			static fn ( $index ) : array => array_diff_key( is_array( $index ) ? $index : [], array_flip( $ids ) ),
			self::TTL_HOURS * 3600,
			[]
		);
	}

	/**
	 * @param string $id
	 * @return string
	 */
	protected function invitation_key( string $id ) : string {
		return self::CACHE_PREFIX . 'invite_' . $id;
	}

	/**
	 * @param Organization $organization
	 * @return string
	 */
	protected function index_key( Organization $organization ) : string {
		return self::CACHE_PREFIX . 'index_' . $organization->get_id();
	}
}