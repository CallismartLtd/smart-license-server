<?php
/**
 * Theme edit file prepares the variables that will be used by the uploader.php
 * 
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\HostedApps\Theme $app
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$title          = sprintf( 'Edit Theme: %s', $app->get_name() );
$other_fields   = array(
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
                'readonly' => true,
                'data-editor-description' => 'Edit the theme\'s app.json file. Certain fields are automatically generated from the theme style.css file and cannot be overridden here.'
            )
        )
    ),
    
    array(
        'label' => __( 'Required PHP Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_php_version',
            'value' => $app->get_required_php(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use theme style.css file to edit the minimum PHP version required to install this theme'
            )
        )
    ),

    array(
        'label' => __( 'Required WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_required_wp_version',
            'value' => $app->requires_at_least(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use theme style.css file to edit the minimum WordPress version required to install this theme'
            )
        )
    ),

    array(
        'label' => __( 'Tested WordPress Version', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_tested_wp_version',
            'value' => $app->get_tested_up_to(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off',
                'readonly'      => true,
                'title'         => 'Use theme style.css file to edit the WordPress version the theme has been tested up to'
            )
        )
    ),
    
    array(
        'label' => __( 'Theme Homepage', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_homepage_url',
            'value' => $app->get_homepage(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'External Repository URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_external_repository_url',
            'value' => $app->get_meta( 'external_repository_url' ),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Theme Preview URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_preview_url',
            'value' => $app->get_meta( 'preview_url' ),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Theme Support URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_support_url',
            'value' => $app->get_support_url(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                // 'autocomplete'  => 'on',
                'spellcheck'    => 'off'
            )
        )
    ),

    array(
        'label' => __( 'Theme Download URL', 'smliser' ),
        'input' => array(
            'type'  => 'text',
            'name'  => 'app_download_url',
            'value' => $app->get_download_url(),
            'class' => 'app-uploader-form-row',
            'attr'  => array(
                'autocomplete'  => 'off',
                'spellcheck'    => 'off'
            )
        )
    ),
);

$screenshots = $app->get_screenshots();

$assets = array(
    'screenshot' => array(
        'title'     => 'Screenshot',
        'images'    => $app->get_screenshot_url() ? [$app->get_screenshot_url()] : [],
        'total'     => $app->get_screenshot_url() ? 1 : 0
    ),

    'screenshots' => array(
        'title'     => 'Additional Screenshots',
        'images'    => $screenshots,
        'total'    => count( array_filter( $screenshots ) )
    ),
);

include SMLISER_PATH . 'templates/admin/repository/uploader.php';