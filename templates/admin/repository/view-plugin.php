<?php
/**
 *  Admin Plugin details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 * @var SmartLicenseServer\HostedApps\Plugin $app The plugin object.
 * @var SmartLicenseServer\PluginRepository $repo_class
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$plugin_meta    = $repo_class->get_metadata( $app->get_slug() );

// Authors listing will differ in the future.
$author = sprintf(
    '<ul>
        <li><a href="%1$s">%2$s</a></li>
    
    </ul>',
    $app->get_author_profile(),
    $app->get_author()
);

$template_sidebar['Author']['content']  = $author;

$screenshots = [];

foreach( $app->get_screenshots() as $screenshot ) {
    $screenshots[] = $screenshot['src'] ?? '';
}

$banners    = $app->get_banners();
$icons      = $app->get_icons();

$images   = [
    'Icons'         => array_filter( $icons ),
    'Banners'       => array_filter( $banners ),
    'Screenshots'   => array_filter( $screenshots ),
];

include_once SMLISER_PATH . 'templates/admin/repository/preview.php';