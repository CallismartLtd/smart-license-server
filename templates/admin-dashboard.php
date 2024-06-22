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
<div class="smliser-admin-dasboard-wrapper" id="smliser-admin-dasboard-wrapper">
    <div class="smliser-admin-dashboard-header"> 
        <div class="smliser-admin-dashboard-header-child">
            <h2>Repository</h2>
            <p><a href="<?php echo esc_url( smliser_repo_page() ) ?>"><span class="dashicons dashicons-plugins-checked"></span> Total Plugins: <?php echo absint( $stats->total_plugins() ) ?></a></p>
            <p><a href="<?php echo esc_url( smliser_license_page() )?>"><span class="dashicons dashicons-privacy"></span> Total Licenses: <?php echo absint( $stats->total_licenses() ) ?></a></p>
            <p title="Total plugin downloads served will update every hour." class="smliser-tooltip"><span class="dashicons dashicons-businessman"></span> Total Downloads Served: <?php echo absint( $stats->get_total_downloads_served() ) ?></p>
        </div>

        <div class="smliser-admin-dashboard-header-child">
            <a href="#" class="smliser-div-link">
                <h2>Plugin Update Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$plugin_update ) ) ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$plugin_update ) ) ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$plugin_update ) ) ?></p>
            </a>
        </div>
        
        <div class="smliser-admin-dashboard-header-child" >
            <a href="#" class="smliser-div-link">
                <h2>License Activation Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$license_activation ) ) ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$license_activation ) ) ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$license_activation ) ) ?></p>
            </a>

        </div>
        
        
        <div class="smliser-admin-dashboard-header-child">
            <a href="#" class="smliser-div-link">
                <h2>License Deactivation Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$license_deactivation ) ) ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$license_deactivation ) ) ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$license_deactivation ) ) ?></p>
            </a>
        </div>
       
    </div>

    <h3>See the performances of each API Route</h3>
    <div class="smliser-admin-dashboard-body">
        <h3>Plugin Update API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$plugin_update ) ); ?></p>
            <canvas id="pluginUpdateChart"></canvas>
        </div>
    </div>

    <div class="smliser-admin-dashboard-body">
        <h3>License Activation API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$license_activation ) ); ?></p>
            <canvas id="licenseActivationChart"></canvas>
        </div>
    </div>
    
    <div class="smliser-admin-dashboard-body">
        <h3>License Deactivation API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$license_deactivation ) ); ?></p>
            <canvas id="licenseDeactivationChart"></canvas>
        </div>
    </div>
</div>

<script type="text/javascript">
    window.smliserStats = {
        pluginUpdateHits: <?php echo json_encode( $plugin_update_hits ); ?>,
        licenseActivationHits: <?php echo json_encode( $license_activation_hits ); ?>,
        licenseDeactivationHits: <?php echo json_encode( $license_deactivation_hits ); ?>,
        
        pluginUpdateVisits: <?php echo json_encode( $plugin_update_visits ); ?>,
        licenseActivationVisits: <?php echo json_encode( $license_activation_visits ); ?>,
        licenseDeactivationVisits: <?php echo json_encode( $license_deactivation_visits ); ?>,

        pluginUniqueVisitors: <?php echo json_encode( $plugin_unique_visitors ) ?>,
        licenseActivationUniqueVisitors: <?php echo json_encode( $license_activation_unique_visitors ) ?>,
        licenseDeactivationUniqueVisitors: <?php echo json_encode( $license_deactivation_unique_visitors ) ?>,
    };
</script>