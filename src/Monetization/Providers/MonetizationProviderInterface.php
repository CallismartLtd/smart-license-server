<?php
/**
 * Smart License Server Monetization Provider Interface.
 *
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization\Providers;

use SmartLicenseServer\Contracts\ServiceProviderInterface;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\Monetization\PricingTier;

/**
 * Monetization Provider Interface
 *
 * A provider is how licenses are sold. Smart License Server never collects
 * money and never manages orders or subscriptions: the provider sells, and
 * when a sale is confirmed it asks the Licensing API (LicensingService, or
 * the REST Licensing API) to issue the license from the pricing tier it sold.
 *
 * Each pricing tier is sold by one provider, which keeps its own data on the
 * tier (a price, a product reference, the store that sells it; its choice).
 * This contract lets the admin edit that data and lets buyers see the offer.
 *
 * Channels: one provider may sell through several separate sellers, e.g.
 * the remote provider sells through each store's service account. A tier's
 * provider ID is then "<provider ID>:<channel>"; licenses issued from it
 * carry that full ID, so each channel's sale references are its own.
 *
 * The core ships the remote provider; every other provider is registered
 * by an extension (MonetizationRegistry).
 */
interface MonetizationProviderInterface extends ServiceProviderInterface {

	/**
	 * Get provider-wide settings schema, edited on the provider's settings page.
	 *
	 * @return array<string, array{
	 *  type: string,
	 *  label: string,
	 *  default: mixed,
	 *  description: string,
	 *  required: bool
	 * }>
	 */
	public function get_settings_schema() : array;

	/**
	 * Set provider configuration.
	 *
	 * @param array<string, mixed> $settings
	 * @return void
	 */
	public function set_settings( array $settings ) : void;

	/**
	 * Get the fields this provider keeps on a pricing tier, edited with the tier.
	 *
	 * Same shape as get_settings_schema(); a "select" field also has
	 * "options" (value => label).
	 *
	 * @param HostedAppsInterface $app The app the tier belongs to.
	 * @return array<string, array{
	 *  type: string,
	 *  label: string,
	 *  default: mixed,
	 *  description: string,
	 *  required: bool,
	 *  options?: array<string, string>
	 * }>
	 */
	public function get_tier_schema( HostedAppsInterface $app ) : array;

	/**
	 * Validate and normalize the tier fields an admin submitted.
	 *
	 * @param array<string, mixed> $input The submitted fields, keyed as in get_tier_schema().
	 * @param HostedAppsInterface  $app   The app the tier belongs to.
	 * @return array<string, mixed>|Exception The data to store on the tier, or why it is invalid
	 *                                        (with a "status" and, when it applies, a "field_id").
	 */
	public function prepare_tier_data( array $input, HostedAppsInterface $app ) : array|Exception;

	/**
	 * The channel a tier with this data is sold through.
	 *
	 * @param array<string, mixed> $data The tier's provider data (from prepare_tier_data()).
	 * @return string The channel; empty when the provider has no channels.
	 */
	public function get_channel( array $data ) : string;

	/**
	 * What buyers see of a tier this provider sells.
	 *
	 * Only this is ever shown publicly; the tier's provider data is not.
	 *
	 * @param PricingTier $tier The tier.
	 * @return array{price: string, checkout_url: string} A price as people read it ("$49 / year")
	 *                                                    and where to buy; each may be empty.
	 */
	public function get_offer( PricingTier $tier ) : array;
}
