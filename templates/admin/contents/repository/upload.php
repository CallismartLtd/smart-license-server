<?php
/**
 * Admin software upload instruction page template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @var string $title
 * @var array $app_action
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var array $app_type_options
 */

defined( 'SMLISER_ROOT' ) || exit; ?>

<div class="application-uploader-page">
    <?php smliser_print_admin_content_header(
    	[
    		'breadcrumbs' => [
    			[
    				'label' => 'Repository',
    				'url'   => $urlmanager->admin_repo_url(),
    				'icon'  => 'ti ti-home-filled'
    			],

    			[
    				'label' => $title
    			]
    		],
    		'actions' => [
    			$app_action,
    			[
    				'title' => 'Settings',
    				'label' => 'Settings',
    				'url'   => $urlmanager->admin_options_url(),
    				'icon'  => 'ti ti-settings'
    			]
    		]
    	]
    ); ?>
    <div class="smliser-admin-page smliser-upload-page ">

        <header class="smliser-page-header">
            <h1 class="smliser-page-title">Upload a Hosted App</h1>
            <p class="smliser-page-subtitle">
                Choose the app type you want to upload to this repository.
                Before you proceed, there are a couple of things to keep in mind.
            </p>
        </header>

        <section class="smliser-upload-instructions notice notice-info">
            <h2 class="smliser-instructions-title">Before you upload</h2>
            <p>
                Prepare the app package (WordPress plugins, themes, or any registered application type)
                you want to upload and compress it into a ZIP file.
            </p>
        </section>

        <section class="smliser-upload-types">
            <h2 class="smliser-upload-types-title">Select app type</h2>
            <p class="smliser-upload-types-subtitle">
                Click one of the options below to start uploading your app.
            </p>

            <div class="smliser-upload-cards">
                <?php foreach ( $app_type_options as $option ) : ?>
                    <a class="smliser-upload-card" href="<?php echo escUrl( $option['url'] ); ?>">
                        <i class="<?php echo escAttr( $option['icon'] ); ?>" aria-hidden="true"></i>
                        <strong class="smliser-upload-card-title"><?php echo escHtml( $option['label'] ); ?></strong>
                        <span class="smliser-upload-card-description"><?php echo escHtml( $option['description'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</div>