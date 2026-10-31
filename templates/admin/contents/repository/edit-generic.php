<?php
/**
 * Generic hosted app editor used when a type has no custom admin template.
 *
 * @var string $type_title
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $app
 */

defined( 'SMLISER_ROOT' ) || exit;

$title = sprintf( 'Edit %s: %s', $type_title, $app->get_name() );
$other_fields = [
	[
		'label' => __( 'Support URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_support_url',
			'value' => $app->get_support_url()?->url() ?? '',
			'class' => 'app-uploader-form-row',
		]
	],
	[
		'label' => __( 'Download URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_download_url',
			'value' => $app->get_custom_download_url()?->url() ?? '',
			'class' => 'app-uploader-form-row',
		]
	],
	[
		'label' => __( 'Homepage URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_homepage_url',
			'value' => $app->get_homepage()->url(),
			'class' => 'app-uploader-form-row',
		]
	],
	[
		'label' => __( 'Documentation URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_documentation_url',
			'value' => $app->get_meta( 'documentation_url', '' ),
			'class' => 'app-uploader-form-row',
		]
	],
	[
		'label' => __( 'App.json File', 'smliser' ),
		'input' => [
			'type'  => 'textarea',
			'name'  => 'app_json_content',
			'value' => smliser_safe_json_encode( $app->get_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'class'    => 'smliser-json-textarea',
				'readonly' => true,
			]
		]
	],
];
$assets = [];

include __DIR__ . '/uploader.php';
