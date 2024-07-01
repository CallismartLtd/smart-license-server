<?php
/**
 * Auth header
 *
 * This template can be overridden by copying it to yourtheme/smliser/auth/auth-header.php.
 *
 * HOWEVER, on occasion Smart License Server will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @package Smliser\Templates\Auth
 * @version 1.0.0
 */

 defined( 'ABSPATH' ) || exit;

 ?><!DOCTYPE html>
 <html <?php language_attributes(); ?>>
 <head>
     <meta name="viewport" content="width=device-width" />
     <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
     <meta name="robots" content="noindex, nofollow" />
     <title><?php esc_html_e( 'Application Authorization', 'smliser' ); ?></title>
     <link rel="stylesheet" href="" type="text/css" />
 </head>
 <body class="smliser-auth-body wp-core-ui">
     <h1 id=""><img src="" alt="<?php esc_attr_e( 'Smart License Server', 'smliser' ); ?>" /></h1>
     <div class="smliser-auth-content">