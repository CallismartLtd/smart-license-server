<?php
/**
 *  Admin Plugin details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 * @var SmartLicenseServer\HostedApps\Plugin $app The plugin object.
 * @var SmliserStats $stats The stats object.
 */

use SmartLicenseServer\FileSystemHelper;
use SmartLicenseServer\HostedApps\SmliserSoftwareCollection;

defined( 'ABSPATH' ) || exit;
/** 
 * Set file information
 * 
 * @var SmartLicenseServer\PluginRepository $repo_class
 */
$repo_class = SmliserSoftwareCollection::get_app_repository_class( $app->get_type() );

$plugin_meta    = $repo_class->get_metadata( $app->get_slug() ); 
$author = sprintf(
    '<ul>
        <li><a href="%1$s">%2$s</a></li>
    
    </ul>',
    $app->get_author_profile(),
    $app->get_author()
);

$info   = sprintf(
    '<ul class="smliser-app-meta">
        <li><span>%1$s</span> <span>#%2$s</span></li>
        <li><span>%3$s</span> <span>%4$s</span></li>
        <li><span>%5$s</span> <a href="%13$s">%6$s</a></li>
        <li><span>%7$s</span> <span>%8$s</span></li>
        <li><span>%9$s</span> <span>%10$s</span></li>
        <li><span>%11$s</span> <span>%12$s</span></li>
    </ul>',
    __( 'APP ID', 'smliser' ),
    $app->get_id(),
    __( 'Platform', 'smliser' ),
    __( 'WordPress', 'smliser' ),
    __( 'License', 'smliser' ),
    $plugin_meta['license'] ?? '',
    __( 'Status', 'smliser' ),
    $app->get_status(),
    __( 'File Size', 'smliser' ),
    FileSystemHelper::format_file_size( $repo_class->filesize( $app->get_file() ) ),
    __( 'Last Updated', 'smliser' ),
    $app->get_last_updated(),
    $plugin_meta['license_uri'] ?? ''
    

);
$template_sidebar   = [
    'Author'                => $author,
    'Performance Metrics'   => '', //TODO: Use Analytics class to build.
    'Plugin Info'           => $info,
    'Installation'          => $app->get_installation(),
    'Changelog'             => $app->get_changelog(),

];

$template_content   = [
    'Icons'         => $app->get_icons(),
    'Banners'       => $app->get_banners(),
    'Screenshots'   => $app->get_screenshots(),
];


include_once SMLISER_PATH . 'templates/admin/repository/preview.php';