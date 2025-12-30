<?php
/**
 * Softwae edit file prepares the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\HostedApps\Software $app
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$title  = sprintf( 'Edit Software: %s', $app->get_name() );

$other_fields   = array(
    array(
        'label' => __( 'Required PHP Version', 'smliser' ),
        'input' => array(
            'type'  => 'textarea',
            'name'  => 'app_required_php_version',
            'value' => smliser_safe_json_encode( $app->get_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                // 'readonly'      => true,
                'class'         => 'smliser-json-textarea',
                'title'         => 'Use plugin readme.txt file to edit the plugin\'s required PHP version.'
            )
        )
    )
);

$screenshots = $app->get_screenshots();

$assets = array(
    'icon' => array(
        'title'     => 'Icons',
        'limit'     => 2,
        'images'    => $app->get_icons(),
        'total'     => count( array_filter( $app->get_icons() ) )
    ),
    'cover' => array(
        'title'     => 'Cover',
        'limit'     => 1,
        'images'    => [$app->get_cover()],
        'total'     => count( array_filter( (array) $app->get_cover() ) )
    ),
    'screenshot' => array(
        'title'     => 'Screenshots',
        'limit'     => 10,
        'images'    => $screenshots,
        'total'     => count( array_filter( $screenshots ) )
    ),
);

include SMLISER_PATH . 'templates/admin/repository/uploader.php';