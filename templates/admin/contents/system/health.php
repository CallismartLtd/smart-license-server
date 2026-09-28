<?php
/**
 * The admin site health page template.
 *
 * A visual health-check page: an overall status gauge, plus individual
 * checks each shown with an animated status icon rather than a plain
 * table row. Supports a 'pending' status for checks resolved via a
 * client-side fetch (HTTP reachability, memory, response time, etc.)
 * that haven't completed yet — those render as a "checking…" spinner
 * and are expected to be updated in place by JS once results arrive.
 *
 * @author Callistus Nwachukwu
 * @var array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}> $checks
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

$menu_args = $page_handler->get_top_menu_args( $request );

// Temporary translation from the current pass/info/warning/critical
// values PHP emits today to the good/fair/usable/critical vocabulary
// this page presents. Once ToolsPage::run_health_checks() emits these
// words directly, delete this map and use $check['status'] as-is.
$status_map = [
    'pass'     => 'good',
    'info'     => 'fair',
    'warning'  => 'usable',
    'critical' => 'critical',
    'pending'  => 'pending', // Reserved for checks resolved via client-side fetch.
];

$status_labels = [
    'good'     => 'Good',
    'fair'     => 'Fair',
    'usable'   => 'Usable',
    'critical' => 'Critical',
    'pending'  => 'Checking…',
];

$tiers  = [ 'good', 'fair', 'usable', 'critical' ];
$counts = array_fill_keys( $tiers, 0 );

$normalized_checks = [];
foreach ( $checks as $check ) {
    $status = $status_map[ $check['status'] ] ?? 'pending';
    $normalized_checks[] = $check + [ 'status' => $status ];

    if ( isset( $counts[ $status ] ) ) {
        $counts[ $status ]++;
    }
}

$total = array_sum( $counts );

// Needle angle: -90deg (critical, far left) to 90deg (good, far right),
// weighted by how "good" the mix of results is. Purely presentational —
// once real async checks land this can be recalculated the same way.
$score = $total > 0
    ? ( ( $counts['good'] * 1.0 + $counts['fair'] * 0.66 + $counts['usable'] * 0.33 ) / $total )
    : 1;
$needle_deg = -90 + ( 180 * $score );

$overall_status = match ( true ) {
    $counts['critical'] > 0                 => 'critical',
    $counts['usable'] > 0                   => 'usable',
    $counts['fair'] > 0                     => 'fair',
    default                                 => 'good',
};
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

    <div class="smliser-health-hero">
        <div class="smliser-health-gauge" role="img" aria-label="Overall site health: <?php echo escAttr( $status_labels[ $overall_status ] ); ?>">
            <svg viewBox="0 0 200 120" width="240" height="144">
                <path d="M 20 110 A 80 80 0 0 1 60 35"  stroke="var(--dashboard-danger)"  stroke-width="14" fill="none" stroke-linecap="round" />
                <path d="M 60 35 A 80 80 0 0 1 100 20"  stroke="var(--dashboard-warning)" stroke-width="14" fill="none" stroke-linecap="round" />
                <path d="M 100 20 A 80 80 0 0 1 140 35" stroke="var(--dashboard-info-text)" stroke-width="14" fill="none" stroke-linecap="round" />
                <path d="M 140 35 A 80 80 0 0 1 180 110" stroke="var(--dashboard-success)" stroke-width="14" fill="none" stroke-linecap="round" />

                <g class="smliser-health-needle" style="--needle-angle: <?php echo (float) $needle_deg; ?>deg;" transform-origin="100 110">
                    <line x1="100" y1="110" x2="100" y2="45" stroke="var(--dashboard-text)" stroke-width="4" stroke-linecap="round" />
                    <circle cx="100" cy="110" r="7" fill="var(--dashboard-text)" />
                </g>
            </svg>
            <div class="smliser-health-gauge-label smliser-health-badge--<?php echo escAttr( $overall_status ); ?>">
                <?php echo escHtml( $status_labels[ $overall_status ] ); ?>
            </div>
        </div>

        <div class="smliser-health-summary-counts">
            <?php foreach ( $tiers as $tier ) : ?>
                <div class="smliser-health-count smliser-health-badge--<?php echo escAttr( $tier ); ?>">
                    <span class="smliser-health-count-value"><?php echo escHtml( (string) $counts[ $tier ] ); ?></span>
                    <span class="smliser-health-count-label"><?php echo escHtml( $status_labels[ $tier ] ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( empty( $normalized_checks ) ) : ?>
        <div class="smliser-not-found-container">
            <p>No health checks have run.</p>
        </div>
    <?php else : ?>
        <div class="smliser-health-list">
            <?php foreach ( $normalized_checks as $check ) : ?>
                <div class="smliser-health-item" data-check-id="<?php echo escAttr( $check['id'] ); ?>" data-check-status="<?php echo escAttr( $check['status'] ); ?>">
                    <div class="smliser-health-item-icon smliser-health-icon--<?php echo escAttr( $check['status'] ); ?>">
                        <?php if ( 'pending' === $check['status'] ) : ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle class="smliser-health-spin" cx="12" cy="12" r="9" stroke-dasharray="42 14" />
                            </svg>
                        <?php elseif ( 'good' === $check['status'] ) : ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9.25" />
                                <path class="smliser-health-draw" d="M8 12.5l2.5 2.5 5.5-6" />
                            </svg>
                        <?php elseif ( 'fair' === $check['status'] ) : ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9.25" />
                                <circle class="smliser-health-draw" cx="12" cy="12" r="3" fill="currentColor" stroke="none" />
                            </svg>
                        <?php elseif ( 'usable' === $check['status'] ) : ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                <path class="smliser-health-draw" d="M12 3.5l9.25 16.25a1 1 0 0 1-.87 1.5H3.62a1 1 0 0 1-.87-1.5L12 3.5z" />
                                <line x1="12" y1="10" x2="12" y2="14.25" />
                                <circle cx="12" cy="17.25" r="0.75" fill="currentColor" stroke="none" />
                            </svg>
                        <?php else : ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9.25" />
                                <line class="smliser-health-draw" x1="8.5" y1="8.5" x2="15.5" y2="15.5" />
                                <line class="smliser-health-draw" x1="15.5" y1="8.5" x2="8.5" y2="15.5" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="smliser-health-item-body">
                        <h4 class="smliser-health-item-label"><?php echo escHtml( $check['label'] ); ?></h4>
                        <p class="smliser-health-item-message"><?php echo escHtml( $check['message'] ); ?></p>
                    </div>
                    <div class="smliser-health-item-action">
                        <?php if ( ! empty( $check['recommendation'] ) ) : ?>
                            <button
                                type="button"
                                class="smliser-btn-glass smliser-view-detail"
                                data-title="Recommendation &mdash; <?php echo escAttr( $check['label'] ); ?>"
                                data-content="<?php echo escAttr( $check['recommendation'] ); ?>"
                            >View</button>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>