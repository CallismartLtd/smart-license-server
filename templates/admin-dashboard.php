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
            <p><a href="<?php echo esc_url( smliser_repo_page() ) ?>"><span class="dashicons dashicons-plugins-checked"></span> Total Plugins: <?php echo absint( $stats->total_plugins() ); ?></a></p>
            <p><a href="<?php echo esc_url( smliser_license_page() )?>"><span class="dashicons dashicons-privacy"></span> Total Licenses: <?php echo absint( $stats->total_licenses() ); ?></a></p>
            <p title="Total plugin downloads served will update every hour." class="smliser-tooltip"><span class="dashicons dashicons-businessman"></span> Total Downloads Served: <?php echo absint( $stats->get_total_downloads_served() ); ?></p>
        </div>

        <div class="smliser-admin-dashboard-header-child">
            <a href="#plugin-api-route" class="smliser-div-link">
                <h2>Plugin Update Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$plugin_update ) ); ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$plugin_update ) ); ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$plugin_update ) ); ?></p>
            </a>
        </div>
        
        <div class="smliser-admin-dashboard-header-child">
            <a href="#license-activation-route" class="smliser-div-link">
                <h2>License Activation Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$license_activation ) ); ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$license_activation ) ); ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$license_activation ) ); ?></p>
            </a>

        </div>
        
        <div class="smliser-admin-dashboard-header-child">
            <a href="#license-deactivation-route" class="smliser-div-link">
                <h2>License Deactivation Route</h2>
                <p><span class="dashicons dashicons-rest-api"></span> Total Hits: <?php echo absint( $stats->get_total_hits( $stats::$license_deactivation ) ); ?></p>
                <p><span class="dashicons dashicons-visibility"></span> Unique Visitors:  <?php echo absint( $stats->get_unique_ips( $stats::$license_deactivation ) ); ?></p>
                <p><span class="dashicons dashicons-chart-bar"></span> Average Visits Today: <?php echo absint( $stats->total_visits_today( $stats::$license_deactivation ) ); ?></p>
            </a>
        </div>
       
    </div>

    <div class="smliser-admin-dashboard-subheader">
    <div class="smliser-admin-dashboard-subheader-items">
            <p>Total API Access: <?php echo absint( array_sum( $stats->get_requests_per_route() ) ); ?></p>
        </div>

        <div class="smliser-admin-dashboard-subheader-items">
            <p>All Visitors: <?php echo absint( $stats->get_unique_ips_count() ); ?></p>
        </div>

        <div class="smliser-admin-dashboard-subheader-items">
            <p>Success Rate: <?php echo absint( ! empty( $status_codes ) ? $status_codes[200] : 0 ); ?></p>
        </div>

        <div class="smliser-admin-dashboard-subheader-items">
            <p>Declined Rate: <?php echo  absint( count( $error_codes ) ); ?></p>
        </div>

        <div class="smliser-admin-dashboard-subheader-items">
            <p title="<?php foreach( $stats->get_requests_per_user() as $ip => $request ): echo esc_attr( 'IP "'.$ip .'" = '. $request . ' | ' ); endforeach;?>" class="smliser-tooltip">Requests Per User: <?php echo  absint( array_sum( $stats->get_requests_per_user() ) ); ?></p>
        </div>

    </div>

    <h3>See the performances for each API Route</h3>
    <div class="smliser-admin-dashboard-body" id="plugin-api-route">
        <h3>Plugin Update API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <canvas id="pluginUpdateChart"></canvas>
            <p>Total hits: <?php echo esc_html( $stats->get_total_hits( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Total Visits today: <?php echo esc_html( $stats->total_visits_today( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Total Plugin Updates: <?php echo esc_html( $stats->get_total_downloads_served() ); ?></p><p>|</p>
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Unique IPs: <?php echo esc_html( $stats->get_unique_ips( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Daily Access Frequency: <?php echo esc_html( count( $stats->get_daily_access_frequency( $stats::$plugin_update ) ) ); ?></p>
            <br/><br/>
        </div>
    </div>

    <div class="smliser-admin-dashboard-body" id="license-activation-route">
        <h3>License Activation API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <canvas id="licenseActivationChart"></canvas>
            <p>Total hits: <?php echo esc_html( $stats->get_total_hits( $stats::$license_activation ) ); ?></p><p>|</p>
            <p>Total Visits today: <?php echo esc_html( $stats->total_visits_today( $stats::$license_activation ) ); ?></p><p>|</p>
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$license_activation ) ); ?></p><p>|</p>
            <p>Unique IPs: <?php echo esc_html( $stats->get_unique_ips( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Daily Access Frequency: <?php echo esc_html( count( $stats->get_daily_access_frequency( $stats::$license_activation ) ) ); ?></p>
            <br/><br/>
        </div>
    </div>
    
    <div class="smliser-admin-dashboard-body" id="license-deactivation-route">
        <h3>License Deactivation API Route</h3>
        <div class="smliser-admin-dashboard-body-item">
            <canvas id="licenseDeactivationChart"></canvas>
            <p>Total hits: <?php echo esc_html( $stats->get_total_hits( $stats::$license_deactivation ) ); ?></p><p>|</p>
            <p>Total Visits today: <?php echo esc_html( $stats->total_visits_today( $stats::$license_deactivation ) ); ?></p><p>|</p>
            <p>Average Access Time: <?php echo esc_html( $stats->get_average_time_between_visits( $stats::$license_deactivation ) ); ?></p><p>|</p>
            <p>Unique IPs: <?php echo esc_html( $stats->get_unique_ips( $stats::$plugin_update ) ); ?></p><p>|</p>
            <p>Daily Access Frequency: <?php echo esc_html( count( $stats->get_daily_access_frequency( $stats::$license_deactivation ) ) ); ?></p>
            <br/><br/>
        </div>
    </div>
</div>

<script type="text/javascript">
    window.smliserStats = {
        pluginUpdateHits: <?php echo wp_kses_post( smliser_safe_json_encode( $plugin_update_hits ) ); ?>,
        pluginDownloads: <?php echo wp_kses_post( smliser_safe_json_encode( $stats->get_total_downloads_served() ) ); ?>,
        
        licenseActivationHits: <?php echo wp_kses_post( smliser_safe_json_encode( $license_activation_hits ) ); ?>,
        licenseDeactivationHits: <?php echo wp_kses_post( smliser_safe_json_encode( $license_deactivation_hits ) ); ?>,
        
        pluginUpdateVisits: <?php echo wp_kses_post( smliser_safe_json_encode( $plugin_update_visits ) ); ?>,
        licenseActivationVisits: <?php echo wp_kses_post( smliser_safe_json_encode( $license_activation_visits ) ); ?>,
        licenseDeactivationVisits: <?php echo wp_kses_post( smliser_safe_json_encode( $license_deactivation_visits ) ); ?>,

        pluginUniqueVisitors: <?php echo wp_kses_post( smliser_safe_json_encode( $plugin_unique_visitors ) ) ?>,
        licenseActivationUniqueVisitors: <?php echo wp_kses_post( smliser_safe_json_encode( $license_activation_unique_visitors ) ); ?>,
        licenseDeactivationUniqueVisitors: <?php echo wp_kses_post( smliser_safe_json_encode( $license_deactivation_unique_visitors ) ); ?>,
    };
</script>