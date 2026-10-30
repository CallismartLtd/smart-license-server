<?php
/**
 * Organization invite email job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\Accounts\OrganizationInviteEmail;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Organizations\OrganizationInvitations;
use SmartLicenseServer\Security\OwnerSubjects\Organization;

/**
 * Emails an invitation to join an organization (OrganizationInvitations::invite()).
 *
 * The link opens the client dashboard's Organizations page with the
 * invitation, behind sign-in. Everything the email needs is in the
 * payload: the worker may not share the web server's cache (APCu keeps a
 * separate store for the CLI), so the job never reads the invitation. A
 * link revoked before the email arrives simply no longer works.
 *
 * Payload:
 *   organization_id (int)    The organization.
 *   email           (string) The address invited.
 *   invited_by      (int)    The user who invited.
 *   token           (string) The token for the link; only the email carries it on.
 */
class OrganizationInviteEmailJob implements JobHandlerInterface {

	/**
	 * @param Mailer     $mailer
	 * @param URLManager $urlmanager
	 */
	public function __construct(
		protected Mailer $mailer,
		protected URLManager $urlmanager
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * @param array{organization_id?: int, email?: string, invited_by?: int, token?: string} $payload
	 * @return mixed
	 */
	public function handle( array $payload = [] ) : mixed {
		$token = (string) ( $payload['token'] ?? '' );
		$email = (string) ( $payload['email'] ?? '' );

		if ( '' === $token || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return [ 'success' => false, 'message' => 'The invitation payload is incomplete.' ];
		}

		$organization = Organization::get_by_id( (int) ( $payload['organization_id'] ?? 0 ) );

		if ( ! $organization ) {
			return [ 'success' => false, 'message' => 'The organization no longer exists.' ];
		}

		$owner   = ContextServiceProvider::get_owner_for( $organization ) ?? ContextServiceProvider::ensure_owner( $organization );
		$inviter = User::get_by_id( (int) ( $payload['invited_by'] ?? 0 ) );
		$invitee = User::get_by_email( $email );
		$url     = $this->urlmanager->client_dashboard_url( 'organizations', [ 'invitation' => $token ] );

		$message = ( new OrganizationInviteEmail(
			$owner,
			$email,
			$invitee ? $invitee->get_display_name() : 'there',
			$inviter ? $inviter->get_display_name() : $organization->get_display_name(),
			$url->url(),
			OrganizationInvitations::TTL_HOURS
		) )->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => 'The "Organization Invite" template is disabled.' ];
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Send Organization Invitation';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Email someone a link to join an organization.';
	}
}
