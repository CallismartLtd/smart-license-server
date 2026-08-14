<?php
/**
 * Dashboard Header.
 *
 * Renders the HTML document opening, <head> section (title, meta tags,
 * styles, scripts placeholders) and opens the <body> tag.
 *
 * Define DASHBOARD_PAGE_TITLE before including this file to set a custom
 * page title. Falls back to a default when not set.
 *
 * @package Dashboard
 */

if ( ! defined( 'DASHBOARD_PAGE_TITLE' ) ) {
	define( 'DASHBOARD_PAGE_TITLE', 'Dashboard' );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo htmlspecialchars( DASHBOARD_PAGE_TITLE, ENT_QUOTES, 'UTF-8' ); ?></title>

	<!-- ==========================================================
	     STYLES
	     Add stylesheet <link> tags below.
	========================================================== -->


	<!-- ==========================================================
	     SCRIPTS (head)
	     Add any <script> tags that must load before body content below.
	========================================================== -->


</head>
<body>