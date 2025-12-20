<?php
/**
 * The admin dashboard page handler file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\HostedApps\SmliserSoftwareCollection;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin dashboard page handler.
 */
class DashboardPage {

    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );

        switch( $tab ) {
            default :
            self::dashboard();
        }
    }

    /**
     * Dashboard Callback method
     */
    private static function dashboard() {
        $totals = [
            'apps'      => RepositoryAnalytics::get_total_apps(),
            'plugins'   => RepositoryAnalytics::get_total_apps( 'plugin' ),
            'themes'    => RepositoryAnalytics::get_total_apps( 'theme' ),
            'software'  => RepositoryAnalytics::get_total_apps( 'software' )
        ];
        
        // Default time periods
        $days_30 = 30;
        $days_7 = 7;
        $months_6 = 6;

        // Prepare chart data for each metric
        $metrics = [
            'Repository Overview' => [
                'summary'   => [
                    'active_installations'  => RepositoryAnalytics::get_active_installations( $days_30 ),
                    'total_downloads'       => RepositoryAnalytics::get_total_downloads(),
                    'total_accesses'        => RepositoryAnalytics::get_total_client_accesses( $days_30 ),
                ],
                'chart_data'    => self::prepare_repository_overview_chart( $days_30, $months_6 ),
            ],

            'Download Analytics'    => [
                'summary'   => [
                    'total_downloads'       => RepositoryAnalytics::get_total_downloads(),
                    'plugins_downloads'     => RepositoryAnalytics::get_total_downloads( 'plugin' ),
                    'themes_downloads'      => RepositoryAnalytics::get_total_downloads( 'theme' ),
                    'software_downloads'    => RepositoryAnalytics::get_total_downloads( 'software' ),
                ],
                'chart_data'    => self::prepare_downloads_chart( $days_30, $days_7 ),
            ],

            'Client Activity' => [
                'summary'   => [
                    'total_accesses'            => RepositoryAnalytics::get_total_client_accesses( $days_30 ),
                    'active_installations'      => RepositoryAnalytics::get_active_installations( $days_30 ),
                    'plugin_installations'      => RepositoryAnalytics::get_active_installations( $days_30, 'plugin' ),
                    'theme_installations'       => RepositoryAnalytics::get_active_installations( $days_30, 'theme' ),
                    'software_installations'    => RepositoryAnalytics::get_active_installations( $days_30, 'software' ),
                ],
                'chart_data'    => self::prepare_client_activity_chart( $days_30, $days_7 ),
            ],

            'License Activity'  => [
                'summary'   => [
                    'activations'           => RepositoryAnalytics::get_license_event_total( 'activation', $days_30 ),
                    'activations_growth'    => RepositoryAnalytics::get_license_event_growth_percentage( 'activation', $days_30 ),
                    'deactivations'         => RepositoryAnalytics::get_license_event_total( 'deactivation', $days_30 ),
                    'deactivations_growth'  => RepositoryAnalytics::get_license_event_growth_percentage( 'deactivation', $days_30 ),
                    'verifications'         => RepositoryAnalytics::get_license_event_total( 'verification', $days_30 ),
                    'verifications_growth'  => RepositoryAnalytics::get_license_event_growth_percentage( 'verification', $days_30 ),
                ],
                'chart_data'    => self::prepare_license_activity_chart( $days_30 ),
                'recent_logs'   => array_slice( RepositoryAnalytics::get_license_activity_logs(), -10, 10, true ),
            ],

            'Performance & Ranking' => [
                'summary'   => [
                    'top_apps'          => RepositoryAnalytics::get_top_apps( 5, 'downloads' ),
                    'maintenance_count' => count( RepositoryAnalytics::get_apps_maintained_by_month( 1 ) ),
                ],
                'chart_data'    => self::prepare_performance_chart( $months_6 ),
                'rankings'      => [
                    'top_downloads' => RepositoryAnalytics::get_top_apps( 10, 'downloads' ),
                    'top_accesses'  => RepositoryAnalytics::get_top_apps( 10, 'client_accesses' ),
                ],
            ],
        ];

        include_once SMLISER_PATH . 'templates/admin-dashboard.php';
    }

    /**
     * Prepare Repository Overview Chart Data
     */
    private static function prepare_repository_overview_chart( int $days, int $months ) : array {
        $apps_by_status = RepositoryAnalytics::get_apps_by_status();
        $maintained = RepositoryAnalytics::get_apps_maintained_by_month( $months );

        // Apps by Status Pie Chart
        $status_labels = [];
        $status_data = [];
        foreach ( $apps_by_status as $type => $statuses ) {
            foreach ( $statuses as $status => $count ) {
                $key = ucfirst( $type ) . ' - ' . ucfirst( $status );
                $status_labels[] = $key;
                $status_data[] = $count;
            }
        }

        // Maintenance Timeline Bar Chart
        $maintenance_labels = [];
        $maintenance_data = [];
        foreach ( $maintained as $type => $months_data ) {
            $type_data = [];
            foreach ( $months_data as $month => $info ) {
                if ( ! in_array( $month, $maintenance_labels ) ) {
                    $maintenance_labels[] = $month;
                }
            }
        }
        sort( $maintenance_labels );

        $maintenance_datasets = [];
        foreach ( $maintained as $type => $months_data ) {
            $dataset = [
                'label' => ucfirst( $type ),
                'data' => [],
            ];
            foreach ( $maintenance_labels as $month ) {
                $dataset['data'][] = $months_data[ $month ]['count'] ?? 0;
            }
            $maintenance_datasets[] = $dataset;
        }

        return [
            'apps_by_status' => [
                'type' => 'pie',
                'data' => [
                    'labels' => $status_labels,
                    'datasets' => [
                        [
                            'label' => 'Apps by Status',
                            'data' => $status_data,
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'position' => 'bottom' ],
                        'title' => [ 'display' => true, 'text' => 'Apps Distribution by Status' ]
                    ]
                ]
            ],
            'maintenance_timeline' => [
                'type' => 'bar',
                'data' => [
                    'labels'   => $maintenance_labels,
                    'datasets' => $maintenance_datasets,
                ],
                'options' => [
                    'responsive'          => true,
                    'maintainAspectRatio' => false,
                    'scales'              => [
                        'x' => [
                            'stacked' => true,
                            'grid'    => [ 'display' => false ], // Remove vertical lines for a cleaner look
                            'border'  => [ 'display' => false ],
                        ],
                        'y' => [
                            'stacked'     => true,
                            'beginAtZero' => true,
                            'border'      => [ 'dash' => [ 5, 5 ], 'display' => false ], // Dashed horizontal lines
                            'grid'        => [ 'color' => '#f1f5f9' ], // Very faint lines
                            'ticks'       => [ 'stepSize' => 1 ] // Better for small counts
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'position' => 'top',
                            'align'    => 'end', // Moves legend to the top right
                            'labels'   => [
                                'usePointStyle' => true, // Circular legend markers instead of boxes
                                'boxWidth'      => 8,
                                'padding'       => 20,
                                'font'          => [ 'weight' => '600' ]
                            ]
                        ],
                        'title' => [ 'display' => false ], // Hide title (use the dashboard card header instead)
                        'tooltip' => [
                            'mode'     => 'index', // Shows all app types in one tooltip when hovering
                            'intersect' => false,
                            'backgroundColor' => '#1e293b',
                            'padding' => 12,
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Prepare Downloads Chart Data
     */
    private static function prepare_downloads_chart( int $days_30, int $days_7 ) : array {
        $downloads_30 = RepositoryAnalytics::get_downloads_per_day( $days_30 );
        $downloads_7 = RepositoryAnalytics::get_downloads_per_day( $days_7 );

        // Downloads by Type (Pie Chart)
        $downloads_by_type = [
            'plugin' => array_sum( RepositoryAnalytics::get_downloads_per_day( $days_30, 'plugin' ) ),
            'theme' => array_sum( RepositoryAnalytics::get_downloads_per_day( $days_30, 'theme' ) ),
            'software' => array_sum( RepositoryAnalytics::get_downloads_per_day( $days_30, 'software' ) ),
        ];

        // 30-day trend (Line Chart)
        $downloads_30_labels = array_keys( $downloads_30 );
        $downloads_30_data = array_values( $downloads_30 );

        // 7-day trend (Bar Chart)
        $downloads_7_labels = array_keys( $downloads_7 );
        $downloads_7_data = array_values( $downloads_7 );

        return [
            'downloads_trend_30' => [
                'type' => 'line',
                'data' => [
                    'labels' => $downloads_30_labels,
                    'datasets' => [
                        [
                            'label' => 'Downloads',
                            'data' => $downloads_30_data,
                            'borderColor' => '#3b82f6',     // Modern Blue
                            'backgroundColor' => 'rgba(59, 130, 246, 0.08)', // Very subtle fill
                            'borderWidth' => 3,
                            'pointBackgroundColor' => '#ffffff',
                            'pointBorderColor' => '#3b82f6',
                            'pointBorderWidth' => 2,
                            'tension' => 0.1
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Downloads Last 30 Days' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ],
            'downloads_week' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $downloads_7_labels,
                    'datasets' => [
                        [
                            'label' => 'Downloads',
                            'data' => $downloads_7_data,
                            'borderColor' => '#3b82f6',     // Modern Blue
                            'backgroundColor' => 'rgba(59, 130, 246, 0.08)', // Very subtle fill
                            'borderWidth' => 1,
                            'pointBackgroundColor' => '#ffffff',
                            'pointBorderColor' => '#3b82f6',
                            'pointBorderWidth' => 2,
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Downloads This Week' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ],
            'downloads_by_type' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['Plugins', 'Themes', 'Software'],
                    'datasets' => [
                        [
                            'label' => 'Downloads',
                            'data' => array_values( $downloads_by_type ),
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'position' => 'bottom' ],
                        'title' => [ 'display' => true, 'text' => 'Downloads by Type (30 Days)' ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Prepare Client Activity Chart Data
     */
    private static function prepare_client_activity_chart( int $days_30, int $days_7 ) : array {
        $accesses_30 = RepositoryAnalytics::get_client_accesses_per_day( $days_30 );
        $accesses_7 = RepositoryAnalytics::get_client_accesses_per_day( $days_7 );

        // Active installations by type (Bar Chart)
        $installations_by_type = [
            'plugin' => RepositoryAnalytics::get_active_installations( $days_30, 'plugin' ),
            'theme' => RepositoryAnalytics::get_active_installations( $days_30, 'theme' ),
            'software' => RepositoryAnalytics::get_active_installations( $days_30, 'software' ),
        ];

        // 30-day accesses (Area Chart)
        $accesses_30_labels = array_keys( $accesses_30 );
        $accesses_30_data = array_values( $accesses_30 );

        // 7-day accesses (Bar Chart)
        $accesses_7_labels = array_keys( $accesses_7 );
        $accesses_7_data = array_values( $accesses_7 );

        return [
            'accesses_trend_30' => [
                'type' => 'line',
                'data' => [
                    'labels' => $accesses_30_labels,
                    'datasets' => [
                        [
                            'label' => 'Client Accesses',
                            'data' => $accesses_30_data,
                            'fill' => true,
                            'backgroundColor' => 'rgba(153, 102, 255, 0.2)',
                            'borderColor' => 'rgb(153, 102, 255)',
                            'tension' => 0.1
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Client Accesses Last 30 Days' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ],
            'accesses_week' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $accesses_7_labels,
                    'datasets' => [
                        [
                            'label' => 'Accesses',
                            'data' => $accesses_7_data,
                            'backgroundColor' => 'rgba(255, 159, 64, 0.5)',
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Client Accesses This Week' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ],
            'installations_by_type' => [
                'type' => 'bar',
                'data' => [
                    'labels' => ['Plugins', 'Themes', 'Software'],
                    'datasets' => [
                        [
                            'label' => 'Active Installations',
                            'data' => array_values( $installations_by_type ),
                            'backgroundColor' => [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                            ],
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Active Installations by Type (30 Days)' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Prepare License Activity Chart Data
     */
    private static function prepare_license_activity_chart( int $days ) : array {
        $activity_per_day = RepositoryAnalytics::get_license_activity_per_day( $days );

        // Prepare stacked data
        $labels = array_keys( $activity_per_day );
        $event_types = ['activation', 'deactivation', 'verification', 'uninstallation'];
        
        $datasets = [];
        $colors = [
            'activation' => 'rgba(75, 192, 192, 0.5)',
            'deactivation' => 'rgba(255, 99, 132, 0.5)',
            'verification' => 'rgba(54, 162, 235, 0.5)',
            'uninstallation' => 'rgba(255, 159, 64, 0.5)',
        ];

        foreach ( $event_types as $event ) {
            $data = [];
            foreach ( $labels as $date ) {
                $data[] = $activity_per_day[ $date ][ $event ] ?? 0;
            }
            $datasets[] = [
                'label' => ucfirst( $event ),
                'data' => $data,
                'backgroundColor' => $colors[ $event ] ?? 'rgba(200, 200, 200, 0.5)',
            ];
        }

        // Event totals (Pie Chart)
        $event_totals = [];
        foreach ( $event_types as $event ) {
            $event_totals[ $event ] = RepositoryAnalytics::get_license_event_total( $event, $days );
        }

        return [
            'license_activity_trend' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => $datasets,
                ],
                'options' => [
                    'responsive' => true,
                    'scales' => [
                        'x' => [ 'stacked' => true ],
                        'y' => [ 'stacked' => true, 'beginAtZero' => true ]
                    ],
                    'plugins' => [
                        'legend' => [ 'position' => 'top' ],
                        'title' => [ 'display' => true, 'text' => 'License Activity Per Day (30 Days)' ]
                    ]
                ]
            ],
            'license_event_totals' => [
                'type' => 'pie',
                'data' => [
                    'labels' => array_map( 'ucfirst', array_keys( $event_totals ) ),
                    'datasets' => [
                        [
                            'label' => 'Events',
                            'data' => array_values( $event_totals ),
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'position' => 'bottom' ],
                        'title' => [ 'display' => true, 'text' => 'License Events Distribution (30 Days)' ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Prepare Performance & Ranking Chart Data
     */
    private static function prepare_performance_chart( int $months ) : array {
        $top_downloads  = RepositoryAnalytics::get_top_apps( 10, 'downloads' );
        $maintained     = RepositoryAnalytics::get_apps_maintained_by_month( $months );

        // Top Apps Bar Chart
        $top_apps_labels = [];
        $top_apps_data = [];
        foreach ( $top_downloads as $type => $apps ) {
            foreach ( $apps as $app ) {
                $app_t      = $app['app_type'] ?? '';
                $app_s      = $app['app_slug'] ?? '';
                $app_obj    = SmliserSoftwareCollection::get_app_by_slug( $app_t, $app_s );
                $label      = $app_obj ? $app_obj->get_name() : 'Unknown';
                $top_apps_labels[] = $label;
                $top_apps_data[] = (int) ( $app['metric_total'] ?? 0 );
            }
        }

        // Maintenance Velocity Line Chart
        $maintenance_labels = [];
        $maintenance_totals = [];
        foreach ( $maintained as $type => $months_data ) {
            foreach ( $months_data as $month => $info ) {
                if ( ! isset( $maintenance_totals[ $month ] ) ) {
                    $maintenance_totals[ $month ] = 0;
                }
                $maintenance_totals[ $month ] += $info['count'];
            }
        }
        ksort( $maintenance_totals );
        $maintenance_labels = array_keys( $maintenance_totals );
        $maintenance_data = array_values( $maintenance_totals );

        return [
            'top_apps_downloads' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $top_apps_labels,
                    'datasets' => [
                        [
                            'label' => 'Downloads',
                            'data' => $top_apps_data,
                            'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false, // Fill the container
                    'indexAxis' => 'y',
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => false ] // Hide title to save space, let the card header do the work
                    ],
                    'scales' => [
                        'x' => [ 
                            'grid' => [ 'display' => false ], // Clean look
                            'beginAtZero' => true 
                        ],
                        'y' => [
                            'grid' => [ 'display' => false ]
                        ]
                    ]
                ]
            ],
            'maintenance_velocity' => [
                'type' => 'line',
                'data' => [
                    'labels' => $maintenance_labels,
                    'datasets' => [
                        [
                            'label' => 'Apps Updated',
                            'data' => $maintenance_data,
                            'borderColor' => 'rgb(255, 99, 132)',
                            'tension' => 0.1
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'title' => [ 'display' => true, 'text' => 'Maintenance Velocity (6 Months)' ]
                    ],
                    'scales' => [
                        'y' => [ 'beginAtZero' => true ]
                    ]
                ]
            ]
        ];
    }
}