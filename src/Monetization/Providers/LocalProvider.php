<?php
/**
 * Local Provider class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization\Providers;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\Monetization\PricingTier;

/**
 * Sells by manual payment, with no store and no extension.
 *
 * The app never touches money. A tier this provider sells shows its price
 * and the seller's payment instructions on a purchase page. The buyer pays
 * outside the app and submits a purchase request with the receipt; an
 * admin checks the payment and approves the request, which issues the
 * license, or rejects it. So the app can sell from day one.
 *
 * Its licenses carry the provider ID "local" and the reference
 * "request-<purchase request ID>". It has no channels.
 */
class LocalProvider implements MonetizationProviderInterface {

	/**
	 * Path of the purchase page; the tier ID follows it.
	 *
	 * @var string
	 */
	public const PURCHASE_PATH = 'purchase';

	/**
	 * @param URLManager $urlmanager
	 */
	public function __construct(
		protected URLManager $urlmanager
	) {}

	/**
	 * {@inheritdoc}
	 */
	public static function get_id() : string {
		return 'local';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_name() : string {
		return 'Manual payment';
	}

	/**
	 * {@inheritdoc}
	 *
	 * The local provider has no provider-wide settings; each tier carries
	 * its own payment instructions.
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
		return [
			'price' => [
				'type'        => 'text',
				'label'       => 'Price',
				'default'     => '',
				'description' => 'The price as buyers read it, e.g. "₦25,000 / year". Shown as is; buyers pay this amount.',
				'required'    => true,
			],
			'payment_instructions' => [
				'type'        => 'textarea',
				'label'       => 'Payment instructions',
				'default'     => '',
				'description' => 'How to pay, shown on the purchase page: e.g. the bank, account name and account number. Buyers then upload their receipt.',
				'required'    => true,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare_tier_data( array $input, HostedAppsInterface $app ) : array|Exception {
		$price        = trim( strip_tags( (string) ( $input['price'] ?? '' ) ) );
		$instructions = trim( strip_tags( str_replace( "\r\n", "\n", (string) ( $input['payment_instructions'] ?? '' ) ) ) );

		if ( '' === $price ) {
			return new Exception( 'missing_price', 'Enter the price buyers pay for this tier.', [ 'status' => 400, 'field_id' => 'price' ] );
		}

		if ( '' === $instructions ) {
			return new Exception( 'missing_payment_instructions', 'Tell buyers how to pay for this tier.', [ 'status' => 400, 'field_id' => 'payment_instructions' ] );
		}

		return [
			'price'                => $price,
			'payment_instructions' => $instructions,
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * The local provider has no channels.
	 */
	public function get_channel( array $data ) : string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 *
	 * The checkout URL is the tier's purchase page.
	 */
	public function get_offer( PricingTier $tier ) : array {
		return [
			'price'        => (string) ( $tier->get_provider_data()['price'] ?? '' ),
			'checkout_url' => $this->get_purchase_url( $tier )->url(),
		];
	}

	/**
	 * The payment instructions buyers follow for a tier.
	 *
	 * @param PricingTier $tier
	 * @return string Plain text.
	 */
	public function get_payment_instructions( PricingTier $tier ) : string {
		return (string) ( $tier->get_provider_data()['payment_instructions'] ?? '' );
	}

	/**
	 * The purchase page of a tier.
	 *
	 * @param PricingTier $tier
	 * @return URL
	 */
	public function get_purchase_url( PricingTier $tier ) : URL {
		return $this->urlmanager->url( sprintf( '%s/%d', self::PURCHASE_PATH, $tier->get_id() ) );
	}

	/**
	 * Where the purchase form of a tier posts.
	 *
	 * @param PricingTier $tier
	 * @return URL
	 */
	public function get_submit_url( PricingTier $tier ) : URL {
		return $this->urlmanager->url( sprintf( '%s/%d/submit', self::PURCHASE_PATH, $tier->get_id() ) );
	}
}