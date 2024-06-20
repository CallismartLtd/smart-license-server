<?php
/**
 * File name admin-dashboard.php
 * The plugin admin page template file.
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;
add_filter( 'wp_kses_allowed_html', 'smliser_allowed_html', 10 , 2 );
?>
<h1>Dashboard</h1>
<div class="smliser-admin-dasboard-wrapper">
    <div class="smliser-admin-dashboard-header"> 
        <div class="smliser-admin-dashboard-header-child">
            <h2>Repository Info</h2>
            <p><a href="<?php echo esc_url( smliser_repo_page() ) ?>"><span class="dashicons dashicons-plugins-checked"></span> Total Plugins: <?php echo absint( $stats->total_plugins() ) ?></a></p>
            <p><span class="dashicons dashicons-yes-alt"></span> Total Licenses: </p>
            <p><span class="dashicons dashicons-businessman"></span> Client: </a></p>
        </div>

        <div class="smliser-admin-dashboard-header-child">
            <h2>Statistics</h2>
            <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Sites: </p>
            <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Sites Activated: </p>
            <p><span class="dashicons dashicons-plugins-checked"></span> Item ID: </p>
        </div>

        <div class="smliser-admin-dashboard-header-child">
            <h2>Statistics</h2>
            <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Sites: </p>
            <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Sites Activated: </p>
            <p><span class="dashicons dashicons-plugins-checked"></span> Item ID: </p>
        </div>

        <div class="smliser-admin-dashboard-header-child">
            <h2>Statistics</h2>
            <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Sites: </p>
            <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Sites Activated: </p>
            <p><span class="dashicons dashicons-plugins-checked"></span> Item ID: </p>
        </div>


    </div>
    
    <div class="smliser-admin-view-page-body">
        <div class="smliser-admin-view-page-body-item">
            <p>License ID: </p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Plugin Name: <p><a href=""></a></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Service ID: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Start Date: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>End Date: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Active on: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>License Key: </p></p>
        </div>
    </div>

    <div class="smliser-admin-view-page-body">
        <div class="smliser-admin-view-page-body-item">
            <p>License ID: </p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Plugin Name: <p><a href=""></a></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Service ID: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Start Date: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>End Date: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Active on: <p></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>License Key: </p></p>
        </div>
    </div>
</div>