<?php
/**
 * Access control dashboard template (dynamic).
 *
 * Renders a complete summary of accounts & access data.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 * @var array $account_summaries Array result of
 * @see SmartLicenseServer\Security\Context\ContextServiceProvider::get_accounts_summary_report()
 */

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Helper: Render icon based on metric type
 */
function smliser_get_metric_icon( $label ) {
    $icons = array(
        'users'                  => 'dashicons-groups',
        'organizations'          => 'dashicons-building',
        'service_accounts'       => 'dashicons-admin-network',
        'organization_members'   => 'dashicons-businessperson',
        'resource_owners'        => 'dashicons-admin-users',
        'orphaned'               => 'dashicons-warning',
        'has_issues'             => 'dashicons-flag',
        'total'                  => 'dashicons-chart-bar',
        'ever_used'              => 'dashicons-yes-alt',
        'never_used'             => 'dashicons-dismiss',
        'most_recent_use'        => 'dashicons-clock',
        'oldest_use'             => 'dashicons-calendar',
    );
    
    $label_lower = strtolower( str_replace( ' ', '_', $label ) );
    
    return $icons[ $label_lower ] ?? 'dashicons-admin-generic';
}

/**
 * Helper: Format metric value for display
 */
function smliser_format_metric_value( $value ) {
    if ( is_bool( $value ) ) {
        return $value ? 'Yes' : 'No';
    }
    
    if ( is_null( $value ) ) {
        return '—';
    }
    
    if ( is_numeric( $value ) ) {
        return number_format_i18n( $value );
    }
    
    return $value;
}

/**
 * Helper: Get status class for integrity issues
 */
function smliser_get_status_class( $has_issues ) {
    return $has_issues ? 'smliser-status-warning' : 'smliser-status-success';
}
?>

<div class="smliser-admin-repository-template">
    <?php self::print_header(); ?>

    <div class="smliser-account-summary-wrapper">

        <?php if ( ! empty( $account_summaries ) ) : ?>

            <!-- Summary Section -->
            <?php if ( isset( $account_summaries['summary'] ) ) : ?>
                <section class="smliser-account-domain-section">
                    <div class="smliser-account-domain-header">
                        <h2 class="smliser-account-domain-title">
                            <span class="dashicons dashicons-category"></span>
                            Account Overview
                        </h2>
                    </div>

                    <div class="smliser-account-domain-content">
                        <div class="smliser-account-metrics-grid">
                            <?php foreach ( $account_summaries['summary'] as $label => $value ) : ?>
                                <div class="smliser-account-metric-card">
                                    <div class="smliser-account-metric-icon">
                                        <span class="dashicons <?php echo esc_attr( smliser_get_metric_icon( $label ) ); ?>"></span>
                                    </div>
                                    <div class="smliser-account-metric-content">
                                        <h3 class="smliser-account-metric-label">
                                            <?php echo esc_html( smliser_format_label( $label ) ); ?>
                                        </h3>
                                        <p class="smliser-account-metric-value">
                                            <?php echo esc_html( smliser_format_metric_value( $value ) ); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Integrity Section -->
            <?php if ( isset( $account_summaries['integrity'] ) ) : ?>
                <section class="smliser-account-domain-section <?php echo esc_attr( smliser_get_status_class( $account_summaries['integrity']['has_issues'] ?? false ) ); ?>">
                    <div class="smliser-account-domain-header">
                        <h2 class="smliser-account-domain-title">
                            <span class="dashicons dashicons-shield"></span>
                            Data Integrity
                        </h2>
                    </div>

                    <div class="smliser-account-domain-content">
                        <div class="smliser-account-metrics-grid">
                            <?php foreach ( $account_summaries['integrity'] as $label => $value ) : ?>
                                <?php if ( $label === 'has_issues' ) continue; ?>
                                <div class="smliser-account-metric-card">
                                    <div class="smliser-account-metric-icon">
                                        <span class="dashicons <?php echo esc_attr( smliser_get_metric_icon( $label ) ); ?>"></span>
                                    </div>
                                    <div class="smliser-account-metric-content">
                                        <h3 class="smliser-account-metric-label">
                                            <?php echo esc_html( smliser_format_label( $label ) ); ?>
                                        </h3>
                                        <p class="smliser-account-metric-value">
                                            <?php echo esc_html( smliser_format_metric_value( $value ) ); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ( $account_summaries['integrity']['has_issues'] ?? false ) : ?>
                            <div class="smliser-account-info-block smliser-account-warning">
                                <span class="dashicons dashicons-info"></span>
                                <strong>Action Required:</strong>
                                <span>Orphaned records detected. Please review and clean up data integrity issues.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Usage Section -->
            <?php if ( isset( $account_summaries['usage']['service_accounts'] ) ) : ?>
                <section class="smliser-account-domain-section">
                    <div class="smliser-account-domain-header">
                        <h2 class="smliser-account-domain-title">
                            <span class="dashicons dashicons-chart-line"></span>
                            Service Account Usage
                        </h2>
                    </div>

                    <div class="smliser-account-domain-content">
                        <div class="smliser-account-metrics-grid">
                            <?php foreach ( $account_summaries['usage']['service_accounts'] as $label => $value ) : ?>
                                <div class="smliser-account-metric-card">
                                    <div class="smliser-account-metric-icon">
                                        <span class="dashicons <?php echo esc_attr( smliser_get_metric_icon( $label ) ); ?>"></span>
                                    </div>
                                    <div class="smliser-account-metric-content">
                                        <h3 class="smliser-account-metric-label">
                                            <?php echo esc_html( smliser_format_label( $label ) ); ?>
                                        </h3>
                                        <p class="smliser-account-metric-value">
                                            <?php echo esc_html( smliser_format_metric_value( $value ) ); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

        <?php else : ?>
            
            <div class="smliser-account-empty-state">
                <span class="dashicons dashicons-warning"></span>
                <h3>No Account Summary Data Available</h3>
                <p>There are currently no account summaries to display. Check back later or contact support if you believe this is an error.</p>
            </div>

        <?php endif; ?>

    </div>
</div>