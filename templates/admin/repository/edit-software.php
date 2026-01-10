<?php
/**
 * Softwae edit file prepares the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\HostedApps\Software $app
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$title  = sprintf( 'Edit Software: %s', $app->get_name() );

$other_fields = array(
    array(
        'label' => __( 'App.json File', 'smliser' ),
        'input' => array(
            'type'  => 'textarea',
            'name'  => 'app_json_content',
            'value' => smliser_safe_json_encode(
                $app->get_manifest(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'class' => 'smliser-json-textarea',
                'readonly' => true
            )
        )
    ),

    array(
        'label' => __( 'Support URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_support_url',
            'value' => $app->get_support_url(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete' => 'off',
                'spellcheck'   => 'off',
                'placeholder'  => 'Optional – link to support page'
            )
        )
    ),
    array(
        'label' => __( 'Download URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_download_url',
            'value' => $app->get_download_url(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete' => 'off',
                'spellcheck'   => 'off',
                'placeholder'  => 'Optional – leave empty to use server download'
            )
        )
    ),
    array(
        'label' => __( 'Homepage URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_homepage_url',
            'value' => $app->get_homepage(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete' => 'off',
                'spellcheck'   => 'off',
                'placeholder'  => 'Optional – link to software homepage'
            )
        )
    ),
    array(
        'label' => __( 'Documentation URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_documentation_url',
            'value' => $app->get_meta( 'documentation_url' ),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete' => 'off',
                'spellcheck'   => 'off',
                'placeholder'  => 'Optional – link to software documentation'
            )
        )
    ),
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