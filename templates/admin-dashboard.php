<?php
/**
 * Admin Dashboard Template
 */
namespace SmartLicenseServer\Admin;

use SmartLicenseServer\HostedApps\HostedApplicationService;
use const SMLISER_APP_NAME;
defined( 'SMLISER_ABSPATH' ) || exit;
?>
<div class="smliser-admin-dashboard-template overview">
    <?php Menu::print_admin_top_menu(
        [
            'breadcrumbs'   => array(
                array(
                    'label' => SMLISER_APP_NAME
                )
            ),
            'actions'   => array(
                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        ]
    ); ?>

    <div class="smliser-admin-body">
        <div class="smliser-dashboard-hero">
            <div class="smliser-dashboard-hero_up">
                <h2>Overview</h2>
            </div>
            <div class="smliser-dashboard-hero_down">
                <?php foreach( $totals as $app_type => $value ) : ?>
                    <div class="smliser-dashboard-hero_down-item">
                        <div class="smliser-dashboard-hero_down-item-icon">
                            <img src="<?php echo esc_url( smliser_get_placeholder_icon( $app_type ) ); ?>" alt="">
                        </div>
                        <div class="smliser-dashboard-hero_down-item-content">
                            <span><?php echo esc_html( $value ); ?></span>
                            <span><?php echo esc_html( sprintf( 'Total %s', ucfirst( $app_type ) ) ); ?></span>
                        </div>

                    </div>

                <?php endforeach; ?>
            </div>
        </div>

        <!-- Metrics Content -->
        <div class="smliser-dashboard-content">
            <?php foreach ( $metrics as $domain_name => $domain_data ) : ?>
                <?php
                $summary = $domain_data['summary'] ?? [];
                $chart_data = $domain_data['chart_data'] ?? [];
                $rankings = $domain_data['rankings'] ?? [];
                $recent_logs = $domain_data['recent_logs'] ?? [];
                $domain_id = sanitize_title( $domain_name );
                ?>

                <div class="smliser-dashboard-content_item" data-domain="<?php echo esc_attr( $domain_id ); ?>">
                    <div class="smliser-domain-header">
                        <h3>
                            <i class="ti <?php echo esc_attr( get_domain_icon( $domain_name ) ); ?>"></i>
                            <?php echo esc_html( $domain_name ); ?>
                        </h3>
                    </div>

                    <div class="smliser-domain-body">
                        <!-- Summary Metrics -->
                        <?php if ( ! empty( $summary ) ) : ?>
                            <div class="smliser-summary-grid">
                                <?php foreach ( $summary as $key => $value ) : ?>
                                    <?php if ( ! is_array( $value ) ) : ?>
                                        <div class="smliser-summary-card">
                                            <div class="smliser-summary-card-icon">
                                                <i class="ti <?php echo esc_attr( get_metric_icon( $key ) ); ?>"></i>
                                            </div>
                                            <div class="smliser-summary-card-content">
                                                <span class="value"><?php echo esc_html( number_format( $value ) ); ?></span>
                                                <span class="label"><?php echo esc_html( smliser_format_metric_label( $key ) ); ?></span>
                                                <?php
                                                // Check for growth metric
                                                $growth_key = $key . '_growth';
                                                if ( isset( $summary[ $growth_key ] ) ) :
                                                    $growth = $summary[ $growth_key ];
                                                    $is_positive = $growth >= 0;
                                                ?>
                                                    <span class="growth <?php echo $is_positive ? 'positive' : 'negative'; ?>">
                                                        <i class="ti ti-trending-<?php echo $is_positive ? 'up' : 'down'; ?>"></i>
                                                        <?php echo esc_html( number_format( abs( $growth ), 2 ) . '%' ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Charts -->
                        <?php if ( ! empty( $chart_data ) ) : ?>
                            <div class="smliser-charts-grid">
                                <?php foreach ( $chart_data as $chart_key => $chart_config ) : ?>
                                    <div class="smliser-chart-wrapper">
                                        <canvas 
                                            id="chart-<?php echo esc_attr( $domain_id . '-' . $chart_key ); ?>"
                                            data-chart-json="<?php echo esc_attr( smliser_json_encode_attr( $chart_config ) ); ?>">
                                        </canvas>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Rankings -->
                        <?php if ( ! empty( $rankings ) ) : ?>
                            <div class="smliser-rankings-section">
                                <?php foreach ( $rankings as $ranking_key => $ranking_data ) : ?>
                                    <div class="smliser-ranking-block">
                                        <h4><?php echo esc_html( smliser_format_metric_label( $ranking_key ) ); ?></h4>
                                        <div class="smliser-ranking-content">
                                            <?php foreach ( $ranking_data as $type => $apps ) : ?>
                                                <?php if ( ! empty( $apps ) ) : ?>
                                                    <div class="smliser-ranking-type">
                                                        <h5>
                                                            <i class="ti <?php echo esc_attr( smliser_get_type_icon( $type ) ); ?>"></i>
                                                            <?php echo esc_html( ucfirst( $type ) ); ?>
                                                        </h5>
                                                        <ol class="smliser-ranking-list">
                                                            <?php foreach ( array_slice( $apps, 0, 5 ) as $index => $app ) : 
                                                                $app_sl     = $app['app_slug'] ?? '';
                                                                $app_ty     = $app['app_type'] ?? '';
                                                                $app_obj    = HostedApplicationService::get_app_by_slug( $app_ty, $app_sl );
                                                                
                                                                ?>
                                                                <li class="smliser-ranking-item">
                                                                    <span class="rank">#<?php echo esc_html( $index + 1 ); ?></span>
                                                                    <?php if ( $app_obj ) : ?>
                                                                        <span class="name">
                                                                            <a href="<?php echo \esc_url( \smliser_admin_repo_tab( 'view', array( 'app_id' => $app_obj->get_id(), 'type' => $app_obj->get_type() ) ) ); ?>" target="_blank" >
                                                                                <?php echo esc_html( $app_obj->get_name() ); ?>
                                                                            </a>
                                                                        </span>
                                                                    <?php else : ?>
                                                                        <span class="name"><?php echo __( 'Unknown', 'smliser' ); ?></span>
                                                                    <?php endif; ?>
                                                                    <span class="score"><?php echo esc_html( number_format( $app['metric_total'] ?? 0 ) ); ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ol>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Logs -->
                        <?php if ( ! empty( $recent_logs ) ) : ?>
                            <div class="smliser-logs-section">
                                <h4>
                                    <i class="ti ti-activity"></i>
                                    Recent Activity
                                </h4>
                                <div class="smliser-logs-table-wrapper">
                                    <table class="smliser-logs-table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Event</th>
                                                <th>License ID</th>
                                                <th>Website</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $recent_logs as $timestamp => $log ) : ?>
                                                <tr>
                                                    <td>
                                                        <span class="timestamp" title="<?php echo esc_attr( $timestamp ); ?>">
                                                            <?php echo esc_html( human_time_diff( strtotime( $timestamp ), current_time( 'timestamp' ) ) . ' ago' ); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="event-badge event-<?php echo esc_attr( $log['event_type'] ?? 'unknown' ); ?>">
                                                            <i class="ti <?php echo esc_attr( smliser_get_event_icon( $log['event_type'] ?? 'unknown' ) ); ?>"></i>
                                                            <?php echo esc_html( ucfirst( $log['event_type'] ?? 'unknown' ) ); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code><?php echo esc_html( $log['license_id'] ?? 'N/A' ); ?></code>
                                                    </td>
                                                    <td>
                                                        <?php if ( ! empty( $log['website'] ) && $log['website'] !== 'N/A' ) : ?>
                                                            <a href="<?php echo esc_url( $log['website'] ); ?>" target="_blank" rel="noopener">
                                                                <?php echo esc_html( parse_url( $log['website'], PHP_URL_HOST ) ?? $log['website'] ); ?>
                                                                <i class="ti ti-external-link"></i>
                                                            </a>
                                                        <?php else : ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="ip-address"><?php echo esc_html( $log['ip_address'] ?? 'N/A' ); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
/**
 * Helper Functions
 */

/**
 * Get icon for domain
 */
function get_domain_icon( $domain_name ) {
    $icons = [
        'Repository Overview'   => 'ti-database',
        'Download Analytics'    => 'ti-download',
        'Client Activity'       => 'ti-activity',
        'License Activity'      => 'ti-license',
        'Performance & Ranking' => 'ti-trophy',
    ];
    return $icons[ $domain_name ] ?? 'ti-chart-line';
}

/**
 * Get icon for metric key
 */
function get_metric_icon( $key ) {
    $icons = [
        'active_installations'      => 'ti-server',
        'total_downloads'           => 'ti-download',
        'total_accesses'            => 'ti-activity',
        'plugins_downloads'         => 'ti-plug',
        'themes_downloads'          => 'ti-palette',
        'software_downloads'        => 'ti-package',
        'plugin_installations'      => 'ti-plug',
        'theme_installations'       => 'ti-palette',
        'software_installations'    => 'ti-package',
        'activations'               => 'ti-check',
        'deactivations'             => 'ti-x',
        'verifications'             => 'ti-shield-check',
    ];
    return $icons[ $key ] ?? 'ti-chart-bar';
}

/**
 * Get icon for app type
 */
function smliser_get_type_icon( $type ) {
    $icons = [
        'plugin'    => 'ti-plug',
        'theme'     => 'ti-palette',
        'software'  => 'ti-package',
    ];
    return $icons[ $type ] ?? 'ti-apps';
}

/**
 * Get icon for event type
 */
function smliser_get_event_icon( $event_type ) {
    $icons = [
        'activation'        => 'ti-check',
        'deactivation'      => 'ti-x',
        'verification'      => 'ti-shield-check',
        'uninstallation'    => 'ti-trash',
        'unknown'           => 'ti-help',
    ];
    return $icons[ $event_type ] ?? 'ti-circle-dot';
}

/**
 * Format metric label for display
 */
function smliser_format_metric_label( $key ) {
    // Skip growth keys
    if ( strpos( $key, '_growth' ) !== false ) {
        return '';
    }

    $labels = [
        'active_installations'      => 'Active Installations',
        'total_downloads'           => 'Total Downloads',
        'total_accesses'            => 'Total Accesses',
        'plugins_downloads'         => 'Plugin Downloads',
        'themes_downloads'          => 'Theme Downloads',
        'software_downloads'        => 'Software Downloads',
        'plugin_installations'      => 'Plugin Installations',
        'theme_installations'       => 'Theme Installations',
        'software_installations'    => 'Software Installations',
        'activations'               => 'Activations',
        'deactivations'             => 'Deactivations',
        'verifications'             => 'Verifications',
        'top_downloads'             => 'Top Apps by Downloads',
        'top_accesses'              => 'Top Apps by Client Accesses',
    ];
    
    if ( isset( $labels[ $key ] ) ) {
        return $labels[ $key ];
    }
    
    // Fallback: Convert snake_case to Title Case
    return ucwords( str_replace( '_', ' ', $key ) );
}
