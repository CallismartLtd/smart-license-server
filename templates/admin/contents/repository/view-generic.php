<?php
/**
 * Generic hosted app details view used when a type has no custom view template.
 *
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $app
 * @var array $template_sidebar
 */

defined( 'SMLISER_ROOT' ) || exit;

$profile = $app->get_author_profile()?->url() ?? '';
$name    = escHtml( $app->get_author() );
$author  = '' !== $profile
	? sprintf( '<ul><li><a href="%s" rel="noopener noreferrer">%s</a></li></ul>', escUrl( $profile ), $name )
	: sprintf( '<ul><li>%s</li></ul>', $name );

$template_sidebar['Author']['content'] = $author;
$images = [];

include __DIR__ . '/preview.php';
