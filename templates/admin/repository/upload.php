<?php
/**
 * Admin software upload instruction page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-page smliser-upload-page">

    <header class="smliser-page-header">
        <h1 class="smliser-page-title">Upload Software</h1>
        <p class="smliser-page-subtitle">
            Choose the software type you want to upload to this repository. 
            Before you proceed, there are a couple of things to keep in mind.
        </p>
    </header>

    <section class="smliser-upload-instructions notice notice-info">
        <h2 class="smliser-instructions-title">Before you upload</h2>
        <p>
            Prepare the software (WordPress plugins, themes, or any other application type) 
            you want to upload and compress it into a ZIP file.
        </p>
    </section>

    <section class="smliser-upload-types">
        <h2 class="smliser-upload-types-title">Select software type</h2>
        <p class="smliser-upload-types-subtitle">
            Click one of the options below to start uploading your software.
        </p>

        <div class="smliser-upload-cards">
            <a class="smliser-upload-card" href="<?php echo smliser_admin_repo_tab( 'add-new', 'plugin' ); ?>">
                <strong class="smliser-upload-card-title">WordPress Plugin</strong>
                <span class="smliser-upload-card-description">Upload a plugin for WordPress sites</span>
            </a>

            <a class="smliser-upload-card" href="<?php echo smliser_admin_repo_tab( 'add-new', 'theme' ); ?>">
                <strong class="smliser-upload-card-title">WordPress Theme</strong>
                <span class="smliser-upload-card-description">Upload a theme for WordPress sites</span>
            </a>

            <a class="smliser-upload-card" href="<?php echo smliser_admin_repo_tab( 'add-new', 'software' ); ?>">
                <strong class="smliser-upload-card-title">Other Software</strong>
                <span class="smliser-upload-card-description">Upload any other type of application</span>
            </a>
        </div>
    </section>

</div>
