<?php
/**
 * Update failed email class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates\System
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

/**
 * Sent when a queued update or rollback did not complete, whether it was
 * started automatically or from the Updates page.
 *
 * {{detail}} holds the end of the update's output.
 */
class UpdateFailedEmail extends UpdateEmail {

	/**
	 * Constructor.
	 *
	 * @param string $to              Recipient (the site administration email).
	 * @param string $current_version Version installed when the attempt started.
	 * @param string $new_version     Version the attempt was for.
	 * @param bool   $security        Whether it is a security release.
	 * @param string $updates_url     URL of the admin Updates page.
	 * @param string $detail          The end of the update's output.
	 * @param string $action          "install" or "rollback".
	 */
	public function __construct(
		string $to,
		string $current_version,
		string $new_version,
		bool $security,
		string $updates_url,
		string $detail = '',
		protected readonly string $action = 'install'
	) {
		parent::__construct( $to, $current_version, $new_version, $security, $updates_url, $detail );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function template_key(): string {
		return 'system_update_failed';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function subject(): string {
		return 'rollback' === $this->action
			? sprintf( 'Restoring %s %s failed', \SMLISER_APP_NAME, $this->new_version )
			: sprintf( '%s to %s %s failed', $this->security ? 'Security update' : 'Update', \SMLISER_APP_NAME, $this->new_version );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function preheader(): string {
		return 'rollback' === $this->action
			? 'The rollback did not complete. Check the Updates page.'
			: sprintf( 'The update to %s did not complete. Check the Updates page to see where the site stands.', $this->new_version );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function variables(): array {
		return array_merge(
			parent::variables(),
			array(
				'{{failure_summary}}' => 'rollback' === $this->action
					? sprintf( 'Restoring version %s did not complete.', htmlspecialchars( $this->new_version, ENT_QUOTES, 'UTF-8' ) )
					: sprintf( 'The update to %s did not complete.', htmlspecialchars( $this->new_version, ENT_QUOTES, 'UTF-8' ) ),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function label(): string {
		return 'Update Failed';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return 'Sent to the site administration email when a queued update or rollback did not complete.';
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
			"Checking for the latest version...\nThe package signature is missing or invalid; it may not come from the publisher. Nothing was changed."
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
				'content'   => 'An update on {{app_name}} needs your attention',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'error',
				'content'   => '{{failure_summary}}',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'      => 'next',
				'type'    => 'text',
				'content' => 'The Updates page shows which version the site is running now and what to do next. The end of the update output is below.',
			),
			array(
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => array(
					array( 'label' => 'Version Before', 'value' => '{{current_version}}' ),
					array( 'label' => 'Target Version', 'value' => '{{new_version}}' ),
					array( 'label' => 'Release Type', 'value' => '{{release_type}}' ),
					array( 'label' => 'Occurred At', 'value' => '{{occurred_at}}' ),
				),
			),
			array(
				'id'      => 'output',
				'type'    => 'text',
				'content' => '{{detail}}',
			),
			array(
				'id'    => 'button',
				'type'  => 'button',
				'label' => 'Open the Updates Page',
				'url'   => '{{updates_url}}',
			),
			$this->closing_block(),
		);
	}
}