<?php
/**
 * Update available email class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates\System
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

/**
 * Sent when a new release is published that automatic updates will not
 * install: they are off, or set to security releases only and this is a
 * regular release. Sent once per version.
 *
 * {{detail}} says why it is not installed automatically.
 */
class UpdateAvailableEmail extends UpdateEmail {

	/**
	 * {@inheritdoc}
	 */
	public static function template_key(): string {
		return 'system_update_available';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function subject(): string {
		return $this->security
			? sprintf( 'Security update: %s %s is available', \SMLISER_APP_NAME, $this->new_version )
			: sprintf( '%s %s is available', \SMLISER_APP_NAME, $this->new_version );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function preheader(): string {
		return $this->security
			? sprintf( 'Version %s fixes a security issue. This site runs %s; install the update soon.', $this->new_version, $this->current_version )
			: sprintf( 'This site runs %s. Review and install %s from the Updates page.', $this->current_version, $this->new_version );
	}

	/**
	 * {@inheritdoc}
	 */
	public function label(): string {
		return 'Update Available';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return 'Sent to the site administration email when a new release is out that automatic updates will not install.';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function preview(): static {
		return new static(
			'preview@example.com',
			'0.4.1',
			'0.4.2',
			true,
			'https://example.com/admin/tools/updates',
			'Automatic updates are off on this site.'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_blocks(): array {
		return array(
			array(
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => 'An update is available for {{app_name}}',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => $this->security ? 'warning' : 'info',
				'content'   => $this->security
					? 'Version {{new_version}} is a security release. Install it as soon as possible.'
					: 'Version {{new_version}} of {{product_name}} is available.',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'      => 'reason',
				'type'    => 'text',
				'content' => '{{detail}} Review the release and install it from the Updates page.',
			),
			array(
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => array(
					array( 'label' => 'Installed', 'value' => '{{current_version}}' ),
					array( 'label' => 'Available', 'value' => '{{new_version}}' ),
					array( 'label' => 'Release Type', 'value' => '{{release_type}}' ),
				),
			),
			array(
				'id'    => 'button',
				'type'  => 'button',
				'label' => 'Review and Install',
				'url'   => '{{updates_url}}',
			),
			$this->closing_block(),
		);
	}
}