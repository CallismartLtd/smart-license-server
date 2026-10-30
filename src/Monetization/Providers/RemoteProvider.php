<?php
/**
 * Remote Provider class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization\Providers;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\Monetization\PricingTier;
use SmartLicenseServer\Security\Actors\ServiceAccount;

/**
 * Sells through a store that runs elsewhere and has no extension here.
 *
 * The store (a WooCommerce site, Smart Woo, any shop) authenticates with a
 * service account and calls the REST Licensing API when it confirms a sale.
 * Each tier this provider sells is bound to one service account: that
 * account is the tier's channel, and only it can issue licenses from the
 * tier. Its licenses carry the provider ID "remote:<service account ID>".
 *
 * A tier can be bound only to a service account of the app's owner; an
 * app without an owner can be bound to any service account. Only accounts
 * that can authenticate are offered.
 */
class RemoteProvider implements MonetizationProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public static function get_id() : string {
		return 'remote';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_name() : string {
		return 'Remote store';
	}

	/**
	 * {@inheritdoc}
	 *
	 * The remote provider has no provider-wide settings.
	 */
	public function get_settings_schema() : array {
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_settings( array $settings ) : void {}

	/**
	 * {@inheritdoc}
	 */
	public function get_tier_schema( HostedAppsInterface $app ) : array {
		$options = [];

		foreach ( $this->bindable_accounts( $app ) as $account ) {
			$options[ $account->get_identifier() ] = $account->get_display_name();
		}

		$description = empty( $options )
			? ( $app->get_owner_id()
				? 'This app\'s owner has no active service account. Create one for the owner under Accounts → Service Accounts, then reload this page.'
				: 'There is no active service account. Create one under Accounts → Service Accounts, then reload this page.' )
			: 'The service account the store uses. Only it can issue licenses from this tier.';

		return [
			'service_account' => [
				'type'        => 'select',
				'label'       => 'Store service account',
				'default'     => '',
				'description' => $description,
				'required'    => true,
				'options'     => $options,
			],
			'price' => [
				'type'        => 'text',
				'label'       => 'Price',
				'default'     => '',
				'description' => 'The price as buyers read it, e.g. "$49 / year". Shown as is.',
				'required'    => false,
			],
			'checkout_url' => [
				'type'        => 'url',
				'label'       => 'Checkout URL',
				'default'     => '',
				'description' => 'Where buyers buy this tier on the store.',
				'required'    => false,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare_tier_data( array $input, HostedAppsInterface $app ) : array|Exception {
		$identifier   = trim( (string) ( $input['service_account'] ?? '' ) );
		$price        = trim( strip_tags( (string) ( $input['price'] ?? '' ) ) );
		$checkout_url = trim( (string) ( $input['checkout_url'] ?? '' ) );

		if ( ! isset( $this->bindable_accounts( $app )[ $identifier ] ) ) {
			return new Exception( 'invalid_service_account', 'Choose an active service account of this app\'s owner.', [ 'status' => 400, 'field_id' => 'service_account' ] );
		}

		if ( '' !== $checkout_url && ! static::is_http_url( $checkout_url ) ) {
			return new Exception( 'invalid_checkout_url', 'The checkout URL must be an http or https URL.', [ 'status' => 400, 'field_id' => 'checkout_url' ] );
		}

		return [
			'service_account' => $identifier,
			'price'           => $price,
			'checkout_url'    => $checkout_url,
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * The channel is the service account ID.
	 */
	public function get_channel( array $data ) : string {
		return (string) ( $data['service_account'] ?? '' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_offer( PricingTier $tier ) : array {
		$data = $tier->get_provider_data();

		return [
			'price'        => (string) ( $data['price'] ?? '' ),
			'checkout_url' => (string) ( $data['checkout_url'] ?? '' ),
		];
	}

	/**
	 * The service accounts a tier of the app can be bound to, keyed by identifier.
	 *
	 * @param HostedAppsInterface $app
	 * @return array<string, ServiceAccount>
	 */
	protected function bindable_accounts( HostedAppsInterface $app ) : array {
		$owner_id = (int) $app->get_owner_id();
		$accounts = [];

		foreach ( ServiceAccount::get_active( $owner_id ?: null ) as $account ) {
			$accounts[ $account->get_identifier() ] = $account;
		}

		return $accounts;
	}

	/**
	 * Whether a URL is an absolute http or https URL.
	 *
	 * @param string $url
	 * @return bool
	 */
	protected static function is_http_url( string $url ) : bool {
		return false !== filter_var( $url, FILTER_VALIDATE_URL )
			&& in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true );
	}
}
