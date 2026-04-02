<?php
/**
 * Cache statistics / monitoring dashboard template.
 *
 * Variables injected by OptionsPage::cache_stats():
 *   @var \SmartLicenseServer\Cache\CacheStats   $stats        — live stats from active adapter
 *   @var string                                 $adapter_id   — active adapter ID
 *   @var string                                 $adapter_name — active adapter display name
 *   @var bool                                   $is_supported — adapter is_supported()
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Environments\WordPress\AdminMenu;
use SmartLicenseServer\Cache\CacheProviderIcons;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args( $request );

$extra        = $stats->get( 'extra', [] );
$hits         = (int) $stats->get( 'hits',         0 );
$misses       = (int) $stats->get( 'misses',        0 );
$entries      = (int) $stats->get( 'entries',       0 );
$memory_used  = (int) $stats->get( 'memory_used',   0 );
$memory_total = (int) $stats->get( 'memory_total',  0 );
$uptime       = (int) $stats->get( 'uptime',        0 );

$total_req    = $hits + $misses;
$tracks_hits  = $total_req > 0;
$hit_rate     = $stats->hit_rate();
$hit_pct      = $tracks_hits ? round( $hit_rate * 100, 1 ) : null;
$miss_pct     = $tracks_hits ? round( 100 - $hit_pct, 1 ) : null;

$has_mem_ceil = $memory_total > 0;
$mem_ratio    = $stats->memory_usage_ratio();
$mem_pct      = $has_mem_ceil ? round( $mem_ratio * 100, 1 ) : null;

// Colour grades
$hit_grade = $hit_pct === null ? 'neutral'
    : ( $hit_pct >= 70 ? 'good' : ( $hit_pct >= 50 ? 'warn' : 'bad' ) );
$mem_grade = $mem_pct === null ? 'neutral'
    : ( $mem_pct >= 90 ? 'bad' : ( $mem_pct >= 70 ? 'warn' : 'good' ) );

// Capability flags
$supports_flush_expired = in_array( $adapter_id, [ 'sqlitecache', 'runtime', 'arraycache' ], true );

// Nonce for all AJAX actions
$nonce = wp_create_nonce( 'smliser_cache_actions' );

// ── Helpers ────────────────────────────────────────────────────────────────
if ( ! function_exists( 'smliser_cs_fmt_bytes' ) ) {
    function smliser_cs_fmt_bytes( int $bytes ): string {
        if ( $bytes <= 0 ) return '0 B';
        $u = [ 'B', 'KB', 'MB', 'GB' ];
        $i = (int) floor( log( $bytes, 1024 ) );
        return round( $bytes / ( 1024 ** $i ), 2 ) . ' ' . $u[ $i ];
    }
}

if ( ! function_exists( 'smliser_cs_fmt_duration' ) ) {
    function smliser_cs_fmt_duration( int $s ): string {
        if ( $s <= 0 ) return '—';
        $d   = intdiv( $s, 86400 );
        $h   = intdiv( $s % 86400, 3600 );
        $m   = intdiv( $s % 3600, 60 );
        $sec = $s % 60;
        $p   = [];
        if ( $d )           $p[] = "{$d}d";
        if ( $h )           $p[] = "{$h}h";
        if ( $m )           $p[] = "{$m}m";
        if ( $sec && ! $d ) $p[] = "{$sec}s";
        return implode( ' ', $p ) ?: '< 1s';
    }
}
?>
<div class="smliser-admin-page">
    <?php AdminMenu::print_admin_top_menu( $menu_args ); ?>

    <div class="smlcd-wrap" data-adapter="<?php echo esc_attr( $adapter_id ); ?>">

        <!-- ── Header ────────────────────────────────────────────────────── -->
        <div class="smlcd-header">
            <div class="smlcd-header__identity">
                <span class="smlcd-header__icon">
                    <?php echo CacheProviderIcons::render( $adapter_id, $adapter_name ); ?>
                </span>
                <div>
                    <h2 class="smlcd-header__name"><?php echo esc_html( $adapter_name ); ?></h2>
                    <span class="smlcd-header__id"><?php echo esc_html( $adapter_id ); ?></span>
                </div>
                <span class="smlcd-badge smlcd-badge--active">
                    <span class="smlcd-pulse"></span>Active
                </span>
                <?php if ( ! $is_supported ) : ?>
                    <span class="smlcd-badge smlcd-badge--error">
                        <i class="ti ti-alert-triangle"></i> Not Supported
                    </span>
                <?php endif; ?>
            </div>

            <div class="smlcd-header__actions">
                <button type="button"
                        class="smlcd-btn smlcd-btn--ghost smlcd-refresh-btn"
                        title="Refresh stats">
                    <i class="ti ti-refresh"></i> Refresh
                </button>

                <?php if ( $supports_flush_expired ) : ?>
                <button type="button"
                        class="smlcd-btn smlcd-btn--ghost smliser-action-button"
                        data-args='<?php echo esc_attr( smliser_safe_json_encode( [
                            'action'  => 'smliser_cache_flush_expired',
                            'payLoad' => [ 'security' => $nonce ],
                        ] ) ); ?>'>
                    <i class="ti ti-clock-off"></i> Flush Stale
                </button>
                <?php endif; ?>

                <button type="button"
                        class="smlcd-btn smlcd-btn--danger smliser-action-button smlcd-confirm-btn"
                        data-confirm="This will clear ALL cached data for <?php echo esc_attr( $adapter_name ); ?>. Are you sure?"
                        data-args='<?php echo esc_attr( smliser_safe_json_encode( [
                            'action'  => 'smliser_cache_clear_all',
                            'payLoad' => [ 'security' => $nonce ],
                        ] ) ); ?>'>
                    <i class="ti ti-trash"></i> Flush Cache
                </button>

                <a href="<?php echo esc_url( smliser_get_current_url()->remove_query_param( 'section' ) ); ?>"
                   class="smlcd-btn smlcd-btn--secondary">
                    <i class="ti ti-settings"></i> Adapters
                </a>
            </div>
        </div>

        <!-- ── Stat cards ─────────────────────────────────────────────────── -->
        <div class="smlcd-cards" id="smlcd-stat-cards">

            <div class="smlcd-card smlcd-card--<?php echo $hit_grade; ?>">
                <div class="smlcd-card__label">Hit Rate</div>
                <div class="smlcd-card__value">
                    <?php if ( $hit_pct !== null ) : ?>
                        <?php echo $hit_pct; ?><span class="smlcd-card__unit">%</span>
                    <?php else : ?>
                        <span class="smlcd-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlcd-card__detail">
                    <?php if ( $tracks_hits ) : ?>
                        <?php echo number_format( $hits ); ?> hits · <?php echo number_format( $misses ); ?> misses
                    <?php else : ?>
                        Not tracked by this adapter
                    <?php endif; ?>
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Total Requests</div>
                <div class="smlcd-card__value">
                    <?php if ( $tracks_hits ) : ?>
                        <?php echo number_format( $total_req ); ?>
                    <?php else : ?>
                        <span class="smlcd-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlcd-card__detail">
                    <?php if ( $tracks_hits ) : ?>
                        Miss rate: <?php echo $miss_pct; ?>%
                    <?php else : ?>
                        Not tracked by this adapter
                    <?php endif; ?>
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Cached Entries</div>
                <div class="smlcd-card__value"><?php echo number_format( $entries ); ?></div>
                <div class="smlcd-card__detail">
                    <?php
                    $expired = (int) ( $extra['expired_entries'] ?? 0 );
                    echo $expired > 0
                        ? '<span class="smlcd-warn">' . number_format( $expired ) . ' expired</span>'
                        : 'All entries live';
                    ?>
                </div>
            </div>

            <div class="smlcd-card smlcd-card--<?php echo $mem_grade; ?>">
                <div class="smlcd-card__label">Memory</div>
                <div class="smlcd-card__value">
                    <?php if ( $mem_pct !== null ) : ?>
                        <?php echo $mem_pct; ?><span class="smlcd-card__unit">%</span>
                    <?php elseif ( $memory_used > 0 ) : ?>
                        <?php echo esc_html( smliser_cs_fmt_bytes( $memory_used ) ); ?>
                    <?php else : ?>
                        <span class="smlcd-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlcd-card__detail">
                    <?php if ( $has_mem_ceil ) : ?>
                        <?php echo esc_html( smliser_cs_fmt_bytes( $memory_used ) ); ?>
                        of <?php echo esc_html( smliser_cs_fmt_bytes( $memory_total ) ); ?>
                    <?php elseif ( $memory_used > 0 ) : ?>
                        <?php echo esc_html( smliser_cs_fmt_bytes( $memory_used ) ); ?> · No fixed ceiling
                    <?php else : ?>
                        Not reported
                    <?php endif; ?>
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Uptime</div>
                <div class="smlcd-card__value">
                    <?php if ( $uptime > 0 ) : ?>
                        <?php echo esc_html( smliser_cs_fmt_duration( $uptime ) ); ?>
                    <?php else : ?>
                        <span class="smlcd-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlcd-card__detail">
                    <?php if ( $uptime > 0 ) : ?>
                        Since last restart
                    <?php elseif ( in_array( $adapter_id, [ 'runtime', 'arraycache' ], true ) ) : ?>
                        Request-scoped
                    <?php else : ?>
                        Not reported
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /#smlcd-stat-cards -->

        <!-- ── Performance breakdown ──────────────────────────────────────── -->
        <?php if ( $tracks_hits ) : ?>
        <div class="smlcd-perf" id="smlcd-perf">
            <div class="smlcd-perf__header">
                <span class="smlcd-perf__title">Request Breakdown</span>
                <span class="smlcd-perf__total"><?php echo number_format( $total_req ); ?> total</span>
            </div>
            <div class="smlcd-perf__bar-wrap">
                <div class="smlcd-perf__bar">
                    <div class="smlcd-perf__bar-hit"
                         style="width: <?php echo $hit_pct; ?>%"
                         title="<?php echo $hit_pct; ?>% hits"></div>
                    <div class="smlcd-perf__bar-miss"
                         style="width: <?php echo $miss_pct; ?>%"
                         title="<?php echo $miss_pct; ?>% misses"></div>
                </div>
            </div>
            <div class="smlcd-perf__legend">
                <span class="smlcd-perf__legend-hit">
                    <span class="smlcd-perf__dot smlcd-perf__dot--hit"></span>
                    Hits <strong><?php echo number_format( $hits ); ?></strong>
                    <em><?php echo $hit_pct; ?>%</em>
                </span>
                <span class="smlcd-perf__legend-miss">
                    <span class="smlcd-perf__dot smlcd-perf__dot--miss"></span>
                    Misses <strong><?php echo number_format( $misses ); ?></strong>
                    <em><?php echo $miss_pct; ?>%</em>
                </span>
                <span class="smlcd-perf__rating smlcd-perf__rating--<?php
                    echo $hit_pct >= 80 ? 'excellent'
                        : ( $hit_pct >= 60 ? 'good'
                        : ( $hit_pct >= 40 ? 'fair' : 'poor' ) );
                ?>">
                    <?php
                    echo $hit_pct >= 80 ? 'Excellent'
                        : ( $hit_pct >= 60 ? 'Good'
                        : ( $hit_pct >= 40 ? 'Fair' : 'Poor' ) );
                    ?>
                </span>
            </div>

            <?php if ( $has_mem_ceil ) : ?>
            <div class="smlcd-perf__mem">
                <span class="smlcd-perf__mem-label">Memory pressure</span>
                <div class="smlcd-perf__mem-track">
                    <div class="smlcd-perf__mem-fill smlcd-perf__mem-fill--<?php echo $mem_grade; ?>"
                         style="width: <?php echo min( $mem_pct, 100 ); ?>%"></div>
                </div>
                <span class="smlcd-perf__mem-pct smlcd-perf__mem-pct--<?php echo $mem_grade; ?>"><?php echo $mem_pct; ?>%</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.smlcd-wrap -->
</div><!-- /.smliser-admin-page -->