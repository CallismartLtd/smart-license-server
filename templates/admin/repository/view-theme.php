<?php
/**
 *  Admin Theme details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 * @var SmartLicenseServer\HostedApps\Theme $app The theme object.
 * @var SmartLicenseServer\FileSystem\ThemeRepository $repo_class
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



$template_sidebar['Author']     = $author;

$images   = [
    'Screenshot'    => array_filter( [$app->get_screenshot_url()] ),
    'Screenshots'   => array_filter( $app->get_screenshots() ),
];

include_once SMLISER_PATH . 'templates/admin/repository/preview.php';