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

use SmartLicenseServer\Admin\Menu;
use SmartLicenseServer\Cache\CacheProviderIcons;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args();

// ── Derived values ─────────────────────────────────────────────────────────────
$extra        = $stats->get( 'extra', [] );
$hits         = (int) $stats->get( 'hits',         0 );
$misses       = (int) $stats->get( 'misses',        0 );
$entries      = (int) $stats->get( 'entries',       0 );
$memory_used  = (int) $stats->get( 'memory_used',   0 );
$memory_total = (int) $stats->get( 'memory_total',  0 );
$uptime       = (int) $stats->get( 'uptime',        0 );

$hit_rate     = $stats->hit_rate();
$mem_ratio    = $stats->memory_usage_ratio();

$tracks_hits  = ! ( $hits === 0 && $misses === 0 );
$hit_pct      = $tracks_hits ? round( $hit_rate * 100, 1 ) : null;
$has_mem_ceil = $memory_total > 0;
$mem_pct      = $has_mem_ceil ? round( $mem_ratio * 100, 1 ) : null;
$has_uptime   = $uptime > 0;

$hit_colour = $hit_pct === null ? 'neutral'
    : ( $hit_pct >= 70 ? 'good' : ( $hit_pct >= 50 ? 'warn' : 'bad' ) );
$mem_colour = $mem_pct === null ? 'neutral'
    : ( $mem_pct >= 90 ? 'bad' : ( $mem_pct >= 70 ? 'warn' : 'good' ) );

// Efficiency rating
$efficiency = 'unknown';
if ( $tracks_hits ) {
    $efficiency = $hit_pct >= 80 ? 'excellent'
        : ( $hit_pct >= 60 ? 'good'
        : ( $hit_pct >= 40 ? 'fair'
        : 'poor' ) );
}

// Capability flags
$supports_key_browser   = $adapter_id === 'apcu';
$supports_flush_expired = in_array( $adapter_id, [ 'sqlitecache', 'runtime', 'arraycache' ], true );
$supports_prefix_delete = ! in_array( $adapter_id, [ 'wpcache' ], true );

// Nonce for AJAX actions
$nonce = wp_create_nonce( 'smliser_cache_actions' );

// Helpers (guard against redeclaration across includes)
if ( ! function_exists( 'smliser_cs_fmt_bytes' ) ) {
    function smliser_cs_fmt_bytes( int $bytes ): string {
        if ( $bytes <= 0 ) return '0 B';
        $u = [ 'B', 'KB', 'MB', 'GB' ];
        $i = (int) floor( log( $bytes, 1024 ) );
        return round( $bytes / ( 1024 ** $i ), 2 ) . ' ' . $u[ $i ];
    }
}

if ( ! function_exists( 'smliser_cs_fmt_uptime' ) ) {
    function smliser_cs_fmt_uptime( int $s ): string {
        if ( $s <= 0 ) return '—';
        $d = intdiv( $s, 86400 ); $h = intdiv( $s % 86400, 3600 );
        $m = intdiv( $s % 3600, 60 ); $sec = $s % 60;
        $p = [];
        if ( $d )         $p[] = "{$d}d";
        if ( $h )         $p[] = "{$h}h";
        if ( $m )         $p[] = "{$m}m";
        if ( $sec && !$d ) $p[] = "{$sec}s";
        return implode( ' ', $p ) ?: '< 1s';
    }
}
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>

    <div class="smlc-dash" data-adapter="<?php echo esc_attr( $adapter_id ); ?>">

        <!-- ── Adapter identity header ──────────────────────────────────────── -->
        <div class="smlc-header">
            <div class="smlc-header__icon">
                <?php echo CacheProviderIcons::render( $adapter_id, $adapter_name ); ?>
            </div>
            <div class="smlc-header__meta">
                <h2 class="smlc-header__name"><?php echo esc_html( $adapter_name ); ?></h2>
                <code class="smlc-header__id"><?php echo esc_html( $adapter_id ); ?></code>
            </div>
            <div class="smlc-header__badges">
                <span class="smlc-badge smlc-badge--active">
                    <span class="smlc-pulse"></span> Active
                </span>
                <?php if ( ! $is_supported ) : ?>
                    <span class="smlc-badge smlc-badge--error">
                        <i class="ti ti-alert-triangle"></i> Not supported
                    </span>
                <?php endif; ?>
                <?php if ( $efficiency !== 'unknown' ) : ?>
                    <span class="smlc-badge smlc-badge--eff smlc-badge--eff-<?php echo $efficiency; ?>">
                        <?php echo ucfirst( $efficiency ); ?> efficiency
                    </span>
                <?php endif; ?>
            </div>
            <div class="smlc-header__actions">
                <button type="button" class="smlc-icon-btn smlc-refresh-btn" title="Refresh stats">
                    <i class="ti ti-refresh"></i>
                </button>
                <a href="<?php echo esc_url( smliser_get_current_url()->remove_query_param( 'section' ) ); ?>"
                   class="smliser-button smliser-button--secondary">
                    <i class="ti ti-settings"></i> Adapters
                </a>
            </div>
        </div>

        <!-- ── Stat cards ───────────────────────────────────────────────────── -->
        <div class="smlc-cards" id="smlc-stat-cards">

            <div class="smlc-card smlc-card--<?php echo $hit_colour; ?>">
                <div class="smlc-card__eyebrow">Hit Rate</div>
                <div class="smlc-card__value">
                    <?php if ( $hit_pct !== null ) : ?>
                        <?php echo $hit_pct; ?><span class="smlc-card__unit">%</span>
                    <?php else : ?>
                        <span class="smlc-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlc-card__sub">
                    <?php if ( $tracks_hits ) : ?>
                        <span class="smlc-hit"><?php echo number_format( $hits ); ?> hits</span>
                        <span class="smlc-dot">·</span>
                        <span class="smlc-miss"><?php echo number_format( $misses ); ?> misses</span>
                    <?php else : ?>
                        <span class="smlc-muted">Not tracked by this adapter</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="smlc-card smlc-card--neutral">
                <div class="smlc-card__eyebrow">Cached Entries</div>
                <div class="smlc-card__value"><?php echo number_format( $entries ); ?></div>
                <div class="smlc-card__sub">
                    <?php $expired = (int) ( $extra['expired_entries'] ?? $extra['expired_slots'] ?? 0 ); ?>
                    <?php if ( $expired > 0 ) : ?>
                        <span class="smlc-warn-txt">+<?php echo number_format( $expired ); ?> expired</span>
                    <?php else : ?>
                        <span class="smlc-muted">All entries live</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="smlc-card smlc-card--<?php echo $mem_colour; ?> smlc-card--wide">
                <div class="smlc-card__eyebrow">Memory</div>
                <div class="smlc-card__value">
                    <?php if ( $mem_pct !== null ) : ?>
                        <?php echo $mem_pct; ?><span class="smlc-card__unit">%</span>
                    <?php else : ?>
                        <?php echo esc_html( smliser_cs_fmt_bytes( $memory_used ) ); ?>
                    <?php endif; ?>
                </div>
                <?php if ( $has_mem_ceil ) : ?>
                    <div class="smlc-mem-bar">
                        <div class="smlc-mem-bar__fill smlc-mem-bar__fill--<?php echo $mem_colour; ?>"
                             style="width:<?php echo min( $mem_pct, 100 ); ?>%"></div>
                    </div>
                    <div class="smlc-card__sub smlc-mem-detail">
                        <span>Used <?php echo esc_html( smliser_cs_fmt_bytes( $memory_used ) ); ?></span>
                        <span>·</span>
                        <span>Free <?php echo esc_html( smliser_cs_fmt_bytes( $memory_total - $memory_used ) ); ?></span>
                        <span>·</span>
                        <span>Total <?php echo esc_html( smliser_cs_fmt_bytes( $memory_total ) ); ?></span>
                    </div>
                <?php else : ?>
                    <div class="smlc-card__sub smlc-muted">
                        <?php echo $memory_used > 0
                            ? esc_html( smliser_cs_fmt_bytes( $memory_used ) ) . ' used · No fixed ceiling'
                            : 'Not reported'; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="smlc-card smlc-card--neutral">
                <div class="smlc-card__eyebrow">Uptime</div>
                <div class="smlc-card__value">
                    <?php if ( $has_uptime ) : ?>
                        <?php echo esc_html( smliser_cs_fmt_uptime( $uptime ) ); ?>
                    <?php else : ?>
                        <span class="smlc-card__na">—</span>
                    <?php endif; ?>
                </div>
                <div class="smlc-card__sub smlc-muted">
                    <?php if ( ! $has_uptime ) : ?>
                        <?php echo in_array( $adapter_id, [ 'runtime', 'arraycache' ], true ) ? 'Request-scoped' : 'Not reported'; ?>
                    <?php else : ?>
                        Since last restart
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /#smlc-stat-cards -->

        <!-- ── Performance panel ────────────────────────────────────────────── -->
        <?php if ( $tracks_hits ) : ?>
        <div class="smlc-panel" id="smlc-perf-panel">
            <h3 class="smlc-panel__title"><i class="ti ti-chart-line"></i> Performance</h3>
            <div class="smlc-perf-grid">

                <!-- Hit rate gauge -->
                <div class="smlc-gauge-wrap">
                    <?php
                    // Arc length for a semicircle of radius 55: π * r = ~172.8
                    $arc_len    = 172.8;
                    $arc_offset = round( $arc_len - ( ( $hit_pct / 100 ) * $arc_len ), 2 );
                    ?>
                    <svg class="smlc-gauge" viewBox="0 0 120 70" xmlns="http://www.w3.org/2000/svg">
                        <path class="smlc-gauge__track"
                              d="M10,65 A55,55 0 0,1 110,65"
                              fill="none" stroke-width="10" stroke-linecap="round"/>
                        <path class="smlc-gauge__fill smlc-gauge__fill--<?php echo $hit_colour; ?>"
                              d="M10,65 A55,55 0 0,1 110,65"
                              fill="none" stroke-width="10" stroke-linecap="round"
                              stroke-dasharray="<?php echo $arc_len; ?>"
                              stroke-dashoffset="<?php echo $arc_offset; ?>"/>
                        <text x="60" y="62" text-anchor="middle" class="smlc-gauge__label">
                            <?php echo $hit_pct; ?>%
                        </text>
                    </svg>
                    <div class="smlc-gauge__caption">Hit Rate</div>
                </div>

                <!-- Efficiency metrics -->
                <div class="smlc-perf-metrics">
                    <?php
                    $total_req = $hits + $misses;
                    $miss_rate = $total_req > 0 ? round( ( $misses / $total_req ) * 100, 1 ) : 0;
                    $metrics   = [
                        [ 'label' => 'Total Requests', 'value' => number_format( $total_req ), 'icon' => 'ti-activity',     'cls' => '' ],
                        [ 'label' => 'Cache Hits',     'value' => number_format( $hits ),      'icon' => 'ti-check',        'cls' => 'good' ],
                        [ 'label' => 'Cache Misses',   'value' => number_format( $misses ),    'icon' => 'ti-x',            'cls' => 'bad' ],
                        [ 'label' => 'Miss Rate',      'value' => $miss_rate . '%',            'icon' => 'ti-trending-down','cls' => $miss_rate > 50 ? 'bad' : ( $miss_rate > 30 ? 'warn' : '' ) ],
                    ];
                    foreach ( $metrics as $m ) : ?>
                        <div class="smlc-perf-metric">
                            <i class="ti <?php echo $m['icon']; ?> <?php echo $m['cls'] ? 'smlc-' . $m['cls'] . '-txt' : 'smlc-muted-txt'; ?>"></i>
                            <div>
                                <span class="smlc-perf-metric__val <?php echo $m['cls'] ? 'smlc-' . $m['cls'] . '-txt' : ''; ?>">
                                    <?php echo $m['value']; ?>
                                </span>
                                <span class="smlc-perf-metric__label"><?php echo $m['label']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Efficiency rating spectrum -->
                <div class="smlc-eff-wrap">
                    <div class="smlc-eff-label">
                        Cache Efficiency
                        <span class="smlc-badge smlc-badge--eff smlc-badge--eff-<?php echo $efficiency; ?>">
                            <?php echo ucfirst( $efficiency ); ?>
                        </span>
                    </div>
                    <div class="smlc-eff-bar">
                        <div class="smlc-eff-bar__segments">
                            <span class="smlc-eff-seg smlc-eff-seg--poor">Poor</span>
                            <span class="smlc-eff-seg smlc-eff-seg--fair">Fair</span>
                            <span class="smlc-eff-seg smlc-eff-seg--good">Good</span>
                            <span class="smlc-eff-seg smlc-eff-seg--excel">Excellent</span>
                        </div>
                        <div class="smlc-eff-bar__marker" style="left:<?php echo min( $hit_pct, 99.5 ); ?>%"></div>
                    </div>
                    <div class="smlc-eff-legend">
                        <span>0%</span><span>40%</span><span>60%</span><span>80%</span><span>100%</span>
                    </div>
                </div>

            </div>
        </div>
        <?php endif; ?>

        <!-- ── Cache management ──────────────────────────────────────────────── -->
        <div class="smlc-panel" id="smlc-manage-panel">
            <h3 class="smlc-panel__title"><i class="ti ti-tool"></i> Cache Management</h3>
            <div class="smlc-manage-grid">

                <!-- Clear all -->
                <div class="smlc-action-card smlc-action-card--danger">
                    <div class="smlc-action-card__icon"><i class="ti ti-trash"></i></div>
                    <div class="smlc-action-card__body">
                        <strong>Clear All Cache</strong>
                        <p>Remove every cached entry immediately. Causes a temporary spike in cache misses while the cache warms back up.</p>
                    </div>
                    <button type="button"
                            class="smliser-button smliser-button--danger smliser-action-button smlc-confirm-btn"
                            data-confirm="This will delete ALL cached data for <?php echo esc_attr( $adapter_name ); ?>. Continue?"
                            data-args='<?php echo esc_attr( smliser_safe_json_encode([
                                'action'  => 'smliser_cache_clear_all',
                                'payLoad' => [ 'security' => $nonce ],
                            ]) ); ?>'>
                        <i class="ti ti-trash"></i> Clear All
                    </button>
                </div>

                <!-- Delete by prefix -->
                <?php if ( $supports_prefix_delete ) : ?>
                <div class="smlc-action-card">
                    <div class="smlc-action-card__icon"><i class="ti ti-filter"></i></div>
                    <div class="smlc-action-card__body">
                        <strong>Delete by Prefix</strong>
                        <p>Remove all keys that begin with a specific string. Useful for targeted invalidation without clearing everything.</p>
                        <div class="smlc-input-row">
                            <input type="text"
                                   id="smlc-prefix-input"
                                   class="smliser-input smlc-action-input"
                                   placeholder="e.g. smliser_license_"
                                   autocomplete="off" />
                            <button type="button"
                                    class="smliser-button smliser-button--secondary smlc-input-action-btn"
                                    data-input="#smlc-prefix-input"
                                    data-action="smliser_cache_delete_by_prefix"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                <i class="ti ti-eraser"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Delete by pattern -->
                <?php if ( $supports_prefix_delete ) : ?>
                <div class="smlc-action-card">
                    <div class="smlc-action-card__icon"><i class="ti ti-regex"></i></div>
                    <div class="smlc-action-card__body">
                        <strong>Delete by Pattern</strong>
                        <p>Remove keys matching a regex. Only works on adapters that support full key enumeration (APCu, SQLite, Runtime).</p>
                        <div class="smlc-input-row">
                            <input type="text"
                                   id="smlc-pattern-input"
                                   class="smliser-input smlc-action-input"
                                   placeholder="e.g. /^smliser_user_\d+/"
                                   autocomplete="off" />
                            <button type="button"
                                    class="smliser-button smliser-button--secondary smlc-input-action-btn"
                                    data-input="#smlc-pattern-input"
                                    data-action="smliser_cache_delete_by_pattern"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                <i class="ti ti-eraser"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Flush expired -->
                <div class="smlc-action-card <?php echo ! $supports_flush_expired ? 'smlc-action-card--disabled' : ''; ?>">
                    <div class="smlc-action-card__icon"><i class="ti ti-clock-off"></i></div>
                    <div class="smlc-action-card__body">
                        <strong>Flush Expired Entries</strong>
                        <?php if ( $supports_flush_expired ) : ?>
                            <p>Prune entries past their TTL that haven't been evicted yet. Frees memory without touching live data.</p>
                        <?php else : ?>
                            <p class="smlc-muted"><?php echo esc_html( $adapter_name ); ?> evicts expired entries automatically — manual pruning is not needed.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ( $supports_flush_expired ) : ?>
                    <button type="button"
                            class="smliser-button smliser-button--secondary smliser-action-button"
                            data-args='<?php echo esc_attr( wp_json_encode([
                                'action'  => 'smliser_cache_flush_expired',
                                'payLoad' => [ 'security' => $nonce ],
                            ]) ); ?>'>
                        <i class="ti ti-clock-off"></i> Flush Expired
                    </button>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ── Key browser (APCu only) ───────────────────────────────────────── -->
        <div class="smlc-panel" id="smlc-keys-panel">
            <div class="smlc-panel__head">
                <h3 class="smlc-panel__title"><i class="ti ti-list-search"></i> Top Keys by Hits</h3>
                <?php if ( $supports_key_browser ) : ?>
                <div class="smlc-panel__controls">
                    <select id="smlc-topn-select" class="smliser-input smlc-topn-select">
                        <option value="10">Top 10</option>
                        <option value="25">Top 25</option>
                        <option value="50">Top 50</option>
                        <option value="100">Top 100</option>
                    </select>
                    <button type="button" class="smlc-icon-btn" id="smlc-load-keys-btn" title="Load top keys">
                        <i class="ti ti-search"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $supports_key_browser ) : ?>
                <div id="smlc-keys-wrap" class="smlc-keys-placeholder">
                    <i class="ti ti-search"></i>
                    <span>Click <strong>search</strong> to load top keys</span>
                </div>
            <?php else : ?>
                <div class="smlc-unavailable">
                    <i class="ti ti-database-off"></i>
                    <div>
                        <strong>Key inspection not available</strong>
                        <p><?php echo esc_html( $adapter_name ); ?> does not expose per-key metadata. Key-level browsing is only available for APCu.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Adapter detail panel ─────────────────────────────────────────── -->
        <?php if ( ! empty( $extra ) ) : ?>
        <div class="smlc-panel" id="smlc-detail-panel">
            <h3 class="smlc-panel__title">
                <i class="ti ti-info-circle"></i> <?php echo esc_html( $adapter_name ); ?> Details
            </h3>
            <div class="smlc-detail-grid">

                <?php if ( in_array( $adapter_id, [ 'redis', 'laravelcache' ], true ) && isset( $extra['redis_version'] ) ) : ?>
                    <?php if ( ! empty( $extra['redis_version'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Redis Version</span>
                        <span class="smlc-detail-item__val"><code>v<?php echo esc_html( $extra['redis_version'] ); ?></code></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['maxmemory_policy'] ) && $extra['maxmemory_policy'] !== '' ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Eviction Policy</span>
                        <span class="smlc-detail-item__val"><code><?php echo esc_html( $extra['maxmemory_policy'] ); ?></code></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['connected_clients'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Connected Clients</span>
                        <span class="smlc-detail-item__val"><?php echo number_format( (int) $extra['connected_clients'] ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['evicted_keys'] ) && (int) $extra['evicted_keys'] > 0 ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Evicted Keys</span>
                        <span class="smlc-detail-item__val smlc-warn-txt"><?php echo number_format( (int) $extra['evicted_keys'] ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['database'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Database Index</span>
                        <span class="smlc-detail-item__val"><code><?php echo (int) $extra['database']; ?></code></span>
                    </div>
                    <?php endif; ?>

                <?php elseif ( isset( $extra['server_count'] ) && ! isset( $extra['redis_version'] ) ) : ?>
                    <?php $display_conns = $extra['client_connections'] ?? $extra['curr_connections'] ?? null; ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Server Pool</span>
                        <span class="smlc-detail-item__val"><?php echo (int) $extra['server_count']; ?> server<?php echo (int) $extra['server_count'] !== 1 ? 's' : ''; ?></span>
                    </div>
                    <?php if ( $display_conns !== null ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Connections</span>
                        <span class="smlc-detail-item__val"><?php echo number_format( (int) $display_conns ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['evictions'] ) && (int) $extra['evictions'] > 0 ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Evictions</span>
                        <span class="smlc-detail-item__val smlc-warn-txt"><?php echo number_format( (int) $extra['evictions'] ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['servers'] ) && is_array( $extra['servers'] ) ) : ?>
                    <div class="smlc-detail-item smlc-detail-item--full">
                        <span class="smlc-detail-item__key">Servers</span>
                        <span class="smlc-detail-item__val">
                            <?php foreach ( $extra['servers'] as $srv ) : ?>
                                <code><?php echo esc_html( $srv ); ?></code>
                            <?php endforeach; ?>
                        </span>
                    </div>
                    <?php endif; ?>

                <?php elseif ( $adapter_id === 'sqlitecache' ) : ?>
                    <?php if ( isset( $extra['db_file'] ) && $extra['db_file'] !== '' ) : ?>
                    <div class="smlc-detail-item smlc-detail-item--full">
                        <span class="smlc-detail-item__key">Database File</span>
                        <span class="smlc-detail-item__val"><code class="smlc-truncate"><?php echo esc_html( $extra['db_file'] ); ?></code></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['db_file_size'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">File Size</span>
                        <span class="smlc-detail-item__val"><?php echo esc_html( smliser_cs_fmt_bytes( (int) $extra['db_file_size'] ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['journal_mode'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Journal Mode</span>
                        <span class="smlc-detail-item__val"><code><?php echo esc_html( strtoupper( $extra['journal_mode'] ) ); ?></code></span>
                    </div>
                    <?php endif; ?>

                <?php elseif ( $adapter_id === 'apcu' ) : ?>
                    <?php if ( isset( $extra['num_slots'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Hash Slots</span>
                        <span class="smlc-detail-item__val"><?php echo number_format( (int) $extra['num_slots'] ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['num_expunges'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Expunges</span>
                        <span class="smlc-detail-item__val <?php echo (int) $extra['num_expunges'] > 0 ? 'smlc-warn-txt' : ''; ?>">
                            <?php echo number_format( (int) $extra['num_expunges'] ); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $extra['num_inserts'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Total Inserts</span>
                        <span class="smlc-detail-item__val"><?php echo number_format( (int) $extra['num_inserts'] ); ?></span>
                    </div>
                    <?php endif; ?>

                <?php elseif ( $adapter_id === 'wpcache' ) : ?>
                    <?php if ( isset( $extra['wp_cache_class'] ) ) : ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Cache Class</span>
                        <span class="smlc-detail-item__val"><code><?php echo esc_html( $extra['wp_cache_class'] ); ?></code></span>
                    </div>
                    <?php endif; ?>
                    <div class="smlc-detail-item">
                        <span class="smlc-detail-item__key">Group Flush</span>
                        <span class="smlc-detail-item__val">
                            <span class="smlc-badge <?php echo ! empty( $extra['group_flush_support'] ) ? 'smlc-badge--active' : 'smlc-badge--warn'; ?>">
                                <?php echo ! empty( $extra['group_flush_support'] ) ? 'Supported' : 'Full flush only'; ?>
                            </span>
                        </span>
                    </div>

                <?php elseif ( in_array( $adapter_id, [ 'runtime', 'arraycache' ], true ) ) : ?>
                    <div class="smlc-detail-item smlc-detail-item--full">
                        <div class="smlc-inline-notice smlc-inline-notice--warn">
                            <i class="ti ti-alert-triangle"></i>
                            Runtime Cache is request-scoped. All data is lost between requests.
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.smlc-dash -->
</div><!-- /.smliser-admin-page -->