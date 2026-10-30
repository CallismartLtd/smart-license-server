<?php
/**
 * Purchase request message email template.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

use SmartLicenseServer\Monetization\PurchaseRequest;

/**
 * Sent to a buyer when a reviewer emails them a note about their request.
 *
 * Tokens, besides PurchaseRequestEmail's:
 *   {{message}} The reviewer's note.
 */
class PurchaseRequestMessageEmail extends PurchaseRequestEmail {

	/**
	 * @param PurchaseRequest $request The purchase request.
	 * @param string          $to      Recipient email address.
	 * @param string          $product What was bought.
	 * @param string          $message The reviewer's note; plain text.
	 */
	public function __construct(
		PurchaseRequest $request,
		string $to,
		string $product,
		private readonly string $message
	) {
		parent::__construct( $request, $to, $product );
	}

	public static function template_key() : string {
		return 'purchase_request_message';
	}

	protected function subject() : string {
		return sprintf( 'About your purchase request #%d', $this->request->get_id() );
	}

	protected function preheader() : string {
		return 'A message about your purchase request.';
	}

	protected function request_variables() : array {
		return [ '{{message}}' => $this->message ];
	}

	public function label() : string {
		return 'Purchase Request Message';
	}

	public function description() : string {
		return 'Sent to a buyer when a reviewer emails them a note while processing their purchase request.';
	}

	public static function preview() : static {
		return new static( static::preview_request(), 'jane@example.com', 'Demo Plugin — Yearly', "The receipt you sent is hard to read.\nPlease reply to this email with a clearer copy." );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => 'Hi {{buyer_name}},',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => 'We have a message about your purchase request #{{request_id}} for {{product}}:',
				'editable'  => true,
				'removable' => false,
			],
			static::message_block( 'message', '{{message}}', true ),
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => 'To answer, contact us at {{support_email}} and mention request #{{request_id}}.',
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
