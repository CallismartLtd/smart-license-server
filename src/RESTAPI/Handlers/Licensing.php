<?php
/**
 * REST Licensing API handler class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI\Handlers
 */

namespace SmartLicenseServer\RESTAPI\Handlers;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicensingService;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Monetization\PricingTier;
use SmartLicenseServer\Monetization\Providers\RemoteProvider;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;

/**
 * The REST Licensing API: how a remote store turns its sales into licenses.
 *
 * A store sells on its own site; when it confirms a sale, it calls this API
 * with a service account. The store sells through the remote provider, and
 * its channel is its service account: it sees and issues from the tiers
 * bound to its account only, and sees and changes the licenses it issued
 * only ("remote:<service account ID>"). Anything outside its channel is
 * not found (404), never forbidden, so other stores' data is not revealed.
 *
 * Capabilities: listing tiers and issuing need monetization.license.issue;
 * reading a license needs monetization.license.issue or .update; changing a
 * license needs monetization.license.update, and revoking it also needs
 * monetization.license.revoke.
 */
class Licensing {

	/**
	 * Class constructor.
	 *
	 * @param Guard            $guard     Holds the principal for the request.
	 * @param LicensingService $licensing The Licensing API.
	 */
	public function __construct(
		protected Guard $guard,
		protected LicensingService $licensing
	) {}

	/*
	|--------
	| GUARDS
	|--------
	*/

	/**
	 * Guard for listing tiers and issuing licenses.
	 *
	 * @param Request $request
	 * @return true|RequestException
	 */
	public function issue_guard( Request $request ) : true|RequestException {
		return $this->require_store( $request, 'monetization.license.issue' );
	}

	/**
	 * Guard for reading licenses.
	 *
	 * @param Request $request
	 * @return true|RequestException
	 */
	public function read_guard( Request $request ) : true|RequestException {
		return $this->require_store( $request, 'monetization.license.issue', 'monetization.license.update' );
	}

	/**
	 * Guard for changing a license.
	 *
	 * @param Request $request
	 * @return true|RequestException
	 */
	public function update_guard( Request $request ) : true|RequestException {
		return $this->require_store( $request, 'monetization.license.update' );
	}

	/*
	|----------
	| HANDLERS
	|----------
	*/

	/**
	 * GET licensing/tiers: the tiers bound to the calling store.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function tiers_response( Request $request ) : Response {
		$tiers = [];

		foreach ( PricingTier::get_by_provider( $this->channel() ) as $tier ) {
			$app = Monetization::get_by_id( $tier->get_monetization_id() )?->get_app();

			$tiers[] = [
				'id'          => $tier->get_id(),
				'name'        => $tier->get_name(),
				'status'      => $tier->get_status(),
				'term_unit'   => $tier->get_term_unit(),
				'term_length' => $tier->get_term_length(),
				'term_label'  => $tier->get_term_label(),
				'max_sites'   => $tier->get_max_sites(),
				'features'    => $tier->get_features(),
				'app'         => $app ? [ 'type' => $app->get_type(), 'slug' => $app->get_slug(), 'name' => $app->get_name() ] : null,
			];
		}

		return Response::json( [ 'tiers' => $tiers ] );
	}

	/**
	 * POST licensing/licenses: issue a license for a sale.
	 *
	 * Fields: tier_id, external_ref (the store's reference for the sale; required),
	 * licensee_email, licensee_name, service_id (generated when empty),
	 * starts_at (a date; now when empty).
	 *
	 * Issuing again with the same external_ref returns the license already
	 * issued, with 200 instead of 201.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function issue_response( Request $request ) : Response {
		$channel      = $this->channel();
		$external_ref = trim( (string) $request->get( 'external_ref', '' ) );
		$tier         = PricingTier::get_by_id( (int) $request->get( 'tier_id', 0 ) );

		if ( '' === $external_ref ) {
			return $this->error( 'missing_external_ref', 'external_ref, your reference for the sale, is required.', 400 );
		}

		if ( ! $tier || $tier->get_provider_id() !== $channel ) {
			return $this->error( 'tier_not_found', 'No pricing tier with this ID is sold by your store.', 404 );
		}

		$starts_at = $this->parse_date( $request->get( 'starts_at', '' ) );

		if ( $starts_at instanceof Exception ) {
			return Response::error( $starts_at, 400 );
		}

		$existing = License::get_by_source( $channel, $external_ref );
		$license  = $this->licensing->issue(
			$tier,
			(string) $request->get( 'licensee_email', '' ),
			(string) $request->get( 'licensee_name', '' ),
			$channel,
			$external_ref,
			(string) $request->get( 'service_id', '' ),
			$starts_at
		);

		if ( $license instanceof Exception ) {
			return Response::error( $license, (int) ( $license->get_error_data()['status'] ?? 400 ) );
		}

		return Response::json( [ 'license' => $this->license_to_array( $license ), 'created' => ! $existing ], $existing ? 200 : 201 );
	}

	/**
	 * GET licensing/licenses?external_ref=…: find the license issued for a sale.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function lookup_response( Request $request ) : Response {
		$external_ref = trim( (string) $request->get( 'external_ref', '' ) );

		if ( '' === $external_ref ) {
			return $this->error( 'missing_external_ref', 'Give the external_ref of the sale.', 400 );
		}

		$license = License::get_by_source( $this->channel(), $external_ref );

		return $license
			? Response::json( [ 'license' => $this->license_to_array( $license ) ] )
			: $this->error( 'license_not_found', 'Your store has no license for this reference.', 404 );
	}

	/**
	 * GET licensing/licenses/{license_id}: one license the store issued.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function license_response( Request $request ) : Response {
		$license = $this->own_license( $request );

		return $license
			? Response::json( [ 'license' => $this->license_to_array( $license ) ] )
			: $this->error( 'license_not_found', 'Your store has no license with this ID.', 404 );
	}

	/**
	 * PATCH licensing/licenses/{license_id}: change a license the store issued.
	 *
	 * Fields, each optional; only those sent are changed, in this order:
	 *  - max_allowed_domains: a number; -1 for unlimited.
	 *  - features: a list, or a comma-separated string.
	 *  - end_date: a date; empty or null for no end. An expired license whose
	 *    new end is in the future gets its status calculated again.
	 *  - status: one of License::get_allowed_statuses(), or empty to calculate
	 *    it from the license dates. "revoked" needs monetization.license.revoke.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function update_response( Request $request ) : Response {
		$license = $this->own_license( $request );

		if ( ! $license ) {
			return $this->error( 'license_not_found', 'Your store has no license with this ID.', 404 );
		}

		$terms = [];

		if ( $request->has( 'max_allowed_domains' ) ) {
			$terms['max_allowed_domains'] = (int) $request->get( 'max_allowed_domains' );
		}

		if ( $request->has( 'features' ) ) {
			$features          = $request->get( 'features' );
			$terms['features'] = is_array( $features ) ? array_values( $features ) : ( preg_split( '/[,\r\n]+/', (string) $features ) ?: [] );
		}

		if ( ! empty( $terms ) ) {
			$result = $this->licensing->update_contract( $license, $terms );

			if ( $result instanceof Exception ) {
				return Response::error( $result, (int) ( $result->get_error_data()['status'] ?? 400 ) );
			}
		}

		if ( $request->has( 'end_date' ) ) {
			$ends_at = $this->parse_date( $request->get( 'end_date' ) );

			if ( $ends_at instanceof Exception ) {
				return Response::error( $ends_at, 400 );
			}

			$result = $this->licensing->extend( $license, $ends_at );

			if ( $result instanceof Exception ) {
				return Response::error( $result, (int) ( $result->get_error_data()['status'] ?? 400 ) );
			}
		}

		if ( $request->has( 'status' ) ) {
			$status = (string) $request->get( 'status', '' );

			if ( License::STATUS_REVOKED === $status && ! $this->principal()->can( 'monetization.license.revoke' ) ) {
				return Response::error( new RequestException( 'unauthorized_scope', 'Revoking a license needs the monetization.license.revoke capability.', [ 'status' => 403 ] ), 403 );
			}

			$result = $this->licensing->set_status( $license, $status );

			if ( $result instanceof Exception ) {
				return Response::error( $result, (int) ( $result->get_error_data()['status'] ?? 400 ) );
			}
		}

		return Response::json( [ 'license' => $this->license_to_array( License::get_by_id( $license->get_id() ) ?? $license ) ] );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Require a service account principal that has any of the capabilities.
	 *
	 * @param Request $request
	 * @param string  ...$capabilities
	 * @return true|RequestException
	 */
	protected function require_store( Request $request, string ...$capabilities ) : true|RequestException {
		$principal = $this->guard->get_principal();

		if ( ! $principal ) {
			return new RequestException( 'missing_auth' );
		}

		if ( ! ( $principal->get_actor() instanceof ServiceAccount ) ) {
			return new RequestException( 'unauthorized_scope', 'The Licensing API is for stores, which authenticate with a service account.', [ 'status' => 403 ] );
		}

		return $principal->can_any( $capabilities ) ? true : new RequestException( 'unauthorized_scope' );
	}

	/**
	 * The authenticated principal; guards have made sure there is one.
	 *
	 * @return Principal
	 */
	protected function principal() : Principal {
		/** @var Principal */
		return $this->guard->get_principal();
	}

	/**
	 * The calling store's channel: "remote:<service account ID>".
	 *
	 * @return string
	 */
	protected function channel() : string {
		/** @var ServiceAccount $account */
		$account = $this->principal()->get_actor();

		return MonetizationRegistry::make_provider_id( RemoteProvider::get_id(), $account->get_identifier() );
	}

	/**
	 * The license in the route, when the calling store issued it.
	 *
	 * @param Request $request
	 * @return License|null
	 */
	protected function own_license( Request $request ) : ?License {
		$license = License::get_by_id( (int) $request->get( 'license_id', 0 ) );

		return $license && $license->get_provider_id() === $this->channel() ? $license : null;
	}

	/**
	 * Parse a date field.
	 *
	 * @param mixed $value A date PHP understands (e.g. "2026-11-01" or an ISO 8601
	 *                     date-time), read as UTC when it has no time zone.
	 * @return DateTimeImmutable|null|Exception Null for an empty value.
	 */
	protected function parse_date( mixed $value ) : DateTimeImmutable|null|Exception {
		if ( null === $value || '' === trim( (string) $value ) ) {
			return null;
		}

		try {
			return ( new DateTimeImmutable( (string) $value, new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		} catch ( \Throwable ) {
			return new Exception( 'invalid_date', sprintf( '"%s" is not a date.', (string) $value ), [ 'status' => 400 ] );
		}
	}

	/**
	 * A license as the Licensing API returns it.
	 *
	 * @param License $license
	 * @return array<string, mixed>
	 */
	protected function license_to_array( License $license ) : array {
		$app         = $license->get_app();
		$activations = [];

		foreach ( $license->get_activations() as $host => $activation ) {
			$activations[] = [ 'host' => $host, 'activated_at' => $activation->get_activated_at()?->format( DateTimeImmutable::ATOM ) ];
		}

		return [
			'id'                  => (int) $license->get_id(),
			'license_key'         => $license->get_license_key(),
			'service_id'          => $license->get_service_id(),
			'status'              => $license->get_status(),
			'licensee'            => [ 'email' => $license->get_licensee_email(), 'name' => $license->get_licensee_fullname() ],
			'app'                 => $app instanceof AbstractHostedApp ? [ 'type' => $app->get_type(), 'slug' => $app->get_slug() ] : null,
			'tier_id'             => $license->get_tier_id(),
			'external_ref'        => $license->get_external_ref(),
			'max_allowed_domains' => (int) $license->get_max_allowed_domains( 'save' ),
			'features'            => $license->get_features(),
			'start_date'          => $license->get_start_date()?->format( DateTimeImmutable::ATOM ),
			'end_date'            => $license->get_end_date()?->format( DateTimeImmutable::ATOM ),
			'activations'         => $activations,
			'created_at'          => $license->get_created_at()?->format( DateTimeImmutable::ATOM ),
			'updated_at'          => $license->get_updated_at()?->format( DateTimeImmutable::ATOM ),
		];
	}

	/**
	 * An error response.
	 *
	 * @param string $code
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	protected function error( string $code, string $message, int $status ) : Response {
		return Response::error( new RequestException( $code, $message, [ 'status' => $status ] ), $status );
	}
}
