<?php
/**
 * Licensing Service class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;

/**
 * The Licensing API: how licenses are issued and changed.
 *
 * Smart License Server never collects money and never manages orders or
 * subscriptions. A provider sells; when a sale is confirmed, the provider
 * asks this API to issue a license from a pricing tier, and later to extend
 * it, change its status or its terms. Every provider uses this same API,
 * built in or from an extension; the REST Licensing API calls it too.
 *
 * Rules:
 *  - A license is issued from an active pricing tier, whose terms are
 *    copied onto it (PricingTier::to_license_contract()).
 *  - A provider can only issue from a tier it sells. An empty provider ID
 *    means an administrator issues the license; any active tier can be used.
 *  - A provider and its reference for the sale identify one license:
 *    issuing again with the same pair returns the license already issued,
 *    so a repeated request never issues twice.
 *  - The licensee email is required: the license is delivered to it.
 *
 * Methods do not throw: they return the license or true, or an Exception
 * whose data has an HTTP "status".
 */
class LicensingService {

	/**
	 * Issue a license from a pricing tier.
	 *
	 * @param PricingTier             $tier           The tier the license is issued from.
	 * @param string                  $licensee_email Where the license is delivered.
	 * @param string                  $licensee_name  The licensee's name, as the provider has it.
	 * @param string                  $provider_id    The provider that sold it; empty when an administrator issues it.
	 * @param string                  $external_ref   The provider's reference for the sale; empty for none.
	 * @param string                  $service_id     The ID a client activates with; generated when empty.
	 * @param DateTimeImmutable|null  $starts_at      When the license starts; now when null.
	 * @return License|Exception The license (the one already issued, for a repeated
	 *                           provider reference), or why it was not issued.
	 */
	public function issue(
		PricingTier $tier,
		string $licensee_email,
		string $licensee_name = '',
		string $provider_id = '',
		string $external_ref = '',
		string $service_id = '',
		?DateTimeImmutable $starts_at = null
	) : License|Exception {
		if ( '' !== $external_ref && '' === $provider_id ) {
			return new Exception( 'invalid_source', 'A provider reference needs a provider ID.', [ 'status' => 400 ] );
		}

		$existing = License::get_by_source( $provider_id, $external_ref );

		if ( $existing ) {
			return $existing;
		}

		if ( ! $tier->exists() ) {
			return new Exception( 'tier_not_found', 'The pricing tier does not exist.', [ 'status' => 404 ] );
		}

		if ( ! $tier->is_active() ) {
			return new Exception( 'tier_archived', sprintf( 'The pricing tier "%s" is archived and issues no new licenses.', $tier->get_name() ), [ 'status' => 409 ] );
		}

		if ( '' !== $provider_id && $provider_id !== $tier->get_provider_id() ) {
			return new Exception( 'tier_not_sold_by_provider', sprintf( 'The pricing tier "%s" is not sold by the provider "%s".', $tier->get_name(), $provider_id ), [ 'status' => 403 ] );
		}

		$app = Monetization::get_by_id( $tier->get_monetization_id() )?->get_app();

		if ( ! ( $app instanceof AbstractHostedApp ) ) {
			return new Exception( 'app_not_found', 'The app this pricing tier belongs to does not exist.', [ 'status' => 404 ] );
		}

		$license = new License();

		try {
			$license->set_licensee_email( $licensee_email );
		} catch ( \InvalidArgumentException $e ) {
			return new Exception( 'invalid_email', $e->getMessage(), [ 'status' => 400 ] );
		}

		if ( '' === $license->get_licensee_email() ) {
			return new Exception( 'missing_email', 'The licensee email is required.', [ 'status' => 400 ] );
		}

		$contract = $tier->to_license_contract( $starts_at ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );

		$license->set_app( $app )
			->set_licensee_fullname( $licensee_name )
			->set_service_id( $service_id )
			->set_tier_id( $tier->get_id() )
			->set_source( $provider_id, $external_ref )
			->set_max_allowed_domains( $contract['max_allowed_domains'] )
			->set_start_date( $contract['start_date'] )
			->set_end_date( $contract['end_date'] )
			->set_features( $contract['features'] );

		if ( ! $license->save() ) {
			// Another request may have issued it under the same reference first.
			return License::get_by_source( $provider_id, $external_ref )
				?? new Exception( 'license_not_saved', 'The license could not be saved.', [ 'status' => 500 ] );
		}

		return $license;
	}

	/**
	 * Move the end of a license, e.g. after a renewal.
	 *
	 * An expired license whose new end is in the future gets its status
	 * calculated from its dates again.
	 *
	 * @param License                $license The license.
	 * @param DateTimeImmutable|null $ends_at The new end; null for no end (lifetime).
	 * @return true|Exception
	 */
	public function extend( License $license, ?DateTimeImmutable $ends_at ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', 'The license does not exist.', [ 'status' => 404 ] );
		}

		$license->set_end_date( $ends_at );

		if ( License::STATUS_EXPIRED === $license->get_status( 'edit' ) && ( null === $ends_at || $ends_at->getTimestamp() > time() ) ) {
			$license->set_status( '' );
		}

		return $this->save( $license );
	}

	/**
	 * Set the status of a license.
	 *
	 * @param License $license The license.
	 * @param string  $status  One of License::get_allowed_statuses(), or empty to
	 *                         calculate the status from the license dates.
	 * @return true|Exception
	 */
	public function set_status( License $license, string $status ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', 'The license does not exist.', [ 'status' => 404 ] );
		}

		if ( '' !== $status && ! in_array( $status, License::get_allowed_statuses(), true ) ) {
			return new Exception( 'invalid_status', sprintf( 'Unknown license status "%s". Use one of: %s.', $status, implode( ', ', License::get_allowed_statuses() ) ), [ 'status' => 400 ] );
		}

		$license->set_status( $status );

		return $this->save( $license );
	}

	/**
	 * Change the terms of a license.
	 *
	 * @param License $license The license.
	 * @param array{
	 *  max_allowed_domains?: int,
	 *  end_date?: DateTimeImmutable|null,
	 *  features?: list<string>
	 * } $terms The terms to change; terms not given are kept.
	 * @return true|Exception
	 */
	public function update_contract( License $license, array $terms ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', 'The license does not exist.', [ 'status' => 404 ] );
		}

		$unknown = array_diff( array_keys( $terms ), [ 'max_allowed_domains', 'end_date', 'features' ] );

		if ( ! empty( $unknown ) ) {
			return new Exception( 'invalid_terms', sprintf( 'Unknown license terms: %s.', implode( ', ', $unknown ) ), [ 'status' => 400 ] );
		}

		if ( array_key_exists( 'max_allowed_domains', $terms ) ) {
			$license->set_max_allowed_domains( (int) $terms['max_allowed_domains'] );
		}

		if ( array_key_exists( 'end_date', $terms ) ) {
			$license->set_end_date( $terms['end_date'] );
		}

		if ( array_key_exists( 'features', $terms ) ) {
			$license->set_features( (array) $terms['features'] );
		}

		return $this->save( $license );
	}

	/**
	 * Find the license a provider sold under the given reference.
	 *
	 * @param string $provider_id  The provider ID.
	 * @param string $external_ref The provider's reference for the sale.
	 * @return License|null
	 */
	public function find_by_reference( string $provider_id, string $external_ref ) : ?License {
		return License::get_by_source( $provider_id, $external_ref );
	}

	/**
	 * Save a changed license.
	 *
	 * @param License $license
	 * @return true|Exception
	 */
	protected function save( License $license ) : true|Exception {
		return $license->save()
			? true
			: new Exception( 'license_not_saved', 'The license could not be saved.', [ 'status' => 500 ] );
	}
}