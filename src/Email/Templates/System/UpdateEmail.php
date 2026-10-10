<?php
/**
 * Update email base class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates\System
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

use SmartLicenseServer\Email\Templates\EmailTemplate;

/**
 * What the update emails share: their data, tokens and plain-text body.
 *
 * Each update event is its own template (available, installed, failed), so
 * an administrator can customise or switch off each one separately, and a
 * custom template saved for one event is never sent for another.
 *
 * Token values are HTML-escaped here: interpolate() inserts them into the
 * rendered HTML as they are, and error messages may contain any character.
 *
 * Tokens:
 *   {{product_name}}     The application's name ("Smart License Server").
 *   {{current_version}}  The version installed before this event.
 *   {{new_version}}      The release the email is about.
 *   {{release_type}}     "Security release" or "Regular release".
 *   {{updates_url}}      The admin Updates page.
 *   {{occurred_at}}      When the email was produced (UTC).
 *   {{detail}}           Event-specific detail (error output, automatic-update mode...).
 *   {{package_source}}   Where the installed package was downloaded from.
 *   {{signed_by}}        The release key that signed it.
 */
abstract class UpdateEmail extends EmailTemplate {

	/**
	 * Constructor.
	 *
	 * @param string $to              Recipient (the site administration email).
	 * @param string $current_version Version installed before this event.
	 * @param string $new_version     Release the email is about.
	 * @param bool   $security        Whether it is a security release.
	 * @param string $updates_url     URL of the admin Updates page.
	 * @param string $detail          Event-specific detail, plain text.
	 * @param array  $package         {source, sha256, signed_by} of an installed package.
	 */
	public function __construct(
		protected readonly string $to,
		protected readonly string $current_version,
		protected readonly string $new_version,
		protected readonly bool $security,
		protected readonly string $updates_url,
		protected readonly string $detail = '',
		protected readonly array $package = array()
	) {}

	/**
	 * {@inheritdoc}
	 */
	protected function recipient(): string {
		return $this->to;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function variables(): array {
		$escape = static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );

		return array_merge(
			parent::variables(),
			array(
				'{{product_name}}'    => $escape( \SMLISER_APP_NAME ),
				'{{current_version}}' => $escape( $this->current_version ),
				'{{new_version}}'     => $escape( $this->new_version ),
				'{{release_type}}'    => $this->security ? 'Security release' : 'Regular release',
				'{{updates_url}}'     => $escape( $this->updates_url ),
				'{{occurred_at}}'     => gmdate( 'D, d M Y H:i' ) . ' UTC',
				'{{detail}}'          => nl2br( $escape( $this->detail ), false ),
				'{{package_source}}'  => $escape( (string) ( $this->package['source'] ?? 'not recorded' ) ),
				'{{signed_by}}'       => $escape( (string) ( $this->package['signed_by'] ?? 'not recorded' ) ),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Built from get_blocks(), so the default email and the editor show the same content.
	 */
	protected function body(): string {
		return $this->render_blocks( $this->get_blocks() );
	}

	/**
	 * {@inheritdoc}
	 *
	 * The blocks carry {{tokens}}, which only the HTML path interpolates;
	 * resolve them here too, and keep line breaks.
	 */
	public function text(): string {
		$html = $this->interpolate( $this->render_blocks( $this->get_blocks() ) );
		$html = preg_replace( '#<br\s*/?>\s*#i', "\n", $html );

		// Keep button links usable: "Label: https://...". Mailto links already show their address.
		$html = preg_replace( '#<a\s[^>]*href="(?!mailto:)([^"]*)"[^>]*>\s*(.*?)\s*</a>#is', '$2: $1', (string) $html );
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Collapse the indentation of the HTML blocks, keep paragraph breaks.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\s*\n\s*\n\s*/', "\n\n", (string) $text );

		return trim( (string) $text );
	}

	/**
	 * The closing block every update email ends with.
	 *
	 * @return array<string, mixed>
	 */
	protected function closing_block(): array {
		return array(
			'id'        => 'closing',
			'type'      => 'closing',
			'content'   => 'This is an automated message from {{app_name}} about its own software updates. Questions? Contact us at {{support_email}}.',
			'editable'  => true,
			'removable' => false,
		);
	}
}