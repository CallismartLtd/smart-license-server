<?php
/**
 * Plugin edit file prepare the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 * @var \SmartLicenseServer\HostedApps\Plugin $app
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$title          = sprintf( 'Edit Plugin: %s', $app->get_name() );
$other_fields   = array(
    array(
        'label' => __( 'Required PHP Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_php_version',
            'value' => $app->get_required_php(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use plugin readme.txt file to edit the plugin\'s required PHP version.'
            )
        )
    ),

    array(
        'label' => __( 'Required WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_wp_version',
            'value' => $app->get_requires_at_least(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use plugin readme.txt file to edit the minimum WordPress version required to install the plugin'
            )
        )
    ),

    array(
        'label' => __( 'Tested WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_tested_wp_version',
            'value' => $app->get_tested_up_to(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use plugin the readme.txt file to edit the WordPress version this plugin has been tested up to.'
            )
        )
    ),

    array(
        'label' => __( 'Plugin Support URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_support_url',
            'value' => $app->get_support_url(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Plugin Download URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_download_url',
            'value' => $app->get_download_url(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Plugin Homepage', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_homepage_url',
            'value' => $app->get_homepage(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),


);

$screenshots = [];

foreach( $app->get_screenshots() as $screenshot ) {
    $screenshots[] = $screenshot['src'] ?? '';
}


$assets = array(
    'icon' => array(
        'title'     => 'Icons',
        'limit'     => 2,
        'images'    => $app->get_icons(),
        'total'    => count( array_filter( $app->get_icons() ) )
    ),
    'banner' => array(
        'title'     => 'Banners',
        'limit'     => 2,
        'images'    => $app->get_banners(),
        'total'     => count( array_filter( $app->get_banners() ) )
    ),
    'screenshot' => array(
        'title'     => 'Screenshots',
        'limit'     => 10,
        'images'    => $screenshots,
        'total'    => count( array_filter( $screenshots ) )
    ),
);


include SMLISER_PATH . 'templates/admin/repository/uploader.php';