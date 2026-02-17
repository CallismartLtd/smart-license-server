<?php
/**
 *  Admin Software details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 * @var SmartLicenseServer\HostedApps\Software $app The plugin object.
 * @var SmartLicenseServer\SoftwareRepository $repo_class
 */

defined( 'SMLISER_ABSPATH' ) || exit;

// Authors listing will differ in the future.
$author = sprintf(
    '<ul>
        <li><a href="%1$s">%2$s</a></li>
    
    </ul>',
    $app->get_author_profile(),
    $app->get_author()
);

$template_sidebar['Author']['content']  = $author;

$cover  = [$app->get_cover()];
$icons  = $app->get_icons();

$images = [
    'Icons'         => array_filter( $icons ),
    'Cover'         => array_filter( $cover ),
    'Screenshots'   => array_filter( $app->get_screenshots() ),
];

include_once SMLISER_PATH . 'templates/admin/repository/preview.php';