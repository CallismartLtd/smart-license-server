<?php
/**
 * Base of the purchase request email templates.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Monetization\PurchaseRequest;

/**
 * An email about a purchase request (manual payment, LocalProvider).
 *
 * The body is rendered from the template's own blocks (get_blocks()), the
 * same blocks the email editor starts from, so the default email and an
 * edited one always say the same things.
 *
 * Every token this class and its subclasses add is HTML-escaped in
 * variables(), because tokens are put into HTML on both render paths: the
 * blocks here, and a template saved from the editor. Line breaks are kept,
 * so a reviewer's multi-line message reads as written.
 *
 * Tokens:
 *   {{buyer_name}}  The buyer's name.
 *   {{request_id}}  The purchase request number.
 *   {{product}}     What was bought: "<app> — <tier>".
 *   {{price}}       The price the buyer was shown.
 */
abstract class PurchaseRequestEmail extends EmailTemplate {

	/**
	 * @param PurchaseRequest $request The purchase request.
	 * @param string          $to      Recipient email address.
	 * @param string          $product What was bought: "<app> — <tier>".
	 */
	public function __construct(
		protected readonly PurchaseRequest $request,
		protected readonly string $to,
		protected readonly string $product
	) {}

	protected function recipient() : string {
		return $this->to;
	}

	/**
	 * The tokens of this template, HTML-escaped.
	 *
	 * @return array<string, string>
	 */
	protected function variables() : array {
		$own = array_merge(
			[
				'{{buyer_name}}' => $this->request->get_buyer_name(),
				'{{request_id}}' => (string) $this->request->get_id(),
				'{{product}}'    => $this->product,
				'{{price}}'      => $this->request->get_price() ?: 'N/A',
			],
			$this->request_variables()
		);

		return array_merge( parent::variables(), array_map( static fn ( $value ) : string => nl2br( \escHtml( (string) $value ), false ), $own ) );
	}

	/**
	 * The tokens a subclass adds, as plain text (variables() escapes them).
	 *
	 * @return array<string, string>
	 */
	protected function request_variables() : array {
		return [];
	}

	protected function body() : string {
		return $this->interpolate( $this->render_blocks( $this->get_blocks() ) );
	}

	/**
	 * A banner block for a reviewer's message, hidden when there is none.
	 *
	 * @param string $id      Block ID.
	 * @param string $token   The message token, e.g. "{{message}}".
	 * @param bool   $visible Whether there is a message.
	 * @return array<string, mixed>
	 */
	protected static function message_block( string $id, string $token, bool $visible ) : array {
		return [
			'id'        => $id,
			'type'      => 'banner',
			'tone'      => 'info',
			'content'   => $token,
			'editable'  => true,
			'removable' => true,
			'visible'   => $visible,
		];
	}

	/**
	 * A purchase request for previews.
	 *
	 * @param array<string, mixed> $data Fields to change.
	 * @return PurchaseRequest
	 */
	protected static function preview_request( array $data = [] ) : PurchaseRequest {
		return PurchaseRequest::from_array(
			array_merge(
				[
					'id'                => 1024,
					'tier_id'           => 1,
					'price'             => '₦25,000 / year',
					'buyer_name'        => 'Jane Doe',
					'buyer_email'       => 'jane@example.com',
					'payment_reference' => 'TRF-0042',
					'status'            => PurchaseRequest::STATUS_PENDING,
				],
				$data
			)
		);
	}
}