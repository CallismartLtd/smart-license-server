<?php
/**
 * Plugin edit file prepare the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 */

defined( 'ABSPATH' ) || exit;

$title          = sprintf( 'Edit %s', $app->get_name() );
$other_fields   = array(
    array(
        'label' => __( 'Required PHP Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_php_version',
            'value' => $app->get_required_php(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Required WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_wp_version',
            'value' => $app->get_required(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Tested WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_tested_wp_version',
            'value' => $app->get_tested(),
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
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

$assets = array( 'banner' => $app->get_banners(), 'screenshot' => $app->get_screenshots() );

include SMLISER_PATH . 'templates/admin/repository/uploader.php';