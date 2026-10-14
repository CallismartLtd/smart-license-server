<?php
/**
 * Softwae edit file prepares the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\HostedApps\Software $app
 */

defined( 'SMLISER_ROOT' ) || exit;

$title = sprintf( 'Edit Software: %s', $app->get_name() );

$other_fields = [
	[
		'label' => __( 'Support URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_support_url',
			'value' => $app->get_support_url(),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'spellcheck'  => 'off',
				'placeholder' => 'Optional – link to support page'
			]
		]
	],
	[
		'label' => __( 'Download URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_download_url',
			'value' => $app->get_download_url(),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'spellcheck'  => 'off',
				'placeholder' => 'Optional – leave empty to use server download'
			]
		]
	],
	[
		'label' => __( 'Homepage URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_homepage_url',
			'value' => $app->get_homepage(),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'spellcheck'  => 'off',
				'placeholder' => 'Optional – link to software homepage'
			]
		]
	],
	[
		'label' => __( 'Documentation URL', 'smliser' ),
		'input' => [
			'type'  => 'text',
			'name'  => 'app_documentation_url',
			'value' => $app->get_meta( 'documentation_url' ),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'spellcheck'  => 'off',
				'placeholder' => 'Optional – link to software documentation'
			]
		]
	],
	[
		'label' => __( 'App.json File', 'smliser' ),
		'input' => [
			'type'  => 'textarea',
			'name'  => 'app_json_content',
			'value' => smliser_safe_json_encode(
				$app->get_manifest(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			),
			'class' => 'app-uploader-form-row',
			'attr'  => [
				'class'    => 'smliser-json-textarea',
				'readonly' => true
			]
		]
	],
];


$screenshots = $app->get_screenshots();

$assets = [
	'icon' => [
		'title'  => 'Icons',
		'images' => $app->get_icons(),
		'total'  => count( array_filter( $app->get_icons() ) )
	],
	'cover' => [
		'title'  => 'Cover',
		'images' => [$app->get_cover()],
		'total'  => count( array_filter( (array) $app->get_cover() ) )
	],
	'screenshot' => [
		'title'  => 'Screenshots',
		'images' => $screenshots,
		'total'  => count( array_filter( $screenshots ) )
	],
];

include __DIR__ . '/uploader.php';
