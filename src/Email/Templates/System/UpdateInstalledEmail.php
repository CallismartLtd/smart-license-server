<?php
/**
 * Update installed email class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates\System
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

use SmartLicenseServer\Environments\Application\Update\UpdateService;

/**
 * Sent after an automatic update installed a new release.
 *
 * Updates an administrator starts from the Updates page or the console are
 * watched as they happen, so they send nothing on success.
 */
class UpdateInstalledEmail extends UpdateEmail {

	/**
	 * {@inheritdoc}
	 */
	public static function template_key(): string {
		return 'system_update_installed';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function subject(): string {
		return sprintf( '%s was updated to %s', \SMLISER_APP_NAME, $this->new_version );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function preheader(): string {
		return sprintf( 'Automatic update from %s to %s completed. The previous version can be restored for %d days.', $this->current_version, $this->new_version, UpdateService::BACKUP_DAYS );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function variables(): array {
		return array_merge( parent::variables(), array( '{{backup_days}}' => (string) UpdateService::BACKUP_DAYS ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function label(): string {
		return 'Update Installed';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return 'Sent to the site administration email after an automatic update installed a new release.';
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
			'',
			array(
				'source'    => 'https://updates.example.com/downloads/software/smart-license-server/artifacts/smart-license-server-0.4.2-standalone.zip',
				'sha256'    => str_repeat( 'a1b2c3d4', 8 ),
				'signed_by' => 'release',
			)
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
				'content'   => '{{app_name}} was updated',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'success',
				'content'   => '{{product_name}} was updated automatically from {{current_version}} to {{new_version}}.',
				'editable'  => true,
				'removable' => false,
			),
			array(
				'id'      => 'backup',
				'type'    => 'text',
				'content' => 'A backup of version {{current_version}} is kept for {{backup_days}} days. If anything is wrong after the update, you can restore it from the Updates page.',
			),
			array(
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => array(
					array( 'label' => 'Previous Version', 'value' => '{{current_version}}' ),
					array( 'label' => 'New Version', 'value' => '{{new_version}}' ),
					array( 'label' => 'Release Type', 'value' => '{{release_type}}' ),
					array( 'label' => 'Signed By', 'value' => '{{signed_by}}' ),
				),
			),
			array(
				'id'      => 'source',
				'type'    => 'text',
				'content' => 'The package was downloaded from {{package_source}} and verified before it was installed.',
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