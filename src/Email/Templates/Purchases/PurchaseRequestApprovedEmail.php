<?php
/**
 * Purchase request approved email template.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\PurchaseRequest;

/**
 * Sent to a buyer when their purchase request is approved: the license.
 *
 * Carries the license key and terms, the reviewer's message when there is
 * one, and, for a buyer who asked for an account, a link to sign up with
 * their address (the license joins the account once the address is
 * verified). The license document is attached.
 *
 * Tokens, besides PurchaseRequestEmail's:
 *   {{license_key}}      The license key.
 *   {{start_date}}       When the license starts.
 *   {{end_date}}         When it ends; "Never (lifetime)" for a lifetime license.
 *   {{activation_limit}} Sites it can be activated on; "Unlimited" when unlimited.
 *   {{message}}          The reviewer's message; may be empty.
 *   {{signup_url}}       The sign-up link; empty unless the buyer asked for an account.
 */
class PurchaseRequestApprovedEmail extends PurchaseRequestEmail {

	/**
	 * @param PurchaseRequest $request    The approved purchase request.
	 * @param string          $to         Recipient email address.
	 * @param string          $product    What was bought.
	 * @param License         $license    The license issued.
	 * @param string          $signup_url Sign-up link for a buyer who asked for an account; empty otherwise.
	 */
	public function __construct(
		PurchaseRequest $request,
		string $to,
		string $product,
		private readonly License $license,
		private readonly string $signup_url = ''
	) {
		parent::__construct( $request, $to, $product );
	}

	public static function template_key() : string {
		return 'purchase_request_approved';
	}

	protected function subject() : string {
		return sprintf( 'Your license for %s', $this->product );
	}

	protected function preheader() : string {
		return 'Your payment is confirmed. Your license key and its terms are inside.';
	}

	protected function request_variables() : array {
		$sites = $this->license->get_max_allowed_domains();

		return [
			'{{license_key}}'      => $this->license->get_license_key(),
			'{{start_date}}'       => $this->license->get_start_date()?->format( \smliser_datetime_format() ) ?? 'N/A',
			'{{end_date}}'         => $this->license->get_end_date()?->format( \smliser_datetime_format() ) ?? 'Never (lifetime)',
			'{{activation_limit}}' => -1 === (int) $sites ? 'Unlimited' : (string) $sites,
			'{{message}}'          => $this->request->get_review_note(),
			'{{signup_url}}'       => $this->signup_url,
		];
	}

	/**
	 * The message, with the license document attached.
	 *
	 * @return EmailMessage|null Null when the template is disabled.
	 */
	public function to_message() : ?EmailMessage {
		$message = parent::to_message();

		$message?->set(
			'attachments',
			[
				[
					'type'     => 'base64',
					'content'  => base64_encode( $this->license->to_document() ),
					'filename' => sprintf( 'license-document-%d.txt', $this->license->get_id() ),
					'mime'     => 'text/plain',
				],
			]
		);

		return $message;
	}

	public function label() : string {
		return 'Purchase Request Approved';
	}

	public function description() : string {
		return 'Sent to a buyer when their purchase request is approved: the license key and terms, with the license document attached.';
	}

	public static function preview() : static {
		$license = License::from_array(
			[
				'id'                  => 77,
				'licensee_fullname'   => 'Jane Doe',
				'license_key'         => 'SMLISER-XXXX-XXXX-XXXX',
				'start_date'          => gmdate( 'Y-m-d' ),
				'end_date'            => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'max_allowed_domains' => 2,
			]
		);

		return new static(
			static::preview_request( [ 'status' => 'approved', 'review_note' => 'Thanks for your purchase!', 'license_id' => 77 ] ),
			'jane@example.com',
			'Demo Plugin — Yearly',
			$license,
			'https://example.com/auth/signup?email=jane%40example.com'
		);
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
				'content'   => 'Your payment for {{product}} is confirmed. Here is your license; the license document is attached. Please keep them safe.',
				'editable'  => true,
				'removable' => false,
			],
			static::message_block( 'message', '{{message}}', '' !== $this->request->get_review_note() ),
			[
				'id'        => 'details',
				'type'      => 'detail_card',
				'rows'      => [
					[ 'label' => 'License Key', 'value' => '{{license_key}}' ],
					[ 'label' => 'Start Date', 'value' => '{{start_date}}' ],
					[ 'label' => 'Expiry Date', 'value' => '{{end_date}}' ],
					[ 'label' => 'Activation Limit', 'value' => '{{activation_limit}} site(s)' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'signup_intro',
				'type'      => 'text',
				'content'   => 'You asked for an account. Sign up with this email address and verify it; this license then appears in your account.',
				'editable'  => true,
				'removable' => true,
				'visible'   => '' !== $this->signup_url,
			],
			[
				'id'        => 'signup',
				'type'      => 'button',
				'label'     => 'Create my account',
				'url'       => '{{signup_url}}',
				'editable'  => true,
				'removable' => true,
				'visible'   => '' !== $this->signup_url,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => 'If you have a question about your license, contact us at {{support_email}} and mention request #{{request_id}}.',
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
